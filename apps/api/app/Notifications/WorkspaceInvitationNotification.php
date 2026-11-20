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
        /** @var array<string, mixed> */
        private readonly array $branding,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = config('workspace_administration.frontend_url').'/invitations/'.rawurlencode($this->token);
        $brandName = is_string($this->branding['display_name'] ?? null) ? $this->branding['display_name'] : 'Dolved';
        $senderName = is_string($this->branding['sender_display_name'] ?? null) && $this->branding['sender_display_name'] !== ''
            ? $this->branding['sender_display_name']
            : $brandName;
        $message = (new MailMessage)
            ->from((string) config('mail.from.address'), $senderName)
            ->subject("You have been invited to {$brandName}")
            ->line("A workspace administrator invited you to collaborate in {$brandName}.")
            ->action('Review invitation', $url)
            ->line('This invitation expires at '.$this->invitation->expires_at->toIso8601String().'.');

        if (is_string($this->branding['footer_text'] ?? null) && $this->branding['footer_text'] !== '') {
            $message->line($this->branding['footer_text']);
        }
        if (is_string($this->branding['support_contact'] ?? null) && $this->branding['support_contact'] !== '') {
            $message->line('Support: '.$this->branding['support_contact']);
        }

        return $message;
    }
}
