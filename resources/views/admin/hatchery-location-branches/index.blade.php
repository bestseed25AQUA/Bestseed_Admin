@extends('admin.layouts.main')

@section('page_title', 'Branches')

@section('content')
<div class="content-wrapper">

    {{-- Page Header --}}
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <h3 class="page-title">Branches of {{ $location->location_name }}</h3>

        {{-- Back Button --}}
        {{-- <a href="{{ route('hatchery-locations.index') }}" class="btn btn-secondary">
            ← Back
        </a> --}}
    </div>

    {{-- Card Box --}}
    <div class="card">
        <div class="card-body">

            {{-- Back + Add Branch Buttons ROW --}}
            <div class="d-flex justify-content-between align-items-center mb-3">

                {{-- Back Button --}}
                <a href="{{ route('hatchery-locations.index') }}" class="btn btn-light shadow-sm">
                    ← Back
                </a>

                {{-- Add Branch Button --}}
                <a href="{{ route('location.branches.create', $location->id) }}" class="btn btn-primary">
                    + Add Branch
                </a>

            </div>

            {{-- Table --}}
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Branch Name</th>
                            <th>Address</th>
                            <th style="width:120px;">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($branches as $key => $branch)
                        <tr>
                            <td>{{ $key + 1 }}</td>
                            <td>{{ $branch->branch_name }}</td>
                            <td>{{ $branch->address ?? 'N/A' }}</td>

                            <td>

                                {{-- EDIT ICON --}}
                                <a href="{{ route('location.branches.edit', $branch->id) }}"
                                   class="btn btn-sm btn-info"
                                   title="Edit"
                                   style="padding:6px 10px;">
                                    <i class="fas fa-edit"></i>
                                </a>

                                {{-- DELETE ICON --}}
                                <form action="{{ route('location.branches.delete', $branch->id) }}"
                                      method="POST"
                                      class="d-inline"
                                      onsubmit="return confirm('Are you sure you want to delete this branch?');">

                                    @csrf
                                    @method('DELETE')

                                    <button type="submit"
                                            class="btn btn-sm btn-danger"
                                            title="Delete"
                                            style="padding:6px 10px;">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>

                                </form>

                            </td>
                        </tr>
                        @endforeach
                    </tbody>

                </table>
            </div>

        </div>
    </div>

</div>
@endsection








{{-- @extends('admin.layouts.main')

@section('page_title', 'Branches')

@section('content')
<div class="content-wrapper">

    <h3 class="page-title">Branches of {{ $location->location_name }}</h3>

    <a href="{{ route('location.branches.create', $location->id) }}" class="btn btn-primary mb-3">
        + Add Branch
    </a>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Branch Name</th>
                <th>Address</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($branches as $key => $branch)
                <tr>
                    <td>{{ $key+1 }}</td>
                    <td>{{ $branch->branch_name }}</td>
                    <td>{{ $branch->address ?? 'N/A' }}</td>
                    <td>
                        <a href="{{ route('location.branches.edit', $branch->id) }}" class="btn btn-sm btn-info">Edit</a>

                        <form action="{{ route('location.branches.delete', $branch->id) }}" method="post" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-danger">Delete</button>
                        </form>

                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

</div>
@endsection --}}
