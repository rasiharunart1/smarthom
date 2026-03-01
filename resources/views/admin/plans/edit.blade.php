@extends('layouts.app')

@section('title', 'Override Subscription Protocol')

@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0" style="color: white; font-weight: 700;">
            <i class="fas fa-edit mr-2 text-primary"></i>Modify Protocol: {{ $plan->name }}
        </h1>
        <a href="{{ route('admin.plans.index') }}" class="btn glass-button btn-secondary" style="width: auto; margin-top: 0; padding: 0.5rem 1rem !important;">
            <i class="fas fa-arrow-left mr-2"></i>Back to Protocols
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="glass-card mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="m-0 font-weight-bold" style="color: var(--primary-green-light);">Resource Constraints</h5>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('admin.plans.update', $plan) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-4">
                            <label class="glass-label">Protocol Name</label>
                            <input type="text" name="name" class="form-control glass-input" value="{{ $plan->name }}" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="glass-label">Maximum Hardware Nodes</label>
                                <div class="input-group">
                                    <input type="number" name="max_devices" class="form-control glass-input" value="{{ $plan->max_devices }}" min="1" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text bg-transparent border-0 text-muted">Units</span>
                                    </div>
                                </div>
                                <small class="text-muted">Total devices a user can register under this tier.</small>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="glass-label">Module Capacity per Node</label>
                                <div class="input-group">
                                    <input type="number" name="max_widgets_per_device" class="form-control glass-input" value="{{ $plan->max_widgets_per_device }}" min="1" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text bg-transparent border-0 text-muted">Widgets</span>
                                    </div>
                                </div>
                                <small class="text-muted">Maximum interactive modules allowed on each dashboard.</small>
                            </div>
                        </div>

                        <div class="pt-3 border-top" style="border-color: rgba(255,255,255,0.1) !important;">
                            <button type="submit" class="btn glass-button glass-button-primary" style="width: auto;">
                                <i class="fas fa-save mr-2"></i>Update Protocol Limits
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="glass-card p-4">
                <h6 class="font-weight-bold mb-3" style="color: var(--primary-green-light);">
                    <i class="fas fa-info-circle mr-2"></i>Protocol Rulebook
                </h6>
                <p class="small text-muted mb-4">Updating these values will immediately affect all users currently assigned to the <strong>{{ $plan->name }}</strong> protocol.</p>
                
                <div class="p-3 rounded mb-4" style="background: rgba(255,191,36,0.05); border: 1px solid rgba(255,191,36,0.1);">
                    <div class="small font-weight-bold text-warning mb-2">Notice</div>
                    <div class="small text-white" style="line-height: 1.4;">Changes are enforced at the backend level. Users who are already over the new limit will cannot add more items until they upgrade or purge existing nodes.</div>
                </div>
            </div>
        </div>
    </div>
@endsection
