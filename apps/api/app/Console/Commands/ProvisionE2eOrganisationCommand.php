<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OrganisationalLocation;
use App\Models\OrganisationalLocationAlias;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ProvisionE2eOrganisationCommand extends Command
{
    protected $signature = 'e2e:provision-organisation
        {--workspace= : Workspace public ID}
        {--manifest= : Absolute path to the frozen organisation manifest}';

    protected $description = 'Provision a frozen synthetic organisation only inside the isolated E2E environment';

    public function handle(): int
    {
        try {
            $this->assertE2eIdentity();
            $workspaceId = $this->requiredOption('workspace');
            $manifestPath = $this->requiredOption('manifest');
            $manifest = $this->manifest($manifestPath);
            $workspace = Workspace::query()->where('public_id', $workspaceId)->sole();

            $mapping = DB::transaction(fn (): array => $this->provision($workspace, $manifest));
            $this->line((string) json_encode([
                'workspace_public_id' => $workspace->public_id,
                'organisation_digest_sha256' => hash_file('sha256', $manifestPath),
                'locations' => $mapping,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

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
            throw new RuntimeException('Organisation provisioning is restricted to the isolated dolved-e2e identity.');
        }
    }

    /** @return array<string, mixed> */
    private function manifest(string $path): array
    {
        if (! str_starts_with($path, '/r28-corpus/') || ! is_file($path)) {
            throw new RuntimeException('The organisation manifest must come from the read-only R28 corpus mount.');
        }
        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded) || ! is_array($decoded['locations'] ?? null) || ! is_array($decoded['aliases'] ?? null)) {
            throw new RuntimeException('The organisation manifest has an invalid shape.');
        }

        return $decoded;
    }

    /** @param array<string, mixed> $manifest @return array<string, string> */
    private function provision(Workspace $workspace, array $manifest): array
    {
        if ($workspace->organisationalLocations()->exists()) {
            throw new RuntimeException('The target workspace already has organisational locations.');
        }

        /** @var array<string, OrganisationalLocation> $locations */
        $locations = [];
        $remaining = collect($manifest['locations']);
        while ($remaining->isNotEmpty()) {
            $before = $remaining->count();
            $remaining = $remaining->reject(function (mixed $raw) use ($workspace, &$locations): bool {
                if (! is_array($raw)
                    || ! is_string($raw['location_id'] ?? null)
                    || ! is_string($raw['name'] ?? null)
                    || ! is_string($raw['kind'] ?? null)) {
                    throw new RuntimeException('A location definition is invalid.');
                }
                $parentKey = $raw['parent_location_id'] ?? null;
                if ($parentKey !== null && (! is_string($parentKey) || ! isset($locations[$parentKey]))) {
                    return false;
                }
                if (isset($locations[$raw['location_id']])) {
                    throw new RuntimeException('A location identity is duplicated.');
                }
                $location = new OrganisationalLocation([
                    'name' => trim($raw['name']),
                    'kind' => Str::lower(trim($raw['kind'])),
                    'parent_id' => $parentKey === null ? null : $locations[$parentKey]->id,
                ]);
                $location->public_id = (string) Str::uuid();
                $location->workspace()->associate($workspace);
                $location->save();
                $locations[$raw['location_id']] = $location;

                return true;
            });
            if ($remaining->count() === $before) {
                throw new RuntimeException('The organisation hierarchy is cyclic or incomplete.');
            }
        }

        foreach ($manifest['aliases'] as $raw) {
            if (! is_array($raw)
                || ! is_string($raw['alias'] ?? null)
                || ! is_array($raw['location_ids'] ?? null)) {
                throw new RuntimeException('An alias definition is invalid.');
            }
            foreach ($raw['location_ids'] as $locationKey) {
                if (! is_string($locationKey) || ! isset($locations[$locationKey])) {
                    throw new RuntimeException('An alias references an unknown location.');
                }
                $alias = new OrganisationalLocationAlias(['alias' => $raw['alias']]);
                $alias->workspace()->associate($workspace);
                $alias->organisationalLocation()->associate($locations[$locationKey]);
                $alias->save();
            }
        }

        return collect($locations)->map(fn (OrganisationalLocation $location): string => $location->public_id)->all();
    }

    private function requiredOption(string $name): string
    {
        $value = trim((string) $this->option($name));
        if ($value === '') {
            throw new RuntimeException("The E2E {$name} option is required.");
        }

        return $value;
    }
}
