@extends('admin.layouts.main')

@section('content')

{{-- Move this to the top of your file --}}
@section('page_title', 'Banner')

<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <!-- Empty space or page title if needed -->
            </div>
            <div class="col-auto ms-auto">
                <div class="breadcrumb-wrapper bg-white shadow-sm px-4 py-2 border">
                    <ol class="breadcrumb mb-0 rounded-end justify-content-end">
                        <li class="breadcrumb-item">
                            <a href="{{ url('/admin') }}" class="text-decoration-none text-dark fw-bold">
                                <i class="fas fa-home me-2 text-primary"></i>Dashboard
                            </a>
                        </li>
                        <li class="breadcrumb-item active text-primary fw-semibold">SEO Settings</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div> 


<div class="page-body">
    <div class="container-xl">
        <div class="card">
            <div class="card-body">

                @include('flash_msg')

                <!-- Title & Add Button -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="fw-semibold text-dark mb-0">SEO</h4>
                    <a href="{{ route('service.create') }}" class="btn btn-success btn-sm">
                        <i class="fas fa-plus"></i> Add SEO
                    </a>
                </div>

                <!-- Table -->
                <div class="table-responsive">
                   <table id="example" class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Page Name</th>
                                <th>Title</th>
                                {{-- <th>Keywords</th> --}}
                                <th>Modified At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($seo_data as $key => $seo)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $seo->page_name }}</td>
                                    <td>{{ Str::limit($seo->title, 40) }}</td>
                                    {{-- <td>
                                        @foreach(explode(',', $seo->keywords) as $keyword)
                                            <span class="badge bg-secondary">{{ trim($keyword) }}</span>
                                        @endforeach
                                    </td> --}}
                                    <td>{{ \Carbon\Carbon::parse($seo->updated_at)->format('d M Y h:i A') }}</td>
                                    <td>
                                        <a href="{{ route('seo-settings.edit', $seo->id) }}"
                                           class="btn btn-primary btn-xs" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        <button type="button"
                                                data-value="{{ $seo->id }}"
                                                class="btn btn-danger btn-xs cdelete"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>

                                        <form id="deleteform{{ $seo->id }}"
                                              method="POST"
                                              action="{{ route('seo-settings.destroy', $seo->id) }}">
                                            @csrf
                                            @method('DELETE')
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
</div>




@endsection 

@section('csscodes')

<style>
    /* Table Styling */
    #datatable_wrapper .dataTables_length, 
    #datatable_wrapper .dataTables_filter {
        margin-bottom: 1rem;
    }

    table.dataTable thead {
        background-color: #f8f9fa;
        font-weight: 600;
    }

    table.dataTable tbody tr {
        border-bottom: 1px solid #dee2e6;
    }

    table.dataTable tbody tr:hover {
        background-color: #f1f1f1;
    }

    table.dataTable.no-footer {
        border-bottom: 1px solid #dee2e6;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 0.2em 0.8em;
        border-radius: 0.375rem;
        margin: 0 2px;
        background-color: #fff;
        border: 1px solid #dee2e6;
        color: #333;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background-color: #198754;
        color: white !important;
        border-color: #198754;
    }

    .dataTables_wrapper .dataTables_filter input {
        border-radius: 0.375rem;
    }
</style>

<link href="{{ asset('admin_assets/datatables/dataTables.bootstrap4.min.css') }}" rel="stylesheet">
<link href="{{ asset('admin_assets/datatables/responsive.bootstrap4.min.css')}}" rel="stylesheet">
@endsection
@section('jscodes')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script src="{{asset('admin_assets/datatables/jquery-3.6.0.min.js')}}"></script>
<!-- DataTables JS -->
<script src="{{asset('admin_assets/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('admin_assets/datatables/dataTables.bootstrap4.min.js')}}"></script>
<script src="{{asset('admin_assets/datatables/dataTables.responsive.min.js')}}"></script>
<script src="{{asset('admin_assets/datatables/responsive.bootstrap4.min.js')}}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


<script language="javascript">
    var table = $('#example').DataTable({
        responsive: true
    });
</script>
  <script>
      document.addEventListener('DOMContentLoaded', function () {
          document.querySelectorAll('.cdelete').forEach(button => {
              button.addEventListener('click', function () {
                  var id = this.getAttribute('data-value');
                  var row = $(this).closest('tr');
                  var form = $('#deleteform' + id);
                  Swal.fire({
                      title: "Are you sure?",
                      text: "You won't be able to revert this!",
                      icon: "warning",
                      showCancelButton: true,
                      confirmButtonColor: "#E73E08",
                      cancelButtonColor: "#6c757d",
                      confirmButtonText: "Yes, delete it!",
                      cancelButtonText: "Cancel",
                  }).then((result) => {
                      if (result.isConfirmed) {
                          $.ajax({
                              url: form.attr('action'),
                              type: 'POST',
                              data: form.serialize(),
                              success: function() {
                                  table.row(row).remove().draw(false);
                                  Swal.fire({ toast: true, position: 'top-right', icon: 'success', title: 'Deleted successfully', showConfirmButton: false, timer: 3000 });
                              },
                              error: function() {
                                  Swal.fire({ toast: true, position: 'top-right', icon: 'error', title: 'Failed to delete', showConfirmButton: false, timer: 3000 });
                              }
                          });
                      }
                  });
              });
          });
      });
  </script>
@endsection

