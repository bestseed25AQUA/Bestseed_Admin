<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>{{ config('app.name', 'Laravel') }} - Login</title>

    <!-- Modern Font: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome (If needed, although not strictly required for this design) -->
    <link rel="stylesheet" href="{{ asset('admin_assets/vendors/iconfonts/font-awesome/css/all.min.css') }}">

    <style>
        :root {
            /* Aqua Theme Colors */
            --color-aqua-main: #10dbff9a; /* Deep Cyan/Aqua for buttons and links */
            --color-aqua-dark: #10dbff6d;
            --color-text-primary: #333333;
            --color-text-secondary: #6c757d;
            --color-border: #e0e0e0;
            --color-error: #dc3545;
        }

        body {
            /* Retained Background Gradient */
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
            /* box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2), 0 4px 15px rgba(0, 0, 0, 0.1); */
            /* --- COMPACT WIDTH --- */
            max-width: 360px;
            width: 100%;
            display: flex;
            flex-direction: column;
        }

        .brand-logo {
            text-align: center;
            margin-bottom: 25px;
        }

        .brand-logo img {
            max-height: 60px;
            object-fit: contain;
            display: inline-block;
        }

        .login-card h4 {
            font-weight: 700;
            color: var(--color-text-primary);
            margin: 0 0 5px;
            text-align: center;
        }

        .login-card h6 {
            color: var(--color-text-secondary);
            margin-bottom: 30px;
            text-align: center;
            font-weight: 400;
        }

        .form-group {
            margin-bottom: 20px;
        }

        /* Input Styling */
        .form-control {
            height: 50px;
            width: 100%;
            padding: 0 15px;
            border-radius: 8px;
            border: 1px solid var(--color-border);
            font-size: 15px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .form-control:focus {
            border-color: var(--color-aqua-main);
            box-shadow: 0 0 0 3px rgba(0, 162, 192, 0.2);
            outline: none;
        }

        .form-control.is-invalid {
            border-color: var(--color-error);
        }

        .invalid-feedback {
            display: block;
            color: var(--color-error);
            font-size: 0.85rem;
            margin-top: 5px;
        }

        /* Primary Button (Aqua Themed) */
        .btn-primary {
            background-color: var(--color-aqua-main);
            color: #fff;
            height: 50px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            width: 100%;
            box-shadow: 0 5px 15px rgba(0, 162, 192, 0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary:hover {
            background-color: var(--color-aqua-dark);
            box-shadow: 0 7px 20px rgba(0, 124, 138, 0.5);
            transform: translateY(-1px);
        }

        /* Links and Secondary Elements */
        .form-check-label {
            color: var(--color-text-secondary);
            font-size: 0.9rem;
            cursor: pointer;
        }

        .auth-link {
            font-size: 0.9rem;
            color: var(--color-aqua-main);
            text-decoration: none;
            transition: color 0.2s;
        }

        .auth-link:hover {
            color: var(--color-aqua-dark);
            text-decoration: underline;
        }

        .bottom-text {
            text-align: center;
            margin-top: 25px;
            font-size: 0.9rem;
            color: var(--color-text-secondary);
        }

        /* Responsive adjustment for small screens */
        @media (max-width: 400px) {
            .login-card {
                max-width: 95%;
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="brand-logo">
                <img src="{{ asset('uploads/logo_68e5f26e3952d.png') }}" alt="{{ config('app.name', 'Bestseed') }}">
            </div>
            <h4>Welcome Back </h4>
            <h6>Sign in to access your dashboard.</h6>

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="form-group">
                    <input id="email" type="email"
                           class="form-control @error('email') is-invalid @enderror"
                           name="email" value="{{ old('email') }}" required autocomplete="email" autofocus placeholder="Email Address">
                    @error('email')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <div class="form-group">
                    <input id="password" type="password"
                           class="form-control @error('password') is-invalid @enderror"
                           name="password" required autocomplete="current-password" placeholder="Password">
                    @error('password')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <label class="form-check-label">
                            <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                            Remember me
                        </label>
                    </div>
                    @if (Route::has('password.request'))
                        {{-- CORRECTED SYNTAX HERE --}}
                        <a href="{{ route('password.request') }}" class="auth-link">Forgot Password?</a>
                    @endif
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">
                        Secure Sign In
                    </button>
                </div>

                {{-- Self-registration link removed: admin accounts are created
                     by an existing admin, not signed up for from the login page. --}}
            </form>
        </div>
    </div>
</body>
</html>
