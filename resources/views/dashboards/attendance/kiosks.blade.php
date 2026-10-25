@extends('layouts.dashboard')

@section('title', 'Gate Kiosks')
@section('page_heading', 'Attendance')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Gate Kiosks</h1>
        <p>A kiosk is a tablet or phone at the gate that scans ID cards without anyone signing in. Open its link once on the device and leave it running: each card says whether it is a student or staff.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) Add Kiosk</button>
</section>

@include('dashboards.attendance.tabs', ['current' => 'admin.attendance.kiosks'])

<section class="dash-panel" data-kiosks data-datatable-url="{{ route('erp.attendance-kiosks.datatable') }}" data-resource-url="{{ url('/api/erp/attendance-kiosks') }}">
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Kiosk</th>
                    <th class="dash-hide-md">Campus</th>
                    <th class="dash-col-num">Scans</th>
                    <th>Last used</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="kioskModal" aria-labelledby="kioskModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'grid'])</span>
            <div>
                <h3 id="kioskModalTitle" data-modal-title>Add kiosk</h3>
                <p>After saving you get the link to open on the device.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name', 'required' => true, 'wide' => true, 'placeholder' => 'Main gate, Staff room…'])
            @include('dashboards.partials.select', ['name' => 'campus_id', 'label' => 'Campus', 'options' => $data['campuses'], 'empty' => 'Any campus'])
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

<dialog class="dash-modal" id="kioskLink" aria-labelledby="kioskLinkTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'key'])</span>
        <div>
            <h3 id="kioskLinkTitle">Kiosk link</h3>
            <p>Open this on the gate device and bookmark it. It is shown only now; make a new link any time.</p>
        </div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-detail-body">
        <input class="dash-kiosk-url" data-kiosk-url readonly aria-label="Kiosk link">
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-copy-url>Copy link</button>
            <a class="dash-btn dash-btn-primary" data-open-url target="_blank" rel="noopener">Open kiosk</a>
        </div>
    </div>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-kiosks.js') }}?v={{ filemtime(public_path('js/admin-kiosks.js')) }}" defer></script>
@endsection
