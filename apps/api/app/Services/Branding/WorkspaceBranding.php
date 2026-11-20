<?php

declare(strict_types=1);

namespace App\Services\Branding;

use App\Models\PlatformAdministrationAuditEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceBrandingAsset;
use App\Models\WorkspaceBrandingProfile;
use App\Models\WorkspaceBrandingRevision;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class WorkspaceBranding
{
    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return [
            'display_name' => 'Dolved',
            'primary_colour' => '#006C4D',
            'accent_colour' => '#008466',
            'primary_foreground' => '#FFFFFF',
            'accent_foreground' => '#FFFFFF',
            'logo_label' => 'Dolved',
            'primary_logo' => null,
            'dark_logo' => null,
            'favicon' => null,
            'sign_in_heading' => 'Welcome back',
            'sign_in_introduction' => 'Sign in to access your workspace.',
            'chat_welcome_heading' => 'Ask Dolved',
            'chat_welcome_introduction' => 'Ask a question grounded in your approved knowledge base.',
            'sender_display_name' => 'Dolved',
            'footer_text' => null,
            'support_contact' => null,
        ];
    }

    /** @return array<string, mixed> */
    public function adminView(Workspace $workspace): array
    {
        $profile = $this->profile($workspace);
        $liveRevision = $this->revision($profile->applied_revision_id);
        $lastRevision = $this->revision($profile->last_custom_revision_id);

        return [
            'tenant' => ['public_id' => $workspace->public_id, 'name' => $workspace->name, 'slug' => $workspace->slug],
            'mode' => $profile->mode,
            'draft_version' => $profile->draft_version,
            'draft' => $profile->draft_configuration ?? $this->defaults(),
            'applied_revision' => $liveRevision?->revision,
            'applied_revision_public_id' => $liveRevision?->public_id,
            'last_custom_revision' => $lastRevision?->revision,
            'can_reapply_custom' => $lastRevision !== null,
            'effective' => $this->effective($workspace),
        ];
    }

    /** @return array<string, mixed> */
    public function effective(Workspace $workspace): array
    {
        $profile = WorkspaceBrandingProfile::query()->where('workspace_id', $workspace->id)->first();
        if ($profile === null || $profile->mode !== 'custom') {
            return ['mode' => 'default', 'revision' => null, 'configuration' => $this->defaults()];
        }
        $revision = $this->revision($profile->applied_revision_id);
        if ($revision === null) {
            return ['mode' => 'default', 'revision' => null, 'configuration' => $this->defaults()];
        }

        return ['mode' => 'custom', 'revision' => $revision->revision, 'revision_public_id' => $revision->public_id, 'configuration' => $revision->configuration];
    }

    /** @return array{configuration_identity: string, accent_identity: string} */
    public function emailSnapshot(Workspace $workspace): array
    {
        $effective = $this->effective($workspace);
        if ($effective['mode'] !== 'custom' || ! is_string($effective['revision_public_id'] ?? null)) {
            return ['configuration_identity' => 'dolved-default-v1', 'accent_identity' => 'dolved-green-v1'];
        }

        return [
            'configuration_identity' => 'workspace-branding:'.$effective['revision_public_id'],
            'accent_identity' => 'workspace-branding-accent:'.$effective['revision_public_id'],
        ];
    }

    /** @param array<string, mixed> $configuration @return array<string, mixed> */
    public function saveDraft(Workspace $workspace, User $actor, int $expectedVersion, array $configuration): array
    {
        DB::transaction(function () use ($workspace, $actor, $expectedVersion, $configuration): void {
            $profile = $this->lockedProfile($workspace);
            if ($profile->draft_version !== $expectedVersion) {
                throw ValidationException::withMessages(['draft_version' => 'This branding draft changed in another session. Refresh before saving.']);
            }
            $before = ['draft_version' => $profile->draft_version];
            $profile->forceFill([
                'draft_configuration' => $this->normalise($workspace, $configuration),
                'draft_version' => $profile->draft_version + 1,
            ])->save();
            $this->audit($actor, $workspace, 'tenant.branding.draft_saved', $before, ['draft_version' => $profile->draft_version]);
        });

        return $this->adminView($workspace);
    }

    /** @return array<string, mixed> */
    public function apply(Workspace $workspace, User $actor, int $expectedVersion): array
    {
        DB::transaction(function () use ($workspace, $actor, $expectedVersion): void {
            $profile = $this->lockedProfile($workspace);
            if ($profile->draft_version !== $expectedVersion) {
                throw ValidationException::withMessages(['draft_version' => 'This branding draft changed in another session. Refresh before applying.']);
            }
            $configuration = $profile->draft_configuration;
            if (! is_array($configuration)) {
                throw ValidationException::withMessages(['branding' => 'Save a valid custom branding draft before applying it.']);
            }
            $configuration = $this->normalise($workspace, $configuration, true);
            $revisionNumber = ((int) WorkspaceBrandingRevision::query()->where('workspace_id', $workspace->id)->max('revision')) + 1;
            $revision = WorkspaceBrandingRevision::query()->create([
                'public_id' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'revision' => $revisionNumber,
                'configuration' => $configuration,
                'applied_by_user_id' => $actor->id,
            ]);
            $before = ['mode' => $profile->mode, 'revision_id' => $profile->applied_revision_id];
            $profile->forceFill(['mode' => 'custom', 'applied_revision_id' => $revision->id, 'last_custom_revision_id' => $revision->id])->save();
            $this->audit($actor, $workspace, 'tenant.branding.custom_applied', $before, ['mode' => 'custom', 'revision' => $revisionNumber]);
        });

        return $this->adminView($workspace);
    }

    /** @return array<string, mixed> */
    public function switchMode(Workspace $workspace, User $actor, string $mode): array
    {
        DB::transaction(function () use ($workspace, $actor, $mode): void {
            $profile = $this->lockedProfile($workspace);
            $before = ['mode' => $profile->mode, 'revision_id' => $profile->applied_revision_id];
            if ($mode === 'custom') {
                $revision = $this->revision($profile->last_custom_revision_id);
                if ($revision === null) {
                    throw ValidationException::withMessages(['mode' => 'Apply a valid custom revision before switching to custom branding.']);
                }
                $profile->forceFill(['mode' => 'custom', 'applied_revision_id' => $revision->id])->save();
            } else {
                $profile->forceFill(['mode' => 'default', 'applied_revision_id' => null])->save();
            }
            $this->audit($actor, $workspace, 'tenant.branding.mode_switched', $before, ['mode' => $profile->mode, 'revision_id' => $profile->applied_revision_id]);
        });

        return $this->adminView($workspace);
    }

    /** @param array<string, mixed> $configuration @return array<string, mixed> */
    private function normalise(Workspace $workspace, array $configuration, bool $forApply = false): array
    {
        $configuration['primary_colour'] = strtoupper((string) $configuration['primary_colour']);
        $configuration['accent_colour'] = strtoupper((string) $configuration['accent_colour']);
        $configuration['primary_foreground'] = $this->foreground($configuration['primary_colour']);
        $configuration['accent_foreground'] = $this->foreground($configuration['accent_colour']);
        foreach (['primary_logo', 'dark_logo', 'favicon'] as $field) {
            $reference = $configuration[$field] ?? null;
            $publicId = is_array($reference) ? ($reference['public_id'] ?? null) : $reference;
            if ($publicId === null || $publicId === '') {
                $configuration[$field] = null;

                continue;
            }
            $asset = WorkspaceBrandingAsset::query()->where('workspace_id', $workspace->id)->where('public_id', $publicId)->first();
            if ($asset === null) {
                throw ValidationException::withMessages([$field => 'The selected branding asset is not available for this tenant.']);
            }
            $configuration[$field] = $this->assetView($asset);
        }
        if ($forApply && $configuration['primary_logo'] === null) {
            throw ValidationException::withMessages(['primary_logo' => 'A primary logo is required before custom branding can be applied.']);
        }

        return $configuration;
    }

    /** @return array<string, mixed> */
    public function assetView(WorkspaceBrandingAsset $asset): array
    {
        return [
            'public_id' => $asset->public_id,
            'kind' => $asset->kind,
            'url' => rtrim((string) config('app.url'), '/').'/api/branding/assets/'.$asset->public_id,
            'width' => $asset->width,
            'height' => $asset->height,
        ];
    }

    private function foreground(string $hex): string
    {
        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');
        $channel = static fn (int $value): float => (($value / 255) <= 0.04045) ? ($value / 255) / 12.92 : (($value / 255 + 0.055) / 1.055) ** 2.4;
        $luminance = 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);

        return (($luminance + 0.05) / 0.05) >= (1.05 / ($luminance + 0.05)) ? '#000000' : '#FFFFFF';
    }

    private function profile(Workspace $workspace): WorkspaceBrandingProfile
    {
        return WorkspaceBrandingProfile::query()->firstOrCreate(['workspace_id' => $workspace->id], ['mode' => 'default', 'draft_version' => 0]);
    }

    private function lockedProfile(Workspace $workspace): WorkspaceBrandingProfile
    {
        $this->profile($workspace);

        return WorkspaceBrandingProfile::query()->where('workspace_id', $workspace->id)->lockForUpdate()->firstOrFail();
    }

    private function revision(?int $id): ?WorkspaceBrandingRevision
    {
        return $id === null ? null : WorkspaceBrandingRevision::query()->find($id);
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    private function audit(User $actor, Workspace $workspace, string $action, ?array $before, ?array $after): void
    {
        PlatformAdministrationAuditEvent::query()->create([
            'event_id' => (string) Str::uuid(),
            'actor_kind' => 'user',
            'actor_identifier' => $actor->public_id,
            'action' => $action,
            'target_type' => 'workspace',
            'target_public_id' => $workspace->public_id,
            'before' => $before,
            'after' => $after,
            'correlation_id' => (string) Str::uuid(),
            'occurred_at' => CarbonImmutable::now(),
        ]);
    }
}
