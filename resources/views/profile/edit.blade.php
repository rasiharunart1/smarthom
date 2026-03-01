@extends('layouts.app')

@section('title', 'Profile Settings')

@section('content')
    <div class="container-fluid">
        <!-- Page Heading -->
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0" style="color: white; font-weight: 700;">
                <i class="fas fa-user-shield mr-2 text-primary"></i>Security & Identity
            </h1>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <!-- Profile Information -->
                @include('profile.partials.update-profile-information-form')

                <!-- Update Password -->
                @include('profile.partials.update-password-form')
            </div>

            <div class="col-lg-4">
                <!-- User Info Card -->
                <div class="glass-card mb-4 text-center p-4">
                    <div class="card-header bg-transparent border-0 pt-0">
                        <h5 class="m-0 font-weight-bold" style="color: var(--primary-green-light);">Active Account</h5>
                    </div>
                    <div class="card-body">
                        <div class="user-avatar mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                            {{ substr($user->name, 0, 1) }}
                        </div>
                        <h5 class="text-white font-weight-bold mb-1">{{ $user->name }}</h5>
                        <p style="color: var(--text-muted); font-size: 0.9rem;">{{ $user->email }}</p>
                        
                        <div class="pt-3 mt-3 border-top" style="border-color: rgba(255,255,255,0.1) !important;">
                            <div class="row">
                                <div class="col">
                                    <div class="glass-label mb-0" style="font-size: 0.7rem;">Member Since</div>
                                    <div class="text-white small">{{ $user->created_at->format('M Y') }}</div>
                                </div>
                                <div class="col border-left" style="border-color: rgba(255,255,255,0.1) !important;">
                                    <div class="glass-label mb-0" style="font-size: 0.7rem;">Verification</div>
                                    @if ($user->email_verified_at)
                                        <div class="text-success small" style="font-weight: 600;">Secure</div>
                                    @else
                                        <div class="text-warning small" style="font-weight: 600;">Pending</div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if ($user->device)
                            <div class="mt-4 pt-3 border-top text-left" style="border-color: rgba(255,255,255,0.1) !important;">
                                <div class="glass-label">Primary Hardware</div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="small text-white">{{ $user->device->name }}</span>
                                    <span class="badge py-1 px-2" style="background: {{ $user->device->isOnline() ? 'rgba(16, 185, 129, 0.1)' : 'rgba(255,255,255,0.05)' }}; color: {{ $user->device->isOnline() ? 'var(--primary-green-light)' : 'var(--text-muted)' }}; border: 1px solid {{ $user->device->isOnline() ? 'rgba(16, 185, 129, 0.2)' : 'rgba(255,255,255,0.1)' }};">
                                        {{ strtoupper($user->device->status) }}
                                    </span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Delete Account -->
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
@endsection
