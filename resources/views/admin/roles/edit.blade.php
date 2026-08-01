@extends('admin.layouts.main')

@section('page_title', 'Edit Role')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">Edit Role</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.roles.index') }}">Roles</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit Role</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Edit Role: {{ $role->name }}</h4>
                        @include('flash_msg')

                        <form class="forms-sample" method="POST" action="{{ route('admin.roles.update', $role->id) }}">
                            @csrf
                            @method('PUT')

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Role Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name"
                                        class="form-control @error('name') is-invalid @enderror"
                                        value="{{ old('name', $role->name) }}" placeholder="Enter role name" required>
                                    @error('name')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Description</label>
                                    <input type="text" name="description"
                                        class="form-control @error('description') is-invalid @enderror"
                                        value="{{ old('description', $role->description) }}" placeholder="Enter role description">
                                    @error('description')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check mt-2">
                                        <input type="checkbox" name="is_default" value="1" class="form-check-input" id="isDefault"
                                            {{ old('is_default', $role->is_default) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="isDefault">Set as Default Role</label>
                                    </div>
                                    <small class="text-muted">Default role will be assigned to new users automatically</small>
                                </div>
                            </div>

                            <hr class="my-4">

                            <h5 class="mb-3">Permissions</h5>
                            <div class="mb-3">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="selectAll">Select All</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAll">Deselect All</button>
                            </div>

                            <div class="row">
                                @foreach($permissions as $module => $modulePermissions)
                                    <div class="col-md-4 mb-4">
                                        <div class="card border">
                                            <div class="card-header bg-light py-2">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input module-checkbox"
                                                        id="module_{{ $module }}" data-module="{{ $module }}">
                                                    <label class="form-check-label fw-bold" for="module_{{ $module }}">
                                                        {{ ucwords(str_replace('-', ' ', $module)) }}
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="card-body py-2">
                                                @foreach($modulePermissions as $permission)
                                                    <div class="form-check">
                                                        <input type="checkbox" name="permissions[]"
                                                            value="{{ $permission->id }}"
                                                            class="form-check-input permission-checkbox permission-{{ $module }}"
                                                            id="permission_{{ $permission->id }}"
                                                            {{ in_array($permission->id, old('permissions', $rolePermissions)) ? 'checked' : '' }}>
                                                        <label class="form-check-label" for="permission_{{ $permission->id }}">
                                                            {{ ucfirst(str_replace($module . '.', '', $permission->slug)) }}
                                                        </label>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-primary me-2">Update Role</button>
                                <a href="{{ route('admin.roles.index') }}" class="btn btn-light">Cancel</a>
                            </div>
                        </form>

                        {{-- Change Password (super-admin self-service).
                             Own <form> because nested forms are invalid HTML
                             and the password change is a distinct workflow
                             from role editing (email-OTP gated + forces
                             re-login). Rendered ONLY when:
                             (a) the viewer is a super admin, AND
                             (b) this is the Super Admin role edit page
                                 (slug === 'super-admin').
                             Sub-admins editing the same role will not see
                             this section. --}}
                        @if(auth()->user()->isSuperAdmin() && $role->slug === 'super-admin')
                            @php $otpSent = session('password_otp_sent') === true; @endphp
                            <hr class="my-4">
                            <h5 class="mb-3">Change Password</h5>
                            <p class="text-muted small mb-3">
                                Updates your own super-admin login password
                                ({{ auth()->user()->email }}). A one-time code
                                is emailed to you to confirm the change. You
                                will be logged out after saving.
                            </p>

                            <form method="POST" action="{{ route('admin.roles.change-password') }}">
                                @csrf

                                @if(!$otpSent)
                                    {{-- Step 1 — collect passwords, request OTP. --}}
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Current Password</label>
                                            <div class="input-group">
                                                <input type="password" name="old_password" autocomplete="current-password"
                                                    class="form-control password-input @error('old_password') is-invalid @enderror"
                                                    placeholder="Enter current password">
                                                <button type="button" class="btn btn-outline-secondary toggle-password" tabindex="-1" aria-label="Show password">
                                                    <i class="fa fa-eye"></i>
                                                </button>
                                            </div>
                                            @error('old_password')
                                                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">New Password</label>
                                            <div class="input-group">
                                                <input type="password" name="password" autocomplete="new-password"
                                                    class="form-control password-input @error('password') is-invalid @enderror"
                                                    placeholder="Minimum 8 characters">
                                                <button type="button" class="btn btn-outline-secondary toggle-password" tabindex="-1" aria-label="Show password">
                                                    <i class="fa fa-eye"></i>
                                                </button>
                                            </div>
                                            @error('password')
                                                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Confirm New Password</label>
                                            <div class="input-group">
                                                <input type="password" name="password_confirmation" autocomplete="new-password"
                                                    class="form-control password-input" placeholder="Re-enter new password">
                                                <button type="button" class="btn btn-outline-secondary toggle-password" tabindex="-1" aria-label="Show password">
                                                    <i class="fa fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-end mt-3">
                                        <button type="submit" class="btn btn-primary">Send OTP to Email</button>
                                    </div>
                                @else
                                    {{-- Step 2 — collected OTP, verify. --}}
                                    <div class="alert alert-info py-2 mb-3">
                                        A 6-digit OTP has been emailed to
                                        <strong>{{ auth()->user()->email }}</strong>.
                                        It expires in 10 minutes.
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Enter OTP</label>
                                            <input type="text" name="otp" inputmode="numeric" pattern="[0-9]{6}"
                                                maxlength="6" autocomplete="one-time-code"
                                                class="form-control @error('otp') is-invalid @enderror"
                                                placeholder="6-digit code">
                                            @error('otp')
                                                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="text-end mt-3">
                                        <button type="submit" class="btn btn-primary">Verify OTP &amp; Save</button>
                                    </div>
                                @endif
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            // Select all permissions
            $('#selectAll').click(function() {
                $('.permission-checkbox').prop('checked', true);
                $('.module-checkbox').prop('checked', true);
            });

            // Deselect all permissions
            $('#deselectAll').click(function() {
                $('.permission-checkbox').prop('checked', false);
                $('.module-checkbox').prop('checked', false);
            });

            // Module checkbox - select/deselect all permissions in module
            $('.module-checkbox').change(function() {
                const module = $(this).data('module');
                const isChecked = $(this).prop('checked');
                $('.permission-' + module).prop('checked', isChecked);
            });

            // Update module checkbox when individual permission changes
            $('.permission-checkbox').change(function() {
                const classes = $(this).attr('class').split(' ');
                const moduleClass = classes.find(c => c.startsWith('permission-') && c !== 'permission-checkbox');
                if (moduleClass) {
                    const module = moduleClass.replace('permission-', '');
                    const allChecked = $('.permission-' + module + ':checked').length === $('.permission-' + module).length;
                    $('#module_' + module).prop('checked', allChecked);
                }
            });

            // Initialize module checkboxes state
            $('.module-checkbox').each(function() {
                const module = $(this).data('module');
                const allChecked = $('.permission-' + module + ':checked').length === $('.permission-' + module).length;
                $(this).prop('checked', allChecked);
            });

            // Password visibility toggle for the Change Password section.
            // Uses event delegation so it works even if the fields are absent
            // (non-super-admin viewers) — the selector just matches nothing.
            $(document).on('click', '.toggle-password', function () {
                const $btn = $(this);
                const $input = $btn.closest('.input-group').find('input.password-input');
                const $icon = $btn.find('i');
                if ($input.attr('type') === 'password') {
                    $input.attr('type', 'text');
                    $icon.removeClass('fa-eye').addClass('fa-eye-slash');
                    $btn.attr('aria-label', 'Hide password');
                } else {
                    $input.attr('type', 'password');
                    $icon.removeClass('fa-eye-slash').addClass('fa-eye');
                    $btn.attr('aria-label', 'Show password');
                }
            });
        });
    </script>
@endpush
