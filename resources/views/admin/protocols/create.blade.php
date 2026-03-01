@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="d-flex align-items-center mb-4">
                <a href="{{ route('admin.protocols.index') }}" class="btn btn-link text-white-50 mr-3">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <h1 class="h3 mb-0 text-white">Add New Protocol</h1>
            </div>

            <div class="glass-card p-4">
                <form action="{{ route('admin.protocols.store') }}" method="POST">
                    @csrf
                    
                    <div class="form-group">
                        <label class="glass-label">Protocol Name</label>
                        <input type="text" name="name" class="form-control glass-input @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="e.g. MQTT" required>
                        @error('name')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="glass-label">Type</label>
                                <select name="type" class="form-control glass-input @error('type') is-invalid @enderror" required>
                                    <option value="Transport" {{ old('type') == 'Transport' ? 'selected' : '' }}>Transport (e.g. TCP, UDP)</option>
                                    <option value="Application" {{ old('type') == 'Application' ? 'selected' : '' }}>Application (e.g. HTTP, MQTT)</option>
                                    <option value="Hardware" {{ old('type') == 'Hardware' ? 'selected' : '' }}>Hardware (e.g. I2C, SPI)</option>
                                    <option value="Other" {{ old('type') == 'Other' ? 'selected' : '' }}>Other</option>
                                </select>
                                @error('type')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="glass-label">Default Port (Optional)</label>
                                <input type="number" name="default_port" class="form-control glass-input @error('default_port') is-invalid @enderror" value="{{ old('default_port') }}" placeholder="e.g. 1883">
                                @error('default_port')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="glass-label">Description</label>
                        <textarea name="description" rows="3" class="form-control glass-input @error('description') is-invalid @enderror" placeholder="Brief description of the protocol">{{ old('description') }}</textarea>
                        @error('description')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="text-right mt-4">
                        <button type="submit" class="btn glass-button btn-primary px-4">Create Protocol</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
