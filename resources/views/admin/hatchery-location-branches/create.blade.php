@extends('admin.layouts.main')

@section('page_title', 'Add Branch')

@section('content')
<div class="content-wrapper">

    {{-- Page Title --}}
    <h3 class="page-title">Add Branch ({{ $location->location_name }})</h3>

    {{-- Card Box --}}
    <div class="card mt-4"> {{-- Added margin-top to pull card down --}}
        <div class="card-body" style="padding: 30px;"> {{-- Increased padding for clean spacing --}}

            {{-- Form --}}
            <form method="POST" action="{{ route('location.branches.store', $location->id) }}">
                @csrf

                {{-- Branch Name --}}
                <div class="mb-4">
                    <label class="form-label">Branch Name</label>
                    <input type="text" name="branch_name" class="form-control" required>
                </div>

                {{-- Address --}}
                <div class="mb-4">
                    <label class="form-label">Address (optional)</label>
                    <textarea name="address" class="form-control"></textarea>
                </div>

                {{-- Buttons --}}
                <div class="mt-4 d-flex" style="gap: 15px;"> {{-- Increased gap between buttons --}}
                    <button type="submit" class="btn btn-success px-4">Create</button>

                    <a href="{{ route('location.branches.index', $location->id) }}" 
                       class="btn btn-secondary px-4">
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

    <h3 class="page-title">Add Branch ({{ $location->location_name }})</h3>

    <form method="POST" action="{{ route('location.branches.store', $location->id) }}">
        @csrf

        <div class="mb-3">
            <label>Branch Name</label>
            <input type="text" name="branch_name" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Address (optional)</label>
            <textarea name="address" class="form-control"></textarea>
        </div>

        <button type="submit" class="btn btn-success">Save</button>
        <a href="{{ route('location.branches.index', $location->id) }}" class="btn btn-secondary">Back</a>
    </form>

</div>
@endsection --}}
