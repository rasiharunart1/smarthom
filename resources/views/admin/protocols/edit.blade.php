@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="d-flex align-items-center mb-4">
                <a href="{{ route('admin.protocols.index') }}" class="btn btn-link text-white-50 mr-3">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <h1 class="h3 mb-0 text-white">Edit Protocol</h1>
            </div>

            <div class="glass-card p-4">
                <form action="{{ route('admin.protocols.update', $protocol) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="form-group">
                        <label class="glass-label">Protocol Name</label>
                        <input type="text" name="name" class="form-control glass-input @error('name') is-invalid @enderror" value="{{ old('name', $protocol->name) }}" required>
                        @error('name')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="glass-label">Type</label>
                                <select name="type" class="form-control glass-input @error('type') is-invalid @enderror" required>
                                    <option value="Transport" {{ old('type', $protocol->type) == 'Transport' ? 'selected' : '' }}>Transport (e.g. TCP, UDP)</option>
                                    <option value="Application" {{ old('type', $protocol->type) == 'Application' ? 'selected' : '' }}>Application (e.g. HTTP, MQTT)</option>
                                    <option value="Hardware" {{ old('type', $protocol->type) == 'Hardware' ? 'selected' : '' }}>Hardware (e.g. I2C, SPI)</option>
                                    <option value="Other" {{ old('type', $protocol->type) == 'Other' ? 'selected' : '' }}>Other</option>
                                </select>
                                @error('type')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="glass-label">Default Port (Optional)</label>
                                <input type="number" name="default_port" class="form-control glass-input @error('default_port') is-invalid @enderror" value="{{ old('default_port', $protocol->default_port) }}">
                                @error('default_port')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="glass-label">Description</label>
                        <textarea name="description" rows="3" class="form-control glass-input @error('description') is-invalid @enderror">{{ old('description', $protocol->description) }}</textarea>
                        @error('description')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="text-right mt-4">
                        <button type="submit" class="btn glass-button btn-primary px-4">Update Protocol</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
