@extends('layouts.app')

@section('title', 'User Management')

@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0" style="color: white; font-weight: 700;">
            <i class="fas fa-users-cog mr-2 text-primary"></i>Global User Directory
        </h1>
    </div>

    <div class="glass-card overflow-hidden">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
            <h5 class="m-0 font-weight-bold" style="color: var(--primary-green-light);">Managed Accounts</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0" style="color: white;">
                    <thead>
                        <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                            <th class="border-0 px-4 py-3" style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Account</th>
                            <th class="border-0 py-3" style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Permission</th>
                            <th class="border-0 py-3" style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Subscription</th>
                            <th class="border-0 py-3 text-center" style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Fleet Size</th>
                            <th class="border-0 py-3" style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Last Seen</th>
                            <th class="border-0 px-4 py-3 text-right" style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Options</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.05);">
                                <td class="px-4 py-4 align-middle">
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar mr-3" style="width: 40px; height: 40px; font-size: 1rem;">
                                            {{ substr($user->name, 0, 1) }}
                                        </div>
                                        <div>
                                            <div style="font-weight: 600;">{{ $user->name }}</div>
                                            <div class="small text-muted">{{ $user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 align-middle">
                                    <span class="badge py-1 px-3" style="background: {{ $user->isAdmin() ? 'rgba(59, 130, 246, 0.1)' : 'rgba(255,255,255,0.05)' }}; color: {{ $user->isAdmin() ? '#60a5fa' : 'white' }}; border: 1px solid {{ $user->isAdmin() ? 'rgba(59, 130, 246, 0.2)' : 'rgba(255,255,255,0.1)' }};">
                                        {{ strtoupper($user->role) }}
                                    </span>
                                </td>
                                <td class="py-4 align-middle">
                                    <div class="d-flex align-items-center">
                                        <span class="small" style="color: {{ $user->isSubscribed() ? 'var(--primary-green-light)' : '#f87171' }}; font-weight: 600;">
                                            {{ strtoupper($user->subscription_plan) }}
                                        </span>
                                        @if($user->subscription_expires_at)
                                            <div class="ml-2 small text-muted" style="font-size: 0.7rem;">Exp: {{ $user->subscription_expires_at->format('M d, Y') }}</div>
                                        @endif
                                        @if($user->lstm_allowed)
                                            <span class="badge ml-2" style="background: rgba(139, 92, 246, 0.2); color: #c4b5fd; border: 1px solid rgba(139, 92, 246, 0.3);">AI</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-4 align-middle text-center">
                                    <div style="font-weight: 700;">{{ $user->devices_count }} <small class="text-muted" style="font-weight: 400;">Units</small></div>
                                </td>
                                <td class="py-4 align-middle small text-muted">
                                    {{ $user->updated_at->diffForHumans() }}
                                </td>
                                <td class="px-4 py-4 align-middle text-right">
                                    <div class="d-flex justify-content-end align-items-center">
                                         <a href="{{ route('admin.devices.index', ['user_id' => $user->id]) }}" class="btn btn-sm mr-2" style="background: rgba(16, 185, 129, 0.1); color: var(--primary-green-light); border: 1px solid rgba(16, 185, 129, 0.2);" title="Monitor Fleet">
                                             <i class="fas fa-server"></i>
                                         </a>
                                         <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm mr-2" style="background: rgba(255,255,255,0.05); color: white; border: 1px solid rgba(255,255,255,0.1);" title="Edit Account">
                                             <i class="fas fa-edit"></i>
                                         </a>
                                        @if($user->id !== auth()->id())
                                            <form action="{{ route('admin.users.index', $user) }}" method="POST" class="d-inline" onsubmit="return confirm('Purge this user account?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm" style="background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2);">
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
            <div class="p-4">
                {{ $users->links() }}
            </div>
        </div>
    </div>
@endsection
