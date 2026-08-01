@extends('admin.layouts.main')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">Edit Market Price</h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('market_prices.index') }}">Today Price List</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit</li>
            </ol>
        </nav>
    </div>
    <div class="card">
        <div class="card-body">
            <form action="{{ route('market_prices.update', $price->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-control select2" data-placeholder="Select Category" required>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}"
                                    {{ old('category_id', $price->category_id) == $category->id ? 'selected' : '' }}>
                                    {{ $category->category_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('category_id')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Location</label>
                        <select name="location_id" class="form-control select2" data-placeholder="Select Location" required>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}"
                                    {{ old('location_id', $price->location_id) == $location->id ? 'selected' : '' }}>
                                    {{ $location->location_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('location_id')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="row mb-3">

                    <div class="col-md-3">
                        <label class="form-label">Size (Count)</label>
                        <input type="text" name="size" class="form-control"
                               value="{{ old('size', $price->size) }}" required>
                        @error('size')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Price (₹)</label>
                        <input type="number" name="price" class="form-control"
                               value="{{ old('price', $price->price) }}" step="0.01" required>
                        @error('price')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Priority</label>
                        <input type="number" name="priority" class="form-control"
                               value="{{ old('priority', $price->priority) }}" min="1" placeholder="1, 2, 3...">
                        @error('priority')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-primary">Update</button>
                    <a href="{{ route('market_prices.index') }}" class="btn btn-light">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Select2 CDN (include only once in your layout if possible) -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2').select2({
        width: '100%',
        placeholder: function(){
            return $(this).data('placeholder');
        }
    });
});
</script>
@endsection
