@extends('layouts.auth')

@section('content')
    <div class="card-soft fade-in">
        <div class="p-4 p-md-5">
            <div class="logo-wrap">
                <img src="{{ asset('assets/img/l.png') }}" alt="Logo" style="max-width:56px;">
            </div>

            <h2 class="title">Konfirmasi Password</h2>
            <div class="subtitle">Area ini aman. Mohon konfirmasi password Anda untuk melanjutkan.</div>

            <form method="POST" action="{{ route('password.confirm') }}" novalidate>
                @csrf

                <div class="form-group">
                    <label class="label" for="password">Password</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password"
                        class="input-soft @error('password') is-invalid @enderror" placeholder="••••••••">
                    @error('password')
                        <div class="error-msg">{{ $message }}</div>
                    @enderror
                </div>

                <div style="margin-top:22px;">
                    <button class="btn-primary-soft" type="submit">
                        Konfirmasi
                    </button>
                </div>
            </form>

            <div class="extra-links" style="margin-top:16px;">
                <a href="{{ route('password.request') }}">Lupa Password?</a>
                <span>&nbsp;•&nbsp;</span>
                <a href="{{ route('login') }}">Kembali ke Login</a>
            </div>
        </div>
    </div>
@endsection
