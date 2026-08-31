{{-- Fields shared by the farm create and edit screens. --}}
<div class="row">
    <div class="col-md-6 form-group">
        <label for="farm_name">Farm Name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="farm_name" name="farm_name"
            value="{{ old('farm_name', $farm->farm_name ?? '') }}" required>
    </div>

    <div class="col-md-6 form-group">
        <label for="farmer_id">Owner (Farmer) <span class="text-danger">*</span></label>
        <select class="form-control" id="farmer_id" name="farmer_id" required>
            <option value="">-- choose a farmer --</option>
            @foreach ($farmers as $farmer)
                <option value="{{ $farmer->id }}"
                    {{ (string) old('farmer_id', $farm->farmer_id ?? '') === (string) $farmer->id ? 'selected' : '' }}>
                    {{ trim($farmer->first_name . ' ' . $farmer->last_name) ?: 'Farmer #' . $farmer->id }}
                    — {{ $farmer->mobile }}
                </option>
            @endforeach
        </select>
        <small class="text-muted">The owner sees this farm in the app and can give others access to it.</small>
    </div>

    <div class="col-md-4 form-group">
        <label for="status">Status <span class="text-danger">*</span></label>
        @php $currentStatus = (string) old('status', $farm->status ?? 1); @endphp
        <select class="form-control" id="status" name="status" required>
            <option value="1" {{ $currentStatus === '1' ? 'selected' : '' }}>Active</option>
            <option value="0" {{ $currentStatus === '0' ? 'selected' : '' }}>Inactive</option>
        </select>
        <small class="text-muted">Inactive farms stay intact but disappear from the app.</small>
    </div>

    <div class="col-md-4 form-group">
        <label for="stocking_date">Stocking Date</label>
        <input type="date" class="form-control" id="stocking_date" name="stocking_date"
            value="{{ old('stocking_date', isset($farm->stocking_date) ? \Illuminate\Support\Carbon::parse($farm->stocking_date)->format('Y-m-d') : '') }}">
    </div>

    <div class="col-md-4 form-group">
        <label for="no_of_tanks">Number of Tanks</label>
        <input type="number" min="0" class="form-control" id="no_of_tanks" name="no_of_tanks"
            value="{{ old('no_of_tanks', $farm->no_of_tanks ?? '') }}">
        <small class="text-muted">Declared count. Actual tank rows are managed from the farm detail page.</small>
    </div>

    <div class="col-md-4 form-group">
        <label for="store">Store (feed in stock)</label>
        <input type="number" step="0.01" min="0" class="form-control" id="store" name="store"
            value="{{ old('store', $farm->store ?? '') }}">
    </div>

    <div class="col-md-4 form-group">
        <label for="low_feed_limit">Low Feed Alert Limit</label>
        <input type="number" step="0.01" min="0" class="form-control" id="low_feed_limit" name="low_feed_limit"
            value="{{ old('low_feed_limit', $farm->low_feed_limit ?? '') }}">
        <small class="text-muted">A notification fires when the store drops below this.</small>
    </div>

    {{-- Farm photos. The app shows up to two, so uploading replaces the set
         rather than appending — otherwise a new photo could land third and
         never be seen. --}}
    <div class="col-md-12 form-group">
        <label for="images">Farm Photos</label>
        <input type="file" class="form-control-file" id="images" name="images[]"
            accept="image/*" multiple>
        <small class="text-muted">Up to 2 images, 5&nbsp;MB each. Uploading replaces the current photos.</small>

        @error('images.*')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror

        @php
            $existingImages = [];
            if (isset($farm) && $farm->images && $farm->images->images) {
                $decoded = json_decode($farm->images->images, true);
                $existingImages = is_array($decoded) ? $decoded : [];
            }
        @endphp

        @if (!empty($existingImages))
            <div class="d-flex flex-wrap mt-2">
                @foreach ($existingImages as $image)
                    <img src="{{ $image }}" alt="Farm photo"
                        class="mr-2 mb-2 rounded border"
                        style="width: 120px; height: 90px; object-fit: cover;"
                        onerror="this.style.display='none'">
                @endforeach
            </div>
        @endif
    </div>
</div>
