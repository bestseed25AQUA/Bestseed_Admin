{{--
    Shared DataTable + SweetAlert wiring for the Farm Management screens.

    Every list in this module uses the same table id (#data-table), the same
    delete-confirmation pattern (a .confirm-form wrapper with a .confirm-btn
    trigger), and the same flash-message toasts — so it lives here once.

    Usage:  @include('admin.farm-management.partials._table-scripts', ['entity' => 'farms'])
--}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

<script>
    $(document).ready(function() {
        if ($('#data-table').length) {
            $('#data-table').DataTable({
                responsive: true,
                pageLength: 10,
                order: [[0, 'desc']],
                language: {
                    search: "",
                    searchPlaceholder: "Search {{ $entity ?? 'records' }}...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "No entries found",
                    emptyTable: "No {{ $entity ?? 'records' }} found",
                    paginate: { first: "<<", last: ">>", next: ">", previous: "<" }
                },
                columnDefs: [{ targets: -1, orderable: false, className: 'text-center' }],
                initComplete: function() {
                    $('.dataTables_filter input').addClass('form-control form-control-sm');
                }
            });
        }

        // Any button inside a .confirm-form asks first, then submits.
        $(document).on('click', '.confirm-btn', function(e) {
            e.preventDefault();
            const form = $(this).closest('form');
            const message = $(this).data('confirm') || 'This action cannot be undone.';

            Swal.fire({
                title: 'Are you sure?',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, continue'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });

        @if (session('success'))
            Swal.fire({
                toast: true, position: 'top-right', icon: 'success',
                title: "{{ addslashes(session('success')) }}",
                showConfirmButton: false, timer: 3500, timerProgressBar: true
            });
        @endif

        @if (session('error') || session('danger'))
            Swal.fire({
                toast: true, position: 'top-right', icon: 'error',
                title: "{{ addslashes(session('error') ?? session('danger')) }}",
                showConfirmButton: false, timer: 4500, timerProgressBar: true
            });
        @endif
    });
</script>
