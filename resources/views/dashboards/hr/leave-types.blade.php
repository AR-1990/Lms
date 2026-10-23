@extends('layouts.dashboard')

@section('title', 'Leave Types')
@section('page_heading', 'HR')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Leave Types</h1>
        <p>Casual, Sick, Annual… Each year staff get the days below; unpaid leave has no limit and is deducted in payroll.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Add Leave Type
    </button>
</section>

@include('dashboards.hr.tabs', ['current' => 'admin.hr.leave-types'])

<section class="dash-panel" data-payroll="leave-types" data-datatable-url="{{ route('erp.leave-types.datatable') }}" data-resource-url="{{ url('/api/erp/leave-types') }}">
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Leave type</th>
                    <th>Days / year</th>
                    <th class="dash-hide-md">Rules</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="payrollModal" aria-labelledby="payrollModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'calendar'])</span>
            <div>
                <h3 id="payrollModalTitle" data-modal-title>Add leave type</h3>
                <p>Changes apply from the next balance created.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid dash-form-scroll">
            @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true])
            @include('dashboards.partials.field', ['name' => 'code', 'label' => 'Code', 'type' => 'text'])
            @include('dashboards.partials.field', ['name' => 'days_per_year', 'label' => 'Days per year', 'type' => 'number', 'required' => true, 'min' => 0, 'step' => '0.5'])
            @include('dashboards.partials.field', ['name' => 'max_carry_forward', 'label' => 'Max days carried forward', 'type' => 'number', 'min' => 0, 'step' => '0.5'])
            <div class="dash-field">
                <label for="leaveGender">Only for</label>
                <select id="leaveGender" name="applicable_gender_id" >
                    <option value="">—</option>
                    @foreach ($data['lookups']['gender'] as $option)
                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="applicable_gender_id" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'min' => 0])
            <label class="dash-switch">
                <input type="checkbox" name="is_paid" checked>
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Paid leave</span>
            </label>
            <label class="dash-switch">
                <input type="checkbox" name="carry_forward">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Unused days carry forward</span>
            </label>
            <label class="dash-switch">
                <input type="checkbox" name="requires_document">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Needs a document (e.g. medical note)</span>
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
<script src="{{ asset('js/admin-payroll.js') }}?v={{ filemtime(public_path('js/admin-payroll.js')) }}" defer></script>
@endsection
