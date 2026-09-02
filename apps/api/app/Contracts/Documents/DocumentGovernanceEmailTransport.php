<?php

declare(strict_types=1);

namespace App\Contracts\Documents;

use App\Mail\DocumentGovernanceMail;
use App\Models\User;

interface DocumentGovernanceEmailTransport
{
    public function send(User $recipient, DocumentGovernanceMail $mail): string;
}
