<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>OnPoint Call invite</title>
</head>
<body style="font-family: sans-serif; color: #1e293b; line-height: 1.5;">
    <h1 style="font-size: 20px;">You're invited to OnPoint Call</h1>
    <p>Hi {{ $user->name }},</p>
    <p>An account has been created for you. Use these credentials to sign in, then choose a new password:</p>
    <ul>
        <li><strong>Email:</strong> {{ $user->email }}</li>
        <li><strong>Temporary password:</strong> {{ $plainPassword }}</li>
    </ul>

    <p>
        <strong>Sign in:</strong>
        <a href="{{ $loginUrl }}">{{ $loginUrl }}</a>
    </p>

    @if (! $canAccessAdmin)
        <p style="color: #64748b; font-size: 13px;">
            You need a calling-list assignment to use the agent window. If sign-in says you are not assigned to any lists, ask an admin to assign you.
        </p>
    @endif
</body>
</html>
