@extends('layouts.app')

@section('title', 'Add Device')

@section('content')
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0" style="color: white; font-weight: 700;">
            <i class="fas fa-plus-circle mr-2 text-primary"></i>Register New Hardware
        </h1>
        <a href="{{ route('devices.index') }}" class="btn glass-button btn-secondary" style="width: auto; margin-top: 0; padding: 0.5rem 1rem !important;">
            <i class="fas fa-arrow-left mr-2"></i>Back to My Fleet
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="glass-card">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="m-0 font-weight-bold" style="color: var(--primary-green-light);">Identity</h5>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('devices.store') }}" method="POST">
                        @csrf

                        @if(auth()->user()->isAdmin() && isset($targetUser))
                            <input type="hidden" name="user_id" value="{{ $targetUser->id }}">
                            <div class="mb-4 p-3 rounded d-flex align-items-center" style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2);">
                                <div class="user-avatar mr-3" style="width: 35px; height: 35px; background: rgba(59, 130, 246, 0.1); color: #60a5fa; font-size: 0.8rem;">
                                    {{ substr($targetUser->name, 0, 1) }}
                                </div>
                                <div>
                                    <div class="small text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 700;">Target Operator</div>
                                    <div class="font-weight-bold" style="color: #60a5fa;">{{ $targetUser->name }}</div>
                                </div>
                                <div class="ml-auto">
                                    <span class="badge badge-primary px-3 py-1" style="background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.3); color: #60a5fa;">ADMIN OVERRIDE</span>
                                </div>
                            </div>
                        @endif

                        <div class="mb-4">
                            <label for="name" class="glass-label">Node Identifier</label>
                            <input type="text" class="form-control glass-input @error('name') is-invalid @enderror" id="name"
                                name="name" value="{{ old('name') }}" placeholder="e.g. Master Terminal"
                                required autofocus>
                            @error('name')
                                <div class="error-msg">{{ $message }}</div>
                            @enderror
                            <small class="form-text mt-2" style="color: var(--text-muted);">
                                Provide a professional label for the hardware unit.
                            </small>
                        </div>

                        <div class="alert mb-4" style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); color: #60a5fa; border-radius: 12px;">
                            <i class="fas fa-info-circle mr-2"></i>
                            After creation, a unique authentication code will be generated for your firmware.
                        </div>

                        <div class="pt-3 border-top d-flex justify-content-between" style="border-color: rgba(255, 255, 255, 0.1) !important;">
                            <a href="{{ route('devices.index') }}" class="btn glass-button btn-secondary" style="width: auto;">
                                Cancel
                            </a>
                            <button type="submit" class="btn glass-button glass-button-primary" style="width: auto;">
                                <i class="fas fa-check-circle mr-2"></i>Register Hardware
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="glass-card mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="m-0 font-weight-bold" style="color: var(--primary-green-light);">Workflow</h5>
                </div>
                <div class="card-body p-4">
                    <ol class="small mb-0" style="color: var(--text-secondary); line-height: 1.6;">
                        <li class="mb-3">Define a name for your IoT node</li>
                        <li class="mb-3">Obtain the machine-generated secure token</li>
                        <li class="mb-3">Flash your ESP32 with the provided credentials</li>
                        <li class="mb-3">Configure visual widgets for data interaction</li>
                        <li>Monitor real-time telemetry from the dashboard</li>
                    </ol>
                </div>
            </div>

            <div class="glass-card" style="border-left: 4px solid var(--primary-green) !important;">
                <div class="card-body p-4">
                    <h6 class="font-weight-bold mb-3" style="color: var(--primary-green-light);">
                        <i class="fas fa-lightbulb mr-2"></i>Pro Tips
                    </h6>
                    <ul class="small mb-0" style="color: var(--text-muted); list-style: none; padding-left: 0;">
                        <li class="mb-2"><i class="fas fa-check mr-2" style="font-size: 0.7rem;"></i>One device per MCU board</li>
                        <li class="mb-2"><i class="fas fa-check mr-2" style="font-size: 0.7rem;"></i>Use descriptive location-based names</li>
                        <li class="mb-2"><i class="fas fa-check mr-2" style="font-size: 0.7rem;"></i>Store your secure tokens safely</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
