@extends('layouts.dashboard')

@section('title', 'Purchase Requests')
@section('page_heading', 'HR')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Purchase Requests</h1>
        <p>Every purchase goes to an administrator for approval before anything is bought.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        New Request
    </button>
</section>

@include('dashboards.hr.tabs', ['current' => 'admin.hr.purchases'])

<section class="dash-panel" data-purchases data-datatable-url="{{ route('erp.purchase-requests.datatable') }}" data-resource-url="{{ route('erp.purchase-requests.index') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Requests</h2>
            <p>Only a request still waiting for approval can be edited.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="">All statuses</option>
                    @foreach (\App\Models\PurchaseRequest::STATUSES as $status)
                        <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="dash-select">
                <span class="dash-visually-hidden">Department</span>
                <select data-filter="department_id">
                    <option value="">All departments</option>
                    @foreach ($data['departments'] as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Request</th>
                    <th class="dash-hide-md">Department</th>
                    <th>Estimate</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="purchaseModal" aria-labelledby="purchaseModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'inbox'])</span>
            <div>
                <h3 id="purchaseModalTitle" data-modal-title>New purchase request</h3>
                <p>Prices are estimates in rupees; the administrator sees the total.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid dash-form-grid-3">
            @include('dashboards.partials.select', ['name' => 'department_id', 'label' => 'Department', 'options' => $data['departments']])
            @include('dashboards.partials.field', ['name' => 'request_date', 'label' => 'Request date', 'type' => 'date'])
            @include('dashboards.partials.field', ['name' => 'needed_by', 'label' => 'Needed by', 'type' => 'date'])
            @include('dashboards.partials.field', ['name' => 'purpose', 'label' => 'Purpose', 'type' => 'textarea', 'required' => true, 'wide' => true])
        </div>

        <div class="dash-line-items">
            <h4 class="dash-form-section">Items</h4>
            <div data-lines></div>
            <button type="button" class="dash-btn dash-btn-ghost dash-btn-sm" data-add-line>@include('partials.dashboard-icon', ['name' => 'plus']) Add item</button>
            <p class="dash-line-total">Estimated total: <strong data-total>Rs 0.00</strong></p>
            <p class="dash-field-error" data-error-for="items" hidden></p>
        </div>

        <template data-line-template>
            <div class="dash-line-item dash-line-item-purchase" data-line>
                <input type="text" maxlength="255" placeholder="Item" data-line-field="item_name" aria-label="Item">
                <select data-line-field="category_id" aria-label="Category">
                    <option value="">Category…</option>
                    @foreach ($data['lookups']['purchase_category'] as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                <input type="number" min="0.001" step="0.001" placeholder="Qty" data-line-field="quantity" aria-label="Quantity">
                <select data-line-field="unit_id" aria-label="Unit">
                    <option value="">Unit…</option>
                    @foreach ($data['lookups']['unit_of_measure'] as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                    @endforeach
                </select>
                <input type="number" min="0" step="0.01" placeholder="Unit price" data-line-field="estimated_unit_price" aria-label="Unit price">
                <button type="button" class="dash-icon-btn" data-remove-line aria-label="Remove item">@include('partials.dashboard-icon', ['name' => 'close'])</button>
            </div>
        </template>

        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Send for Approval</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-purchases.js') }}?v={{ filemtime(public_path('js/admin-purchases.js')) }}" defer></script>
@endsection
