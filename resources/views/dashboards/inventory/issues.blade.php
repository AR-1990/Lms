@extends('layouts.dashboard')

@section('title', 'Issues & Sales')
@section('page_heading', 'Inventory')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Issues & Sales</h1>
        <p>Stock given to staff, students, departments or rooms — or sold to a student (the amount goes on their fee challan).</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) Issue Stock</button>
</section>

@include('dashboards.inventory.tabs', ['current' => 'admin.inventory.issues'])

<section class="dash-panel" data-inventory="issues" data-datatable-url="{{ url('/api/erp/stock-issues/datatable') }}" data-resource-url="{{ url('/api/erp/stock-issues') }}" data-search-url="{{ url('/api/erp/inventory/items/search') }}" data-recipients-url="{{ url('/api/erp/inventory/recipients') }}" data-can-approve="{{ auth()->user()->hasPermission('approve-requests') ? 1 : 0 }}">
    <div class="dash-panel-head">
        <div>
            <h2>Issues</h2>
            <p>Open an issue to take items back.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select"><span class="dash-visually-hidden">Type</span>
                <select data-filter="is_sale">
                    <option value="">Issues and sales</option>
                    <option value="0">Issues only</option>
                    <option value="1">Sales only</option>
                </select>
            </label>
            <label class="dash-select"><span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="">All</option>
                    <option value="issued">Issued</option>
                    <option value="partially_returned">Partly returned</option>
                    <option value="returned">Returned</option>
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Issue</th>
                    <th>To</th>
                    <th>Store</th>
                    <th>Date</th>
                    <th>Cost / sale</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="issueModal" aria-labelledby="issueModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'users'])</span>
            <div>
                <h3 id="issueModalTitle" data-modal-title>Issue stock</h3>
                <p data-modal-sub>Stock leaves the store at its average cost.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-scroll">
            <div class="dash-form-grid">
                @include('dashboards.partials.select', ['name' => 'location_id', 'label' => 'From store', 'options' => $data['stores'], 'required' => true, 'id' => 'issStore'])
                @include('dashboards.partials.field', ['name' => 'issued_on', 'label' => 'Date', 'type' => 'date', 'required' => true, 'id' => 'issDate'])
                <div class="dash-field">
                    <label for="recipientType">To</label>
                    <select id="recipientType" name="recipient_type" required>
                        <option value="employee">Employee</option>
                        <option value="student">Student</option>
                        <option value="department">Department</option>
                        <option value="location">Room / location</option>
                    </select>
                </div>
                <div class="dash-field">
                    <label for="recipientSearch">Find</label>
                    <input id="recipientSearch" type="search" placeholder="Type a name or code" autocomplete="off" data-recipient-search>
                    <input type="hidden" name="recipient_id">
                    <ul class="dash-holder-results" data-recipient-results></ul>
                    <p class="dash-field-error" data-error-for="recipient_id" hidden></p>
                </div>
                @include('dashboards.partials.field', ['name' => 'purpose', 'label' => 'Purpose', 'id' => 'issPurpose'])
                <label class="dash-switch">
                    <input type="checkbox" name="is_sale">
                    <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                    <span>Sell to the student (adds a fee challan)</span>
                </label>
                @include('dashboards.partials.field', ['name' => 'due_date', 'label' => 'Challan due date', 'type' => 'date', 'id' => 'issDue'])
                <label class="dash-switch">
                    <input type="checkbox" name="received_signature">
                    <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                    <span>Receiver signed</span>
                </label>
            </div>
            <div class="dash-lines" data-lines="issue">
                <div class="dash-lines-head">
                    <strong>Items</strong>
                    <input type="search" class="dash-input" placeholder="Search an item by name or code…" data-line-search aria-label="Search item">
                </div>
                <ul class="dash-holder-results" data-line-results></ul>
                <table class="dash-table dash-lines-table">
                    <thead><tr><th>Item</th><th>Qty</th><th>Sale price (Rs)</th><th>Return by</th><th></th></tr></thead>
                    <tbody data-line-rows><tr class="dash-muted" data-line-empty><td colspan="5">No items yet. Search above to add.</td></tr></tbody>
                </table>
                <p class="dash-field-error" data-error-for="items" hidden></p>
            </div>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions" data-modal-actions>
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Issue</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-modal-wide" id="issueView" aria-labelledby="issueViewTitle">
    <form data-return-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'users'])</span>
            <div><h3 id="issueViewTitle" data-view-title>Issue</h3><p data-view-sub></p></div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-scroll">
            <table class="dash-table dash-lines-table"><thead><tr><th>Item</th><th>Issued</th><th>Returned</th><th>Return now</th></tr></thead><tbody data-return-rows></tbody></table>
            <div class="dash-form-grid" data-return-fields>
                @include('dashboards.partials.field', ['name' => 'returned_on', 'label' => 'Returned on', 'type' => 'date', 'id' => 'retDate'])
                @include('dashboards.partials.select', ['name' => 'condition_id', 'label' => 'Condition', 'options' => $data['conditions'], 'id' => 'retCondition'])
            </div>
            <p class="dash-field-error" data-error-for="items" hidden></p>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Close</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit data-return-submit>Take Back</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-inventory.js') }}?v={{ filemtime(public_path('js/admin-inventory.js')) }}" defer></script>
@endsection
