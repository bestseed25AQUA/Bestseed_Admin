@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">Site Settings</div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if($settings)
                        <div class="table-responsive">
                            <table class="table">
                                <tr>
                                    <th>Site Name</th>
                                    <td>{{ $settings->site_name }}</td>
                                </tr>
                                <tr>
                                    <th>Email</th>
                                    <td>{{ $settings->email }}</td>
                                </tr>
                                <tr>
                                    <th>Phone</th>
                                    <td>{{ $settings->mobile }}</td>
                                </tr>
                                <!-- Add more fields as needed -->
                            </table>
                        </div>
                        <a href="{{ route('admin.site-settings.edit', $settings->id) }}" class="btn btn-primary">Edit Settings</a>
                    @else
                        <p>No settings found. <a href="{{ route('admin.site-settings.create') }}">Create Settings</a></p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
