<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>{{ config('app.name', 'Bestseed') }} - OTP Verification</title>

    <!-- Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{ asset('admin_assets/vendors/iconfonts/font-awesome/css/all.min.css') }}">

    <style>
        :root {
            --color-aqua-main: #10dbff9a;
            --color-aqua-dark: #10dbff6d;
            --color-text-primary: #333333;
            --color-text-secondary: #6c757d;
            --color-border: #e0e0e0;
            --color-error: #dc3545;
        }

        body {
            background: linear-gradient(135deg, #77e0f8 0%, #73c7ef 100%);
            font-family: 'Inter', sans-serif;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-card {
            background: #ffffff;
            padding: 40px;
            border-radius: 12px;
            max-width: 360px;
            width: 100%;
        }

        .brand-logo {
            text-align: center;
            margin-bottom: 25px;
        }

        .brand-logo img {
            max-height: 60px;
        }

        h4 {
            font-weight: 700;
            text-align: center;
            margin-bottom: 5px;
            color: var(--color-text-primary);
        }

        h6 {
            text-align: center;
            color: var(--color-text-secondary);
            margin-bottom: 30px;
            font-weight: 400;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-control {
            height: 50px;
            width: 100%;
            padding: 0 15px;
            border-radius: 8px;
            border: 1px solid var(--color-border);
            font-size: 15px;
        }

        .form-control:focus {
            border-color: var(--color-aqua-main);
            box-shadow: 0 0 0 3px rgba(0, 162, 192, 0.2);
            outline: none;
        }

        .btn-primary {
            background-color: var(--color-aqua-main);
            color: #fff;
            height: 50px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
        }

        .btn-primary:hover {
            background-color: var(--color-aqua-dark);
        }

        .auth-link {
            font-size: 0.9rem;
            color: var(--color-aqua-main);
            text-decoration: none;
            background: none;
            border: none;
            cursor: pointer;
        }

        .auth-link:hover {
            text-decoration: underline;
        }

        .error-text {
            color: var(--color-error);
            font-size: 0.85rem;
            margin-top: 5px;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-card">

        <div class="brand-logo">
            <img src="{{ asset('uploads/logo_68e5f26e3952d.png') }}" alt="Bestseed">
        </div>

        <h4>Two-Step Verification</h4>
        <h6>Enter the OTP sent to your email</h6>

        <form method="POST" action="{{ route('admin.otp.verify') }}">
            @csrf

            <div class="form-group">
                <input type="text"
                       name="otp"
                       inputmode="numeric"
                       pattern="[0-9]{6}"
                       maxlength="6"
                       class="form-control"
                       placeholder="Enter 6-digit OTP"
                       required>
            </div>

            @error('otp')
                <div class="error-text">{{ $message }}</div>
            @enderror

            <button type="submit" class="btn-primary">
                Verify OTP
            </button>
        </form>

        <div class="text-center mt-3">
            <form method="POST" action="{{ route('admin.otp.resend') }}">
                @csrf
                <button class="auth-link">
                    Resend OTP
                </button>
            </form>
        </div>

    </div>
</div>

</body>
</html>





{{-- @extends('layouts.app')

@section('content')
<div class="container">
    <div class="card mx-auto" style="max-width:400px;">
        <div class="card-body">
            <h4 class="text-center">Two-Step Verification</h4>

            <form method="POST" action="{{ route('admin.otp.verify') }}">
                @csrf --}}
                {{-- <input type="text" name="otp" class="form-control mt-3"
                       placeholder="Enter 6-digit OTP" required> --}}

                {{-- <input type="text"
                name="otp"
                inputmode="numeric"
                pattern="[0-9]{6}"
                maxlength="6"
                class="form-control mt-3"
                placeholder="Enter 6-digit OTP"
                required>
                --}}

                {{-- @error('otp')
                    <small class="text-danger">{{ $message }}</small>
                @enderror

                <button class="btn btn-primary w-100 mt-3">
                    Verify OTP
                </button>
            </form> --}}

            {{-- <form method="POST" action="{{ route('admin.otp.resend') }}">
                @csrf
                <button class="btn btn-link w-100 mt-2">
                    Resend OTP
                </button>
            </form> --}}
        {{-- </div>
    </div>
</div>
@endsection --}}
