<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\WorkspaceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly WorkspaceInvitation $invitation,
        private readonly string $token,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = config('workspace_administration.frontend_url').'/invitations/'.rawurlencode($this->token);

        return (new MailMessage)
            ->subject('You have been invited to a Dolved workspace')
            ->line('A workspace administrator invited you to collaborate in Dolved.')
            ->action('Review invitation', $url)
            ->line('This invitation expires at '.$this->invitation->expires_at->toIso8601String().'.');
    }
}
