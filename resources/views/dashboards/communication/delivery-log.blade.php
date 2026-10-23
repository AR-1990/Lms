@extends('layouts.dashboard')

@section('title', 'Delivery Log')
@section('page_heading', 'Announcements')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Delivery Log</h1>
        <p>Every SMS, email and push message the school sent, with its result. Bell notifications are not listed here.</p>
    </div>
</section>

@include('dashboards.communication.tabs', ['tabCurrent' => 'admin.announcements.delivery-log'])

<section class="dash-panel" data-delivery-log data-datatable-url="{{ route('erp.notification-logs.datatable') }}">
    <div class="dash-panel-head">
        <h2>Messages</h2>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Channel</span>
                <select data-filter="channel">
                    <option value="">Every channel</option>
                    @foreach (array_diff($data['channels'], ['in_app']) as $channel)
                        <option value="{{ $channel }}">{{ ucfirst($channel) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="dash-select">
                <span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="">Every status</option>
                    @foreach (['queued', 'sent', 'delivered', 'failed'] as $status)
                        <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Message</th>
                    <th class="dash-hide-md">To</th>
                    <th>Status</th>
                    <th class="dash-hide-md">When</th>
                </tr>
            </thead>
        </table>
    </div>
</section>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-announcements.js') }}?v={{ filemtime(public_path('js/admin-announcements.js')) }}" defer></script>
@endsection
