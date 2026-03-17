<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrance Quiz Invitation</title>
</head>
<body style="margin:0;padding:0;background:#f3f6fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f6fb;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #dbe7ff;">
                    <tr>
                        <td style="height:6px;background:linear-gradient(90deg,#2563eb,#06b6d4);"></td>
                    </tr>
                    <tr>
                        <td style="padding:28px 24px 14px;">
                            <p style="margin:0 0 10px;font-size:12px;letter-spacing:1.2px;text-transform:uppercase;color:#2563eb;font-weight:700;">
                                Entrance Quiz Invitation
                            </p>
                            <h1 style="margin:0;font-size:24px;line-height:1.25;color:#0f172a;">Your entrance exam is ready</h1>
                            <p style="margin:14px 0 0;font-size:15px;line-height:1.65;color:#475569;">
                                Dear {{ $fullName }},
                            </p>
                            <p style="margin:10px 0 0;font-size:15px;line-height:1.65;color:#475569;">
                                {{ $introMessage }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px 18px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #dbeafe;border-radius:12px;background:#f8fbff;">
                                <tr>
                                    <td style="padding:16px 18px;">
                                        <p style="margin:0 0 10px;font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#1d4ed8;font-weight:700;">Quiz Details</p>
                                        <p style="margin:6px 0;font-size:14px;color:#334155;"><strong>Program/Course:</strong> {{ $course }}</p>
                                        <p style="margin:6px 0;font-size:14px;color:#334155;"><strong>Quiz:</strong> {{ $quizTitle }}</p>
                                        <p style="margin:6px 0;font-size:14px;color:#334155;"><strong>Timer:</strong> {{ $durationMinutes }} minute(s)</p>
                                        @if(!empty($expiresAt))
                                        <p style="margin:6px 0;font-size:14px;color:#334155;"><strong>Expires at:</strong> {{ $expiresAt }}</p>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px 8px;">
                            <p style="margin:0 0 8px;font-size:14px;font-weight:700;color:#0f172a;">Important</p>
                            <p style="margin:0;font-size:14px;line-height:1.65;color:#475569;">
                                Use your existing <strong>student credentials</strong> to sign in before opening the quiz link.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 24px 24px;">
                            <a href="{{ $quizLink }}" style="display:inline-block;padding:12px 22px;border-radius:10px;background:#2563eb;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;">
                                Open Entrance Quiz
                            </a>
                            <p style="margin:14px 0 0;font-size:12px;line-height:1.6;color:#64748b;word-break:break-all;">
                                If the button does not work, copy and paste this link:<br>
                                {{ $quizLink }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
