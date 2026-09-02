<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Documents\AssembleDocumentGovernanceEmailEnvelope;
use App\Actions\Documents\BuildDocumentGovernanceEmail;
use App\Actions\Documents\ClaimDocumentGovernanceEmailEnvelope;
use App\Actions\Documents\CompleteDocumentGovernanceEmailAttempt;
use App\Actions\Documents\ReclaimExpiredDocumentGovernanceEmailAttempts;
use App\Actions\Documents\ScanDocumentGovernanceRemindersAndAuthorityTransitions;
use App\Actions\Documents\SealDocumentGovernanceEmailEnvelope;
use App\Actions\Documents\SealDueDocumentGovernanceEmailDigests;
use App\Actions\Documents\VerifyDocumentGovernanceEmailAttempt;
use App\Contracts\Documents\DocumentGovernanceEmailTransport;
use App\Enums\DocumentGovernanceEventKey;
use App\Enums\DocumentGovernanceStatus;
use App\Enums\DocumentStatus;
use App\Enums\GovernanceEmailAttemptStatus;
use App\Enums\GovernanceEmailEnvelopeStatus;
use App\Exceptions\GovernanceEmailTransportException;
use App\Jobs\DispatchDocumentGovernanceEmailEnvelope;
use App\Mail\DocumentGovernanceMail;
use App\Models\Document;
use App\Models\DocumentFamily;
use App\Models\DocumentGovernanceEmailEnvelope;
use App\Models\DocumentGovernanceEmailEnvelopeAttempt;
use App\Models\DocumentGovernanceNotification;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Models\WorkspaceNotificationSetting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DocumentGovernanceEmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_immediate_and_digest_envelopes_have_deterministic_membership_and_sealing(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $assemble = app(AssembleDocumentGovernanceEmailEnvelope::class);
        $immediate = $assemble->handle($this->notification($owner, $workspace, DocumentGovernanceEventKey::ImportBatchCompleted));
        $this->assertSame(GovernanceEmailEnvelopeStatus::Ready, $immediate?->assembly_status);
        $this->assertNotNull($immediate?->sealed_rendering_basis_digest);
        $this->assertCount(1, $immediate?->members ?? []);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-02 10:00:00', 'UTC'));
        $first = $assemble->handle($this->notification($owner, $workspace, DocumentGovernanceEventKey::GovernanceReviewDueSoon));
        $second = $assemble->handle($this->notification($owner, $workspace, DocumentGovernanceEventKey::GovernanceReviewOverdue));
        $this->assertSame($first?->id, $second?->id);
        $this->assertSame(GovernanceEmailEnvelopeStatus::Assembling, $first?->assembly_status);
        $sealed = app(SealDocumentGovernanceEmailEnvelope::class)->handle($first->id);
        $this->assertSame(GovernanceEmailEnvelopeStatus::Ready, $sealed?->assembly_status);
        $this->assertSame([1, 2], $sealed?->members()->pluck('ordinal')->all());

        $late = $assemble->handle($this->notification($owner, $workspace, DocumentGovernanceEventKey::GovernanceReviewDueSoon));
        $this->assertNotSame($sealed?->id, $late?->id);
        $this->assertSame('2026-09-03', $late?->digest_date?->toDateString());
    }

    public function test_workspace_gate_suppresses_without_consuming_an_attempt(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        WorkspaceNotificationSetting::query()->create([
            'workspace_id' => $workspace->id,
            'email_delivery_enabled' => false,
            'default_email_enabled' => true,
        ]);
        $envelope = app(AssembleDocumentGovernanceEmailEnvelope::class)
            ->handle($this->notification($owner, $workspace, DocumentGovernanceEventKey::ImportBatchCompleted));

        $this->assertNull(app(ClaimDocumentGovernanceEmailEnvelope::class)->handle($envelope->id));
        $this->assertSame(GovernanceEmailEnvelopeStatus::Suppressed, $envelope->refresh()->assembly_status);
        $this->assertSame('no_deliverable_members', $envelope->suppression_reason);
        $this->assertDatabaseCount('document_governance_email_envelope_attempts', 0);
        $this->assertDatabaseHas('document_governance_email_envelope_member_decisions', [
            'decision' => 'suppressed',
            'suppression_reason' => 'workspace_email_disabled',
        ]);
    }

    public function test_claim_verification_and_acceptance_are_fenced_and_idempotent(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $envelope = app(AssembleDocumentGovernanceEmailEnvelope::class)
            ->handle($this->notification($owner, $workspace, DocumentGovernanceEventKey::ImportBatchCompleted));
        $claim = app(ClaimDocumentGovernanceEmailEnvelope::class)->handle($envelope->id);
        $attempt = $claim['attempt'];
        $verified = app(VerifyDocumentGovernanceEmailAttempt::class)
            ->handle($attempt->id, $attempt->attempt_token, $attempt->generation);
        $this->assertNotNull($verified);

        $complete = app(CompleteDocumentGovernanceEmailAttempt::class);
        $this->assertTrue($complete->handle($attempt->id, $attempt->attempt_token, 1, 'provider-1'));
        $this->assertFalse($complete->handle($attempt->id, $attempt->attempt_token, 1, 'provider-1'));
        $this->assertSame(GovernanceEmailEnvelopeStatus::Sent, $envelope->refresh()->assembly_status);
        $this->assertSame(GovernanceEmailAttemptStatus::Accepted, $attempt->refresh()->status);
    }

    public function test_expired_attempt_is_abandoned_and_envelope_becomes_retryable(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $envelope = app(AssembleDocumentGovernanceEmailEnvelope::class)
            ->handle($this->notification($owner, $workspace, DocumentGovernanceEventKey::ImportBatchCompleted));
        $attempt = app(ClaimDocumentGovernanceEmailEnvelope::class)->handle($envelope->id)['attempt'];
        DocumentGovernanceEmailEnvelopeAttempt::query()->whereKey($attempt->id)->update(['lease_expires_at' => now()->subSecond()]);

        $this->assertSame(1, app(ReclaimExpiredDocumentGovernanceEmailAttempts::class)->handle());
        $this->assertSame(GovernanceEmailAttemptStatus::Abandoned, $attempt->refresh()->status);
        $this->assertSame(GovernanceEmailEnvelopeStatus::Ready, $envelope->refresh()->assembly_status);
        $this->assertSame(2, app(ClaimDocumentGovernanceEmailEnvelope::class)->handle($envelope->id)['attempt']->generation);
    }

    public function test_digest_tampering_fails_closed_before_any_provider_boundary(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $envelope = app(AssembleDocumentGovernanceEmailEnvelope::class)
            ->handle($this->notification($owner, $workspace, DocumentGovernanceEventKey::ImportBatchCompleted));
        $attempt = app(ClaimDocumentGovernanceEmailEnvelope::class)->handle($envelope->id)['attempt'];
        DocumentGovernanceEmailEnvelope::query()->whereKey($envelope->id)->update([
            'dispatch_decision_digest' => str_repeat('0', 64),
        ]);

        $this->assertNull(app(VerifyDocumentGovernanceEmailAttempt::class)
            ->handle($attempt->id, $attempt->attempt_token, $attempt->generation));
        $this->assertSame(GovernanceEmailAttemptStatus::FailedPermanent, $attempt->refresh()->status);
        $this->assertSame(GovernanceEmailEnvelopeStatus::FailedPermanent, $envelope->refresh()->assembly_status);
        $this->assertSame('rendering_integrity_failure', $envelope->terminal_failure_category);
    }

    public function test_immediate_email_uses_dolved_fallback_safe_copy_and_a_live_allowlisted_route(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $document = Document::factory()->for($workspace)->create();
        $notification = $this->notification($owner, $workspace, DocumentGovernanceEventKey::ImportBatchCompleted);
        $notification->forceFill([
            'target_kind' => 'document',
            'target_public_id' => $document->public_id,
            'target_display_label' => '<script>unsafe-file.pdf</script>',
            'parameters' => ['storage_url' => 'https://storage.invalid/signed-secret'],
        ])->save();
        $envelope = app(AssembleDocumentGovernanceEmailEnvelope::class)->handle($notification);
        $claim = app(ClaimDocumentGovernanceEmailEnvelope::class)->handle($envelope->id);

        $mail = app(BuildDocumentGovernanceEmail::class)->handle($claim['envelope']->refresh());
        $html = $mail->render();
        $text = view('mail.governance.text', get_object_vars($mail))->render();

        $this->assertStringContainsString('Dolved', $html);
        $this->assertStringContainsString($workspace->name, $html);
        $this->assertStringContainsString("/app/workspaces/{$workspace->public_id}/documents/{$document->public_id}", $html);
        $this->assertStringContainsString('Manage notification preferences', $html);
        $this->assertStringContainsString('Import complete', $text);
        $this->assertStringNotContainsString('unsafe-file.pdf', $html);
        $this->assertStringNotContainsString('storage.invalid', $html);
        $this->assertSame($envelope->envelope_key, $mail->idempotencyKey);
    }

    public function test_fenced_dispatch_records_transport_acceptance_once(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $document = Document::factory()->for($workspace)->create();
        $notification = $this->notification($owner, $workspace, DocumentGovernanceEventKey::ImportBatchCompleted);
        $notification->forceFill(['target_kind' => 'document', 'target_public_id' => $document->public_id])->save();
        $envelope = app(AssembleDocumentGovernanceEmailEnvelope::class)->handle($notification);
        $transport = new class implements DocumentGovernanceEmailTransport
        {
            public ?DocumentGovernanceMail $mail = null;

            public function send(User $recipient, DocumentGovernanceMail $mail): string
            {
                $this->mail = $mail;

                return 'provider-message-1';
            }
        };
        $this->app->instance(DocumentGovernanceEmailTransport::class, $transport);

        $this->app->call([new DispatchDocumentGovernanceEmailEnvelope($envelope->id), 'handle']);
        $this->assertNotNull($transport->mail);
        $this->assertSame(GovernanceEmailEnvelopeStatus::Sent, $envelope->refresh()->assembly_status);
        $this->assertSame('provider-message-1', $envelope->provider_message_id);
        $this->assertDatabaseHas('document_governance_email_envelope_attempts', [
            'envelope_id' => $envelope->id,
            'generation' => 1,
            'status' => GovernanceEmailAttemptStatus::Accepted->value,
            'provider_message_id' => 'provider-message-1',
        ]);

        $this->app->call([new DispatchDocumentGovernanceEmailEnvelope($envelope->id), 'handle']);
        $this->assertDatabaseCount('document_governance_email_envelope_attempts', 1);
    }

    public function test_retryable_transport_failure_is_recorded_without_retry_to_success(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $document = Document::factory()->for($workspace)->create();
        $notification = $this->notification($owner, $workspace, DocumentGovernanceEventKey::ImportBatchCompleted);
        $notification->forceFill(['target_kind' => 'document', 'target_public_id' => $document->public_id])->save();
        $envelope = app(AssembleDocumentGovernanceEmailEnvelope::class)->handle($notification);
        $transport = new class implements DocumentGovernanceEmailTransport
        {
            public int $calls = 0;

            public function send(User $recipient, DocumentGovernanceMail $mail): string
            {
                $this->calls++;
                throw new GovernanceEmailTransportException('temporary refusal');
            }
        };
        $this->app->instance(DocumentGovernanceEmailTransport::class, $transport);

        try {
            $this->app->call([new DispatchDocumentGovernanceEmailEnvelope($envelope->id), 'handle']);
            $this->fail('The retryable transport failure should escape to the queue.');
        } catch (GovernanceEmailTransportException) {
            $this->assertSame(1, $transport->calls);
        }

        $this->assertSame(GovernanceEmailEnvelopeStatus::Ready, $envelope->refresh()->assembly_status);
        $this->assertNotNull($envelope->next_attempt_at);
        $this->assertDatabaseHas('document_governance_email_envelope_attempts', [
            'envelope_id' => $envelope->id,
            'status' => GovernanceEmailAttemptStatus::FailedRetryable->value,
            'failure_category' => 'mail_transport_failure',
        ]);
        $this->assertNull(app(ClaimDocumentGovernanceEmailEnvelope::class)->handle($envelope->id));
    }

    public function test_unknown_template_identity_fails_permanently_before_transport(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $envelope = app(AssembleDocumentGovernanceEmailEnvelope::class)
            ->handle($this->notification($owner, $workspace, DocumentGovernanceEventKey::ImportBatchCompleted));
        DocumentGovernanceEmailEnvelope::query()->whereKey($envelope->id)->update(['template_version' => 99]);
        $transport = new class implements DocumentGovernanceEmailTransport
        {
            public int $calls = 0;

            public function send(User $recipient, DocumentGovernanceMail $mail): string
            {
                $this->calls++;

                return 'must-not-send';
            }
        };
        $this->app->instance(DocumentGovernanceEmailTransport::class, $transport);

        $this->app->call([new DispatchDocumentGovernanceEmailEnvelope($envelope->id), 'handle']);

        $this->assertSame(0, $transport->calls);
        $this->assertSame(GovernanceEmailEnvelopeStatus::FailedPermanent, $envelope->refresh()->assembly_status);
        $this->assertSame('rendering_or_dispatch_contract_failure', $envelope->last_error);
    }

    public function test_due_digest_is_sealed_once_and_queued_with_ordered_safe_members(): void
    {
        Queue::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-02 16:00:00', 'UTC'));
        [$owner, $workspace] = $this->ownerWorkspace();
        $assemble = app(AssembleDocumentGovernanceEmailEnvelope::class);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-02 10:00:00', 'UTC'));
        $envelope = $assemble->handle($this->notification($owner, $workspace, DocumentGovernanceEventKey::GovernanceReviewDueSoon));
        $assemble->handle($this->notification($owner, $workspace, DocumentGovernanceEventKey::GovernanceReviewOverdue));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-02 16:00:00', 'UTC'));

        $this->assertSame(1, app(SealDueDocumentGovernanceEmailDigests::class)->handle());
        $this->assertSame(0, app(SealDueDocumentGovernanceEmailDigests::class)->handle());
        Queue::assertPushed(DispatchDocumentGovernanceEmailEnvelope::class, 1);
        $claim = app(ClaimDocumentGovernanceEmailEnvelope::class)->handle($envelope->id);
        $mail = app(BuildDocumentGovernanceEmail::class)->handle($claim['envelope']->refresh());
        $this->assertSame('Document reviews need attention', $mail->heading);
        $this->assertSame(['Review due soon', 'Review overdue'], array_column($mail->items, 'title'));
        $this->assertStringContainsString('/documents/attention', $mail->actionUrl);
    }

    public function test_preferences_are_scoped_and_workspace_settings_require_an_administrator(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $member = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($member)->member()->create();
        $url = "/api/workspaces/{$workspace->public_id}/governance-notification-preferences";

        $this->actingAs($member)->putJson($url.'/workspace', [
            'email_delivery_enabled' => false,
            'default_email_enabled' => false,
        ])->assertNotFound();
        $this->actingAs($owner)->putJson($url.'/workspace', [
            'email_delivery_enabled' => true,
            'default_email_enabled' => false,
        ])->assertOk()->assertJsonPath('data.default_email_enabled', false);
        $this->actingAs($member)->putJson($url.'/personal', [
            'category_group' => 'review_reminders',
            'email_enabled' => false,
        ])->assertOk();
        $this->actingAs($member)->getJson($url)->assertOk()
            ->assertJsonPath('data.workspace.can_manage', false)
            ->assertJsonPath('data.personal.0.category_group', 'review_reminders');
    }

    public function test_daily_scanner_uses_occurrence_identity_as_the_only_idempotency_authority(): void
    {
        Queue::fake();
        [$owner, $workspace] = $this->ownerWorkspace();
        $family = DocumentFamily::factory()->for($workspace)->create([
            'owner_user_id' => $owner->id,
            'review_due_date' => today()->addDays(7),
        ]);
        $blocked = Document::factory()->for($workspace)->for($family, 'family')->create([
            'status' => DocumentStatus::Indexed,
            'governance_status' => DocumentGovernanceStatus::Approved,
            'approved_at' => now()->subDay(),
            'effective_from' => now()->addDay(),
        ]);
        Document::factory()->for($workspace)->for($family, 'family')->create([
            'status' => DocumentStatus::Indexed,
            'governance_status' => DocumentGovernanceStatus::Approved,
            'approved_at' => now()->subDays(2),
            'effective_from' => now()->subDays(2),
            'predecessor_document_id' => $blocked->id,
        ]);

        $scanner = app(ScanDocumentGovernanceRemindersAndAuthorityTransitions::class);
        $scanner->handle();
        $scanner->handle();
        $this->assertDatabaseCount('document_governance_events', 3);
        $this->assertDatabaseHas('document_governance_events', ['event_key' => DocumentGovernanceEventKey::GovernanceAuthorityBlocked->value]);
        $this->assertDatabaseHas('document_governance_events', ['event_key' => DocumentGovernanceEventKey::GovernanceAuthorityAttained->value]);
        $this->assertDatabaseMissing('document_governance_events', ['occurrence_key' => null]);
    }

    private function notification(User $recipient, Workspace $workspace, DocumentGovernanceEventKey $event): DocumentGovernanceNotification
    {
        $membership = WorkspaceMembership::query()->where('workspace_id', $workspace->id)->where('user_id', $recipient->id)->firstOrFail();

        return DocumentGovernanceNotification::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'recipient_user_id' => $recipient->id,
            'recipient_user_public_id' => $recipient->public_id,
            'recipient_workspace_membership_id' => $membership->id,
            'event_key' => $event,
            'source_event_id' => (string) Str::uuid(),
            'template_key' => $event->value,
            'template_version' => 1,
            'parameters' => [],
            'severity' => 'info',
            'expires_at' => now()->addDays(90),
        ]);
    }

    /** @return array{User, Workspace} */
    private function ownerWorkspace(): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        return [$owner, Workspace::factory()->withOwner($owner)->create()];
    }
}
