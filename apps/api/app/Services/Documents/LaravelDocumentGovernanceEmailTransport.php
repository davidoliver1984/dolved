<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Contracts\Documents\DocumentGovernanceEmailTransport;
use App\Exceptions\GovernanceEmailTransportException;
use App\Mail\DocumentGovernanceMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class LaravelDocumentGovernanceEmailTransport implements DocumentGovernanceEmailTransport
{
    public function send(User $recipient, DocumentGovernanceMail $mail): string
    {
        try {
            $sent = Mail::to($recipient)->send($mail);
            $messageId = $sent?->getMessageId();
            if (! is_string($messageId) || $messageId === '') {
                throw new GovernanceEmailTransportException('The configured mail transport returned no message identity.');
            }

            return $messageId;
        } catch (GovernanceEmailTransportException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new GovernanceEmailTransportException('The configured mail transport rejected the governance email.', true, $exception);
        }
    }
}
