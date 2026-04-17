@extends('layouts.app')

@section('title', 'Global Hardware Inventory')

@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0" style="color: white; font-weight: 700;">
            <i class="fas fa-server mr-2 text-primary"></i>Universal Hardware Governance
        </h1>
        @if(isset($filteredUser))
            <div class="d-flex align-items-center">
                <div class="glass-card py-2 px-3 mr-2 d-flex align-items-center" style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2);">
                    <i class="fas fa-filter text-primary mr-2" style="font-size: 0.8rem;"></i>
                    <span class="small text-white">Fleet: <strong>{{ $filteredUser->name }}</strong></span>
                </div>
                <a href="{{ route('devices.create', ['user_id' => $filteredUser->id]) }}" class="btn btn-sm glass-button glass-button-primary mr-2" style="width: auto; padding: 6px 15px !important;">
                    <i class="fas fa-plus mr-1"></i> Add Hardware
                </a>
                <a href="{{ route('admin.devices.index') }}" class="btn btn-sm glass-button" style="width: auto; padding: 6px 15px !important;">
                    <i class="fas fa-times mr-1"></i> Clear
                </a>
            </div>
        @endif
    </div>

    {{-- Flash success --}}
    @if(session('success'))
        <div class="alert border-0 mb-4" style="background: rgba(16, 185, 129, 0.12); color: #6ee7b7; border-radius: 10px; border-left: 3px solid #10b981 !important;">
            <i class="fas {{ session('approval_status') === 'revoked' ? 'fa-ban' : 'fa-check-circle' }} mr-2"></i>
            {{ session('success') }}
        </div>
    @endif

    <div class="glass-card overflow-hidden">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
            <h5 class="m-0 font-weight-bold" style="color: var(--primary-green-light);">Global Fleet Overview</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0" style="color: white;">
                    <thead>
                        <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                            <th class="border-0 px-4 py-3" style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Sync Status</th>
                            <th class="border-0 py-3" style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Hardware Node</th>
                            <th class="border-0 py-3" style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Operator</th>
                            <th class="border-0 py-3" style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Access Key</th>
                            {{-- NEW: Approval column --}}
                            <th class="border-0 py-3" style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Approval</th>
                            <th class="border-0 py-3" style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Last Connectivity</th>
                            <th class="border-0 px-4 py-3 text-right" style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($devices as $device)
                            <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.05);">
                                {{-- Online status --}}
                                <td class="px-4 py-4 align-middle">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-circle mr-2 {{ $device->isOnline() ? 'pulse-online' : '' }}"
                                           style="font-size: 0.6rem; color: {{ $device->isOnline() ? 'var(--primary-green-light)' : 'var(--text-muted)' }};"></i>
                                        <span style="font-size: 0.75rem; font-weight: 600; color: {{ $device->isOnline() ? 'var(--primary-green-light)' : 'var(--text-muted)' }};">
                                            {{ strtoupper($device->status) }}
                                        </span>
                                    </div>
                                </td>

                                {{-- Device name --}}
                                <td class="py-4 align-middle">
                                    <div style="font-weight: 600;">{{ $device->name }}</div>
                                    <div class="small text-muted">ID: {{ $device->id }}</div>
                                </td>

                                {{-- Owner --}}
                                <td class="py-4 align-middle">
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar mr-2" style="width: 25px; height: 25px; font-size: 0.7rem;">
                                            {{ substr($device->user->name, 0, 1) }}
                                        </div>
                                        <span class="small">{{ $device->user->name }}</span>
                                    </div>
                                </td>

                                {{-- Device code --}}
                                <td class="py-4 align-middle">
                                    <code style="background: rgba(255,255,255,0.05); color: var(--primary-green-light); padding: 0.2rem 0.4rem; border-radius: 4px; font-size: 0.75rem;">{{ $device->device_code }}</code>
                                </td>

                                {{-- ✅ NEW: Approval status + toggle button --}}
                                <td class="py-4 align-middle">
                                    @if($device->isApproved())
                                        <div class="d-flex flex-column">
                                            <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #6ee7b7; border: 1px solid rgba(16, 185, 129, 0.3); padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; width: fit-content;">
                                                <i class="fas fa-check-circle mr-1"></i> APPROVED
                                            </span>
                                            @if($device->approved_at)
                                                <span class="small mt-1" style="color: var(--text-muted); font-size: 0.7rem;">
                                                    by {{ $device->approvedBy?->name ?? 'Admin' }}
                                                    &bull; {{ $device->approved_at->diffForHumans() }}
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="badge" style="background: rgba(251, 191, 36, 0.15); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.3); padding: 4px 10px; border-radius: 20px; font-size: 0.7rem;">
                                            <i class="fas fa-clock mr-1"></i> PENDING
                                        </span>
                                    @endif
                                </td>

                                {{-- Last seen --}}
                                <td class="py-4 align-middle small text-muted">
                                    {{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Standby' }}
                                </td>

                                {{-- Actions --}}
                                <td class="px-4 py-4 align-middle text-right">
                                    <div class="d-flex justify-content-end align-items-center gap-1">

                                        {{-- Inspect live dashboard --}}
                                        <a href="{{ route('dashboard', ['device_id' => $device->id]) }}"
                                           class="btn btn-sm mr-1"
                                           style="background: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2);"
                                           title="Inspect Live Dashboard">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        {{-- ✅ Toggle Approve / Revoke --}}
                                        <form action="{{ route('admin.devices.toggle-approval', $device) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('{{ $device->isApproved() ? 'Revoke access for ' . $device->device_code . '? The device will immediately stop working.' : 'Approve ' . $device->device_code . '? The device will be allowed to connect.' }}')">
                                            @csrf
                                            @if($device->isApproved())
                                                <button type="submit" class="btn btn-sm mr-1"
                                                        style="background: rgba(251, 191, 36, 0.1); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.25);"
                                                        title="Revoke Approval">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            @else
                                                <button type="submit" class="btn btn-sm mr-1"
                                                        style="background: rgba(16, 185, 129, 0.1); color: #6ee7b7; border: 1px solid rgba(16, 185, 129, 0.25);"
                                                        title="Approve Device">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            @endif
                                        </form>

                                        {{-- Delete --}}
                                        <form action="{{ route('admin.devices.destroy', $device) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Immediately terminate this hardware node and all linked widgets?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm"
                                                    style="background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2);"
                                                    title="Purge Hardware">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4">
                {{ $devices->links() }}
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
            0%   { opacity: 1; text-shadow: 0 0 5px currentColor; }
            50%  { opacity: 0.4; text-shadow: none; }
            100% { opacity: 1; text-shadow: 0 0 5px currentColor; }
        }
        .gap-1 { gap: 4px; }
    </style>
@endpush
