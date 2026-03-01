@extends('layouts.auth')

@section('content')
    <div class="card-soft fade-in">
        <div class="p-4 p-md-5">
            <div class="logo-wrap">
                <img src="{{ asset('assets/img/l.png') }}" alt="Logo" style="max-width:56px;">
            </div>

            <h2 class="title">Reset Password</h2>
            <div class="subtitle">Silakan atur password baru Anda.</div>

            <form method="POST" action="{{ route('password.store') }}" novalidate>
                @csrf

                <input type="hidden" name="token" value="{{ request()->route('token') }}">

                <div class="form-group">
                    <label class="label" for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email', request('email')) }}"
                        required autofocus autocomplete="username" class="input-soft @error('email') is-invalid @enderror"
                        placeholder="you@example.com">
                    @error('email')
                        <div class="error-msg">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group" style="margin-top:10px;">
                    <label class="label" for="password">Password Baru</label>
                    <input id="password" type="password" name="password" required autocomplete="new-password"
                        class="input-soft @error('password') is-invalid @enderror" placeholder="••••••••">
                    @error('password')
                        <div class="error-msg">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group" style="margin-top:10px;">
                    <label class="label" for="password_confirmation">Konfirmasi Password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required
                        autocomplete="new-password" class="input-soft @error('password_confirmation') is-invalid @enderror"
                        placeholder="••••••••">
                    @error('password_confirmation')
                        <div class="error-msg">{{ $message }}</div>
                    @enderror
                </div>

                <div style="margin-top:22px;">
                    <button class="btn-primary-soft" type="submit">
                        Reset Password
                    </button>
                </div>
            </form>

            <div class="extra-links" style="margin-top:16px;">
                <a href="{{ route('login') }}">Kembali ke Login</a>
            </div>
        </div>
    </div>
@endsection
