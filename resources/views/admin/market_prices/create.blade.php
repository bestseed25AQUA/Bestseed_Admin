@extends('admin.layouts.main')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">Add Today Price</h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('market_prices.index') }}">Today Price List</a></li>
                <li class="breadcrumb-item active" aria-current="page">Create</li>
            </ol>
        </nav>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('market_prices.store') }}" method="POST">
                @csrf

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Category</label>
                        <select id="categorySelect" class="form-control select2" multiple data-placeholder="Select Category">
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Location</label>
                        <select id="locationSelect" class="form-control select2" multiple data-placeholder="Select Location">
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->location_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Price Table -->
                <div class="table-responsive">
                    <table class="table table-bordered" id="priceTable">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Location</th>
                                <th>Size (Count)</th>
                                <th>Price (₹)</th>
                                <th>Priority</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dynamic rows will appear here -->
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <a href="{{ route('market_prices.index') }}" class="btn btn-light">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Select2 CDN -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Dynamic Table Generation Script -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const categorySelect = document.getElementById('categorySelect');
    const locationSelect = document.getElementById('locationSelect');
    const priceTableBody = document.getElementById('priceTable').querySelector('tbody');

    function getSelectedOptions(selectElement) {
        // Use .val() for Select2 compatibility (returns array of selected values)
        return Array.from(selectElement.selectedOptions).map(option => ({
            id: option.value,
            text: option.text
        }));
    }

    function generateTable() {
        priceTableBody.innerHTML = '';
        const categories = getSelectedOptions(categorySelect);
        const locations = getSelectedOptions(locationSelect);
        let i = 0;
        categories.forEach(category => {
            locations.forEach(location => {
                const row = `
                    <tr>
                        <td>
                            ${category.text}
                            <input type="hidden" name="entries[${i}][category_id]" value="${category.id}">
                        </td>
                        <td>
                            ${location.text}
                            <input type="hidden" name="entries[${i}][location_id]" value="${location.id}">
                        </td>
                        <td>
                            <input type="text" name="entries[${i}][size]" class="form-control" required>
                        </td>
                        <td>
                            <input type="number" name="entries[${i}][price]" class="form-control" step="0.01" required>
                        </td>
                        <td>
                            <input type="number" name="entries[${i}][priority]" class="form-control" min="1" placeholder="1, 2, 3...">
                        </td>
                    </tr>
                `;
                priceTableBody.insertAdjacentHTML('beforeend', row);
                i++;
            });
        });
    }

    // Update table on selection changes
    categorySelect.addEventListener('change', generateTable);
    locationSelect.addEventListener('change', generateTable);

    // Re-initialize table on page load
    generateTable();
});
</script>

@endsection
