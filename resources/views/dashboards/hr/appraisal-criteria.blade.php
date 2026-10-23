@extends('layouts.dashboard')

@section('title', 'Appraisal Criteria')
@section('page_heading', 'HR')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Appraisal Criteria</h1>
        <p>What staff are rated on, scored 1–5. The weight decides how much each one counts in the overall score; teaching-only and non-teaching criteria go only to those staff.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) Add Criterion</button>
</section>

@include('dashboards.hr.tabs', ['current' => 'admin.hr.appraisal-criteria'])

<section class="dash-panel" data-appraisals="criteria" data-datatable-url="{{ route('erp.appraisal-criteria.datatable') }}" data-resource-url="{{ url('/api/erp/appraisal-criteria') }}">
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Criterion</th>
                    <th>Weight</th>
                    <th>Applies to</th>
                    <th>Order</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="criterionModal" aria-labelledby="criterionModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'layers'])</span>
            <div>
                <h3 id="criterionModalTitle" data-modal-title>Add criterion</h3>
                <p>Changes apply to cycles launched after saving.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid dash-form-scroll">
            @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'wide' => true])
            @include('dashboards.partials.field', ['name' => 'description', 'label' => 'What it means', 'type' => 'textarea', 'wide' => true])
            @include('dashboards.partials.field', ['name' => 'weight_percent', 'label' => 'Weight (%)', 'type' => 'number', 'min' => 0, 'step' => '0.5'])
            <div class="dash-field">
                <label for="criterionAppliesTo">Applies to</label>
                <select id="criterionAppliesTo" name="applies_to">
                    <option value="all">All staff</option>
                    <option value="teaching">Teaching staff only</option>
                    <option value="non_teaching">Non-teaching staff only</option>
                </select>
                <p class="dash-field-error" data-error-for="applies_to" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'min' => 0])
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
<script src="{{ asset('js/admin-appraisals.js') }}?v={{ filemtime(public_path('js/admin-appraisals.js')) }}" defer></script>
@endsection
