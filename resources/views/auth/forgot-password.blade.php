@extends('layouts.auth')

@section('content')
    <div class="card-soft fade-in">
        <div class="p-4 p-md-5">
            <div class="logo-wrap">
                <img src="{{ asset('assets/img/l.png') }}" alt="Logo" style="max-width:56px;">
            </div>

            <h2 class="title">Lupa Password</h2>
            <div class="subtitle">Masukkan email Anda, kami akan mengirim tautan untuk reset password.</div>

            {{-- Alert SweetAlert akan ditampilkan via script di bawah, tidak pakai alert HTML lagi --}}

            <form method="POST" action="{{ route('password.email') }}" novalidate>
                @csrf

                <div class="form-group">
                    <label class="label" for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                        class="input-soft @error('email') is-invalid @enderror" placeholder="you@example.com">
                    @error('email')
                        <div class="error-msg">{{ $message }}</div>
                    @enderror
                </div>

                <div style="margin-top:22px;">
                    <button class="btn-primary-soft" type="submit">
                        Kirim Tautan Reset Password
                    </button>
                </div>
            </form>

            <div class="extra-links" style="margin-top:16px;">
                <a href="{{ route('login') }}">Kembali ke Login</a>
                @if (Route::has('register'))
                    <span>&nbsp;•&nbsp;</span>
                    <a href="{{ route('register') }}">Daftar</a>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    {{-- SweetAlert2 CDN (gunakan sekali di layout jika ingin lebih rapi) --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @if (session('status'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Email terkirim',
                    text: 'Tautan reset password telah kami kirim ke email Anda.',
                    confirmButtonText: 'OK',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-primary-soft'
                    }
                });
            });
        </script>
    @endif

    {{-- Opsional: tampilkan SweetAlert error jika ada error email --}}
    @if ($errors->has('email'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal mengirim',
                    text: @json($errors->first('email')),
                    confirmButtonText: 'OK',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-primary-soft'
                    }
                });
            });
        </script>
    @endif
@endpush
