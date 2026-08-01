@extends('admin.layouts.main')

@section('page_title', 'Create Role')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">Create Role</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.roles.index') }}">Roles</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Create Role</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Create New Role</h4>
                        @include('flash_msg')

                        <form class="forms-sample" method="POST" action="{{ route('admin.roles.store') }}">
                            @csrf

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Role Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name"
                                        class="form-control @error('name') is-invalid @enderror"
                                        value="{{ old('name') }}" placeholder="Enter role name" required>
                                    @error('name')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Description</label>
                                    <input type="text" name="description"
                                        class="form-control @error('description') is-invalid @enderror"
                                        value="{{ old('description') }}" placeholder="Enter role description">
                                    @error('description')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check mt-2">
                                        <input type="checkbox" name="is_default" value="1" class="form-check-input" id="isDefault" {{ old('is_default') ? 'checked' : '' }}>
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
                                                            {{ in_array($permission->id, old('permissions', [])) ? 'checked' : '' }}>
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
                                <button type="submit" class="btn btn-primary me-2">Create Role</button>
                                <a href="{{ route('admin.roles.index') }}" class="btn btn-light">Cancel</a>
                            </div>
                        </form>
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
        });
    </script>
@endpush
