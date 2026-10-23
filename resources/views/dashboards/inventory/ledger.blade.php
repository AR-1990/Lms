@extends('layouts.dashboard')

@section('title', 'Stock Ledger')
@section('page_heading', 'Inventory')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Stock Ledger</h1>
        <p>Every movement in and out of every store, with the document behind it.</p>
    </div>
</section>

@include('dashboards.inventory.tabs', ['current' => 'admin.inventory.ledger'])

<section class="dash-panel" data-inventory="ledger" data-datatable-url="{{ url('/api/erp/stock-movements/datatable') }}" data-resource-url="{{ url('/api/erp/stock-movements') }}" data-search-url="{{ url('/api/erp/inventory/items/search') }}" data-recipients-url="{{ url('/api/erp/inventory/recipients') }}" data-can-approve="{{ auth()->user()->hasPermission('approve-requests') ? 1 : 0 }}">
    <div class="dash-panel-head">
        <div>
            <h2>Movements</h2>
            <p>Use the date range and column search to trace an item.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select"><span class="dash-visually-hidden">Store</span>
                <select data-filter="location_id">
                    <option value="">All stores</option>
                    @foreach ($data['stores'] as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach
                </select>
            </label>
            <label class="dash-select"><span class="dash-visually-hidden">Type</span>
                <select data-filter="movement_type">
                    <option value="">All movements</option>
@foreach (\App\Models\StockMovement::TYPES as $type)                    <option value="{{ $type }}">{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
@endforeach
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Item</th>
                    <th>Store</th>
                    <th>Movement</th>
                    <th>Qty</th>
                    <th>Unit cost</th>
                    <th>Balance</th>
                    <th>Document</th>
                </tr>
            </thead>
        </table>
    </div>
</section>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-inventory.js') }}?v={{ filemtime(public_path('js/admin-inventory.js')) }}" defer></script>
@endsection
