@extends('layouts.dashboard')

@section('title', 'Expense Categories')
@section('page_heading', 'Accounts')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Expense Categories</h1>
        <p>Each category posts its expenses to its own expense account.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) Add Category</button>
</section>

@include('dashboards.accounting.tabs', ['current' => 'accounts.expense-categories'])

<section class="dash-panel" data-expenses="expense-categories" data-datatable-url="{{ url('/api/erp/expense-categories/datatable') }}" data-resource-url="{{ url('/api/erp/expense-categories') }}" data-attachments-url="{{ url('/api/erp/attachments') }}" data-can-approve="{{ auth()->user()->hasPermission('approve-requests') ? 1 : 0 }}">
    <div class="dash-panel-head">
        <div>
            <h2>Categories</h2>
            <p>Add sub-categories with "Inside".</p>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Posted to</th>
                    <th>Expenses</th>
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
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name', 'required' => true, 'id' => 'ecName'])
            @include('dashboards.partials.select', ['name' => 'parent_id', 'label' => 'Inside', 'options' => $data['categories'], 'id' => 'ecParent'])
            @include('dashboards.partials.select', ['name' => 'expense_account_id', 'label' => 'Expense account', 'options' => $data['accounts'], 'required' => true, 'withCode' => true, 'id' => 'ecAccount'])
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
<script src="{{ asset('js/admin-expenses.js') }}?v={{ filemtime(public_path('js/admin-expenses.js')) }}" defer></script>
@endsection
