@csrf

<div class="row">
    <div class="col-md-6 form-group">
        <label for="label">Package name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="label" name="label" required
               value="{{ old('label', $plan->label) }}" placeholder="e.g. 3 Months">
        <small class="text-muted">What the farmer sees. "3 Months", "Season Pack".</small>
    </div>

    <div class="col-md-3 form-group">
        <label for="months">Months <span class="text-danger">*</span></label>
        <input type="number" min="1" max="120" class="form-control" id="months" name="months" required
               value="{{ old('months', $plan->months) }}">
        <small class="text-muted">How long it runs.</small>
    </div>

    <div class="col-md-3 form-group">
        <label for="farm_limit">Farms allowed <span class="text-danger">*</span></label>
        <input type="number" min="0" max="500" class="form-control" id="farm_limit" name="farm_limit" required
               value="{{ old('farm_limit', $plan->farm_limit) }}">
        {{-- Stated plainly, because "allowed" could be read as a cap rather
             than a grant — and it is the one number this whole feature turns on. --}}
        <small class="text-muted">
            How many NEW farms this package lets them create while it runs.
            Enter <strong>0</strong> for an access-only package: it makes the
            farms they already have usable again without adding more.
        </small>
    </div>

    <div class="col-md-4 form-group">
        <label for="amount">Price ({{ $currency }}) <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0" class="form-control" id="amount" name="amount" required
               value="{{ old('amount', $plan->amount) }}">
    </div>

    <div class="col-md-4 form-group">
        <label for="reminders">Warn before expiry (days)</label>
        <input type="text" class="form-control" id="reminders" name="reminders"
               value="{{ old('reminders', is_array($plan->reminders) ? implode(', ', $plan->reminders) : '') }}"
               placeholder="30, 15, 7, 3, 1">
        <small class="text-muted">
            Largest first. The first number is when this package starts showing
            as expiring soon. Leave blank for sensible defaults.
        </small>
    </div>

    <div class="col-md-4 form-group">
        <label for="sort_order">Order</label>
        <input type="number" min="0" max="9999" class="form-control" id="sort_order" name="sort_order"
               value="{{ old('sort_order', $plan->sort_order ?: $plan->months) }}">
        <small class="text-muted">Where it sits in the list. Low numbers first.</small>
    </div>

    <div class="col-12 form-group">
        <div class="form-check pl-4">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                   {{ old('is_active', $plan->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">
                <strong>On sale</strong>
                <small class="d-block text-muted">
                    Unticked, the package is retired — no longer offered, but
                    subscriptions already sold from it keep working.
                </small>
            </label>
        </div>
    </div>
</div>

<button type="submit" class="btn btn-primary">
    <i class="fas fa-save mr-1"></i> {{ $plan->exists ? 'Save changes' : 'Create package' }}
</button>
<a href="{{ route('subscription-plans.index') }}" class="btn btn-outline-secondary">Cancel</a>
