@extends('layouts.app')

@section('title', 'Devices')

@section('content')
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0" style="color: white; font-weight: 700;">
            <i class="fas fa-microchip mr-2 text-primary"></i>Managed Hardware
        </h1>
        <a href="{{ route('devices.create') }}" class="btn glass-button glass-button-primary" style="width: auto; margin-top: 0; padding: 0.5rem 1.25rem !important;">
            <i class="fas fa-plus mr-2"></i>Provision New Device
        </a>
    </div>

    <!-- Devices List -->
    <div class="glass-card overflow-hidden">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
            <h5 class="m-0 font-weight-bold" style="color: var(--primary-green-light);">Hardware Inventory</h5>
        </div>
        <div class="card-body p-0">
            @if ($devices->count() > 0)
                <div class="table-responsive">
                    <table class="table mb-0" style="color: white;">
                        <thead>
                            <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                                <th class="border-0 px-4 py-3" style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Situs</th>
                                <th class="border-0 py-3" style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Identifier</th>
                                <th class="border-0 py-3" style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Access Token</th>
                                <th class="border-0 py-3 text-center" style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Modules</th>
                                <th class="border-0 py-3" style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Last Sync</th>
                                <th class="border-0 px-4 py-3 text-right" style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($devices as $device)
                                <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.05); transition: background 0.3s;" onmouseover="this.style.background='rgba(255,255,255,0.02)'" onmouseout="this.style.background='transparent'">
                                    <td class="px-4 py-4 align-middle">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-circle mr-2 {{ $device->isOnline() ? 'pulse-online' : '' }}" 
                                               style="font-size: 0.6rem; color: {{ $device->isOnline() ? 'var(--primary-green-light)' : 'var(--text-muted)' }};"></i>
                                            <span style="font-size: 0.75rem; font-weight: 600; color: {{ $device->isOnline() ? 'var(--primary-green-light)' : 'var(--text-muted)' }};">
                                                {{ strtoupper($device->status) }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-4 align-middle">
                                        <div style="font-weight: 600;">{{ $device->name }}</div>
                                        @if(($device->_share_source ?? 'owned') === 'shared')
                                            <div class="mt-1">
                                                <span class="badge" style="background: rgba(99,179,237,0.12); color: #90cdf4; border: 1px solid rgba(99,179,237,0.25); border-radius: 20px; font-size: 0.68rem; padding: 2px 8px; font-weight: 600;">
                                                    <i class="fas fa-share-alt mr-1"></i>Shared
                                                    @if($device->_share_permission === 'control')
                                                        &mdash; 🎮 Control
                                                    @else
                                                        &mdash; 👁 View only
                                                    @endif
                                                </span>
                                                @if($device->_shared_by)
                                                    <span class="ml-1" style="font-size: 0.7rem; color: var(--text-muted);">by {{ $device->_shared_by->name }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-4 align-middle">
                                        <div class="d-flex align-items-center">
                                            <code style="background: rgba(255,255,255,0.05); color: var(--primary-green-light); padding: 0.3rem 0.6rem; border-radius: 6px; font-size: 0.8rem; border: 1px solid rgba(255,255,255,0.1);">{{ Str::limit($device->device_code, 12) }}</code>
                                            <button class="btn btn-link btn-sm text-muted ml-2" onclick="copyToClipboard('{{ $device->device_code }}')" title="Copy Code">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="py-4 align-middle text-center">
                                        <span class="badge py-1 px-2" style="background: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2);">
                                            {{ $device->widget->widget_count ?? 0 }}
                                        </span>
                                    </td>
                                    <td class="py-4 align-middle" style="font-size: 0.85rem; color: var(--text-muted);">
                                        {{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Standby' }}
                                    </td>
                                    <td class="px-4 py-4 align-middle text-right">
                                        <div class="btn-group">
                                            <a href="{{ route('dashboard', ['device_id' => $device->id]) }}" 
                                               class="btn btn-sm mr-2" style="background: rgba(16, 185, 129, 0.1); color: var(--primary-green-light); border: 1px solid rgba(16, 185, 129, 0.2);" title="Dashboard">
                                                <i class="fas fa-chart-line"></i>
                                            </a>
                                            @if(($device->_share_source ?? 'owned') === 'owned')
                                                <a href="{{ route('devices.edit', $device) }}" 
                                                   class="btn btn-sm mr-2" style="background: rgba(251, 191, 36, 0.1); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.2);" title="Settings">
                                                    <i class="fas fa-cog"></i>
                                                </a>
                                                <a href="{{ route('devices.shares.index', $device->device_code) }}"
                                                   class="btn btn-sm mr-2" style="background: rgba(99,179,237,0.1); color: #90cdf4; border: 1px solid rgba(99,179,237,0.2);" title="Manage Sharing">
                                                    <i class="fas fa-share-alt"></i>
                                                </a>
                                                <form action="{{ route('devices.destroy', $device) }}" method="POST" class="d-inline" onsubmit="return confirm('Erase this hardware from inventory?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm" style="background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2);" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-5">
                    <div class="mb-4" style="opacity: 0.2;">
                        <i class="fas fa-microchip fa-5x"></i>
                    </div>
                    <h4 style="color: white; font-weight: 600;">Inventory Empty</h4>
                    <p style="color: var(--text-muted);">Provision your first hardware to begin monitoring.</p>
                    <a href="{{ route('devices.create') }}" class="btn glass-button glass-button-primary mt-3" style="width: auto;">
                        Provision Hardware
                    </a>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .pulse-online {
            animation: pulse-ring 2s infinite;
        }
        @keyframes pulse-ring {
            0% { opacity: 1; text-shadow: 0 0 5px currentColor; }
            50% { opacity: 0.4; text-shadow: none; }
            100% { opacity: 1; text-shadow: 0 0 5px currentColor; }
        }
        .table thead th {
            letter-spacing: 0.05em;
            font-weight: 700;
        }
        .btn-link:hover {
            color: var(--primary-green-light) !important;
            transform: scale(1.1);
        }
    </style>
@endpush

@push('scripts')
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                if(window.showNotification) {
                    showNotification('Device key copied', 'success');
                } else {
                    alert('Copied');
                }
            });
        }
    </script>
@endpush