@extends('layouts.dashboard')

@section('title', 'Leave Requests')
@section('page_heading', 'Attendance')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Leave Requests</h1>
        <p>Parents ask for leave from their portal or the app; approved days show as "leave" on the register.</p>
    </div>
</section>

@include('dashboards.attendance.tabs', ['current' => 'admin.attendance.leaves'])

<section class="dash-panel" data-attendance="leaves" data-datatable-url="{{ route('erp.leave-requests.datatable') }}" data-resource-url="{{ url('/api/erp/leave-requests') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Requests</h2>
            <p>Open a pending request to approve or reject it.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="">All</option>
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Dates</th>
                    <th class="dash-hide-md">Reason</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="leaveModal" aria-labelledby="leaveTitle">
    <form data-leave-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'mail'])</span>
            <div>
                <h3 id="leaveTitle" data-detail-title>Leave request</h3>
                <p data-detail-subtitle></p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-detail-body" data-detail-body></div>
        <div class="dash-form-grid" data-decision-fields>
            @include('dashboards.partials.field', ['name' => 'remarks', 'label' => 'Remarks', 'wide' => true, 'id' => 'leaveRemarks'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions" data-decision-actions>
            <button type="button" class="dash-btn dash-btn-ghost" data-decision="rejected">Reject</button>
            <button type="button" class="dash-btn dash-btn-primary" data-decision="approved">@include('partials.dashboard-icon', ['name' => 'check']) Approve</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-attendance.js') }}?v={{ filemtime(public_path('js/admin-attendance.js')) }}" defer></script>
@endsection
