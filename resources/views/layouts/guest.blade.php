<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Tewe.io') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --primary-green: #10b981;
            --primary-green-dark: #059669;
            --primary-green-light: #34d399;
            --text-primary: #ffffff;
            --text-secondary: #a0aec0;
        }

        body {
            background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 50%, #0a0e27 100%);
            background-attachment: fixed;
            color: var(--text-primary);
            min-height: 100vh;
        }

        .glass-container {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            padding: 2.5rem;
            width: 100%;
            max-width: 450px;
        }

        .glass-input {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: white !important;
            border-radius: 12px !important;
            padding: 0.75rem 1rem !important;
            transition: all 0.3s ease;
        }

        .glass-input:focus {
            background: rgba(255, 255, 255, 0.08) !important;
            border-color: var(--primary-green) !important;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1) !important;
        }

        .glass-button {
            background: linear-gradient(135deg, var(--primary-green), var(--primary-green-dark)) !important;
            border: none !important;
            border-radius: 12px !important;
            padding: 0.75rem !important;
            font-weight: 600 !important;
            color: white !important;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 1.5rem;
        }

        .glass-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.2);
        }

        .brand-logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-green), var(--primary-green-dark));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            box-shadow: 0 8px 16px rgba(16, 185, 129, 0.3);
        }

        .link-muted {
            color: var(--text-secondary);
            font-size: 0.9rem;
            text-decoration: none;
            transition: color 0.2s;
        }

        .link-muted:hover {
            color: var(--primary-green-light);
        }
    </style>
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen flex flex-col justify-center items-center p-4">
        <div class="brand-logo" style="background: transparent; box-shadow: none;">
             <img src="{{ asset('assets/img/logo_tewe.png') }}" alt="Tewe.io Logo" style="width: 100%; height: 100%; object-fit: contain;">
        </div>

        <div class="glass-container">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
