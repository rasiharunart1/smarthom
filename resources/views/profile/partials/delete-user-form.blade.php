<div class="glass-card mb-4" style="border-left: 4px solid #ef4444 !important;">
    <div class="card-header bg-transparent border-0 pt-4 px-4">
        <h5 class="m-0 font-weight-bold text-danger">Privacy & Data Removal</h5>
        <p class="small mb-0" style="color: var(--text-muted);">
            Account termination sequence will permanently purge all telemetry and hardware linking.
        </p>
    </div>
    <div class="card-body p-4">
        <button type="button" class="btn glass-button btn-danger" data-toggle="modal" data-target="#deleteAccountModal" style="width: auto; background: rgba(239, 68, 68, 0.1) !important; color: #f87171 !important; border: 1px solid rgba(239, 68, 68, 0.2) !important;">
            Terminate Account
        </button>
    </div>
</div>

<!-- Delete Account Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content glass-modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-danger font-weight-bold">
                    Confirm Termination
                </h5>
                <button type="button" class="close text-white opacity-1" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <form method="post" action="{{ route('profile.destroy') }}">
                @csrf
                @method('delete')

                <div class="modal-body py-4">
                    <div class="p-3 mb-4" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 12px; color: #f87171;">
                        <h6 class="font-weight-bold">Erase all data?</h6>
                        <p class="small mb-0">
                            This operation is irreversible. All linked IoT devices will lose connectivity. Confirm your identity below.
                        </p>
                    </div>

                    <div class="form-group mb-0">
                        <label class="glass-label" for="password">Security Verification</label>
                        <input type="password"
                            class="form-control glass-input @error('password', 'userDeletion') is-invalid @enderror" id="password"
                            name="password" placeholder="Confirm your account secret" required>
                        @error('password', 'userDeletion')
                            <div class="error-msg">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0 pb-4">
                    <button type="button" class="btn glass-button btn-secondary" data-dismiss="modal" style="width: auto;">
                        Abort
                    </button>
                    <button type="submit" class="btn glass-button btn-danger" style="width: auto; background: #ef4444 !important; border: none !important; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3) !important;">
                        Execute Termination
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@if ($errors->userDeletion->isNotEmpty())
    @push('scripts')
        <script>
            $(document).ready(function() {
                $('#deleteAccountModal').modal('show');
            });
        </script>
    @endpush
@endif
