@extends('layouts.dashboard')

@section('title', 'Activity Log')
@section('page_heading', 'Activity Log')

@section('extra_css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-dt@2.1.8/css/dataTables.dataTables.min.css">
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Activity Log</h1>
        <p>Who did what, on which module, and when. Entries are permanent and cannot be edited.</p>
    </div>
</section>

<section
    class="dash-panel"
    data-activity-log
    data-datatable-url="{{ route('erp.activity-logs.datatable') }}"
    data-show-url="{{ route('erp.activity-logs.index') }}"
>
    <div class="dash-filters dash-log-filters">
        <label class="dash-select">
            <span class="dash-visually-hidden">Module</span>
            <select data-filter="module">
                <option value="">All modules</option>
                @foreach ($data['modules'] as $module)
                    <option value="{{ $module['value'] }}">{{ $module['label'] }}</option>
                @endforeach
            </select>
        </label>
        <label class="dash-select">
            <span class="dash-visually-hidden">Action</span>
            <select data-filter="action">
                <option value="">All actions</option>
                @foreach ($data['actions'] as $action)
                    <option value="{{ $action['value'] }}">{{ $action['label'] }}</option>
                @endforeach
            </select>
        </label>
        <label class="dash-date-field">
            <span>From</span>
            <input type="date" data-filter="date_from" max="{{ now()->toDateString() }}">
        </label>
        <label class="dash-date-field">
            <span>To</span>
            <input type="date" data-filter="date_to" max="{{ now()->toDateString() }}">
        </label>
        <button type="button" class="dash-filters-reset" data-filters-reset>Clear</button>
    </div>

    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>When</th>
                    <th>Who</th>
                    <th>Module</th>
                    <th>Action</th>
                    <th>What happened</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Details</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="activityModal" aria-labelledby="activityModalTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'activity'])</span>
        <div>
            <h3 id="activityModalTitle" data-detail-title>Activity</h3>
            <p data-detail-meta></p>
        </div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">
            @include('partials.dashboard-icon', ['name' => 'close'])
        </button>
    </div>
    <div class="dash-detail-body" data-detail-body></div>
    <div class="dash-modal-actions">
        <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Close</button>
    </div>
</dialog>
@endsection

@section('extra_js')
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@2.1.8/js/dataTables.min.js" defer></script>
<script src="{{ asset('js/data-grid.js') }}?v={{ filemtime(public_path('js/data-grid.js')) }}" defer></script>
<script src="{{ asset('js/admin-activity-log.js') }}?v={{ filemtime(public_path('js/admin-activity-log.js')) }}" defer></script>
@endsection
