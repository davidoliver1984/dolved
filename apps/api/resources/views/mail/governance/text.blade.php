{{ $brandName }}.
@if ($workspaceName !== $brandName)
{{ $workspaceName }}
@endif

{{ $heading }}

{{ $summary }}
@foreach ($items as $item)

{{ $item['title'] }}
{{ $item['message'] }}
@endforeach

{{ $actionLabel }}: {{ $actionUrl }}

Manage notification preferences: {{ $preferenceUrl }}

This message contains no document content. Sign in to {{ $brandName }} to view authorised workspace information.
@if ($supportContact)
Support: {{ $supportContact }}
@endif

@if ($footerText){{ $footerText }}
@endif
Sent by {{ $brandName }}.
