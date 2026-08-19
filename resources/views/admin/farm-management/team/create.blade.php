@extends('admin.layouts.main')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title d-flex align-items-center">
                <i class="fas fa-user-plus mr-2"></i>Add Manager / Partner
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('farm-management.team.index') }}">Managers &amp; Partners</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Add</li>
                </ol>
            </nav>
        </div>

        <div class="card">
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <div class="alert alert-info">
                    <i class="fas fa-info-circle mr-1"></i>
                    Adding someone here records their access. To let them actually open the farm in the
                    app, issue an <a href="{{ route('farm-management.grants.index') }}">access code</a>
                    for them to scan.
                </div>

                <form action="{{ route('farm-management.team.store') }}" method="POST">
                    @csrf
                    @include('admin.farm-management.team._form', ['member' => null])

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Add</button>
                        <a href="{{ route('farm-management.team.index') }}" class="btn btn-light ml-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
