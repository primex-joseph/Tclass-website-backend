<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TCLASS Admission Approved</title>
</head>
<body style="font-family: Arial, sans-serif; color: #0f172a;">
    <h2 style="margin-bottom: 8px;">Admission Approved</h2>
    <p>Hello {{ $fullName }},</p>
    <p>Your admission has been approved. You can now sign in to TCLASS using:</p>
    <p><strong>Username:</strong> {{ $studentNumber }}</p>
    <p><strong>Temporary Password:</strong> {{ $temporaryPassword }}</p>
    <p>Please change your password immediately after your first login.</p>
    <p>Thank you.</p>
</body>
</html>

