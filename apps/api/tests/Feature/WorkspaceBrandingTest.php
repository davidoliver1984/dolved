<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Enums\WorkspaceInvitationStatus;
use App\Enums\WorkspaceRole;
use App\Models\PlatformAdministrationAuditEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceBrandingAsset;
use App\Models\WorkspaceBrandingRevision;
use App\Models\WorkspaceInvitation;
use App\Services\Branding\WorkspaceBranding;
use App\Services\Documents\DolvedGovernanceEmailBrandingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

final class WorkspaceBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.disks.local.root' => sys_get_temp_dir().'/dolved-branding-tests/'.Str::uuid()]);
        config(['branding.asset_disk' => 'local']);
    }

    public function test_only_platform_administrators_can_read_or_change_tenant_branding(): void
    {
        $workspace = Workspace::factory()->withOwner()->create();
        $owner = $workspace->creator;
        $administrator = User::factory()->create(['platform_role' => PlatformRole::Administrator]);

        $this->actingAs($owner)->getJson('/api/platform/tenants')->assertNotFound();
        $this->actingAs($owner)->getJson("/api/platform/tenants/{$workspace->public_id}/branding")->assertNotFound();
        $this->actingAs($administrator)->getJson('/api/platform/tenants')
            ->assertOk()
            ->assertJsonPath('data.0.public_id', $workspace->public_id)
            ->assertJsonPath('data.0.branding_mode', 'default');
    }

    public function test_draft_apply_default_and_reapply_are_versioned_audited_and_safe_for_members(): void
    {
        $workspace = Workspace::factory()->withOwner()->create();
        $administrator = User::factory()->create(['platform_role' => PlatformRole::Administrator]);
        $member = $workspace->creator;

        $asset = $this->actingAs($administrator)->post(
            "/api/platform/tenants/{$workspace->public_id}/branding/assets",
            ['kind' => 'primary_logo', 'asset' => UploadedFile::fake()->image('client-logo.jpg', 240, 80)->size(100)],
        )->assertCreated()->json('data');

        $configuration = [
            'display_name' => 'Alderbridge Care',
            'logo_label' => 'Alderbridge Care',
            'primary_logo' => $asset['public_id'],
            'dark_logo' => null,
            'favicon' => null,
            'primary_colour' => '#6b2d84',
            'accent_colour' => '#2255aa',
            'sign_in_heading' => 'Welcome to Alderbridge',
            'sign_in_introduction' => 'Use your work account to continue.',
            'chat_welcome_heading' => 'Ask Alderbridge',
            'chat_welcome_introduction' => 'Ask about an approved policy or procedure.',
            'sender_display_name' => 'Alderbridge Care',
            'footer_text' => 'Internal service correspondence.',
            'support_contact' => 'support@alderbridge.example',
        ];
        $saved = $this->actingAs($administrator)->putJson(
            "/api/platform/tenants/{$workspace->public_id}/branding/draft",
            ['draft_version' => 0, 'configuration' => $configuration],
        )->assertOk()->assertJsonPath('data.mode', 'default')->assertJsonPath('data.draft_version', 1)->json('data');

        $this->actingAs($administrator)->putJson(
            "/api/platform/tenants/{$workspace->public_id}/branding/draft",
            ['draft_version' => 0, 'configuration' => $configuration],
        )->assertUnprocessable()->assertJsonValidationErrors('draft_version');

        $applied = $this->actingAs($administrator)->postJson(
            "/api/platform/tenants/{$workspace->public_id}/branding/apply",
            ['draft_version' => $saved['draft_version']],
        )->assertOk()->assertJsonPath('data.mode', 'custom')->assertJsonPath('data.effective.configuration.display_name', 'Alderbridge Care')->json('data');
        $this->assertSame(1, WorkspaceBrandingRevision::query()->count());
        $identity = app(WorkspaceBranding::class)->emailSnapshot($workspace);
        $emailBrand = app(DolvedGovernanceEmailBrandingResolver::class)->resolve($identity['configuration_identity'], $identity['accent_identity']);
        $this->assertSame('Alderbridge Care', $emailBrand->brandName);
        $this->assertSame('Alderbridge Care', $emailBrand->senderDisplayName);
        $this->assertSame('support@alderbridge.example', $emailBrand->supportContact);

        $this->actingAs($member)->getJson("/api/workspaces/{$workspace->public_id}/branding")
            ->assertOk()
            ->assertJsonPath('data.mode', 'custom')
            ->assertJsonPath('data.configuration.primary_logo.public_id', $asset['public_id']);

        $token = str_repeat('a', 64);
        WorkspaceInvitation::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'invited_email' => 'invitee@example.test',
            'intended_role' => WorkspaceRole::Member,
            'invited_by_user_id' => $administrator->id,
            'token_digest' => hash('sha256', $token),
            'status' => WorkspaceInvitationStatus::Pending,
            'expires_at' => now()->addDay(),
        ]);
        $this->getJson("/api/branding/invitations/{$token}")
            ->assertOk()
            ->assertJsonPath('data.configuration.display_name', 'Alderbridge Care');
        $this->getJson('/api/branding/invitations/'.str_repeat('b', 64))->assertNotFound();

        $this->actingAs($administrator)->putJson(
            "/api/platform/tenants/{$workspace->public_id}/branding/mode",
            ['mode' => 'default'],
        )->assertOk()->assertJsonPath('data.effective.configuration.display_name', 'Dolved');
        $this->actingAs($administrator)->putJson(
            "/api/platform/tenants/{$workspace->public_id}/branding/mode",
            ['mode' => 'custom'],
        )->assertOk()->assertJsonPath('data.applied_revision', $applied['applied_revision']);

        $this->assertSame([
            'tenant.branding.draft_saved',
            'tenant.branding.custom_applied',
            'tenant.branding.mode_switched',
            'tenant.branding.mode_switched',
        ], PlatformAdministrationAuditEvent::query()->orderBy('id')->pluck('action')->all());
    }

    public function test_assets_are_reencoded_and_cross_tenant_references_are_rejected(): void
    {
        $first = Workspace::factory()->create();
        $second = Workspace::factory()->create();
        $administrator = User::factory()->create(['platform_role' => PlatformRole::Administrator]);
        $asset = $this->actingAs($administrator)->post(
            "/api/platform/tenants/{$first->public_id}/branding/assets",
            ['kind' => 'primary_logo', 'asset' => UploadedFile::fake()->image('logo.webp', 200, 60)->size(100)],
        )->assertCreated()->json('data');

        $this->get("/api/branding/assets/{$asset['public_id']}")
            ->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->actingAs($administrator)->putJson(
            "/api/platform/tenants/{$second->public_id}/branding/draft",
            ['draft_version' => 0, 'configuration' => [
                'display_name' => 'Second', 'logo_label' => 'Second', 'primary_logo' => $asset['public_id'],
                'dark_logo' => null, 'favicon' => null, 'primary_colour' => '#112233', 'accent_colour' => '#334455',
                'sign_in_heading' => null, 'sign_in_introduction' => null, 'chat_welcome_heading' => null,
                'chat_welcome_introduction' => null, 'sender_display_name' => null, 'footer_text' => null, 'support_contact' => null,
            ]],
        )->assertUnprocessable()->assertJsonValidationErrors('primary_logo');
    }

    public function test_large_png_favicon_is_resized_and_safely_compressed(): void
    {
        $workspace = Workspace::factory()->create();
        $administrator = User::factory()->create(['platform_role' => PlatformRole::Administrator]);

        $asset = $this->actingAs($administrator)->post(
            "/api/platform/tenants/{$workspace->public_id}/branding/assets",
            ['kind' => 'favicon', 'asset' => UploadedFile::fake()->image('favicon.png', 1024, 768)->size(700)],
        )->assertCreated()->json('data');

        $this->assertLessThanOrEqual(512, max($asset['width'], $asset['height']));
        $stored = WorkspaceBrandingAsset::query()->where('public_id', $asset['public_id'])->sole();
        $this->assertLessThanOrEqual(524288, $stored->size_bytes);
        $this->assertSame('image/png', $stored->media_type);
    }
}
