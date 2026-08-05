<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank you from Zeosync</title>
</head>
<body style="margin:0; padding:0; background:#f4f6f8; font-family:Arial, sans-serif;">
    <div style="max-width:700px; margin:0 auto; padding:24px;">
        <div style="background:#1d4ed8; padding:22px; color:#fff; border-radius:12px 12px 0 0; text-align:center;">
            <h1 style="margin:0; font-size:24px;">Thank you for reaching out!</h1>
        </div>
        <div style="background:#fff; padding:24px; border-radius:0 0 12px 12px; box-shadow:0 10px 30px rgba(0,0,0,0.08);">
            <p style="font-size:16px; color:#0f172a;">Hi {{ $contact->name }},</p>
            <p style="font-size:16px; color:#334155; line-height:1.7;">Thank you for contacting Zeosync. We received your message and one of our team members will connect with you soon.</p>
            <p style="font-size:16px; color:#334155; line-height:1.7;"><strong>Your request:</strong></p>
            <p style="font-size:15px; color:#475569; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:16px;">{{ $contact->message }}</p>
            <p style="font-size:16px; color:#334155; line-height:1.7;">If you need to update your request, just reply to this email.</p>
            <p style="font-size:16px; color:#334155; line-height:1.7;">Thanks again,<br>The Zeosync Team</p>
        </div>
    </div>
</body>
</html>
