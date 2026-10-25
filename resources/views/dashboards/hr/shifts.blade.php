@extends('layouts.dashboard')

@section('title', 'Shifts')
@section('page_heading', 'HR')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Shifts</h1>
        <p>When the working day starts. A check-in (QR, kiosk, biometric or the register) after the grace minutes is marked late with the minutes, and the payroll late policy applies.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Add Shift
    </button>
</section>

@include('dashboards.hr.tabs', ['current' => 'admin.hr.shifts'])

<section class="dash-panel" data-hr="shifts" data-datatable-url="{{ route('erp.work-shifts.datatable') }}" data-resource-url="{{ url('/api/erp/work-shifts') }}">
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Shift</th>
                    <th>Time</th>
                    <th class="dash-col-num">Grace</th>
                    <th class="dash-col-num dash-hide-md">Staff</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="hrModal" aria-labelledby="hrModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'clock'])</span>
            <div>
                <h3 id="hrModalTitle" data-modal-title>Add shift</h3>
                <p>Give staff a shift on their employee record; others follow the default shift.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name', 'required' => true, 'wide' => true, 'placeholder' => 'Morning, Evening, Admin office…'])
            @include('dashboards.partials.field', ['name' => 'starts_at', 'label' => 'Starts at', 'type' => 'time', 'required' => true])
            @include('dashboards.partials.field', ['name' => 'ends_at', 'label' => 'Ends at', 'type' => 'time', 'required' => true])
            @include('dashboards.partials.field', ['name' => 'grace_minutes', 'label' => 'Grace minutes', 'type' => 'number', 'required' => true, 'min' => 0, 'max' => 180])
            <label class="dash-switch">
                <input type="checkbox" name="is_default">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Default shift</span>
            </label>
            <label class="dash-switch">
                <input type="checkbox" name="is_active" checked>
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Active</span>
            </label>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-hr.js') }}?v={{ filemtime(public_path('js/admin-hr.js')) }}" defer></script>
@endsection
