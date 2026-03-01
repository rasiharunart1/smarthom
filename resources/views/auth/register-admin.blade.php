@extends('layouts.auth')

@section('content')
    <div class="form-title">Admin Registration</div>
    <div class="form-subtitle">Create an administrator account</div>

    <form method="POST" action="{{ route('admin.register') }}">
        @csrf

        <div class="form-group">
            <label class="glass-label" for="name">Full Name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus
                class="glass-input @error('name') is-invalid @enderror" placeholder="Admin Name">
            @error('name')
                <div class="error-msg">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="glass-label" for="email">Email Address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required
                class="glass-input @error('email') is-invalid @enderror" placeholder="admin@example.com">
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

        <div class="form-group">
            <label class="glass-label" for="password_confirmation">Confirm Password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required
                class="glass-input" placeholder="••••••••">
        </div>

        <div class="form-group">
            <label class="glass-label" for="auth_code">Authentication Code</label>
            <input id="auth_code" type="password" name="auth_code" required
                class="glass-input @error('auth_code') is-invalid @enderror" placeholder="Secret Code">
            @error('auth_code')
                <div class="error-msg">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="glass-button">
            Register as Admin
        </button>
    </form>

    <div class="auth-links">
        Already have an account? 
        @if (Route::has('login'))
            <a href="{{ route('login') }}">Sign In</a>
        @endif
    </div>
@endsection
