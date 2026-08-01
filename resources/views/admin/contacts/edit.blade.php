@extends('admin.layouts.main')

@section('page_title', 'Edit Contact')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">Edit Contact</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('contacts.index') }}">Contacts</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Edit Contact</h4>
                        @include('flash_msg')

                        <form class="forms-sample" method="POST" action="{{ route('contacts.update', $contact->id) }}" id="contactForm">
                            @csrf
                            @method('PUT')

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Label</label>
                                    <select name="label"
                                        class="form-control @error('label') is-invalid @enderror" required>
                                        <option value="">-- Select where this contact appears --</option>
                                        <option value="Main Office" {{ old('label', $contact->label) == 'Main Office' ? 'selected' : '' }}>Main Office (Home screen)</option>
                                        <option value="Profile Help" {{ old('label', $contact->label) == 'Profile Help' ? 'selected' : '' }}>Profile Help (Profile → Help)</option>
                                        <option value="Booking Help" {{ old('label', $contact->label) == 'Booking Help' ? 'selected' : '' }}>Booking Help (Booking details → Call)</option>
                                    </select>
                                    @error('label')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" name="status" id="status"
                                            {{ old('status', $contact->status) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="status">Active</label>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" name="phone"
                                        class="form-control @error('phone') is-invalid @enderror"
                                        value="{{ old('phone', $contact->phone) }}" placeholder="e.g., 9704756582">
                                    @error('phone')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">WhatsApp Number</label>
                                    <input type="text" name="whatsapp"
                                        class="form-control @error('whatsapp') is-invalid @enderror"
                                        value="{{ old('whatsapp', $contact->whatsapp) }}" placeholder="e.g., 9704756582">
                                    @error('whatsapp')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-primary me-2" id="submitBtn">Update</button>
                                <a href="{{ route('contacts.index') }}" class="btn btn-light">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
