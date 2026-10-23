@extends('layouts.dashboard')

@section('title', 'Stock')
@section('page_heading', 'Inventory')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Stock</h1>
        <p>Every stock item with what is on hand. Red rows are at or below their reorder level.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) Add Item</button>
</section>

@include('dashboards.inventory.tabs', ['current' => 'admin.inventory.index'])

<section class="dash-panel" data-inventory="stock" data-datatable-url="{{ url('/api/erp/inventory/items/datatable') }}" data-resource-url="{{ url('/api/erp/inventory/items') }}" data-search-url="{{ url('/api/erp/inventory/items/search') }}" data-recipients-url="{{ url('/api/erp/inventory/recipients') }}" data-can-approve="{{ auth()->user()->hasPermission('approve-requests') ? 1 : 0 }}">
    <div class="dash-panel-head">
        <div>
            <h2>Items</h2>
            <p>Quantities are across all stores unless you pick one.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select"><span class="dash-visually-hidden">Store</span>
                <select data-filter="location_id">
                    <option value="">All stores</option>
                    @foreach ($data['stores'] as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach
                </select>
            </label>
            <label class="dash-select"><span class="dash-visually-hidden">Category</span>
                <select data-filter="item_category_id">
                    <option value="">All categories</option>
                    @foreach ($data['item_categories'] as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach
                </select>
            </label>
            <label class="dash-select"><span class="dash-visually-hidden">Low stock</span>
                <select data-filter="low">
                    <option value="">All items</option>
                    <option value="1">Low stock only</option>
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Category</th>
                    <th>On hand</th>
                    <th class="dash-hide-md">Reorder at</th>
                    <th class="dash-hide-md">Cost / price</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="itemModal" aria-labelledby="itemModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'inbox'])</span>
            <div>
                <h3 id="itemModalTitle" data-modal-title>Add item</h3>
                <p data-modal-sub>Leave the code blank to number it automatically.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-scroll">
            <div class="dash-form-grid">
                @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name', 'required' => true, 'id' => 'itemName'])
                @include('dashboards.partials.select', ['name' => 'item_category_id', 'label' => 'Category', 'options' => $data['item_categories'], 'required' => true, 'id' => 'itemCategory'])
                @include('dashboards.partials.field', ['name' => 'sku', 'label' => 'Code', 'hint' => 'automatic', 'id' => 'itemSku'])
                @include('dashboards.partials.select', ['name' => 'unit_id', 'label' => 'Unit', 'options' => $data['units'], 'id' => 'itemUnit'])
                @include('dashboards.partials.field', ['name' => 'brand', 'label' => 'Brand', 'id' => 'itemBrand'])
                @include('dashboards.partials.field', ['name' => 'size', 'label' => 'Size', 'id' => 'itemSize'])
                @include('dashboards.partials.field', ['name' => 'color', 'label' => 'Colour', 'id' => 'itemColor'])
                @include('dashboards.partials.field', ['name' => 'barcode', 'label' => 'Barcode', 'id' => 'itemBarcode'])
                @include('dashboards.partials.field', ['name' => 'reorder_level', 'label' => 'Reorder level', 'type' => 'number', 'step' => '0.001', 'min' => '0', 'id' => 'itemReorder'])
                @include('dashboards.partials.field', ['name' => 'reorder_quantity', 'label' => 'Reorder quantity', 'type' => 'number', 'step' => '0.001', 'min' => '0', 'id' => 'itemReorderQty'])
                @include('dashboards.partials.field', ['name' => 'standard_cost', 'label' => 'Standard cost (Rs)', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'id' => 'itemCost'])
                @include('dashboards.partials.field', ['name' => 'sale_price', 'label' => 'Sale price (Rs)', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'id' => 'itemPrice'])
                @include('dashboards.partials.field', ['name' => 'description', 'label' => 'Notes', 'type' => 'textarea', 'wide' => true, 'id' => 'itemNotes'])
                <label class="dash-switch">
                    <input type="checkbox" name="is_sellable">
                    <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                    <span>Sold to students (uniform, books)</span>
                </label>
                <label class="dash-switch">
                    <input type="checkbox" name="is_returnable">
                    <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                    <span>Comes back after use (sports kit)</span>
                </label>
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
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Item</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-inventory.js') }}?v={{ filemtime(public_path('js/admin-inventory.js')) }}" defer></script>
@endsection
