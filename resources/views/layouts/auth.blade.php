<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'Tewe.io') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link href="{{ asset('assets/vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet" type="text/css">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --primary-green: #10b981;
            --primary-green-dark: #059669;
            --primary-green-light: #34d399;
            --text-primary: #ffffff;
            --text-secondary: #a0aec0;
            --text-muted: #718096;
        }

        body {
            background-color: #050B14;
            background-attachment: fixed;
            color: var(--text-primary);
            min-height: 100vh;
            font-family: 'Figtree', sans-serif;
            overflow: hidden;
        }

        .auth-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }

        .brand-logo {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--primary-green), var(--primary-green-dark));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 2rem;
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3);
            color: white;
            font-size: 1.8rem;
        }

        .glass-card {
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.10);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
            padding: 2.5rem;
            width: 100%;
            max-width: 450px;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-title {
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 0.5rem;
            color: white;
        }

        .form-subtitle {
            font-size: 0.9rem;
            color: var(--text-secondary);
            text-align: center;
            margin-bottom: 2rem;
        }

        .glass-input, .form-control {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: white !important;
            border-radius: 12px !important;
            padding: 0.8rem 1.2rem !important;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
        }

        .glass-input:focus, .form-control:focus {
            background: rgba(255, 255, 255, 0.08) !important;
            border-color: var(--primary-green) !important;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1) !important;
            outline: none;
            color: white !important;
        }

        input::-webkit-calendar-picker-indicator {
            filter: invert(1);
        }

        .glass-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            margin-left: 0.2rem;
        }

        .glass-button {
            background: linear-gradient(135deg, var(--primary-green), var(--primary-green-dark)) !important;
            border: none !important;
            border-radius: 12px !important;
            padding: 0.8rem !important;
            font-weight: 700 !important;
            color: white !important;
            transition: all 0.3s ease;
            width: 100%;
            cursor: pointer;
            margin-top: 1rem;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }

        .glass-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }

        .error-msg {
            color: #ef4444;
            font-size: 0.75rem;
            margin-top: 0.4rem;
            margin-left: 0.2rem;
        }

        .auth-links {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .auth-links a {
            color: var(--primary-green-light);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .auth-links a:hover {
            color: var(--primary-green);
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        input::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body>
    <!-- Ambient Background Glows -->
    <div style="position:fixed;top:-15%;left:-10%;width:50%;height:50%;background:rgba(16,185,129,0.18);border-radius:50%;filter:blur(140px);pointer-events:none;z-index:0;"></div>
    <div style="position:fixed;bottom:-15%;right:-10%;width:60%;height:60%;background:rgba(37,99,235,0.18);border-radius:50%;filter:blur(150px);pointer-events:none;z-index:0;"></div>
    <div style="position:fixed;top:20%;right:15%;width:30%;height:30%;background:rgba(20,184,166,0.09);border-radius:50%;filter:blur(100px);pointer-events:none;z-index:0;"></div>
    <div class="auth-wrapper" style="position:relative;z-index:1;">
        <div class="brand-logo" style="background: transparent; box-shadow: none;">
            <img src="{{ asset('assets/img/logo_tewe.png') }}" alt="Tewe.io Logo" style="width: 100%; height: 100%; object-fit: contain;">
        </div>
        
        <div class="glass-card">
            @yield('content')
        </div>
    </div>
</body>
</html>
