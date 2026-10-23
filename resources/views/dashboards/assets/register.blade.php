@extends('layouts.dashboard')

@section('title', 'Assets')
@section('page_heading', 'Assets')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
@php
    $canManage = auth()->user()->hasPermission('manage-assets');
    $canAssign = auth()->user()->hasPermission('assign-assets') || $canManage;
    $canDispose = auth()->user()->hasPermission('approve-requests') && $canManage;
@endphp
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Asset Register</h1>
        <p>Every laptop, projector, desk and vehicle: where it is, who has it, its condition and its value.</p>
    </div>
    @if ($canManage)
        <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) Register Asset</button>
    @endif
</section>

@include('dashboards.assets.tabs', ['current' => 'admin.assets.index'])

<section class="dash-panel" data-assets="register" data-datatable-url="{{ url('/api/erp/assets/datatable') }}" data-resource-url="{{ url('/api/erp/assets') }}"
    data-can-manage="{{ $canManage ? 1 : 0 }}" data-can-assign="{{ $canAssign ? 1 : 0 }}" data-can-dispose="{{ $canDispose ? 1 : 0 }}">
    <div class="dash-panel-head">
        <div>
            <h2>All assets</h2>
            <p>Open an asset to hand it over, take it back, report a repair or dispose of it.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select"><span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="">All statuses</option>
                    @foreach (\App\Models\Asset::STATUSES as $status)
                        <option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="dash-select"><span class="dash-visually-hidden">Category</span>
                <select data-filter="asset_category_id">
                    <option value="">All categories</option>
                    @foreach ($data['categories'] as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                </select>
            </label>
            <label class="dash-select"><span class="dash-visually-hidden">Location</span>
                <select data-filter="location_id">
                    <option value="">All locations</option>
                    @foreach ($data['locations'] as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Asset</th>
                    <th>Category</th>
                    <th>With / where</th>
                    <th>Status</th>
                    <th class="dash-hide-md">Book value</th>
                    <th class="dash-hide-md">Bought</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

{{-- Register / edit --}}
<dialog class="dash-modal dash-form-modal dash-modal-wide" id="assetModal" aria-labelledby="assetModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'server'])</span>
            <div>
                <h3 id="assetModalTitle" data-modal-title>Register asset</h3>
                <p>Leave the tag blank to number it automatically.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid dash-form-scroll">
            @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name', 'required' => true, 'placeholder' => 'e.g. Dell Latitude 5440 Laptop', 'id' => 'assetName'])
            @include('dashboards.partials.select', ['name' => 'asset_category_id', 'label' => 'Category', 'options' => $data['categories'], 'required' => true, 'id' => 'assetCategory'])
            @include('dashboards.partials.field', ['name' => 'asset_tag', 'label' => 'Tag', 'hint' => 'automatic', 'id' => 'assetTag'])
            @include('dashboards.partials.field', ['name' => 'serial_no', 'label' => 'Serial no', 'id' => 'assetSerial'])
            @include('dashboards.partials.field', ['name' => 'brand', 'label' => 'Brand', 'id' => 'assetBrand'])
            @include('dashboards.partials.field', ['name' => 'model', 'label' => 'Model', 'id' => 'assetModel'])
            @include('dashboards.partials.select', ['name' => 'location_id', 'label' => 'Location', 'options' => $data['locations'], 'id' => 'assetLocation'])
            @include('dashboards.partials.select', ['name' => 'condition_id', 'label' => 'Condition', 'options' => $data['conditions'], 'id' => 'assetCondition'])
            @include('dashboards.partials.select', ['name' => 'vendor_id', 'label' => 'Vendor', 'options' => $data['vendors'], 'id' => 'assetVendor'])
            @include('dashboards.partials.field', ['name' => 'invoice_no', 'label' => 'Invoice no', 'id' => 'assetInvoice'])
            @include('dashboards.partials.field', ['name' => 'purchase_date', 'label' => 'Purchase date', 'type' => 'date', 'id' => 'assetBought'])
            @include('dashboards.partials.field', ['name' => 'purchase_cost', 'label' => 'Cost (Rs)', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'id' => 'assetCost'])
            @include('dashboards.partials.field', ['name' => 'warranty_expires_on', 'label' => 'Warranty until', 'type' => 'date', 'id' => 'assetWarranty'])
            @include('dashboards.partials.field', ['name' => 'depreciation_start_date', 'label' => 'Depreciate from', 'type' => 'date', 'hint' => 'purchase month', 'id' => 'assetDepFrom'])
            @include('dashboards.partials.field', ['name' => 'description', 'label' => 'Notes', 'type' => 'textarea', 'wide' => true, 'id' => 'assetNotes'])
            <div class="dash-field dash-field-wide" data-post-purchase>
                <label class="dash-switch">
                    <input type="checkbox" name="post_purchase">
                    <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                    <span>Post the purchase to the ledger (asset account ← vendor payable, or the payment method below)</span>
                </label>
            </div>
            @include('dashboards.partials.select', ['name' => 'payment_method_id', 'label' => 'Paid by', 'options' => $data['payment_methods'], 'empty' => 'On credit (vendor payable)', 'id' => 'assetPaidBy'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Asset</button>
        </div>
    </form>
</dialog>

{{-- Details with actions and history --}}
<dialog class="dash-modal dash-modal-wide" id="assetDetails" aria-labelledby="assetDetailsTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'server'])</span>
        <div>
            <h3 id="assetDetailsTitle" data-details-title>Asset</h3>
            <p data-details-subtitle></p>
        </div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-form-scroll">
        <dl class="dash-asset-facts" data-details-facts></dl>
        <div class="dash-asset-actions" data-details-actions></div>
        <h4>Hand-overs</h4>
        <ul class="dash-asset-history" data-details-assignments></ul>
        <h4>Repairs</h4>
        <ul class="dash-asset-history" data-details-repairs></ul>
        <h4>Depreciation</h4>
        <ul class="dash-asset-history" data-details-depreciation></ul>
    </div>
</dialog>

{{-- Hand over --}}
<dialog class="dash-modal dash-form-modal" id="assetAssign" aria-labelledby="assetAssignTitle">
    <form data-action-form="assign" novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'users'])</span>
            <div><h3 id="assetAssignTitle">Hand over</h3><p data-action-asset></p></div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            <div class="dash-field">
                <label for="assignType">To</label>
                <select id="assignType" name="assignee_type" required>
                    <option value="employee">Employee</option>
                    <option value="student">Student</option>
                    <option value="department">Department</option>
                    <option value="location">Room / location</option>
                </select>
            </div>
            <div class="dash-field">
                <label for="assignSearch">Find</label>
                <input id="assignSearch" type="search" placeholder="Type a name or code" autocomplete="off" data-holder-search>
                <input type="hidden" name="assignee_id">
                <ul class="dash-holder-results" data-holder-results></ul>
                <p class="dash-field-error" data-error-for="assignee_id" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'assigned_on', 'label' => 'Handed on', 'type' => 'date', 'required' => true, 'id' => 'assignOn'])
            @include('dashboards.partials.field', ['name' => 'expected_return_on', 'label' => 'Return by', 'type' => 'date', 'id' => 'assignReturnBy'])
            @include('dashboards.partials.select', ['name' => 'condition_out_id', 'label' => 'Condition', 'options' => $data['conditions'], 'id' => 'assignCondition'])
            <label class="dash-switch">
                <input type="checkbox" name="acknowledgement_signed">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Hand-over form signed</span>
            </label>
            @include('dashboards.partials.field', ['name' => 'remarks', 'label' => 'Remarks', 'type' => 'textarea', 'wide' => true, 'id' => 'assignRemarks'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>Hand Over</button>
        </div>
    </form>
</dialog>

{{-- Take back --}}
<dialog class="dash-modal dash-form-modal" id="assetReturn" aria-labelledby="assetReturnTitle">
    <form data-action-form="return" novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'arrow-left'])</span>
            <div><h3 id="assetReturnTitle">Take back</h3><p data-action-asset></p></div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'returned_on', 'label' => 'Returned on', 'type' => 'date', 'required' => true, 'id' => 'returnOn'])
            @include('dashboards.partials.select', ['name' => 'condition_in_id', 'label' => 'Condition', 'options' => $data['conditions'], 'id' => 'returnCondition'])
            @include('dashboards.partials.select', ['name' => 'location_id', 'label' => 'Put in', 'options' => $data['locations'], 'empty' => 'Same place', 'id' => 'returnLocation'])
            @include('dashboards.partials.field', ['name' => 'remarks', 'label' => 'Remarks', 'type' => 'textarea', 'wide' => true, 'id' => 'returnRemarks'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>Take Back</button>
        </div>
    </form>
</dialog>

{{-- Report repair --}}
<dialog class="dash-modal dash-form-modal" id="assetRepair" aria-labelledby="assetRepairTitle">
    <form data-action-form="repair" novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'settings'])</span>
            <div><h3 id="assetRepairTitle">Report a repair</h3><p data-action-asset></p></div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.select', ['name' => 'maintenance_type_id', 'label' => 'Type', 'options' => $data['maintenance_types'], 'id' => 'repairType'])
            @include('dashboards.partials.select', ['name' => 'vendor_id', 'label' => 'Repairer', 'options' => $data['vendors'], 'id' => 'repairVendor'])
            @include('dashboards.partials.field', ['name' => 'reported_on', 'label' => 'Reported on', 'type' => 'date', 'required' => true, 'id' => 'repairReported'])
            @include('dashboards.partials.field', ['name' => 'sent_on', 'label' => 'Sent on', 'type' => 'date', 'id' => 'repairSent'])
            @include('dashboards.partials.field', ['name' => 'description', 'label' => 'Problem', 'type' => 'textarea', 'wide' => true, 'id' => 'repairProblem'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>Report Repair</button>
        </div>
    </form>
</dialog>

{{-- Dispose --}}
<dialog class="dash-modal dash-form-modal" id="assetDispose" aria-labelledby="assetDisposeTitle">
    <form data-action-form="dispose" novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'eraser'])</span>
            <div><h3 id="assetDisposeTitle">Dispose of asset</h3><p data-action-asset></p></div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'disposed_on', 'label' => 'Date', 'type' => 'date', 'required' => true, 'id' => 'disposeOn'])
            <div class="dash-field">
                <label for="disposeMethod">How</label>
                <select id="disposeMethod" name="method" required>
                    @foreach (\App\Models\AssetDisposal::METHODS as $method)<option value="{{ $method }}">{{ ucfirst($method) }}</option>@endforeach
                </select>
            </div>
            @include('dashboards.partials.field', ['name' => 'sale_amount', 'label' => 'Sold for (Rs)', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'id' => 'disposeSale'])
            @include('dashboards.partials.select', ['name' => 'payment_method_id', 'label' => 'Money received by', 'options' => $data['payment_methods'], 'id' => 'disposePaidBy'])
            @include('dashboards.partials.field', ['name' => 'reason', 'label' => 'Reason (goes on the voucher)', 'type' => 'textarea', 'required' => true, 'wide' => true, 'id' => 'disposeReason'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-danger" data-submit>Dispose</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-assets.js') }}?v={{ filemtime(public_path('js/admin-assets.js')) }}" defer></script>
@endsection
