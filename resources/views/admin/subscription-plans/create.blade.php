@extends('admin.layouts.main')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title d-flex align-items-center">
            <i class="fas fa-box-open mr-2"></i> New Package
        </h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('subscription-plans.index') }}">Packages</a></li>
                <li class="breadcrumb-item active" aria-current="page">New Package</li>
            </ol>
        </nav>
    </div>

    @include('admin.partials.flash')

    <div class="card">
        <div class="card-body">
            @if (isset($sold) && $sold > 0)
                <div class="alert alert-info py-2 px-3 small">
                    <i class="fas fa-info-circle mr-1"></i>
                    {{ $sold }} subscription(s) have been sold from this package.
                    Changing it here affects only <strong>future</strong> sales — each one
                    already sold keeps the farms, price and length it was sold under.
                </div>
            @endif

            <form action="{{ route('subscription-plans.store') }}" method="POST">
                
                @include('admin.subscription-plans._form')
            </form>
        </div>
    </div>
</div>
@endsection
