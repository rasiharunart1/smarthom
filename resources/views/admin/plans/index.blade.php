@extends('layouts.app')

@section('title', 'Subscription Protocol Governance')

@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0" style="color: white; font-weight: 700;">
            <i class="fas fa-crown mr-2 text-primary"></i>Subscription Protocols
        </h1>
        <a href="{{ route('admin.plans.create') }}" class="btn glass-button glass-button-primary" style="width: auto; margin-top: 0; padding: 0.5rem 1rem !important;">
            <i class="fas fa-plus mr-2"></i>New Protocol
        </a>
    </div>

    <div class="row">
        @foreach ($plans as $plan)
            <div class="col-lg-4 mb-4">
                <div class="glass-card h-100 d-flex flex-column">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="m-0 font-weight-bold" style="color: {{ $plan->slug === 'free' ? 'var(--text-muted)' : ($plan->slug === 'pro' ? 'var(--primary-green-light)' : '#60a5fa') }};">
                                {{ $plan->name }}
                            </h5>
                            <span class="badge" style="background: rgba(255,255,255,0.05); color: white; border: 1px solid rgba(255,255,255,0.1);">
                                {{ strtoupper($plan->slug) }}
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-4 flex-grow-1">
                        <div class="mb-4">
                            <div class="glass-label mb-1">Hardware Limit</div>
                            <div class="h4 font-weight-bold text-white mb-0">{{ $plan->max_devices }} <small class="text-muted">Nodes</small></div>
                        </div>
                        <div class="mb-4">
                            <div class="glass-label mb-1">Module Capacity</div>
                            <div class="h4 font-weight-bold text-white mb-0">{{ $plan->max_widgets_per_device }} <small class="text-muted">per Node</small></div>
                        </div>
                        
                        <div class="p-3 rounded mb-4" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);">
                            <div class="small font-weight-bold mb-2 text-primary">Active Features</div>
                            <ul class="list-unstyled mb-0 small text-muted">
                                <li class="mb-1"><i class="fas fa-check-circle text-success mr-2"></i> Real-time Telemetry</li>
                                <li class="mb-1"><i class="fas fa-{{ isset($plan->features['history']) ? 'check-circle text-success' : 'times-circle text-danger' }} mr-2"></i> {{ $plan->features['history'] ?? 'No' }} Data Retention</li>
                                <li><i class="fas fa-{{ ($plan->features['api_access'] ?? false) ? 'check-circle text-success' : 'times-circle text-danger' }} mr-2"></i> API Bridge Access</li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0 p-4 pt-0 text-right d-flex justify-content-end align-items-center">
                         @if($plan->slug !== 'free')
                        <form action="{{ route('admin.plans.destroy', $plan) }}" method="POST" class="mr-2" onsubmit="return confirm('Delete this plan? Users assigned to this plan might be affected.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger glass-button" style="width: auto;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                        @endif
                        <a href="{{ route('admin.plans.edit', $plan) }}" class="btn glass-button glass-button-primary" style="width: auto;">
                            <i class="fas fa-edit mr-2"></i>Modify
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
