@extends('layouts.app')

@section('title', 'Override User Account')

@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0" style="color: white; font-weight: 700;">
            <i class="fas fa-user-edit mr-2 text-primary"></i>Override Identification: {{ $user->name }}
        </h1>
        <a href="{{ route('admin.users.index') }}" class="btn glass-button btn-secondary" style="width: auto; margin-top: 0; padding: 0.5rem 1rem !important;">
            <i class="fas fa-arrow-left mr-2"></i>Back to Directory
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="glass-card mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="m-0 font-weight-bold" style="color: var(--primary-green-light);">Permission & Access</h5>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('admin.users.update', $user) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="glass-label">Identified Name</label>
                                <input type="text" name="name" class="form-control glass-input" value="{{ $user->name }}" required>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="glass-label">Email Access</label>
                                <input type="email" name="email" class="form-control glass-input" value="{{ $user->email }}" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="glass-label">Access Level (Role)</label>
                                <select name="role" class="form-control glass-input">
                                    <option value="user" {{ $user->role === 'user' ? 'selected' : '' }}>USER (Standard)</option>
                                    <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>ADMIN (Full Governance)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="glass-label">Subscription Protocol</label>
                                <select name="subscription_plan" class="form-control glass-input">
                                    @foreach($plans as $plan)
                                        <option value="{{ $plan->slug }}" {{ $user->subscription_plan === $plan->slug ? 'selected' : '' }}>
                                            {{ strtoupper($plan->name) }} ({{ $plan->max_devices }} Nodes)
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="glass-label">Contract Expiration (Nulllable)</label>
                            <input type="datetime-local" name="subscription_expires_at" class="form-control glass-input" 
                                   value="{{ $user->subscription_expires_at ? $user->subscription_expires_at->format('Y-m-d\TH:i') : '' }}">
                        </div>

                        <div class="pt-3 border-top" style="border-color: rgba(255,255,255,0.1) !important;">
                            <button type="submit" class="btn glass-button glass-button-primary" style="width: auto;">
                                Apply Global Override
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="glass-card text-center p-4 h-100">
                <div class="user-avatar mx-auto mb-3" style="width: 100px; height: 100px; font-size: 2.5rem;">
                    {{ substr($user->name, 0, 1) }}
                </div>
                <h4 class="text-white">{{ $user->name }}</h4>
                <p class="text-muted mb-4">{{ $user->email }}</p>
                
                <div class="text-left border-top pt-3" style="border-color: rgba(255,255,255,0.1) !important;">
                    <div class="glass-label">Current Configuration</div>
                    <div class="mb-2 d-flex justify-content-between">
                        <span class="text-muted small">Managed Items:</span>
                        <span class="text-white small font-weight-bold">{{ $user->devices_count ?? $user->devices->count() }} / {{ $user->getLimit('max_devices') }} Nodes</span>
                    </div>
                    <div class="mb-2 d-flex justify-content-between">
                        <span class="text-muted small">Module Capacity:</span>
                        <span class="text-white small font-weight-bold">{{ $user->getLimit('max_widgets_per_device') }} per Node</span>
                    </div>
                    <div class="mb-2 d-flex justify-content-between">
                        <span class="text-muted small">Account Creation:</span>
                        <span class="text-white small">{{ $user->created_at->format('M d, Y') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
