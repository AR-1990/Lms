@extends('layouts.dashboard')

@section('title', 'Fee Refunds')
@section('page_heading', 'Fees')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Refunds</h1>
        <p>Security deposits and unused advance fees paid back to families. Refunds are paid from the student's profile, under Leaving &amp; clearance; each one posts a payment voucher.</p>
    </div>
</section>

@include('dashboards.fees.tabs', ['current' => 'accounts.fees.refunds'])

<section class="dash-panel" data-fees="refunds" data-datatable-url="{{ route('erp.fees.refunds.datatable') }}" data-resource-url="{{ url('/api/erp/fees/refunds') }}" data-profile-url="{{ url('/admin/students') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Refund register</h2>
            <p>Search by refund number or student. Reversed refunds stay listed.</p>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Refund</th>
                    <th class="dash-hide-md">Paid from</th>
                    <th>Date</th>
                    <th class="dash-col-num">Amount</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-fees.js') }}?v={{ filemtime(public_path('js/admin-fees.js')) }}" defer></script>
@endsection
