<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Workspace;
use App\Support\Imports\WorkspaceChecksumLock;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use Tests\TestCase;

final class PostgresWorkspaceChecksumSerializationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL row-lock semantics require the isolated PostgreSQL test profile.');
        }
    }

    public function test_concurrent_first_creation_serializes_and_rollback_remains_recoverable(): void
    {
        $workspace = Workspace::factory()->create();
        $otherWorkspace = Workspace::factory()->create();
        $checksum = str_repeat('c', 64);
        $locks = app(WorkspaceChecksumLock::class);
        $secondary = $this->secondaryPdo();

        DB::beginTransaction();
        try {
            $locks->acquire($workspace->id, $checksum);

            $this->beginWithShortLockTimeout($secondary);
            try {
                $this->insertReservation($secondary, $workspace->id, $checksum);
                $this->fail('A concurrent first insertion did not wait on the uncommitted reservation identity.');
            } catch (PDOException $exception) {
                $this->assertSame('55P03', $exception->getCode());
                $secondary->rollBack();
            }

            $this->beginWithShortLockTimeout($secondary);
            $this->insertReservation($secondary, $otherWorkspace->id, $checksum);
            $this->lockReservation($secondary, $otherWorkspace->id, $checksum);
            $secondary->commit();

            DB::rollBack();
        } catch (\Throwable $error) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            throw $error;
        }

        $this->assertDatabaseMissing('workspace_checksum_reservations', [
            'workspace_id' => $workspace->id,
            'source_checksum_sha256' => $checksum,
        ]);

        $secondary->beginTransaction();
        $this->insertReservation($secondary, $workspace->id, $checksum);
        $this->lockReservation($secondary, $workspace->id, $checksum);
        $secondary->commit();
        $this->assertDatabaseHas('workspace_checksum_reservations', [
            'workspace_id' => $workspace->id,
            'source_checksum_sha256' => $checksum,
        ]);
    }

    public function test_preexisting_reservation_blocks_same_workspace_but_not_another_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $otherWorkspace = Workspace::factory()->create();
        $checksum = str_repeat('d', 64);
        $locks = app(WorkspaceChecksumLock::class);
        DB::table('workspace_checksum_reservations')->insert([
            ['workspace_id' => $workspace->id, 'source_checksum_sha256' => $checksum, 'created_at' => now()],
            ['workspace_id' => $otherWorkspace->id, 'source_checksum_sha256' => $checksum, 'created_at' => now()],
        ]);
        $secondary = $this->secondaryPdo();

        DB::beginTransaction();
        try {
            $locks->acquire($workspace->id, $checksum);

            $this->beginWithShortLockTimeout($secondary);
            try {
                $this->lockReservation($secondary, $workspace->id, $checksum);
                $this->fail('The same workspace/checksum reservation did not serialize.');
            } catch (PDOException $exception) {
                $this->assertSame('55P03', $exception->getCode());
                $secondary->rollBack();
            }

            $secondary->beginTransaction();
            $this->lockReservation($secondary, $otherWorkspace->id, $checksum);
            $secondary->commit();

            DB::commit();
        } catch (\Throwable $error) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            throw $error;
        }

        $secondary->beginTransaction();
        $this->lockReservation($secondary, $workspace->id, $checksum);
        $secondary->commit();
        $this->assertTrue(true);
    }

    private function secondaryPdo(): PDO
    {
        $configuration = config('database.connections.pgsql');
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $configuration['host'],
            $configuration['port'],
            $configuration['database'],
        );

        return new PDO($dsn, $configuration['username'], $configuration['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }

    private function beginWithShortLockTimeout(PDO $connection): void
    {
        $connection->beginTransaction();
        $connection->exec("SET LOCAL lock_timeout = '100ms'");
    }

    private function insertReservation(PDO $connection, int $workspaceId, string $checksum): void
    {
        $statement = $connection->prepare(
            'INSERT INTO workspace_checksum_reservations (workspace_id, source_checksum_sha256, created_at)
             VALUES (:workspace_id, :checksum, CURRENT_TIMESTAMP)
             ON CONFLICT (workspace_id, source_checksum_sha256) DO NOTHING',
        );
        $statement->execute(['workspace_id' => $workspaceId, 'checksum' => $checksum]);
    }

    private function lockReservation(PDO $connection, int $workspaceId, string $checksum): void
    {
        $statement = $connection->prepare(
            'SELECT id FROM workspace_checksum_reservations
             WHERE workspace_id = :workspace_id AND source_checksum_sha256 = :checksum
             FOR UPDATE',
        );
        $statement->execute(['workspace_id' => $workspaceId, 'checksum' => $checksum]);
        $this->assertNotFalse($statement->fetchColumn());
    }
}
