<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TCLASS Reply</title>
</head>
<body style="margin:0; padding:0; background:#f3f6fb; font-family: Arial, Helvetica, sans-serif; color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 0; background:#f3f6fb;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px; background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="padding:22px 24px; background:#eff6ff; border-bottom:1px solid #e2e8f0;">
                            <p style="margin:0; font-size:12px; letter-spacing:.08em; text-transform:uppercase; color:#475569;">TCLASS Support Reply</p>
                            <h1 style="margin:8px 0 0 0; font-size:22px; line-height:1.3; color:#0f172a;">Hello {{ $firstName }} {{ $lastName }},</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;">
                            <p style="margin:0 0 14px 0; font-size:14px; line-height:1.7; color:#334155;">
                                Thank you for your message. Our team has reviewed your inquiry and sent the response below:
                            </p>
                            <div style="padding:16px; border:1px solid #bfdbfe; border-radius:10px; background:#f8fbff; color:#1e3a8a; font-size:14px; line-height:1.7;">
                                {!! nl2br(e($replyBody)) !!}
                            </div>

                            <p style="margin:18px 0 10px 0; font-size:12px; color:#64748b;">Original message from you:</p>
                            <div style="padding:14px; border:1px solid #e2e8f0; border-radius:10px; background:#f8fafc; color:#475569; font-size:13px; line-height:1.6;">
                                {!! nl2br(e($originalMessage)) !!}
                            </div>

                            <p style="margin:18px 0 0 0; font-size:12px; color:#64748b;">
                                Replied by: <strong style="color:#334155;">{{ $repliedBy }}</strong>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 24px; border-top:1px solid #e2e8f0; background:#f8fafc;">
                            <p style="margin:0; font-size:12px; color:#64748b;">
                                Tarlac Center for Learning and Skills Success
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
