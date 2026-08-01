@extends('admin.layouts.main')

@section('content')

{{-- Move this to the top of your file --}}
@section('page_title', 'Site Settings')
    <div class="content-wrapper">
          <div class="page-header">
            <h3 class="page-title">
                Site Settings
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Site Settings</li>
                </ol>
            </nav>
          </div>
          <div class="row">

<div class="col-12 grid-margin stretch-card">
  <div class="card">
    <div class="card-body">
      <h4 class="card-title">Edit Site Settings</h4>

      <form class="forms-sample" method="POST" action="{{ route('site-settings.update', $data->id) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <p class="card-description">General Info</p>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Site Name</label>
            <input type="text" name="site_name" class="form-control" value="{{ old('site_name', $data->site_name) }}">
            @error('site_name')
              <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
            @enderror
          </div>

          <div class="col-md-6">
            <label class="form-label">Address</label>
            <input type="text" name="address" class="form-control" value="{{ old('address', $data->address) }}">
            @error('address')
              <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
            @enderror
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3">{{ old('description', $data->description) }}</textarea>
            @error('description')
              <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
            @enderror
          </div>

          <div class="col-md-6">
            <label class="form-label">Iframe</label>
            <input type="text" name="iframe" class="form-control" value="{{ old('iframe', $data->iframe) }}">
            @error('iframe')
              <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
            @enderror
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Iframe 2</label>
            <input type="text" name="iframe2" class="form-control" value="{{ old('iframe2', $data->iframe2) }}">
            @error('iframe2')
              <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
            @enderror
          </div>

          <div class="col-md-6">
            <label class="form-label">Mobile</label>
            <input type="text" name="mobile" class="form-control" value="{{ old('mobile', $data->mobile) }}">
            @error('mobile')
              <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
            @enderror
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Mobile 2</label>
            <input type="text" name="mobile2" class="form-control" value="{{ old('mobile2', $data->mobile2) }}">
            @error('mobile2')
              <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
            @enderror
          </div>

          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="{{ old('email', $data->email) }}">
            @error('email')
              <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
            @enderror
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Email 2</label>
            <input type="email" name="email2" class="form-control" value="{{ old('email2', $data->email2) }}">
            @error('email2')
              <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
            @enderror
          </div>
        </div>

        <p class="card-description mt-4">Images & Logos</p>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Logo</label>
            <input type="file" name="logo" class="form-control">
            @if($data->logo)
              <img src="{{ asset('site_settings/' . $data->logo) }}" class="img-thumbnail mt-2" width="100">
            @endif
            @error('logo')
              <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
            @enderror
          </div>

          <div class="col-md-6">
            <label class="form-label">Favicon</label>
            <input type="file" name="favicon" class="form-control">
            @if($data->favicon)
              <img src="{{ asset('site_settings/' . $data->favicon) }}" class="img-thumbnail mt-2" width="50">
            @endif
            @error('favicon')
              <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
            @enderror
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Footer Logo</label>
            <input type="file" name="footer_logo" class="form-control">
            @if($data->footer_logo)
              <img src="{{ asset('site_settings/' . $data->footer_logo) }}" class="img-thumbnail mt-2" width="100">
            @endif
            @error('footer_logo')
              <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
            @enderror
          </div>

          <div class="col-md-6">
            <label class="form-label">Home Page Banner</label>
            <input type="file" name="home_page_banner" class="form-control">
            @if($data->home_page_banner)
              <img src="{{ asset('site_settings/' . $data->home_page_banner) }}" class="img-thumbnail mt-2" width="150">
            @endif
            @error('home_page_banner')
              <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
            @enderror
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Header Image</label>
            <input type="file" name="header_image" class="form-control">
            @if($data->header_image)
              <img src="{{ asset('site_settings/' . $data->header_image) }}" class="img-thumbnail mt-2" width="150">
            @endif
            @error('header_image')
              <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
            @enderror
          </div>
        </div>

        <div class="text-end mt-4">
          <button type="submit" class="btn btn-primary me-2">Update</button>
          {{-- <a href="{{ route('admin') }}" class="btn btn-light">Cancel</a> --}}
        </div>
      </form>
    </div>
  </div>
</div>










          </div>
        </div>
@endsection

