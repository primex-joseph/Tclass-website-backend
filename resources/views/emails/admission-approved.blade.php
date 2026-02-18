<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Approved</title>
</head>
<body style="margin:0; padding:0; background:#f1f5f9; font-family:'Segoe UI', Arial, sans-serif; color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:680px; background:#ffffff; border:1px solid #dbeafe; border-radius:16px; overflow:hidden;">
                    <tr>
                        <td style="background:linear-gradient(135deg,#0f2d74,#1d4ed8); padding:24px;">
                            <p style="margin:0; color:#bfdbfe; font-size:12px; letter-spacing:.08em; text-transform:uppercase;">Admission Result</p>
                            <h1 style="margin:8px 0 0; color:#ffffff; font-size:22px; line-height:1.3;">Tarlac Center for Learning and Skills Success</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 24px 24px;">
                            <h2 style="margin:0 0 14px; font-size:22px; color:#0f172a;">Admission Approved</h2>
                            <p style="margin:0 0 12px; font-size:15px; line-height:1.7;">Hello {{ $fullName }},</p>
                            <p style="margin:0 0 14px; font-size:15px; line-height:1.7;">
                                Your admission has been approved. You can now sign in using the credentials below:
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; margin-bottom:18px;">
                                <tr>
                                    <td style="padding:10px 0; border-bottom:1px solid #e2e8f0; width:180px; color:#475569;"><strong>Username</strong></td>
                                    <td style="padding:10px 0; border-bottom:1px solid #e2e8f0;"><span style="font-family:Consolas,monospace;">{{ $studentNumber }}</span></td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0; border-bottom:1px solid #e2e8f0; color:#475569;"><strong>Temporary Password</strong></td>
                                    <td style="padding:10px 0; border-bottom:1px solid #e2e8f0;"><span style="font-family:Consolas,monospace;">{{ $temporaryPassword }}</span></td>
                                </tr>
                            </table>

                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 20px;">
                                <tr>
                                    <td style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:12px 14px; color:#1e3a8a; font-size:13px;">
                                        Please change your password immediately after your first login.
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0; font-size:15px; line-height:1.7;">
                                Regards,<br>
                                <strong>Tarlac Center for Learning and Skills Success</strong>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
