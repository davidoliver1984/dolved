<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light only">
    <title>{{ $mailSubject }}</title>
</head>
<body style="margin:0;background:#f4f2ea;color:#17221e;font-family:Arial,Helvetica,sans-serif;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">A workspace administrator invited you to collaborate in {{ $brandName }}.</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f2ea;padding:24px 12px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #d8ddd9;border-radius:16px;overflow:hidden;">
            <tr><td align="center" style="padding:28px 32px 18px;font-size:30px;font-weight:800;letter-spacing:-1px;text-align:center;">
                @if ($logoUrl)<img alt="{{ $brandName }}" src="{{ $logoUrl }}" style="display:block;width:auto;height:auto;max-height:80px;max-width:220px;margin:0 auto 12px;">@endif
                <span>{{ $brandName }}</span>
            </td></tr>
            <tr><td style="padding:8px 32px 0;"><h1 style="margin:0;font-size:28px;line-height:1.2;">You&apos;re invited</h1></td></tr>
            <tr><td style="padding:14px 32px 6px;color:#43534c;font-size:17px;line-height:1.55;">A workspace administrator invited you to collaborate in {{ $brandName }}.</td></tr>
            <tr><td style="padding:20px 32px 12px;">
                <a href="{{ $actionUrl }}" style="display:inline-block;background-color:{{ $accentColour }};background-image:linear-gradient({{ $accentColour }},{{ $accentColour }});color:{{ $accentForeground }};text-decoration:none;font-weight:700;padding:13px 20px;border-radius:8px;">Review invitation</a>
            </td></tr>
            <tr><td style="padding:4px 32px 24px;color:#68766f;font-size:14px;line-height:1.5;">This invitation expires at {{ $expiresAt }}.</td></tr>
            <tr><td style="border-top:1px solid #e6e9e7;padding:20px 32px 28px;color:#68766f;font-size:13px;line-height:1.5;">
                If the button does not work, copy this link into your browser:<br><a href="{{ $actionUrl }}" style="color:{{ $linkColour }};word-break:break-all;">{{ $actionUrl }}</a>
                @if ($supportContact)<br><br>Support: <a href="mailto:{{ $supportContact }}" style="color:{{ $linkColour }};">{{ $supportContact }}</a>@endif
            </td></tr>
        </table>
        <div style="padding:18px;color:#7a8580;font-size:12px;">@if ($footerText){{ $footerText }} · @endif Sent by {{ $brandName }}</div>
    </td></tr>
</table>
</body>
</html>
