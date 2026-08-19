<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <style>
        body { margin:0; padding:0; -webkit-text-size-adjust:none; -ms-text-size-adjust:none; }
        table { border-collapse:collapse; }
        img { border:0; display:block; }
        .button { display:inline-block; }
        @media only screen and (max-width:600px) {
            .container { width:100% !important; padding:20px !important; }
            .content { padding:20px !important; }
            h1 { font-size:20px !important; }
        }
    </style>
</head>
<body style="font-family:Helvetica,Arial,sans-serif;background:#f4f6f8;padding:30px;">

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td align="center">
                <table class="container" width="600" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px;width:100%;background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.06);">

                    <!-- Header -->
                    <tr>
                        <td style="background:linear-gradient(90deg,#2563eb,#1e40af);padding:18px 24px;color:#ffffff;">
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                                <tr>
                                    <td style="font-weight:700;font-size:18px;">{{ config('app.name', env('APP_NAME', 'Zeosync')) }}</td>
                                    <td align="right" style="font-size:13px;color:rgba(255,255,255,0.9);">Subscription Cancelled</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td class="content" style="padding:30px;color:#333333;">
                            <h1 style="margin:0 0 12px 0;font-size:22px;color:#0f172a;">Subscription Cancelled</h1>

                            <p style="margin:0 0 16px 0;color:#334155;line-height:1.6;">Hello,</p>

                            <p style="margin:0 0 16px 0;color:#334155;line-height:1.6;">Your subscription has been successfully cancelled.</p>

                            <p style="margin:0 0 20px 0;color:#334155;line-height:1.6;"><strong>Plan:</strong> {{ $subscription->plan->name ?? 'N/A' }}</p>

                            <p style="margin:0 0 20px 0;color:#334155;line-height:1.6;">You can resubscribe anytime if you wish to continue using our services.</p>

                            <!-- CTA Button -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:12px 0 20px 0;">
                                <tr>
                                    <td align="left">
                                        <a class="button" href="{{ url('/') }}" style="background:#2563eb;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;display:inline-block;font-weight:600;">Open Dashboard</a>
                                    </td>
                                </tr>
                            </table>

                            <hr style="border:none;border-top:1px solid #e6eef8;margin:18px 0;">

                            <p style="font-size:13px;color:#6b7280;margin:0;">This is an automated email from {{ config('app.name', env('APP_NAME', 'Zeosync')) }}.</p>

                            <p style="font-size:12px;color:#94a3b8;margin-top:12px;word-break:break-word;">If the button doesn't work, copy and paste this link: <a href="{{ url('/') }}" style="color:#2563eb;">{{ url('/dashboard') }}</a></p>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background:#f8fafc;padding:14px 24px;color:#94a3b8;font-size:12px;" align="center">
                            <div style="max-width:520px;margin:0 auto;">&copy; {{ date('Y') }} {{ config('app.name', env('APP_NAME', 'Zeosync')) }}. All rights reserved.</div>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>