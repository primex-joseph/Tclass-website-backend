<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrance Exam Schedule Invitation</title>
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
                        <td style="padding:28px 24px 16px;">
                            <p style="margin:0 0 10px;font-size:12px;letter-spacing:1.2px;text-transform:uppercase;color:#2563eb;font-weight:700;">
                                Entrance Exam Invitation
                            </p>
                            <h1 style="margin:0;font-size:24px;line-height:1.25;color:#0f172a;">You have been invited to the entrance exam</h1>
                            <p style="margin:14px 0 0;font-size:15px;line-height:1.65;color:#475569;">
                                Dear {{ $fullName }},
                            </p>
                            <p style="margin:10px 0 0;font-size:15px;line-height:1.65;color:#475569;">
                                {{ $introMessage }}
                            </p>
                            <p style="margin:10px 0 0;font-size:15px;line-height:1.65;color:#475569;">
                                Applied Program/Course: <strong style="color:#0f172a;">{{ $course }}</strong>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px 18px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #dbeafe;border-radius:12px;background:#f8fbff;">
                                <tr>
                                    <td style="padding:16px 18px;">
                                        <p style="margin:0 0 10px;font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#1d4ed8;font-weight:700;">Schedule Details</p>
                                        <p style="margin:6px 0;font-size:14px;color:#334155;"><strong>When (Date):</strong> {{ $examDate }}</p>
                                        <p style="margin:6px 0;font-size:14px;color:#334155;"><strong>Time:</strong> {{ $examTime }}</p>
                                        <p style="margin:6px 0;font-size:14px;color:#334155;"><strong>Day:</strong> {{ $examDay }}</p>
                                        <p style="margin:6px 0;font-size:14px;color:#334155;"><strong>Where:</strong> {{ $location }}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px 10px;">
                            <p style="margin:0 0 8px;font-size:14px;font-weight:700;color:#0f172a;">Things to bring</p>
                            <div style="font-size:14px;line-height:1.65;color:#475569;white-space:pre-line;">{{ $thingsToBring }}</div>
                        </td>
                    </tr>
                    @if(!empty($attireNote))
                    <tr>
                        <td style="padding:0 24px 10px;">
                            <p style="margin:0 0 8px;font-size:14px;font-weight:700;color:#0f172a;">Proper attire note</p>
                            <p style="margin:0;font-size:14px;line-height:1.65;color:#475569;">{{ $attireNote }}</p>
                        </td>
                    </tr>
                    @endif
                    @if(!empty($additionalNote))
                    <tr>
                        <td style="padding:0 24px 10px;">
                            <p style="margin:0 0 8px;font-size:14px;font-weight:700;color:#0f172a;">Additional note</p>
                            <p style="margin:0;font-size:14px;line-height:1.65;color:#475569;">{{ $additionalNote }}</p>
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td style="padding:18px 24px 26px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;">
                                <tr>
                                    <td style="padding:14px 16px;">
                                        <p style="margin:0;font-size:13px;line-height:1.6;color:#475569;">
                                            Please arrive at least <strong>15 to 30 minutes early</strong> for verification and attendance.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:16px 0 0;font-size:14px;line-height:1.7;color:#475569;">
                                Thank you, and we look forward to seeing you.
                            </p>
                            <p style="margin:8px 0 0;font-size:14px;line-height:1.7;color:#475569;">
                                <strong style="color:#0f172a;">Tarlac Center for Learning and Skills Success (TCLASS)</strong><br>
                                Admissions Office
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
