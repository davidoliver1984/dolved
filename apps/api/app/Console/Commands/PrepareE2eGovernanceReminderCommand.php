<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Documents\ScanDocumentGovernanceRemindersAndAuthorityTransitions;
use App\Actions\Documents\UpdateDocumentFamilyMetadata;
use App\Models\Document;
use App\Models\User;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

final class PrepareE2eGovernanceReminderCommand extends Command
{
    protected $signature = 'e2e:prepare-governance-reminder
        {--workspace= : Workspace public ID}
        {--document= : Document public ID}
        {--actor= : Acting user public ID}';

    protected $description = 'Create a review-due condition through real domain actions only inside the isolated E2E environment';

    public function handle(UpdateDocumentFamilyMetadata $update, ScanDocumentGovernanceRemindersAndAuthorityTransitions $scan): int
    {
        try {
            $this->assertE2eIdentity();
            $workspaceId = $this->requiredOption('workspace');
            $document = Document::query()->with(['family.category'])
                ->where('public_id', $this->requiredOption('document'))
                ->whereHas('workspace', fn ($query) => $query->where('public_id', $workspaceId))
                ->sole();
            $actor = User::query()->where('public_id', $this->requiredOption('actor'))->sole();
            $family = $document->family;
            if ($family === null || $family->owner_user_id !== $actor->id) {
                throw new RuntimeException('The E2E actor must own the selected document family.');
            }

            $family = $update->handle(
                $family,
                $actor,
                $family->description,
                $family->category,
                now('UTC')->addDays(7)->toDateString(),
            );
            $scan->handle();

            $this->line((string) json_encode([
                'document_family_public_id' => $family->public_id,
                'review_due_date' => $family->review_due_date?->toDateString(),
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
            throw new RuntimeException('E2E governance setup is restricted to the isolated dolved-e2e identity.');
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
