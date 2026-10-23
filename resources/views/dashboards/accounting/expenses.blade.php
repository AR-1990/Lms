@extends('layouts.dashboard')

@section('title', 'Expenses')
@section('page_heading', 'Accounts')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Expenses</h1>
        <p>Bills and spending: record as a draft, an approver approves, then pay it (a payment voucher is posted). Attach the bill scan.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) Record Expense</button>
</section>

@include('dashboards.accounting.tabs', ['current' => 'accounts.expenses'])

<section class="dash-panel" data-expenses="expenses" data-datatable-url="{{ url('/api/erp/expenses/datatable') }}" data-resource-url="{{ url('/api/erp/expenses') }}" data-attachments-url="{{ url('/api/erp/attachments') }}" data-can-approve="{{ auth()->user()->hasPermission('approve-requests') ? 1 : 0 }}">
    <div class="dash-panel-head">
        <div>
            <h2>Expenses</h2>
            <p>Open an expense to approve, pay, cancel or attach the bill.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select"><span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="">All</option>
@foreach (\App\Models\Expense::STATUSES as $status)                    <option value="{{ $status }}">{{ ucfirst($status) }}</option>
@endforeach
                </select>
            </label>
            <label class="dash-select"><span class="dash-visually-hidden">Category</span>
                <select data-filter="expense_category_id">
                    <option value="">All categories</option>
                    @foreach ($data['categories'] as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Expense</th>
                    <th>Category</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th class="dash-hide-md">Voucher</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="expenseModal" aria-labelledby="expenseModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'inbox'])</span>
            <div>
                <h3 id="expenseModalTitle" data-modal-title>Record expense</h3>
                <p data-modal-sub>Saved as a draft until approved.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.select', ['name' => 'expense_category_id', 'label' => 'Category', 'options' => $data['categories'], 'required' => true, 'id' => 'exCategory'])
            @include('dashboards.partials.select', ['name' => 'vendor_id', 'label' => 'Vendor', 'options' => $data['vendors'], 'id' => 'exVendor'])
            @include('dashboards.partials.field', ['name' => 'expense_date', 'label' => 'Bill date', 'type' => 'date', 'required' => true, 'id' => 'exDate'])
            @include('dashboards.partials.field', ['name' => 'reference_no', 'label' => 'Bill / reference no', 'id' => 'exRef'])
            @include('dashboards.partials.field', ['name' => 'amount', 'label' => 'Amount (Rs)', 'type' => 'number', 'step' => '0.01', 'min' => '0.01', 'required' => true, 'id' => 'exAmount'])
            @include('dashboards.partials.field', ['name' => 'tax_amount', 'label' => 'Tax (Rs)', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'id' => 'exTax'])
            @include('dashboards.partials.field', ['name' => 'description', 'label' => 'What for', 'type' => 'textarea', 'wide' => true, 'id' => 'exWhat'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Draft</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-modal-wide" id="expenseView" aria-labelledby="expenseViewTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'inbox'])</span>
        <div><h3 id="expenseViewTitle" data-view-title>Expense</h3><p data-view-sub></p></div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-form-scroll">
        <dl class="dash-asset-facts" data-view-facts></dl>
        <h4>Bill scan</h4>
        <ul class="dash-asset-history" data-view-files></ul>
        <label class="dash-btn dash-btn-ghost dash-file-btn">@include('partials.dashboard-icon', ['name' => 'upload']) Attach bill<input type="file" accept="image/*,.pdf" data-view-upload hidden></label>
    </div>
    <div class="dash-modal-actions" data-view-actions></div>
</dialog>

<dialog class="dash-modal dash-form-modal" id="payModal" aria-labelledby="payModalTitle">
    <form data-pay-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'send'])</span>
            <div>
                <h3 id="payModalTitle" data-modal-title>Pay expense</h3>
                <p data-modal-sub></p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'paid_on', 'label' => 'Paid on', 'type' => 'date', 'required' => true, 'id' => 'payOn'])
            @include('dashboards.partials.select', ['name' => 'payment_method_id', 'label' => 'Paid by', 'options' => $data['payment_methods'], 'required' => true, 'id' => 'payMethod'])
            @include('dashboards.partials.field', ['name' => 'reference_no', 'label' => 'Cheque / transfer ref', 'id' => 'payRef'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Pay</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-expenses.js') }}?v={{ filemtime(public_path('js/admin-expenses.js')) }}" defer></script>
@endsection
