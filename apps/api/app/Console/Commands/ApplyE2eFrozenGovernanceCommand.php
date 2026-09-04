<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Documents\ApproveDocumentVersion;
use App\Actions\Documents\WithdrawDocumentVersion;
use App\Enums\DocumentGovernanceStatus;
use App\Models\Document;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

final class ApplyE2eFrozenGovernanceCommand extends Command
{
    protected $signature = 'e2e:apply-frozen-governance
        {--workspace= : Workspace public ID}
        {--actor= : Acting user public ID}
        {--manifest= : Frozen manifest path mounted below /r28-corpus}';

    protected $description = 'Replay frozen document governance dates through real actions in the isolated E2E environment';

    public function handle(ApproveDocumentVersion $approve, WithdrawDocumentVersion $withdraw): int
    {
        try {
            $this->assertE2eIdentity();
            $workspaceId = $this->requiredOption('workspace');
            $actorId = $this->requiredOption('actor');
            $manifestPath = $this->validatedManifestPath($this->requiredOption('manifest'));
            $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
            $entries = $manifest['documents'] ?? null;
            if (! is_array($entries) || ($manifest['document_count'] ?? null) !== count($entries)) {
                throw new RuntimeException('The frozen governance manifest document inventory is invalid.');
            }

            $actor = User::query()->where('public_id', $actorId)->sole();
            $documents = Document::query()
                ->whereHas('workspace', fn ($query) => $query->where('public_id', $workspaceId))
                ->get()
                ->keyBy('source_filename');
            if ($documents->count() !== count($entries)) {
                throw new RuntimeException('The workspace document count does not match the frozen manifest.');
            }

            usort($entries, fn (array $left, array $right): int => [
                (string) ($left['family_id'] ?? ''),
                $this->effectiveDate($left),
                (string) ($left['version_id'] ?? ''),
            ] <=> [
                (string) ($right['family_id'] ?? ''),
                $this->effectiveDate($right),
                (string) ($right['version_id'] ?? ''),
            ]);

            $counts = ['draft' => 0, 'approved' => 0, 'withdrawn' => 0];
            foreach ($entries as $entry) {
                $filename = (string) ($entry['filename'] ?? '');
                $status = (string) ($entry['governance_status'] ?? 'approved');
                if ($filename === '' || ! array_key_exists($status, $counts)) {
                    throw new RuntimeException('The frozen governance entry is invalid.');
                }
                $document = $documents->get($filename);
                if (! $document instanceof Document) {
                    throw new RuntimeException("The frozen document {$filename} is absent from the workspace.");
                }
                if ($status === 'draft') {
                    if ($document->governance_status !== DocumentGovernanceStatus::Draft) {
                        throw new RuntimeException("The frozen draft {$filename} has already transitioned.");
                    }
                    $counts['draft']++;

                    continue;
                }

                CarbonImmutable::setTestNow(CarbonImmutable::parse($this->effectiveDate($entry), 'UTC')->startOfDay()->subSecond());
                $document = $approve->handle($document, $actor);
                if ($status === 'withdrawn') {
                    $supersededDate = $entry['superseded_date'] ?? null;
                    if (! is_string($supersededDate) || $supersededDate === '') {
                        throw new RuntimeException("The frozen withdrawal date for {$filename} is absent.");
                    }
                    CarbonImmutable::setTestNow(CarbonImmutable::parse($supersededDate, 'UTC')->startOfDay());
                    $withdraw->handle($document, $actor);
                }
                $counts[$status]++;
            }

            $this->line((string) json_encode([
                'workspace_public_id' => $workspaceId,
                'manifest' => $this->manifestIdentity($manifestPath),
                'documents' => count($entries),
                'governance' => $counts,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    private function effectiveDate(array $entry): string
    {
        $effectiveDate = $entry['effective_date'] ?? null;
        if (is_string($effectiveDate) && $effectiveDate !== '') {
            return CarbonImmutable::parse($effectiveDate, 'UTC')->toDateString();
        }
        $supersededDate = $entry['superseded_date'] ?? null;
        if (is_string($supersededDate) && $supersededDate !== '') {
            return CarbonImmutable::parse($supersededDate, 'UTC')->subDay()->toDateString();
        }

        return '2026-01-01';
    }

    private function validatedManifestPath(string $path): string
    {
        $resolved = realpath($path);
        $root = realpath((string) config('e2e.frozen_corpus_root'));
        if ($resolved === false || $root === false || ! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The frozen governance manifest must resolve below the configured read-only corpus root.');
        }

        return $resolved;
    }

    private function manifestIdentity(string $path): array
    {
        return ['path' => $path, 'sha256' => hash_file('sha256', $path)];
    }

    private function assertE2eIdentity(): void
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");
        $databaseMarker = (string) config('e2e.database_marker');
        if (! app()->environment('e2e')
            || config('e2e.resource_marker') !== 'dolved-e2e'
            || $databaseMarker === ''
            || ! str_contains($database, $databaseMarker)) {
            throw new RuntimeException('Frozen governance replay is restricted to the isolated dolved-e2e identity.');
        }
    }

    private function requiredOption(string $name): string
    {
        $value = trim((string) $this->option($name));
        if ($value === '') {
            throw new RuntimeException("The E2E {$name} value is required.");
        }

        return $value;
    }
}
