@extends('layouts.auth')

@section('content')
    <div class="form-title">Welcome Back</div>
    <div class="form-subtitle">Please sign in to your smarthome account</div>

    @if (session('status'))
        <div class="alert alert-success" style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: var(--primary-green-light); border-radius: 12px; font-size: 0.85rem; padding: 0.75rem; margin-bottom: 1.5rem;">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="form-group">
            <label class="glass-label" for="email">Email Address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" autofocus required
                class="glass-input @error('email') is-invalid @enderror" placeholder="you@example.com">
            @error('email')
                <div class="error-msg">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="glass-label" for="password">Password</label>
            <input id="password" type="password" name="password" required
                class="glass-input @error('password') is-invalid @enderror" placeholder="••••••••">
            @error('password')
                <div class="error-msg">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group d-flex justify-content-between align-items-center mb-4">
            <label class="d-flex align-items-center mb-0" style="cursor: pointer; font-size: 0.85rem; color: var(--text-secondary);">
                <input type="checkbox" name="remember" class="mr-2" style="accent-color: var(--primary-green);">
                Remember me
            </label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" style="font-size: 0.85rem; color: var(--primary-green-light); text-decoration: none;">
                    Forgot Password?
                </a>
            @endif
        </div>

        <button type="submit" class="glass-button">
            Sign In
        </button>
    </form>

    <div class="auth-links">
        Don't have an account? 
        @if (Route::has('register'))
            <a href="{{ route('register') }}">Create Account</a>
        @endif
    </div>
@endsection
