<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Documents\BuildDocumentGovernanceEmail;
use App\Actions\Documents\ClaimDocumentGovernanceEmailEnvelope;
use App\Actions\Documents\CompleteDocumentGovernanceEmailAttempt;
use App\Actions\Documents\FailDocumentGovernanceEmailAttempt;
use App\Actions\Documents\VerifyDocumentGovernanceEmailAttempt;
use App\Contracts\Documents\DocumentGovernanceEmailTransport;
use App\Exceptions\GovernanceEmailTransportException;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use LogicException;
use Throwable;

final class DispatchDocumentGovernanceEmailEnvelope implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 60, 120, 240];

    public function __construct(public readonly int $envelopeId)
    {
        $this->onConnection('governance');
        $this->onQueue((string) config('documents.governance_queue', 'document-governance'));
        $this->afterCommit();
    }

    public function handle(
        ClaimDocumentGovernanceEmailEnvelope $claim,
        VerifyDocumentGovernanceEmailAttempt $verify,
        BuildDocumentGovernanceEmail $build,
        DocumentGovernanceEmailTransport $transport,
        CompleteDocumentGovernanceEmailAttempt $complete,
        FailDocumentGovernanceEmailAttempt $fail,
    ): void {
        $claimed = $claim->handle($this->envelopeId);
        if ($claimed === null) {
            return;
        }

        $attempt = $claimed['attempt'];
        $verified = $verify->handle($attempt->id, $attempt->attempt_token, $attempt->generation);
        if ($verified === null) {
            return;
        }

        try {
            $envelope = $claimed['envelope']->refresh();
            $mail = $build->handle($envelope);
            $recipient = User::query()->where('public_id', $envelope->recipient_user_public_id)->firstOrFail();
            $providerMessageId = $transport->send($recipient, $mail);
            if (! $complete->handle($attempt->id, $attempt->attempt_token, $attempt->generation, $providerMessageId)) {
                throw new LogicException('Governance email acceptance arrived after its fenced attempt lost authority.');
            }
        } catch (GovernanceEmailTransportException $exception) {
            $recorded = $fail->handle(
                $attempt->id,
                $attempt->attempt_token,
                $attempt->generation,
                'mail_transport_failure',
                $exception->retryable,
            );
            if ($recorded && $exception->retryable) {
                throw $exception;
            }
        } catch (Throwable $exception) {
            $recorded = $fail->handle(
                $attempt->id,
                $attempt->attempt_token,
                $attempt->generation,
                'rendering_or_dispatch_contract_failure',
                false,
            );
            if (! $recorded) {
                throw $exception;
            }
        }
    }
}
