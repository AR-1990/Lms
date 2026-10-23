@extends('layouts.dashboard')

@section('title', 'Item Categories')
@section('page_heading', 'Inventory')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Item Categories</h1>
        <p>Each category sets where its stock, its use and its sales are posted.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) Add Category</button>
</section>

@include('dashboards.inventory.tabs', ['current' => 'admin.inventory.categories'])

<section class="dash-panel" data-inventory="categories" data-datatable-url="{{ url('/api/erp/item-categories/datatable') }}" data-resource-url="{{ url('/api/erp/item-categories') }}" data-search-url="{{ url('/api/erp/inventory/items/search') }}" data-recipients-url="{{ url('/api/erp/inventory/recipients') }}" data-can-approve="{{ auth()->user()->hasPermission('approve-requests') ? 1 : 0 }}">
    <div class="dash-panel-head">
        <div>
            <h2>Categories</h2>
            <p>Sales to students go on the fee head chosen here.</p>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Category</th>
                    <th class="dash-hide-md">Used up as</th>
                    <th>Sold under</th>
                    <th>Items</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="categoryModal" aria-labelledby="categoryModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'layers'])</span>
            <div>
                <h3 id="categoryModalTitle" data-modal-title>Add category</h3>
                <p data-modal-sub></p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-scroll">
            <div class="dash-form-grid">
                @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name', 'required' => true, 'id' => 'icName'])
                @include('dashboards.partials.select', ['name' => 'parent_id', 'label' => 'Inside', 'options' => $data['item_categories'], 'id' => 'icParent'])
                @include('dashboards.partials.select', ['name' => 'inventory_account_id', 'label' => 'Stock account', 'options' => $data['accounts'], 'withCode' => true, 'empty' => '1500 Inventory', 'id' => 'icStock'])
                @include('dashboards.partials.select', ['name' => 'expense_account_id', 'label' => 'Expense when used', 'options' => $data['accounts'], 'withCode' => true, 'empty' => '5600 Stationery & Printing', 'id' => 'icExpense'])
                @include('dashboards.partials.select', ['name' => 'sale_fee_head_id', 'label' => 'Fee head when sold', 'options' => $data['fee_heads'], 'empty' => 'Not sold', 'id' => 'icFeeHead'])
                <label class="dash-switch">
                    <input type="checkbox" name="is_active" checked>
                    <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                    <span>Active</span>
                </label>
            </div>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions" data-modal-actions>
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Category</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-inventory.js') }}?v={{ filemtime(public_path('js/admin-inventory.js')) }}" defer></script>
@endsection
