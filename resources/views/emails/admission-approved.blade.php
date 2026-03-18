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

                            {{-- Congratulations on passing the entrance exam --}}
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:18px;">
                                <tr>
                                    <td style="background:linear-gradient(135deg,#ecfdf5,#d1fae5); border:1px solid #86efac; border-radius:12px; padding:16px 18px;">
                                        <p style="margin:0 0 6px; font-size:16px; font-weight:700; color:#166534;">Congratulations! You passed the entrance examination.</p>
                                        <p style="margin:0 0 8px; font-size:14px; line-height:1.6; color:#15803d;">
                                            We are pleased to inform you that you have successfully passed your entrance exam and your admission to <strong>TCLASS</strong> has been officially approved.
                                        </p>
                                        @if(isset($score) && isset($total))
                                        <div style="display:inline-block; background:#bbf7d0; border:1px solid #4ade80; border-radius:8px; padding:6px 12px; font-size:14px; color:#166534; font-weight:600;">
                                            Exam Score: {{ $score }} / {{ $total }}
                                        </div>
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            {{-- Login Credentials --}}
                            @if(!empty($temporaryPassword))
                            <p style="margin:0 0 8px; font-size:15px; font-weight:700; color:#0f172a;">Your Official Login Credentials</p>
                            <p style="margin:0 0 10px; font-size:14px; line-height:1.6; color:#475569;">
                                Below are your official student credentials. Use these to sign in to the Student Portal:
                            </p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; margin-bottom:18px;">
                                <tr>
                                    <td style="padding:10px 0; border-bottom:1px solid #e2e8f0; width:180px; color:#475569;"><strong>Username / Student No.</strong></td>
                                    <td style="padding:10px 0; border-bottom:1px solid #e2e8f0;"><span style="font-family:Consolas,monospace; font-weight:700;">{{ $studentNumber }}</span></td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0; border-bottom:1px solid #e2e8f0; color:#475569;"><strong>Temporary Password</strong></td>
                                    <td style="padding:10px 0; border-bottom:1px solid #e2e8f0;"><span style="font-family:Consolas,monospace; font-weight:700;">{{ $temporaryPassword }}</span></td>
                                </tr>
                            </table>

                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 20px;">
                                <tr>
                                    <td style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:12px 14px; color:#1e3a8a; font-size:13px;">
                                        Please change your password immediately after your first login.
                                    </td>
                                </tr>
                            </table>
                            @else
                            <p style="margin:0 0 8px; font-size:15px; font-weight:700; color:#0f172a;">Your Account</p>
                            <p style="margin:0 0 14px; font-size:14px; line-height:1.6; color:#475569;">
                                Your existing account has been linked to this application. You can continue to sign in using your current credentials.
                            </p>
                            @if(!empty($studentNumber))
                            <p style="margin:0 0 14px; font-size:14px; color:#475569;"><strong>Student Number:</strong> <span style="font-family:Consolas,monospace;">{{ $studentNumber }}</span></p>
                            @endif
                            @endif

                            {{-- Enrollment Reminder --}}
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:20px;">
                                <tr>
                                    <td style="background:#fefce8; border:1px solid #fde047; border-radius:12px; padding:16px 18px;">
                                        <p style="margin:0 0 6px; font-size:14px; font-weight:700; color:#854d0e;">📋 Important: Self-Enrollment Required</p>
                                        <p style="margin:0; font-size:13px; line-height:1.65; color:#92400e;">
                                            Please log in to the <strong>Student Portal</strong> using your official credentials above and complete your enrollment by yourself. Navigate to the <strong>Enrollment</strong> section to select your subjects and finalize your registration.
                                        </p>
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
