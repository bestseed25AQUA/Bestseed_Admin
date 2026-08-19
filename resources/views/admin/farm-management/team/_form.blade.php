{{-- Fields shared by the add and edit screens for managers/partners. --}}
<div class="row">
    <div class="col-md-6 form-group">
        <label for="farm_id">Farm <span class="text-danger">*</span></label>
        <select class="form-control" id="farm_id" name="farm_id" required>
            <option value="">-- choose a farm --</option>
            @foreach ($farms as $farm)
                <option value="{{ $farm->id }}"
                    {{ (string) old('farm_id', $member->farm_id ?? ($selectedFarm ?? '')) === (string) $farm->id ? 'selected' : '' }}>
                    {{ $farm->farm_name }}
                    @if ($farm->farmer)
                        — owner: {{ trim($farm->farmer->first_name . ' ' . $farm->farmer->last_name) }}
                    @endif
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6 form-group">
        <label for="is_partner">Role <span class="text-danger">*</span></label>
        @php
            // Existing row wins; otherwise fall back to the ?role= query the
            // "Add" button on a farm page passes through.
            $currentRole = $member->is_partner ?? (($selectedRole ?? 'manager') === 'partner' ? 1 : 0);
            $currentRole = (string) old('is_partner', $currentRole);
        @endphp
        <select class="form-control" id="is_partner" name="is_partner" required>
            <option value="0" {{ $currentRole === '0' ? 'selected' : '' }}>Manager</option>
            <option value="1" {{ $currentRole === '1' ? 'selected' : '' }}>Partner</option>
        </select>
    </div>

    <div class="col-md-6 form-group">
        <label for="name">Name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="name" name="name"
            value="{{ old('name', $member->name ?? '') }}" required>
    </div>

    <div class="col-md-6 form-group">
        <label for="phone">Phone <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="phone" name="phone" maxlength="10"
            value="{{ old('phone', $member->phone ?? '') }}" required>
        <small class="text-muted">10 digits. The same number can be on several farms, but only once per farm.</small>
    </div>
</div>

<hr>
<label class="d-block"><strong>What they may do on this farm</strong></label>
@include('admin.farm-management.partials._permissions', ['values' => $member ?? ['view_access' => 1]])
