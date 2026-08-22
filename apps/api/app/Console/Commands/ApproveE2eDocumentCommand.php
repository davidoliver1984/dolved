<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Documents\ApproveDocumentVersion;
use App\Enums\DocumentGovernanceStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

final class ApproveE2eDocumentCommand extends Command
{
    protected $signature = 'e2e:approve-document
        {--workspace= : Workspace public ID}
        {--document= : Document public ID}
        {--actor= : Acting user public ID}';

    protected $description = 'Approve an uploaded document through real governance invariants in the isolated E2E environment';

    public function handle(ApproveDocumentVersion $approve): int
    {
        try {
            $this->assertE2eIdentity();
            $workspaceId = $this->requiredOption('workspace');
            $documentId = $this->requiredOption('document');
            $actorId = $this->requiredOption('actor');

            $document = Document::query()
                ->where('public_id', $documentId)
                ->whereHas('workspace', fn ($query) => $query->where('public_id', $workspaceId))
                ->sole();
            $actor = User::query()->where('public_id', $actorId)->sole();

            if ($document->governance_status === DocumentGovernanceStatus::Draft) {
                $document = $approve->handle($document, $actor);
            } elseif ($document->governance_status !== DocumentGovernanceStatus::Approved) {
                throw new RuntimeException('Only draft or already-approved E2E documents are accepted.');
            }

            $this->line((string) json_encode([
                'document_public_id' => $document->public_id,
                'governance_status' => $document->governance_status->value,
            ], JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
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
            throw new RuntimeException('E2E document approval is restricted to the isolated dolved-e2e identity.');
        }
    }

    private function requiredOption(string $name): string
    {
        $value = trim((string) $this->option($name));
        if ($value === '') {
            throw new RuntimeException("The E2E {$name} public ID is required.");
        }

        return $value;
    }
}
