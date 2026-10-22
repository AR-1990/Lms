@extends('layouts.dashboard')

@section('title', 'Fiscal Years')
@section('page_heading', 'Accounts')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Fiscal Years</h1>
        <p>Financial years for the books. A closed year accepts no new or changed vouchers.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Add Fiscal Year
    </button>
</section>

@include('dashboards.accounting.tabs', ['current' => 'accounts.fiscal-years'])

<section class="dash-panel" data-accounting="fiscal-years" data-datatable-url="{{ route('erp.ledger.fiscal-years.datatable') }}" data-resource-url="{{ url('/api/erp/ledger/fiscal-years') }}">
    <div class="dash-panel-head">
        <div>
            <h2>All fiscal years</h2>
            <p>Years may not overlap. A year with vouchers can be closed but not deleted.</p>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Year</th>
                    <th>Starts</th>
                    <th>Ends</th>
                    <th class="dash-hide-md">Vouchers</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="accountingModal" aria-labelledby="accountingModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'calendar'])</span>
            <div>
                <h3 id="accountingModalTitle" data-modal-title>Add fiscal year</h3>
                <p>For example FY 2027-28, 1 July to 30 June.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>

        <div class="dash-form-grid">
            <div class="dash-field dash-field-wide">
                <label for="yearName">Name</label>
                <input id="yearName" name="name" required maxlength="30" autocomplete="off" placeholder="FY 2027-28">
                <p class="dash-field-error" data-error-for="name" hidden></p>
            </div>
            <div class="dash-field">
                <label for="yearStarts">Starts on</label>
                <input id="yearStarts" name="starts_on" type="date" required>
                <p class="dash-field-error" data-error-for="starts_on" hidden></p>
            </div>
            <div class="dash-field">
                <label for="yearEnds">Ends on</label>
                <input id="yearEnds" name="ends_on" type="date" required>
                <p class="dash-field-error" data-error-for="ends_on" hidden></p>
            </div>
            <label class="dash-switch dash-field-wide">
                <input type="checkbox" name="is_current">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Current year</span>
            </label>
        </div>

        <p class="dash-form-error" data-form-error hidden></p>

        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Year</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-accounting.js') }}?v={{ filemtime(public_path('js/admin-accounting.js')) }}" defer></script>
@endsection
