@extends('layouts.dashboard')

@section('title', 'Inquiries')
@section('page_heading', 'Admissions')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Inquiries</h1>
        <p>Calls, walk-ins and website forms. Convert an inquiry when the family applies.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        New Inquiry
    </button>
</section>

@include('dashboards.students.tabs', ['current' => 'admin.admissions.inquiries'])

<section class="dash-panel" data-students="inquiries" data-datatable-url="{{ route('erp.admission-inquiries.datatable') }}" data-resource-url="{{ route('erp.admission-inquiries.index') }}" data-applications-url="{{ route('admin.admissions.applications') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Inquiries</h2>
            <p>Follow up before the date shown.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="">All statuses</option>
                    @foreach (\App\Models\AdmissionInquiry::STATUSES as $status)
                        <option value="{{ $status }}">{{ \Illuminate\Support\Str::headline($status) }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Parent</th>
                    <th>Child &amp; class</th>
                    <th class="dash-hide-md">Follow up</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="followUpModal" aria-labelledby="followUpTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'chat'])</span>
        <div>
            <h3 id="followUpTitle" data-follow-title>Follow-ups</h3>
            <p data-follow-sub></p>
        </div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-form-scroll">
        <form class="dash-form-grid" data-follow-form novalidate>
            @include('dashboards.partials.field', ['name' => 'followed_up_on', 'label' => 'Date', 'type' => 'date', 'required' => true, 'id' => 'fuDate'])
            @include('dashboards.partials.select', ['name' => 'channel_id', 'label' => 'How', 'options' => $data['lookups']['follow_up_channel'], 'id' => 'fuChannel'])
            @include('dashboards.partials.field', ['name' => 'outcome', 'label' => 'What was said', 'type' => 'textarea', 'required' => true, 'wide' => true, 'id' => 'fuOutcome'])
            @include('dashboards.partials.field', ['name' => 'next_follow_up_on', 'label' => 'Follow up again on', 'type' => 'date', 'id' => 'fuNext', 'hint' => 'Leave empty if no more follow-up is needed.'])
            <p class="dash-form-error dash-field-wide" data-form-error hidden></p>
            <div><button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save follow-up</button></div>
        </form>
        <ul class="dash-list dash-follow-list" data-follow-list></ul>
    </div>
</dialog>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="inquiryModal" aria-labelledby="inquiryModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'mail'])</span>
            <div>
                <h3 id="inquiryModalTitle" data-modal-title>New inquiry</h3>
                <p>Only the parent's name and phone are required.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid dash-form-grid-3">
            @include('dashboards.partials.field', ['name' => 'parent_name', 'label' => 'Parent name', 'required' => true])
            @include('dashboards.partials.field', ['name' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => true])
            @include('dashboards.partials.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email'])
            @include('dashboards.partials.field', ['name' => 'child_name', 'label' => 'Child name'])
            @include('dashboards.partials.field', ['name' => 'child_dob', 'label' => 'Child date of birth', 'type' => 'date'])
            @include('dashboards.partials.select', ['name' => 'class_sought_id', 'label' => 'Class sought', 'options' => $data['classes']])
            @include('dashboards.partials.select', ['name' => 'source_id', 'label' => 'Source', 'options' => $data['lookups']['inquiry_source']])
            @include('dashboards.partials.field', ['name' => 'follow_up_on', 'label' => 'Follow up on', 'type' => 'date'])
            <div class="dash-field">
                <label for="inquiryStatus">Status</label>
                <select id="inquiryStatus" name="status">
                    @foreach (['new', 'contacted', 'visit_scheduled', 'closed'] as $status)
                        <option value="{{ $status }}">{{ \Illuminate\Support\Str::headline($status) }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="status" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'previous_school', 'label' => 'Previous school'])
            @include('dashboards.partials.field', ['name' => 'message', 'label' => 'Notes', 'type' => 'textarea', 'wide' => true])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary">@include('partials.dashboard-icon', ['name' => 'check']) Save Inquiry</button>
        </div>
    </form>
</dialog>

@include('dashboards.students.application-form')
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-students.js') }}?v={{ filemtime(public_path('js/admin-students.js')) }}" defer></script>
@endsection
