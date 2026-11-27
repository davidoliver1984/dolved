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
        $accentColour = is_string($this->branding['accent_colour'] ?? null) ? $this->branding['accent_colour'] : '#008466';
        $accentForeground = is_string($this->branding['accent_foreground'] ?? null) ? $this->branding['accent_foreground'] : '#FFFFFF';
        $linkColour = is_string($this->branding['primary_colour'] ?? null) ? $this->branding['primary_colour'] : '#006C4D';
        $logoUrl = is_array($this->branding['primary_logo'] ?? null) && is_string($this->branding['primary_logo']['url'] ?? null)
            ? $this->branding['primary_logo']['url']
            : null;
        $footerText = is_string($this->branding['footer_text'] ?? null) ? $this->branding['footer_text'] : null;
        $supportContact = is_string($this->branding['support_contact'] ?? null) ? $this->branding['support_contact'] : null;
        $message = (new MailMessage)
            ->from((string) config('mail.from.address'), $senderName)
            ->subject("You have been invited to {$brandName}")
            ->view(['html' => 'mail.invitation.html', 'text' => 'mail.invitation.text'], [
                'mailSubject' => "You have been invited to {$brandName}",
                'brandName' => $brandName,
                'logoUrl' => $logoUrl,
                'accentColour' => $accentColour,
                'accentForeground' => $accentForeground,
                'linkColour' => $linkColour,
                'actionUrl' => $url,
                'expiresAt' => $this->invitation->expires_at->toIso8601String(),
                'footerText' => $footerText,
                'supportContact' => $supportContact,
            ]);

        return $message;
    }
}
