{{-- Flash messages, as the toast the rest of the panel uses.

     Extracted so a new screen gets the same behaviour without copying thirty
     lines of Swal config into it. --}}
@php
    // `$errors` is bound by session middleware. Guarded so this partial can
    // also be rendered outside a request — a console preview, a test — without
    // dying on a null bag.
    $flashErrors = ($errors ?? null) && $errors->any() ? $errors->all() : [];
@endphp

@if (session('success') || session('error') || $flashErrors)
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                @if (session('success'))
                    Swal.fire({
                        toast: true, position: 'top-right', icon: 'success',
                        title: "{{ addslashes(session('success')) }}",
                        showConfirmButton: false, timer: 3500, timerProgressBar: true
                    });
                @endif

                @if (session('error'))
                    Swal.fire({
                        toast: true, position: 'top-right', icon: 'error',
                        title: "{{ addslashes(session('error')) }}",
                        showConfirmButton: false, timer: 4500, timerProgressBar: true
                    });
                @endif
            });
        </script>
    @endpush

    {{-- Validation errors stay on the page rather than in a toast: they name
         fields the admin has to go back and fix, and a toast that vanishes in
         four seconds takes the list with it. --}}
    @if ($flashErrors)
        <div class="alert alert-danger">
            <strong>Please check the form.</strong>
            <ul class="mb-0 mt-1 pl-3">
                @foreach ($flashErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
@endif
