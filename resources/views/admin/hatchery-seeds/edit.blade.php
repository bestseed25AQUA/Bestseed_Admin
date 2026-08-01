@extends('admin.layouts.main')

@section('page_title', 'Edit Hatchery Seed')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">Edit Hatchery Seed</h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('hatchery-seeds.index') }}">Hatchery Seeds</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit</li>
            </ol>
        </nav>
    </div>

    <div class="row">
        <div class="col-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Update Seed Info</h4>
                    @include('flash_msg')

                    {{-- <form method="POST" action="{{ route('hatchery-seeds.update', $hatchery_seed->id) }}">
                        @csrf
                        @method('PUT') --}}

<form method="POST" action="{{ route('hatchery-seeds.update', $hatchery_seed->id) }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div class="row">
        {{-- Hatchery --}}
        <div class="col-md-6 mb-3">
            <label class="form-label">Hatchery Name</label>
            <select name="hatchery_id" class="form-control">
                <option value="">Select Hatchery</option>
                @foreach($data as $hatchery)
                    <option value="{{ $hatchery->id }}" {{ $hatchery_seed->hatchery_id == $hatchery->id ? 'selected' : '' }}>
                        {{ $hatchery->picker_label }}
                    </option>
                @endforeach
            </select>
            @error('hatchery_id')
                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
            @enderror
        </div>

        {{-- Category --}}
        <div class="col-md-6 mb-3">
            <label class="form-label">Category</label>
            <select name="category_id" class="form-control">
                <option value="">Select Category</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" {{ $hatchery_seed->category_id == $category->id ? 'selected' : '' }}>
                        {{ $category->category_name }}
                    </option>
                @endforeach
            </select>
            @error('category_id')
                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
            @enderror
        </div>

        {{-- Location --}}
        <div class="col-md-6 mb-3">
            <label class="form-label">Location</label>
            <select name="location_id" class="form-control">
                <option value="">Select Location</option>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" {{ $hatchery_seed->location_id == $location->id ? 'selected' : '' }}>
                        {{ $location->location_name }}
                    </option>
                @endforeach
            </select>
            @error('location_id')
                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
            @enderror
        </div>

        {{-- Seed Brand --}}
        <div class="col-md-6 mb-3">
            <label class="form-label">Seed Brand</label>
            <input type="text" name="seed_brand" class="form-control" value="{{ old('seed_brand', $hatchery_seed->seed_brand) }}">
        </div>

        {{-- Broad Stock --}}
        <div class="col-md-6 mb-3">
            <label class="form-label">Broad Stock</label>
            <input type="number" name="broad_stock" class="form-control" value="{{ old('broad_stock', $hatchery_seed->broad_stock) }}">
        </div>

        {{-- Brood Stock --}}
        {{-- <div class="col-md-6 mb-3">
            <label class="form-label">Brood Stock</label>
            <input type="number" name="brood_stock" class="form-control" value="{{ old('brood_stock', $hatchery_seed->brood_stock) }}">
        </div> --}}

        {{-- Count --}}
        <div class="col-md-6 mb-3">
            <label class="form-label">Count</label>
            <input type="number" name="count" class="form-control" value="{{ old('count', $hatchery_seed->count) }}">
        </div>

        {{-- Price per Piece --}}
        <div class="col-md-6 mb-3">
            <label class="form-label">Price per Piece (₹)</label>
            <input type="number" step="0.01" name="price_per_piece" class="form-control" value="{{ old('price_per_piece', $hatchery_seed->price_per_piece) }}">
        </div>

        {{-- Stock Available Date --}}
        <div class="col-md-6 mb-3">
            <label class="form-label">Stock Available Date</label>
            <input type="date" name="stock_available_date" class="form-control" value="{{ old('stock_available_date', $hatchery_seed->stock_available_date) }}">
        </div>

        {{-- Packing Start Date --}}
        <div class="col-md-6 mb-3">
            <label class="form-label">Packing Start Date</label>
            <input type="date" name="packing_start_date" class="form-control" value="{{ old('packing_start_date', $hatchery_seed->packing_start_date) }}">
        </div>

        {{-- Expire Date --}}
        <div class="col-md-6 mb-3">
            <label class="form-label">Expire Date</label>
            <input type="date" name="expire_date" class="form-control" value="{{ old('expire_date', $hatchery_seed->expire_date) }}">
        </div>

        {{-- Delivery Location --}}
        {{-- <div class="col-md-6 mb-3">
            <label class="form-label">Delivery Location</label>
            <input type="text" name="delivery_location" class="form-control" value="{{ old('delivery_location', $hatchery_seed->delivery_location) }}">
        </div> --}}

        {{-- Pincode --}}
        <div class="col-md-6 mb-3">
            <label class="form-label">Pincode</label>
            <input type="text" name="pincode" class="form-control" value="{{ old('pincode', $hatchery_seed->pincode) }}">
        </div>

        {{-- FilePond Image --}}
        <div class="col-md-6 mb-3">
            <label class="form-label">Upload Image</label>
            <input type="file"
                   name="image_path"
                   class="filepond"
                   accept="image/png, image/jpeg"
                   data-files='@json(
                       !empty($hatchery_seed->image_path) && file_exists(public_path($hatchery_seed->image_path))
                       ? [
                           [
                               "source" => asset($hatchery_seed->image_path),
                               "options" => ["type" => "local"]
                           ]
                       ]
                       : []
                   )'>
            @error('image_path')
                <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
            @enderror
        </div>

        {{-- Description --}}
        <div class="col-md-12 mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3">{{ old('description', $hatchery_seed->description) }}</textarea>
        </div>

        {{-- Submit --}}
        <div class="col-md-12">
            <button type="submit" class="btn btn-primary">Update Hatchery Seed</button>
        </div>
    </div>
</form>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection
