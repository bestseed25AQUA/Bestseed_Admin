@extends('admin.layouts.main')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">Edit Wanted Stock Listing</h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('seed-wanted-listings.index') }}">Wanted Stock Listings</a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </nav>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('seed-wanted-listings.update', $listing->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control"
                               value="{{ old('title', $listing->title) }}" required>
                        @error('title') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Species <span class="text-danger">*</span></label>
                        <select name="species" class="form-control" required>
                            <option value="shrimp" {{ old('species', $listing->species) == 'shrimp' ? 'selected' : '' }}>Shrimp</option>
                            <option value="fish" {{ old('species', $listing->species) == 'fish' ? 'selected' : '' }}>Fish</option>
                        </select>
                        @error('species') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Count / KG <span class="text-danger">*</span></label>
                        <select name="count_or_kg" class="form-control" required>
                            <option value="count" {{ old('count_or_kg', $listing->count_or_kg) == 'count' ? 'selected' : '' }}>Count</option>
                            <option value="kg" {{ old('count_or_kg', $listing->count_or_kg) == 'kg' ? 'selected' : '' }}>KG</option>
                        </select>
                        @error('count_or_kg') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Count / KG Value</label>
                        <input type="text" name="count_or_kg_value" class="form-control"
                               value="{{ old('count_or_kg_value', $listing->count_or_kg_value) }}"
                               placeholder="e.g. 42c-60c">
                        @error('count_or_kg_value') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Payment</label>
                        <input type="text" name="payment" class="form-control"
                               value="{{ old('payment', $listing->payment) }}" placeholder="e.g. Advance">
                        @error('payment') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Minimum</label>
                        <input type="text" name="minimum" class="form-control"
                               value="{{ old('minimum', $listing->minimum) }}" placeholder="e.g. 5 tons">
                        @error('minimum') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Area</label>
                        <input type="text" name="area" class="form-control"
                               value="{{ old('area', $listing->area) }}" placeholder="e.g. East Godavari">
                        @error('area') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Price</label>
                        <input type="text" name="price" class="form-control"
                               value="{{ old('price', $listing->price) }}" placeholder="e.g. 250">
                        @error('price') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control"
                               value="{{ old('phone', $listing->phone) }}" placeholder="e.g. 9876543210">
                        @error('phone') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-primary">Update</button>
                    <a href="{{ route('seed-wanted-listings.index') }}" class="btn btn-light">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
