@extends('admin.layouts.main')

@section('page_title', 'Edit Branch')

@section('content')
<div class="content-wrapper">

    {{-- Page Title --}}
    <h3 class="page-title">Edit Branch ({{ $location->location_name }})</h3>

    {{-- Card Box --}}
    <div class="card mt-2" style="margin-top: 10px !important;">
        <div class="card-body">

            {{-- Update Form --}}
            <form method="POST" action="{{ route('location.branches.update', $branch->id) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label">Branch Name</label>
                    <input type="text" name="branch_name" class="form-control" 
                        value="{{ $branch->branch_name }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Address (optional)</label>
                    <textarea name="address" class="form-control">{{ $branch->address }}</textarea>
                </div>

                {{-- Buttons --}}
                <div class="mt-4 d-flex" style="gap: 15px;">
                    <button type="submit" class="btn btn-primary">Update</button>

                    {{-- Cancel Button --}}
                    <a href="{{ route('location.branches.index', $location->id) }}" 
                       class="btn btn-light">
                        Cancel
                    </a>
                </div>

            </form>

        </div>
    </div>

</div>
@endsection





{{-- @extends('admin.layouts.main')

@section('content')
<div class="content-wrapper">

    <h3 class="page-title">Edit Branch ({{ $location->location_name }})</h3>

    <form method="POST" action="{{ route('location.branches.update', $branch->id) }}">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label>Branch Name</label>
            <input type="text" name="branch_name" class="form-control" value="{{ $branch->branch_name }}" required>
        </div>

        <div class="mb-3">
            <label>Address (optional)</label>
            <textarea name="address" class="form-control">{{ $branch->address }}</textarea>
        </div>

        <button type="submit" class="btn btn-primary">Update</button>
    </form>

</div>
@endsection --}}
