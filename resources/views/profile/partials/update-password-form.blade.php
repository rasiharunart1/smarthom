<div class="glass-card mb-4">
    <div class="card-header bg-transparent border-0 pt-4 px-4">
        <h5 class="m-0 font-weight-bold" style="color: var(--primary-green-light);">Authentication Security</h5>
        <p class="small mb-0" style="color: var(--text-muted);">Ensure your account remains protected with a highly complex password.</p>
    </div>
    <div class="card-body p-4">
        <form method="post" action="{{ route('password.update') }}">
            @csrf
            @method('put')

            <div class="form-group mb-4">
                <label class="glass-label" for="update_password_current_password">Current Secret Key</label>
                <input type="password"
                    class="form-control glass-input @error('current_password', 'updatePassword') is-invalid @enderror"
                    id="update_password_current_password" name="current_password" autocomplete="current-password" placeholder="••••••••">
                @error('current_password', 'updatePassword')
                    <div class="error-msg">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group mb-4">
                <label class="glass-label" for="update_password_password">New Secret Key</label>
                <input type="password" class="form-control glass-input @error('password', 'updatePassword') is-invalid @enderror"
                    id="update_password_password" name="password" autocomplete="new-password" placeholder="••••••••">
                @error('password', 'updatePassword')
                    <div class="error-msg">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group mb-4">
                <label class="glass-label" for="update_password_password_confirmation">Confirm New Key</label>
                <input type="password"
                    class="form-control glass-input @error('password_confirmation', 'updatePassword') is-invalid @enderror"
                    id="update_password_password_confirmation" name="password_confirmation" autocomplete="new-password" placeholder="••••••••">
                @error('password_confirmation', 'updatePassword')
                    <div class="error-msg">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex align-items-center">
                <button type="submit" class="btn glass-button btn-warning" style="width: auto; background: rgba(251, 191, 36, 0.1) !important; color: #fbbf24 !important; border: 1px solid rgba(251, 191, 36, 0.2) !important;">
                    Modify Access Key
                </button>

                @if (session('status') === 'password-updated')
                    <span class="ml-3 small text-success" style="font-weight: 600;">
                        Security credentials revamped
                    </span>
                @endif
            </div>
        </form>
    </div>
</div>
