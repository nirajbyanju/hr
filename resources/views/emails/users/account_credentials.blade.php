<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Account Credentials</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <p>Hello {{ $userName }},</p>

    <p>Your account has been created in the HR Payroll system.</p>

    <p><strong>Login Email:</strong> {{ $userEmail }}<br>
    <strong>Temporary Password:</strong> {{ $plainPassword }}</p>

    <p>Please sign in and change your password from the dashboard.</p>

    <p>Regards,<br>{{ config('app.name') }}</p>
</body>
</html>
