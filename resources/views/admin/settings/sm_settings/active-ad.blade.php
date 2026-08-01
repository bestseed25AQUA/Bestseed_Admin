@extends('admin.layouts.main')
@section('content')

    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Active Ad</li>
                    </ol>
                </div>
            @section('page_title')
                Active Ad
            @endsection
        </div>
    </div>
    <!-- end page title -->
</div>

<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-body">
                @include('flash_msg')
                <h4 class="page-title">Active Ad on pages</h4>
                <hr>
                <form action="{{ route('active-ad.update', $data->id) }}" method="post"
                    enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <div class="row">

                          <link href="{{ asset('admin_assets/sumoselector/sumoselect.min.css') }}"
                                    rel="stylesheet">
                                <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> <!-- Ensure jQuery is loaded -->
                                <script src="{{ asset('admin_assets/sumoselector/jquery.sumoselect.min.js') }}"></script>

                                <div class="col-md-12 w-100">
                                    <div class="form-group">
                                        <label for="pages" class="control-label">Features</label> <br>
                                        <select multiple id="pages" class="form-control" name="pages[]">
                                            <option value="home" {{ in_array('home', explode(',', $data->pages)) ? 'selected' : '' }}>home</option>
                                            <option value="about" {{ in_array('about', explode(',', $data->pages)) ? 'selected' : '' }}>about</option>
                                            <option value="resort" {{ in_array('resort', explode(',', $data->pages)) ? 'selected' : '' }}>resort</option>
                                            <option value="gallery" {{ in_array('gallery', explode(',', $data->pages)) ? 'selected' : '' }}>gallery</option>
                                            <option value="management" {{ in_array('management', explode(',', $data->pages)) ? 'selected' : '' }}>management</option>
                                            <option value="ongoing-project" {{ in_array('ongoing-project', explode(',', $data->pages)) ? 'selected' : '' }}>ongoing-project</option>
                                            <option value="complete-project" {{ in_array('complete-project', explode(',', $data->pages)) ? 'selected' : '' }}>complete-project</option>
                                            <option value="blog" {{ in_array('blog', explode(',', $data->pages)) ? 'selected' : '' }}>blog</option>
                                            <option value="blog-view" {{ in_array('blog-view', explode(',', $data->pages)) ? 'selected' : '' }}>blog-view</option>
                                            <option value="contact" {{ in_array('contact', explode(',', $data->pages)) ? 'selected' : '' }}>contact</option>
                                        </select>
                                    </div>
                                </div>

                                <script>
                                    $('#pages').SumoSelect({
                                        placeholder: 'Select Pages',
                                        csvDispCount: 3, // Display up to 3 items in the select box
                                        captionFormat: '{0} selected',
                                        captionFormatAllSelected: 'All selected',
                                        floatWidth: '100%', // Set dropdown width to 100% of parent container
                                    });
                                </script>
                            
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-success waves-effect waves-light">Update
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
@section('csscodes')
<link rel="stylesheet" href="{{ asset('admin_assets') . '/libs/dropify/dropify.min.css' }}">
@endsection
@section('jscodes')
<!-- Plugins js -->
<script src="{{ asset('admin_assets') . '/libs/dropify/dropify.min.js' }}"></script>

<!-- Init js-->
<script src="{{ asset('admin_assets') . '/js/pages/form-fileuploads.init.js' }}"></script>
<style>
  /* Default styles for SumoSelect */
  .SumoSelect.open>.optWrapper {
      width: 80vw;
      /* height: 130px; */
  }

  .SumoSelect>.CaptionCont {
      /* width: 1092px; */
      width: 80vw;
  }

  .SumoSelect>.optWrapper>.options {
      width: 80vw;
  }

  /* Media query for mobile view */
  @media screen and (max-width: 767px) {
      .SumoSelect.open>.optWrapper {
          width: auto;
          /* Adjust width as needed for mobile */
          height: auto;
          /* Adjust height as needed for mobile */
      }

      .SumoSelect>.CaptionCont {
          width: auto;
          /* Adjust width as needed for mobile */
      }

      .SumoSelect>.optWrapper>.options {
          width: auto;
          /* Adjust width as needed for mobile */
      }
  }
</style>
@endsection
