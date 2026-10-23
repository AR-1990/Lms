@extends('layouts.dashboard')

@section('title', 'Performance Alerts')
@section('page_heading', 'Performance')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>At-risk Alerts</h1>
        <p>Raised automatically when a student crosses an alert rule. Record what was done, then resolve.</p>
    </div>
</section>

@include('dashboards.performance.tabs', ['current' => 'admin.performance.alerts'])

<section class="dash-panel" data-performance="alerts" data-api-url="{{ url('/api/erp/performance') }}" @if (auth()->user()->hasPermission('manage-counselling')) data-counselling-url="{{ route('admin.performance.counselling') }}" @endif>
    <div class="dash-panel-head">
        <div class="dash-filters-inline">
            <label class="dash-select"><span class="dash-visually-hidden">Status</span>
                <select data-status>
                    <option value="">Open &amp; acknowledged</option>
                    <option value="resolved">Resolved</option>
                </select>
            </label>
        </div>
    </div>
    <ul class="dash-alert-list" data-alerts><li class="dash-muted">Loading…</li></ul>
</section>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-performance.js') }}?v={{ filemtime(public_path('js/admin-performance.js')) }}" defer></script>
@endsection
