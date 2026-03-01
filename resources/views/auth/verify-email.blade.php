@extends('layouts.auth')

@section('content')
    <div class="card-soft fade-in">
        <div class="p-4 p-md-5">
            <div class="logo-wrap">
                <img src="{{ asset('assets/img/l.png') }}" alt="Logo" style="max-width:56px;">
            </div>

            <h2 class="title">MEGADATA POWERPLANT</h2>
            <div class="subtitle">Verifikasi Email Diperlukan</div>

            {{-- @if (session('status') === 'verification-link-sent')
                <div class="alert alert-success py-2 px-3 mb-3" style="font-size:.75rem;">
                    Tautan verifikasi baru telah dikirim ke email yang Anda gunakan saat mendaftar.
                </div>
            @endif --}}
            @if (session('status'))
                <div class="alert alert-success py-2 px-3 mb-3" style="font-size:.75rem;">
                    Tautan reset password telah kami kirim ke email Anda.
                </div>
            @endif
            <div class="text-muted" style="font-size:.9rem;">
                Terima kasih telah mendaftar! Sebelum memulai, silakan verifikasi alamat email Anda dengan mengklik tautan
                yang baru saja kami kirimkan. Jika Anda belum menerimanya, kami dapat mengirim ulang.
            </div>

            <div class="d-flex align-items-center justify-content-between mt-4" style="gap:10px;">
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button type="submit" class="btn-primary-soft">
                        Kirim Ulang Email Verifikasi
                    </button>
                </form>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn-secondary-soft">
                        Keluar
                    </button>
                </form>
            </div>

            <div class="extra-links" style="margin-top:16px;">
                <a href="{{ route('login') }}">Kembali ke Login</a>
            </div>
        </div>
    </div>
@endsection
