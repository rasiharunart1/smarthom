@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800 text-white">Protocols Management</h1>
            <a href="{{ route('admin.protocols.create') }}" class="btn glass-button btn-primary">
                <i class="fas fa-plus mr-2"></i>Add Protocol
            </a>
        </div>

        <div class="glass-card">
            <div class="table-responsive">
                <table class="table text-white mb-0">
                    <thead>
                        <tr>
                            <th class="border-top-0">Name</th>
                            <th class="border-top-0">Type</th>
                            <th class="border-top-0">Default Port</th>
                            <th class="border-top-0">Description</th>
                            <th class="border-top-0 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($protocols as $protocol)
                        <tr>
                            <td class="align-middle border-light-10">{{ $protocol->name }}</td>
                            <td class="align-middle border-light-10">
                                <span class="badge badge-light">{{ $protocol->type }}</span>
                            </td>
                            <td class="align-middle border-light-10">{{ $protocol->default_port ?? '-' }}</td>
                            <td class="align-middle border-light-10">{{ Str::limit($protocol->description, 50) }}</td>
                            <td class="align-middle border-light-10 text-right">
                                <a href="{{ route('admin.protocols.edit', $protocol) }}" class="btn btn-sm btn-info glass-button mr-1">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('admin.protocols.destroy', $protocol) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this protocol?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger glass-button">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 border-light-10 text-muted">No protocols found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            @if($protocols->hasPages())
            <div class="p-3 border-top border-light-10">
                {{ $protocols->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
