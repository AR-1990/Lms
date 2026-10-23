@extends('layouts.dashboard')

@section('title', 'Asset Categories')
@section('page_heading', 'Assets')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Asset Categories</h1>
        <p>Each category sets the ledger accounts and the depreciation rule for its assets.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) Add Category</button>
</section>

@include('dashboards.assets.tabs', ['current' => 'admin.assets.categories'])

<section class="dash-panel" data-assets="categories" data-datatable-url="{{ url('/api/erp/asset-categories/datatable') }}" data-resource-url="{{ url('/api/erp/asset-categories') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Categories</h2>
            <p>Life is in months; salvage is the share of cost never depreciated.</p>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Category</th>
                    <th class="dash-hide-md">Asset account</th>
                    <th>Depreciation</th>
                    <th>Assets</th>
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
                <p data-action-asset>Accounts are used when posting purchases, depreciation and disposals.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name', 'required' => true, 'id' => 'catName'])
            @include('dashboards.partials.field', ['name' => 'code', 'label' => 'Code', 'id' => 'catCode'])
            @include('dashboards.partials.select', ['name' => 'parent_id', 'label' => 'Inside', 'options' => $data['categories'], 'id' => 'catParent'])
            <div class="dash-field">
                <label for="catMethod">Depreciation</label>
                <select id="catMethod" name="depreciation_method" required>
                    <option value="straight_line">Straight line</option>
                    <option value="reducing_balance">Reducing balance</option>
                    <option value="none">None (land, art…)</option>
                </select>
            </div>
            @include('dashboards.partials.field', ['name' => 'useful_life_months', 'label' => 'Useful life (months)', 'type' => 'number', 'min' => '1', 'id' => 'catLife'])
            @include('dashboards.partials.field', ['name' => 'salvage_percent', 'label' => 'Salvage (%)', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'max' => '100', 'id' => 'catSalvage'])
            @include('dashboards.partials.select', ['name' => 'asset_account_id', 'label' => 'Asset account', 'options' => $data['accounts'], 'withCode' => true, 'id' => 'catAsset'])
            @include('dashboards.partials.select', ['name' => 'depreciation_account_id', 'label' => 'Depreciation expense', 'options' => $data['accounts'], 'withCode' => true, 'empty' => '5500 Depreciation Expense', 'id' => 'catDepExp'])
            @include('dashboards.partials.select', ['name' => 'accumulated_depreciation_account_id', 'label' => 'Accumulated depreciation', 'options' => $data['accounts'], 'withCode' => true, 'empty' => '1690 Accumulated Depreciation', 'id' => 'catAccum'])
            <label class="dash-switch">
                <input type="checkbox" name="is_active" checked>
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Active</span>
            </label>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Category</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-assets.js') }}?v={{ filemtime(public_path('js/admin-assets.js')) }}" defer></script>
@endsection
