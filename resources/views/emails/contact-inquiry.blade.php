<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Inquiry</title>
</head>
<body style="margin:0; padding:0; background:#f1f5f9; font-family:'Segoe UI', Arial, sans-serif; color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:680px; background:#ffffff; border:1px solid #dbeafe; border-radius:16px; overflow:hidden;">
                    <tr>
                        <td style="background:linear-gradient(135deg,#0f2d74,#1d4ed8); padding:24px;">
                            <p style="margin:0; color:#bfdbfe; font-size:12px; letter-spacing:.08em; text-transform:uppercase;">Website Contact Submission</p>
                            <h1 style="margin:8px 0 0; color:#ffffff; font-size:22px; line-height:1.3;">Tarlac Center for Learning and Skills Success</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;">
                            <h2 style="margin:0 0 16px; font-size:22px;">New Contact Form Inquiry</h2>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; margin-bottom:18px;">
                                <tr>
                                    <td style="padding:10px 0; border-bottom:1px solid #e2e8f0; width:140px; color:#475569;"><strong>Full Name</strong></td>
                                    <td style="padding:10px 0; border-bottom:1px solid #e2e8f0;">{{ $firstName }} {{ $lastName }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0; border-bottom:1px solid #e2e8f0; color:#475569;"><strong>Email</strong></td>
                                    <td style="padding:10px 0; border-bottom:1px solid #e2e8f0;">{{ $email }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0; border-bottom:1px solid #e2e8f0; color:#475569;"><strong>Phone</strong></td>
                                    <td style="padding:10px 0; border-bottom:1px solid #e2e8f0;">{{ $phone ?: 'N/A' }}</td>
                                </tr>
                            </table>

                            <p style="margin:0 0 8px; font-size:14px; color:#475569;"><strong>Message</strong></p>
                            <div style="background:#f8fafc; border:1px solid #dbeafe; border-radius:10px; padding:14px; white-space:pre-wrap; line-height:1.7; font-size:15px;">{{ $body }}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
