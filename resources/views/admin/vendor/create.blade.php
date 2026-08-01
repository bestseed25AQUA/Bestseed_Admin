@extends('admin.layouts.main')

@section('content')

{{-- Move this to the top of your file --}}
@section('page_title', 'Vendor')
  {{-- <div class="main-panel"> --}}
    <div class="content-wrapper">
          <div class="page-header">
            <h3 class="page-title">
                Vendor 
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Vendor</li>
                </ol>
            </nav>
          </div>
          <div class="row">

<div class="col-12 grid-margin stretch-card">
  <div class="card">
    <div class="card-body">
      <h4 class="card-title">Create Vendor</h4>
 @include('flash_msg')
      <form class="forms-sample" method="POST" action="{{ route('vendors.store') }}" enctype="multipart/form-data">
        @csrf
        {{-- @method('PUT') --}}
{{-- <p class="card-description mt-4">User Info</p> --}}
<div class="row mb-3">
    <div class="col-md-6">
        <label class="form-label">Name</label>
        <input type="text" name="user_name" class="form-control" value="{{ old('user_name') }}">
        @error('user_name')
            <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
        @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Mobile Number</label>
        <input type="text" name="user_mobile" class="form-control" value="{{ old('user_mobile') }}">
        @error('user_mobile')
            <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
        @enderror
    </div>
</div>

{{-- <div class="row mb-3">
    <div class="col-md-6">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control">
        @error('password')
            <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
        @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Confirm Password</label>
        <input type="password" name="password_confirmation" class="form-control">
        @error('password_confirmation')
            <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
        @enderror
    </div>
</div> --}}


        <div class="text-end mt-4">
          <button type="submit" class="btn btn-primary me-2">Create</button>
          {{-- <a href="{{ route('admin') }}" class="btn btn-light">Cancel</a> --}}
        </div>
      </form>
    </div>
  </div>
</div>










          </div>
        </div>
@endsection

