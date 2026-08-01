@extends('admin.layouts.main')

@section('page_title', 'Edit User Profile')

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">Edit Profile</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('user_profile.index') }}">Users</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit Profile</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Update Profile Info</h4>
                        @include('flash_msg')

                        <form method="POST" action="{{ route('user_profile.update', $user->id) }}"
                            enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            <div class="row">
                                {{-- First Name --}}
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-control"
                                        value="{{ old('first_name', $user->first_name) }}">
                                    @error('first_name')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>

                                {{-- Last Name --}}
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-control"
                                        value="{{ old('last_name', $user->last_name) }}">
                                    @error('last_name')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Mobile</label>
                                    <input type="text" name="mobile" class="form-control"
                                        value="{{ old('mobile', $user->mobile) }}">
                                    @error('mobile')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>



                                {{-- Language --}}
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Language</label>
                                    <select name="language" class="form-control">
                                        @foreach ([
            'en' => 'English',
            'hi' => 'Hindi',
            'te' => 'Telugu',
            'ta' => 'Tamil',
            'kn' => 'Kannada',
            'ml' => 'Malayalam',
            'mr' => 'Marathi',
            'gu' => 'Gujarati',
            'pa' => 'Punjabi',
            'bn' => 'Bengali',
            'or' => 'Odia',
            'ur' => 'Urdu',
        ] as $key => $lang)
                                            <option value="{{ $key }}"
                                                {{ $user->language == $key ? 'selected' : '' }}>
                                                {{ $lang }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('language')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Profile Image</label>
                                    <input type="file" name="profile_image" class="filepond"
                                        data-existing="{{ $user->profile_image ?? '' }}" accept="image/*">
                                    @error('profile_image')
                                        <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>



                                {{-- Submit Button --}}
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary">Update Profile</button>
                                </div>
                            </div>
                        </form>


                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
