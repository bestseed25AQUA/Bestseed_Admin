@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-edit mr-2"></i>Edit Farm
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('farm-management.farms.index') }}">Farms</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('farm-management.farms.show', $farm->id) }}">{{ $farm->farm_name }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit</li>
                </ol>
            </nav>
        </div>

        <div class="card">
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('farm-management.farms.update', $farm->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    @include('admin.farm-management.farms._form', ['farm' => $farm, 'farmers' => $farmers])

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save Changes</button>
                        <a href="{{ route('farm-management.farms.show', $farm->id) }}" class="btn btn-light ml-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
