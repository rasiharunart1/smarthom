@extends('layouts.app')

@section('title', 'Define New Subscription Protocol')

@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0" style="color: white; font-weight: 700;">
            <i class="fas fa-plus-circle mr-2 text-primary"></i>Define New Protocol
        </h1>
        <a href="{{ route('admin.plans.index') }}" class="btn glass-button btn-secondary" style="width: auto; margin-top: 0; padding: 0.5rem 1rem !important;">
            <i class="fas fa-arrow-left mr-2"></i>Back to Protocols
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="glass-card mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="m-0 font-weight-bold" style="color: var(--primary-green-light);">Resource Definition</h5>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('admin.plans.store') }}" method="POST">
                        @csrf

                        <div class="row">
                            <div class="col-md-12 mb-4">
                                <label class="glass-label">Protocol Name</label>
                                <input type="text" name="name" class="form-control glass-input @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="e.g. Enterprise Tier" required>
                                @error('name')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="glass-label">Maximum Hardware Nodes</label>
                                <div class="input-group">
                                    <input type="number" name="max_devices" class="form-control glass-input @error('max_devices') is-invalid @enderror" value="{{ old('max_devices', 5) }}" min="1" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text bg-transparent border-0 text-muted">Units</span>
                                    </div>
                                </div>
                                @error('max_devices')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="glass-label">Module Capacity per Node</label>
                                <div class="input-group">
                                    <input type="number" name="max_widgets_per_device" class="form-control glass-input @error('max_widgets_per_device') is-invalid @enderror" value="{{ old('max_widgets_per_device', 10) }}" min="1" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text bg-transparent border-0 text-muted">Widgets</span>
                                    </div>
                                </div>
                                @error('max_widgets_per_device')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="pt-3 border-top" style="border-color: rgba(255,255,255,0.1) !important;">
                            <button type="submit" class="btn glass-button glass-button-primary" style="width: auto;">
                                <i class="fas fa-save mr-2"></i>Create Protocol Tier
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="glass-card p-4">
                <h6 class="font-weight-bold mb-3" style="color: var(--primary-green-light);">
                    <i class="fas fa-cogs mr-2"></i>Configuration Guide
                </h6>
                <p class="small text-muted mb-4">Define a new subscription tier for your users. The Slug must be unique and is used internally.</p>
                
                <div class="p-3 rounded mb-4" style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2);">
                    <div class="small font-weight-bold text-success mb-2">Standard Features</div>
                    <ul class="list-unstyled mb-0 small text-white-50">
                        <li><i class="fas fa-check mr-2"></i> 30 Days Data Retention</li>
                        <li><i class="fas fa-check mr-2"></i> API Access Enabled</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
