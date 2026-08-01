@extends('admin.layouts.main')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title d-flex align-items-center">
            <i class="fas fa-bullhorn mr-2"></i> Hatchery Posts
        </h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin') }}"><i class="fas fa-home mr-1"></i> Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Hatchery Posts</li>
            </ol>
        </nav>
    </div>

    @include('flash_msg')

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="card-title mb-0">All Posts</h4>
                <a href="{{ route('hatchery-updates.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus-circle mr-1"></i> Add Post
                </a>
            </div>

            <div class="table-responsive">
                <table id="order-listing" class="table table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Media</th>
                            <th>Description</th>
                            <th>Hashtags</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($posts as $post)
                        <tr>
                            <td class="font-weight-bold text-primary">#{{ $post->id }}</td>
                            <td>{{ $post->title }}</td>
                            <td>
                                @if($post->media_path)
                                    @if($post->media_type === 'video')
                                        <video width="80" controls>
                                            <source src="{{ asset($post->media_path) }}" type="video/mp4">
                                        </video>
                                    @else
                                        <img src="{{ asset($post->media_path) }}" class="img-thumbnail" style="width: 80px;">
                                    @endif
                                @else
                                    <span class="badge badge-secondary">No Media</span>
                                @endif
                            </td>
                            <td>{!! Str::limit(strip_tags($post->description), 60) !!}</td>
                            <td>
                                @if($post->hashtags)
                                    @php
                                        $tags = is_array($post->hashtags) ? $post->hashtags : explode(',', $post->hashtags);
                                    @endphp
                                    @foreach($tags as $tag)
                                        <span class="badge badge-info">#{{ trim($tag) }}</span>
                                    @endforeach
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $post->is_active ? 'badge-success' : 'badge-danger' }}">
                                    {{ $post->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex">
                                    <a href="{{ route('hatchery-updates.edit', $post->id) }}"
                                       class="btn btn-sm btn-primary btn-action ml-1"
                                       title="Edit Post">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="{{ route('hatchery-updates.destroy', $post->id) }}"
                                          method="POST"
                                          class="d-inline ml-1 delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="btn btn-sm btn-danger btn-action btn-delete"
                                                title="Delete Post">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
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

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    // Delete confirmation
    $(document).on('click', '.btn-delete', function(e) {
        e.preventDefault();
        var form = $(this).closest('form');
        Swal.fire({
            title: 'Delete this post?',
            text: "This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
});
</script>
@endpush
