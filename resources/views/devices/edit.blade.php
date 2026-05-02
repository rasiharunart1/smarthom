@extends('layouts.app')

@section('title', 'Edit Device')

@section('content')
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0" style="color: white; font-weight: 700;">
            <i class="fas fa-edit mr-2 text-primary"></i>Edit Device
        </h1>
        <a href="{{ route('dashboard') }}" class="btn glass-button btn-secondary" style="width: auto; margin-top: 0; padding: 0.5rem 1rem !important;">
            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="glass-card h-100">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="m-0 font-weight-bold" style="color: var(--primary-green-light);">Device Information</h5>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('device.update') }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-4">
                            <label for="name" class="glass-label">Device Name</label>
                            <input type="text" class="form-control glass-input @error('name') is-invalid @enderror" id="name"
                                name="name" value="{{ old('name', $device->name) }}" required placeholder="e.g. Workshop Controller">
                            @error('name')
                                <div class="error-msg">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="device_code" class="glass-label">Device Authentication Code</label>
                            <div class="input-group">
                                <input type="text" class="form-control glass-input" id="device_code"
                                    value="{{ $device->device_code }}" readonly style="border-top-right-radius: 0 !important; border-bottom-right-radius: 0 !important;">
                                <div class="input-group-append">
                                    <button class="btn glass-button glass-button-primary mt-0" type="button"
                                        onclick="copyToClipboard('{{ $device->device_code }}')"
                                        style="border-top-left-radius: 0 !important; border-bottom-left-radius: 0 !important; padding: 0.5rem 1rem !important; margin-top: 0 !important; width: auto;">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="form-text mt-2" style="color: var(--text-muted);">
                                Use this code in your device firmware to authenticate with the platform.
                            </small>
                        </div>

                        <div class="mb-4">
                            <label class="glass-label">Current Connectivity</label>
                            <div>
                                <span class="badge py-2 px-3" style="background: {{ $device->isOnline() ? 'rgba(16, 185, 129, 0.15)' : 'rgba(255, 255, 255, 0.05)' }}; color: {{ $device->isOnline() ? 'var(--primary-green-light)' : 'var(--text-muted)' }}; border: 1px solid {{ $device->isOnline() ? 'rgba(16, 185, 129, 0.3)' : 'rgba(255, 255, 255, 0.1)' }}; border-radius: 10px;">
                                    <i class="fas fa-circle mr-2 {{ $device->isOnline() ? 'pulse-online' : '' }}"></i>{{ strtoupper($device->status) }}
                                </span>
                            </div>
                            <small class="form-text mt-2" style="color: var(--text-muted);">
                                Last active: {{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Never' }}
                            </small>
                        </div>

                        <div class="pt-3 border-top" style="border-color: rgba(255, 255, 255, 0.1) !important;">
                            <button type="submit" class="btn glass-button glass-button-primary" style="width: auto;">
                                <i class="fas fa-save mr-2"></i>Update Identification
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Manage Sharing -->
            <div class="glass-card mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="m-0 font-weight-bold" style="color: var(--primary-green-light);">Sharing</h5>
                </div>
                <div class="card-body p-4">
                    <p class="small mb-3" style="color: var(--text-muted);">
                        Bagikan akses device ini ke user lain di platform. Kamu tetap sebagai pemilik penuh.
                    </p>
                    <a href="{{ route('device.shares.index') }}" class="btn glass-button w-100" style="background: rgba(99,179,237,0.1); color: #90cdf4; border: 1px solid rgba(99,179,237,0.3);">
                        <i class="fas fa-share-alt mr-2"></i>Manage Sharing
                    </a>
                </div>
            </div>

            <!-- Stats -->
            <div class="glass-card mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="m-0 font-weight-bold" style="color: var(--primary-green-light);">Maintenance</h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-4 pb-4 border-bottom" style="border-color: rgba(255, 255, 255, 0.1) !important;">
                        <h6 class="font-weight-bold mb-3" style="color: white; font-size: 0.9rem;">Security Update</h6>
                        <p class="small" style="color: var(--text-muted);">
                            Generating a new code will immediately invalidate the old one. Your device will disconnect until updated.
                        </p>
                        <form action="{{ route('device.regenerate-code') }}" method="POST"
                            onsubmit="return confirm('Generate a new authentication code? This will disconnect your hardware.')">
                            @csrf
                            <button type="submit" class="btn glass-button btn-warning w-100" style="background: rgba(245, 158, 11, 0.1) !important; color: #fbbf24 !important; border: 1px solid rgba(245, 158, 11, 0.3) !important;">
                                <i class="fas fa-sync mr-2"></i>Regenerate Secret Key
                            </button>
                        </form>
                    </div>

                    <div>
                        <h6 class="font-weight-bold text-danger mb-3" style="font-size: 0.9rem;">Permanent Deletion</h6>
                        <p class="small" style="color: var(--text-muted);">
                            This action is final. All widgets, historical data, and configurations will be removed forever.
                        </p>
                        <form action="{{ route('devices.destroy', $device) }}" method="POST"
                            onsubmit="return confirm('Permanently delete this device? This cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn glass-button btn-danger w-100" style="background: rgba(239, 68, 68, 0.1) !important; color: #f87171 !important; border: 1px solid rgba(239, 68, 68, 0.3) !important;">
                                <i class="fas fa-trash mr-2"></i>Delete This Device
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .pulse-online {
            animation: pulse-ring 2s infinite;
        }
        @keyframes pulse-ring {
            0% { opacity: 1; }
            50% { opacity: 0.4; }
            100% { opacity: 1; }
        }
    </style>
@endpush

@push('scripts')
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                if(window.showNotification) {
                    showNotification('Authentication code copied', 'success');
                } else {
                    alert('Code copied');
                }
            });
        }
    </script>
@endpush
