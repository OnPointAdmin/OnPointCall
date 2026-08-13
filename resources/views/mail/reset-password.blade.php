<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reset your password</title>
</head>
<body style="font-family: sans-serif; color: #1e293b; line-height: 1.5;">
    <h1 style="font-size: 20px;">Reset your password</h1>
    <p>Hi {{ $user->name }},</p>
    <p>You requested a password reset for your OnPoint Call account. Click the link below to choose a new password:</p>
    <p>
        <a href="{{ $resetUrl }}">{{ $resetUrl }}</a>
    </p>
    <p style="color: #64748b; font-size: 13px;">
        This link expires in {{ $expireMinutes }} minutes. If you did not request a password reset, you can ignore this email.
    </p>
</body>
</html>
