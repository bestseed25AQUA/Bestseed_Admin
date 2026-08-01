<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin Login OTP</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f4f6f8; padding:20px;">
    <div style="max-width:500px; margin:auto; background:#ffffff; padding:20px; border-radius:6px;">
        <h2 style="color:#333;">Admin Login OTP</h2>

        <p>Hello,</p>
        <p>Your One-Time Password (OTP) for admin login is:</p>

        <h1 style="letter-spacing:4px; color:#0a58ca;">
            {{ $otp }}
        </h1>

        <p>This OTP is valid for <strong>10 minutes</strong>.</p>

        <p>If you did not request this login, please ignore this email.</p>

        <hr>
        <p style="font-size:12px; color:#888;">
            Bestseed Admin Panel
        </p>
    </div>
</body>
</html>
