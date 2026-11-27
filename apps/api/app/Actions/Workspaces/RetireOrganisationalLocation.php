<?php

declare(strict_types=1);

namespace App\Actions\Workspaces;

use App\Models\OrganisationalLocation;
use App\Models\User;
use App\Support\Workspaces\RecordWorkspaceAdministrationAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RetireOrganisationalLocation
{
    public function __construct(private RecordWorkspaceAdministrationAudit $audit) {}

    public function handle(OrganisationalLocation $location, User $actor, string $correlationId): OrganisationalLocation
    {
        return DB::transaction(function () use ($actor, $correlationId, $location): OrganisationalLocation {
            $locked = OrganisationalLocation::query()->whereKey($location->id)->lockForUpdate()->firstOrFail();
            if ($locked->retired_at !== null) {
                return $locked;
            }

            $activeChildren = $locked->children()->whereNull('retired_at')->count();
            if ($activeChildren > 0) {
                throw ValidationException::withMessages([
                    'location' => ['Retire this location’s active child locations first.'],
                ]);
            }

            $usage = [
                'applicability_snapshots' => $locked->applicabilitySnapshots()->count(),
                'family_defaults' => $locked->defaultForDocumentFamilies()->count(),
            ];
            $locked->retired_at = now();
            $locked->save();

            $this->audit->record(
                $locked->workspace,
                $actor,
                'location_retired',
                'organisational_location',
                $locked->public_id,
                ['status' => 'active'],
                ['status' => 'retired'],
                $correlationId,
                ['retained_references' => $usage],
            );

            return $locked;
        });
    }
}
