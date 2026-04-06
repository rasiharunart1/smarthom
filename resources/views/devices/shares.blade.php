@extends('layouts.app')

@section('title', 'Manage Sharing — ' . $device->name)

@section('content')
    {{-- Page Heading --}}
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0" style="color: white; font-weight: 700;">
            <i class="fas fa-share-alt mr-2 text-primary"></i>Manage Sharing
            <small class="ml-2" style="font-size: 0.75rem; color: var(--text-muted); font-weight: 400;">{{ $device->name }}</small>
        </h1>
        <a href="{{ route('devices.edit', $device->device_code) }}" class="btn glass-button btn-secondary" style="width: auto; margin-top: 0; padding: 0.5rem 1rem !important;">
            <i class="fas fa-arrow-left mr-2"></i>Back to Device
        </a>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert mb-4" style="background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.3); color: #6ee7b7; border-radius: 10px; padding: 1rem 1.25rem;">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="alert mb-4" style="background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.3); color: #fca5a5; border-radius: 10px; padding: 1rem 1.25rem;">
            <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
        </div>
    @endif

    <div class="row">
        {{-- Add New Share --}}
        <div class="col-lg-5 mb-4">
            <div class="glass-card h-100">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="m-0 font-weight-bold" style="color: var(--primary-green-light);">
                        <i class="fas fa-user-plus mr-2"></i>Bagikan ke User
                    </h5>
                </div>
                <div class="card-body p-4">
                    <p class="small mb-4" style="color: var(--text-muted);">
                        Masukkan email akun yang terdaftar di platform ini. User akan menerima notifikasi via email.
                    </p>

                    <form action="{{ route('devices.shares.store', $device->device_code) }}" method="POST">
                        @csrf

                        <div class="mb-4">
                            <label for="email" class="glass-label">Email User</label>
                            <input type="email" class="form-control glass-input @error('email') is-invalid @enderror"
                                id="email" name="email" value="{{ old('email') }}"
                                placeholder="karyawan@email.com" required>
                            @error('email')
                                <div class="error-msg">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="glass-label">Level Akses</label>
                            <div class="d-flex gap-3" style="gap: 12px;">
                                <label class="permission-card @if(old('permission','control')==='view') active @endif" id="label-view">
                                    <input type="radio" name="permission" value="view" class="d-none"
                                        {{ old('permission','control') === 'view' ? 'checked' : '' }}
                                        onchange="togglePermission('view')">
                                    <div class="icon mb-2" style="font-size: 1.4rem;">👁</div>
                                    <div class="fw-bold" style="font-size: 0.9rem;">View</div>
                                    <div class="small" style="color: var(--text-muted); font-size: 0.75rem;">Hanya lihat data</div>
                                </label>
                                <label class="permission-card @if(old('permission','control')==='control') active @endif" id="label-control">
                                    <input type="radio" name="permission" value="control" class="d-none"
                                        {{ old('permission','control') === 'control' ? 'checked' : '' }}
                                        onchange="togglePermission('control')">
                                    <div class="icon mb-2" style="font-size: 1.4rem;">🎮</div>
                                    <div class="fw-bold" style="font-size: 0.9rem;">Control</div>
                                    <div class="small" style="color: var(--text-muted); font-size: 0.75rem;">Lihat & kendalikan</div>
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn glass-button glass-button-primary w-100">
                            <i class="fas fa-share mr-2"></i>Bagikan Device
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Current Shares --}}
        <div class="col-lg-7 mb-4">
            <div class="glass-card">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="m-0 font-weight-bold" style="color: var(--primary-green-light);">
                        <i class="fas fa-users mr-2"></i>User yang Memiliki Akses
                        <span class="badge ml-2" style="background: rgba(99,179,237,0.15); color: #90cdf4; border: 1px solid rgba(99,179,237,0.3); font-size: 0.75rem; border-radius: 20px; padding: 3px 10px;">
                            {{ $shares->count() }} user
                        </span>
                    </h5>
                </div>
                <div class="card-body p-4">
                    @if($shares->isEmpty())
                        <div class="text-center py-5" style="color: var(--text-muted);">
                            <i class="fas fa-user-lock mb-3" style="font-size: 2.5rem; opacity: 0.3;"></i>
                            <p class="mb-0">Belum ada user yang mendapat akses ke device ini.</p>
                        </div>
                    @else
                        <div class="share-list">
                            @foreach($shares as $share)
                                <div class="share-item d-flex align-items-center justify-content-between">
                                    {{-- User info --}}
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar mr-3">
                                            {{ strtoupper(substr($share->sharedWith->name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="font-weight-bold" style="color: white; font-size: 0.9rem;">{{ $share->sharedWith->name }}</div>
                                            <div class="small" style="color: var(--text-muted);">{{ $share->sharedWith->email }}</div>
                                            <div class="mt-1">
                                                <span class="permission-badge permission-{{ $share->permission }}">
                                                    @if($share->permission === 'control')
                                                        <i class="fas fa-gamepad mr-1"></i>Control
                                                    @else
                                                        <i class="fas fa-eye mr-1"></i>View
                                                    @endif
                                                </span>
                                                <span class="small ml-2" style="color: var(--text-muted); font-size: 0.72rem;">
                                                    sejak {{ $share->created_at->diffForHumans() }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Actions --}}
                                    <div class="d-flex" style="gap: 8px;">
                                        {{-- Toggle permission --}}
                                        <form action="{{ route('devices.shares.update', [$device->device_code, $share->id]) }}" method="POST">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="permission" value="{{ $share->permission === 'view' ? 'control' : 'view' }}">
                                            <button type="submit" class="btn btn-sm action-btn action-btn-toggle"
                                                title="{{ $share->permission === 'view' ? 'Upgrade ke Control' : 'Downgrade ke View' }}"
                                                onclick="return confirm('Ubah akses {{ $share->sharedWith->name }} ke {{ $share->permission === 'view' ? 'Control' : 'View' }}?')">
                                                <i class="fas fa-exchange-alt"></i>
                                            </button>
                                        </form>
                                        {{-- Revoke --}}
                                        <form action="{{ route('devices.shares.destroy', [$device->device_code, $share->id]) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm action-btn action-btn-revoke"
                                                title="Cabut akses"
                                                onclick="return confirm('Cabut akses {{ $share->sharedWith->name }} dari device ini?')">
                                                <i class="fas fa-user-times"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .permission-card {
        flex: 1;
        cursor: pointer;
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 12px;
        padding: 16px 12px;
        text-align: center;
        color: var(--text-muted);
        background: rgba(255,255,255,0.03);
        transition: all 0.2s ease;
        user-select: none;
    }
    .permission-card:hover {
        border-color: rgba(99,179,237,0.4);
        background: rgba(99,179,237,0.05);
    }
    .permission-card.active {
        border-color: rgba(99,179,237,0.6);
        background: rgba(99,179,237,0.1);
        color: #90cdf4;
    }

    .share-list { display: flex; flex-direction: column; gap: 12px; }

    .share-item {
        background: rgba(255,255,255,0.03);
        border: 1px solid rgba(255,255,255,0.07);
        border-radius: 12px;
        padding: 14px 16px;
        transition: background 0.2s;
    }
    .share-item:hover { background: rgba(255,255,255,0.06); }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #1e3a5f, #0f6fad);
        color: white;
        font-weight: 700;
        font-size: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .permission-badge {
        display: inline-flex;
        align-items: center;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 600;
    }
    .permission-control {
        background: rgba(59,130,246,0.15);
        color: #93c5fd;
        border: 1px solid rgba(59,130,246,0.3);
    }
    .permission-view {
        background: rgba(16,185,129,0.12);
        color: #6ee7b7;
        border: 1px solid rgba(16,185,129,0.25);
    }

    .action-btn {
        width: 34px;
        height: 34px;
        padding: 0;
        border-radius: 8px !important;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid;
        transition: all 0.2s;
        font-size: 0.8rem;
    }
    .action-btn-toggle {
        background: rgba(245,158,11,0.1);
        color: #fbbf24;
        border-color: rgba(245,158,11,0.3);
    }
    .action-btn-toggle:hover {
        background: rgba(245,158,11,0.2);
        color: #fbbf24;
    }
    .action-btn-revoke {
        background: rgba(239,68,68,0.1);
        color: #f87171;
        border-color: rgba(239,68,68,0.3);
    }
    .action-btn-revoke:hover {
        background: rgba(239,68,68,0.2);
        color: #f87171;
    }
</style>
@endpush

@push('scripts')
<script>
    function togglePermission(val) {
        document.getElementById('label-view').classList.toggle('active', val === 'view');
        document.getElementById('label-control').classList.toggle('active', val === 'control');
    }
    // Init on load
    const checked = document.querySelector('input[name="permission"]:checked');
    if (checked) togglePermission(checked.value);
</script>
@endpush
