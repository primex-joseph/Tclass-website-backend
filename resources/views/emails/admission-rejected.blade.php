<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Rejected</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5; color: #1f2937;">
    <h2>TCLASS Admission Update</h2>
    <p>Dear {{ $fullName }},</p>
    <p>Thank you for submitting your registration form. After review, your admission application was not approved at this time.</p>
    <p><strong>Reason:</strong> {{ $reason }}</p>
    <p>You may coordinate with the admissions office for clarification and next steps.</p>
    <p>Regards,<br>TCLASS Admissions</p>
</body>
</html>
