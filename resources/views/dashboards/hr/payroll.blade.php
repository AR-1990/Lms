@extends('layouts.dashboard')

@section('title', 'Payroll')
@section('page_heading', 'HR')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Payroll</h1>
        <p>Generate a month → check payslips → approve → post to the ledger → pay. A draft can be regenerated any time.</p>
    </div>
    <form class="dash-head-actions" data-generate-form>
        <label class="dash-field-inline"><span class="dash-visually-hidden">Month</span><input type="month" name="month" value="{{ now()->format('Y-m') }}" required></label>
        <button type="submit" class="dash-btn dash-btn-primary">@include('partials.dashboard-icon', ['name' => 'plus']) Generate Payroll</button>
    </form>
</section>

@include('dashboards.hr.tabs', ['current' => 'admin.hr.payroll'])

<section class="dash-panel" data-payroll="runs" data-datatable-url="{{ route('erp.payroll-runs.datatable') }}" data-api="{{ url('/api/erp') }}" data-run-url="{{ url('/admin/hr/payroll') }}">
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Status</th>
                    <th class="dash-hide-md">Staff</th>
                    <th class="dash-hide-md">Gross</th>
                    <th>Net</th>
                </tr>
            </thead>
        </table>
    </div>
</section>


@endsection

@section('extra_js')
<script src="{{ asset('js/admin-payroll.js') }}?v={{ filemtime(public_path('js/admin-payroll.js')) }}" defer></script>
@endsection
