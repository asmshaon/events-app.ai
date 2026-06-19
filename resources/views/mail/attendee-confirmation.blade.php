<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration confirmed</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#18181b;">
    <div style="max-width:560px;margin:0 auto;padding:24px;">
        <div style="background:#ffffff;border:1px solid #e4e4e7;border-radius:12px;padding:28px;">
            <h1 style="margin:0 0 8px;font-size:20px;">You're on the list! 🎉</h1>
            <p style="margin:0 0 20px;color:#52525b;">Hi {{ $attendeeName }}, your spot for the following event is confirmed.</p>

            <h2 style="margin:0 0 12px;font-size:18px;">{{ $eventName }}</h2>
            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <tr>
                    <td style="padding:6px 0;color:#71717a;width:90px;">When</td>
                    <td style="padding:6px 0;">{{ $when ?? 'To be announced' }}@if($when && $timezone) <span style="color:#71717a;">({{ $timezone }})</span>@endif</td>
                </tr>
                <tr>
                    <td style="padding:6px 0;color:#71717a;">Where</td>
                    <td style="padding:6px 0;">{{ $where }}</td>
                </tr>
            </table>

            <p style="margin:24px 0 0;color:#52525b;font-size:13px;">We'll send you a reminder as the event approaches. See you there!</p>
        </div>
    </div>
</body>
</html>
