{{ $brandName }}

You're invited

A workspace administrator invited you to collaborate in {{ $brandName }}.

Review invitation: {{ $actionUrl }}

This invitation expires at {{ $expiresAt }}.
@if ($supportContact)

Support: {{ $supportContact }}
@endif
@if ($footerText)

{{ $footerText }}
@endif

Sent by {{ $brandName }}.
