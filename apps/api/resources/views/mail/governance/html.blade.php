<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $mailSubject }}</title>
</head>
<body style="margin:0;background:#f4f2ea;color:#17221e;font-family:Arial,Helvetica,sans-serif;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $summary }}</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f2ea;padding:24px 12px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #d8ddd9;border-radius:16px;overflow:hidden;">
            <tr><td style="padding:28px 32px 18px;font-size:30px;font-weight:800;letter-spacing:-1px;">Dolved<span style="color:#008466;">.</span></td></tr>
            <tr><td style="padding:0 32px 8px;color:#5f6f68;font-size:14px;">{{ $workspaceName }}</td></tr>
            <tr><td style="padding:8px 32px 0;"><h1 style="margin:0;font-size:28px;line-height:1.2;">{{ $heading }}</h1></td></tr>
            <tr><td style="padding:14px 32px 6px;color:#43534c;font-size:17px;line-height:1.55;">{{ $summary }}</td></tr>
            @if ($items !== [])
                <tr><td style="padding:12px 32px 0;">
                    @foreach ($items as $item)
                        <div style="margin:0 0 12px;padding:16px;border:1px solid #d8ddd9;border-radius:10px;">
                            <strong style="display:block;font-size:16px;">{{ $item['title'] }}</strong>
                            <span style="display:block;margin-top:5px;color:#5f6f68;line-height:1.45;">{{ $item['message'] }}</span>
                        </div>
                    @endforeach
                </td></tr>
            @endif
            <tr><td style="padding:20px 32px 28px;">
                <a href="{{ $actionUrl }}" style="display:inline-block;background:#008466;color:#ffffff;text-decoration:none;font-weight:700;padding:13px 20px;border-radius:8px;">{{ $actionLabel }}</a>
            </td></tr>
            <tr><td style="border-top:1px solid #e6e9e7;padding:20px 32px 28px;color:#68766f;font-size:13px;line-height:1.5;">
                <a href="{{ $preferenceUrl }}" style="color:#006d55;">Manage notification preferences</a><br>
                This message contains no document content. Sign in to Dolved to view authorised workspace information.
            </td></tr>
        </table>
        <div style="padding:18px;color:#7a8580;font-size:12px;">Dolved · Grounded knowledge for your team</div>
    </td></tr>
</table>
</body>
</html>
