// Initialize DataTable with options
function initDataTable(tableId, options = {}) {
    const defaults = {
        paging: false,
        searching: true,
        info: false,
        ordering: false,
        responsive: true,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "No entries found",
            infoFiltered: "(filtered from _MAX_ total entries)",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        },
        columnDefs: [
            { width: "15%", targets: 5 } // Default action column width
        ]
    };

    // Merge default options with custom options
    const config = { ...defaults, ...options };
    
    // Initialize and return the DataTable instance
    return $(tableId).DataTable(config);
}

// Show toast notification
function showToast(icon, message) {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    Toast.fire({
        icon: icon,
        title: message
    });
}

// Initialize clipboard
function initClipboard(selector = '.copy-btn') {
    return new ClipboardJS(selector);
}

// Initialize DataTable when document is ready
$(document).ready(function() {
    // Auto-initialize data-tables with data-datatable attribute
    $('[data-datatable]').each(function() {
        const tableId = '#' + $(this).attr('id');
        const options = $(this).data('datatable-options') || {};
        initDataTable(tableId, options);
    });
});
