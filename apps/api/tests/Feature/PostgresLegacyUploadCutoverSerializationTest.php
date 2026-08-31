<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Imports\InventoryLegacyUploads;
use App\Models\Document;
use App\Models\LegacyUploadInitializationGate;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use Tests\TestCase;

final class PostgresLegacyUploadCutoverSerializationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL row-lock semantics require the isolated PostgreSQL test profile.');
        }
    }

    public function test_initializer_waiting_behind_gate_closure_observes_closed_gate(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->withOwner($user)->create();
        $document = Document::factory()->for($workspace)->for($user, 'createdBy')->uploading()->create();
        $this->assertSame(1, app(InventoryLegacyUploads::class)->handle(10));

        $secondary = $this->secondaryPdo();

        DB::beginTransaction();
        try {
            $gate = LegacyUploadInitializationGate::query()->lockForUpdate()->findOrFail(1);

            $this->beginWithShortLockTimeout($secondary);
            try {
                $this->lockGate($secondary);
                $this->fail('A concurrent initializer did not serialize on the cutover gate.');
            } catch (PDOException $exception) {
                $this->assertSame('55P03', $exception->getCode());
                $secondary->rollBack();
            }

            $gate->closed = true;
            $gate->closed_at = now();
            $gate->save();
            DB::commit();
        } catch (\Throwable $error) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            throw $error;
        }

        $secondary->beginTransaction();
        $closed = $this->lockGate($secondary);
        $secondary->commit();

        $this->assertTrue($closed);
        $this->assertTrue(LegacyUploadInitializationGate::query()->findOrFail(1)->closed);

        try {
            DB::table('documents')->where('id', $document->id)->update([
                'legacy_upload_initiated_before_cutover' => null,
            ]);
            $this->fail('The database allowed a marked document identity to be cleared.');
        } catch (QueryException $exception) {
            $this->assertSame('P0001', $exception->getCode());
        }

        try {
            DB::table('legacy_upload_initialization_gate')->where('id', 1)->update([
                'closed' => false,
                'closed_at' => null,
            ]);
            $this->fail('The database allowed the cutover gate to reopen.');
        } catch (QueryException $exception) {
            $this->assertSame('P0001', $exception->getCode());
        }
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

    private function lockGate(PDO $connection): bool
    {
        $statement = $connection->query(
            'SELECT closed FROM legacy_upload_initialization_gate WHERE id = 1 FOR UPDATE',
        );

        return $statement->fetchColumn();
    }
}
