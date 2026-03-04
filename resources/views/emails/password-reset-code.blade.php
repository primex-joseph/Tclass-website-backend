<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TCLASS Password Reset Code</title>
</head>
<body style="margin:0; padding:0; background:#f8fafc; font-family:Arial, Helvetica, sans-serif; color:#0f172a;">
    <div style="max-width:560px; margin:0 auto; padding:32px 20px;">
        <div style="background:linear-gradient(135deg, #0f3b8c, #1d4ed8); border-radius:24px 24px 0 0; padding:28px 32px; color:#ffffff;">
            <div style="font-size:12px; letter-spacing:0.12em; text-transform:uppercase; opacity:0.8;">TCLASS Portal</div>
            <h1 style="margin:12px 0 0; font-size:28px; line-height:1.2;">Password Reset Verification</h1>
        </div>
        <div style="background:#ffffff; border:1px solid #dbe4f0; border-top:none; border-radius:0 0 24px 24px; padding:32px;">
            <p style="margin:0 0 12px; font-size:16px; line-height:1.7;">Hello {{ $name }},</p>
            <p style="margin:0 0 20px; font-size:15px; line-height:1.7; color:#334155;">
                We received a request to reset your TCLASS portal password. Use the verification code below to continue.
            </p>
            <div style="margin:28px 0; padding:20px; border-radius:20px; background:#eff6ff; border:1px solid #bfdbfe; text-align:center;">
                <div style="font-size:12px; letter-spacing:0.16em; text-transform:uppercase; color:#1d4ed8; margin-bottom:8px;">Verification Code</div>
                <div style="font-size:36px; font-weight:700; letter-spacing:0.32em; color:#0f172a;">{{ $code }}</div>
            </div>
            <p style="margin:0 0 12px; font-size:14px; line-height:1.7; color:#475569;">
                This code expires in {{ $expiresInMinutes }} minutes. If you did not request a password reset, you can ignore this email.
            </p>
            <p style="margin:20px 0 0; font-size:13px; color:#64748b;">Provincial Government of Tarlac</p>
        </div>
    </div>
</body>
</html>
