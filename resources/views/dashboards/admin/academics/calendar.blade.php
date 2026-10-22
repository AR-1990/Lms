@extends('layouts.dashboard')

@section('title', 'Calendar')
@section('page_heading', 'Academics')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>School Calendar</h1>
        <p>Holidays stop attendance being marked. Public events appear on the website's home and news pages.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Add Event
    </button>
</section>

@include('dashboards.admin.academics.tabs', ['current' => 'admin.academics.calendar'])

<section class="dash-panel" data-calendar data-datatable-url="{{ route('erp.calendar.datatable') }}" data-resource-url="{{ url('/api/erp/calendar') }}">
    <div class="dash-panel-head">
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">From</span>
                <input type="date" data-filter="from" value="{{ now()->startOfMonth()->toDateString() }}" aria-label="From">
            </label>
            <label class="dash-select">
                <span class="dash-visually-hidden">Type</span>
                <select data-filter="event_type_id">
                    <option value="">All types</option>
                    @foreach ($data['event_types'] as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Dates</th>
                    <th class="dash-hide-md">Shown</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="calendarModal" aria-labelledby="calendarModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'calendar'])</span>
            <div>
                <h3 id="calendarModalTitle" data-modal-title>Add event</h3>
                <p>Leave the end date empty for a one-day event.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'title', 'label' => 'Title', 'required' => true, 'wide' => true])
            @include('dashboards.partials.select', ['name' => 'event_type_id', 'label' => 'Type', 'options' => $data['event_types']])
            @include('dashboards.partials.field', ['name' => 'venue', 'label' => 'Venue'])
            @include('dashboards.partials.field', ['name' => 'starts_on', 'label' => 'From', 'type' => 'date', 'required' => true])
            @include('dashboards.partials.field', ['name' => 'ends_on', 'label' => 'To', 'type' => 'date'])
            @include('dashboards.partials.field', ['name' => 'start_time', 'label' => 'Starts at', 'type' => 'time'])
            @include('dashboards.partials.field', ['name' => 'end_time', 'label' => 'Ends at', 'type' => 'time'])
            @include('dashboards.partials.field', ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'wide' => true])
            <label class="dash-switch">
                <input type="checkbox" name="is_holiday">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Holiday (no attendance)</span>
            </label>
            <label class="dash-switch">
                <input type="checkbox" name="is_public">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Show on website</span>
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
<script src="{{ asset('js/admin-schedule.js') }}?v={{ filemtime(public_path('js/admin-schedule.js')) }}" defer></script>
@endsection
