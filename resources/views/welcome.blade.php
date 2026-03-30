<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <title>{{ config('app.name', 'Tewe.io') }} - Realtime IoT Platform</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('assets/vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet" type="text/css">

    <style>
        :root {
            --primary-green: #10b981;
            --primary-green-dark: #059669;
            --primary-green-light: #34d399;
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.1);
            --text-primary: #ffffff;
            --text-secondary: #a0aec0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 25%, #2d1b4e 50%, #1e3a5f 75%, #0f2167 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            background-attachment: fixed;
            color: var(--text-primary);
            min-height: 100vh;
            font-family: 'Outfit', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-x: hidden;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .container {
            position: relative;
            width: 100%;
            max-width: 1000px;
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 40px;
            align-items: center;
        }

        @media (max-width: 850px) {
            .container {
                grid-template-columns: 1fr;
                text-align: center;
            }
        }

        .hero-text h1 {
            font-size: 4rem;
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #fff 0%, var(--primary-green) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -2px;
        }

        .hero-text p {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 30px;
            line-height: 1.6;
            max-width: 500px;
        }

        @media (max-width: 850px) {
            .hero-text h1 { font-size: 3rem; }
            .hero-text p { margin: 0 auto 30px; }
        }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .glass-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.05) 0%, transparent 70%);
            pointer-events: none;
        }

        .logo-box {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-green), var(--primary-green-dark));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
        }

        .logo-box img {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }

        .brand-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: #fff;
        }

        .tagline {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 32px;
            display: block;
        }

        .actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 14px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            text-decoration: none;
            transition: all 0.3s ease;
            gap: 10px;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-green), var(--primary-green-dark));
            color: white;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 25px rgba(16, 185, 129, 0.3);
        }

        .btn-outline {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            color: white;
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-3px);
            border-color: var(--primary-green);
        }

        .footer {
            margin-top: 32px;
            font-size: 0.8rem;
            color: var(--text-muted);
            opacity: 0.6;
        }

        /* Decorative elements */
        .decoration {
            position: absolute;
            z-index: -1;
            filter: blur(80px);
            border-radius: 50%;
            opacity: 0.4;
        }

        .decor-1 {
            width: 300px;
            height: 300px;
            background: var(--primary-green);
            top: -100px;
            right: -100px;
        }

        .decor-2 {
            width: 250px;
            height: 250px;
            background: #4f46e5;
            bottom: -50px;
            left: -50px;
        }
    </style>
</head>

<body>
    <div class="decoration decor-1"></div>
    <div class="decoration decor-2"></div>

    <div class="container">
        <div class="hero-text">
            <h1>Tewe.io<br>IoT PLATFORM</h1>
            <p>Platform IoT masa depan yang didesain untuk skalabilitas dan efisiensi. Hubungkan, pantau, dan kendalikan berbagai perangkat pintar Anda dalam satu ekosistem yang canggih dan realtime.</p>
        </div>

        <div class="glass-card">
            <div class="logo-box">
                <img src="{{ asset('assets/img/logo_tewe.png') }}" alt="Tewe.io Logo">
            </div>
            <h2 class="brand-name">Tewe.io</h2>
            <span class="tagline">Next-Gen IoT Ecosystem</span>

            <div class="actions">
                @auth
                    <a href="{{ url('/dashboard') }}" class="btn btn-primary">
                        <i class="fas fa-th-large"></i> Dashboard
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline w-100">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </button>
                    </form>
                @else
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Masuk Akun
                        </a>
                    @endif
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="btn btn-outline">
                            <i class="fas fa-user-plus"></i> Daftar Sekarang
                        </a>
                    @endif
                    @if (Route::has('admin.register'))
                        <div style="display: flex; gap: 8px; margin-top: 8px;">
                            <a href="{{ route('admin.register') }}" class="btn btn-outline" style="flex: 1; opacity: 0.7; font-size: 0.85em; padding: 10px;">
                                <i class="fas fa-user-shield"></i> Daftar Admin
                            </a>
                            @if (Route::has('admin.login'))
                                <a href="{{ route('admin.login') }}" class="btn btn-outline" style="flex: 1; opacity: 0.7; font-size: 0.85em; padding: 10px;">
                                    <i class="fas fa-lock text-sm"></i> Login Admin
                                </a>
                            @endif
                        </div>
                    @endif
                @endauth
            </div>

            <div class="footer">
                &copy; {{ date('Y') }} Tewe.io. All rights reserved.
            </div>
        </div>
    </div>
</body>

</html>

