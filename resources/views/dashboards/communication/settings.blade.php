@extends('layouts.dashboard')

@php
    $type = $data['type'];
    [$heading, $intro, $addLabel, $url] = $type === 'categories'
        ? ['Announcement Categories', 'Notice, Holiday, Event… with the badge colour people see.', 'Add Category', url('/api/erp/announcement-categories')]
        : ['Message Templates', 'What the school sends automatically, per event and channel. Use {placeholders} from the list below the text.', 'Add Template', url('/api/erp/message-templates')];
@endphp

@section('title', $heading)
@section('page_heading', 'Announcements')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>{{ $heading }}</h1>
        <p>{{ $intro }}</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        {{ $addLabel }}
    </button>
</section>

@include('dashboards.communication.tabs', ['tabCurrent' => 'admin.announcements.'.$type])

<section class="dash-panel" data-communication-settings="{{ $type }}" data-datatable-url="{{ $url }}/datatable" data-resource-url="{{ $url }}">
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    @if ($type === 'categories')
                        <th>Category</th><th class="dash-hide-md">Used by</th><th>Status</th>
                    @else
                        <th>Template</th><th class="dash-hide-md">Event</th><th>Status</th>
                    @endif
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal {{ $type === 'templates' ? 'dash-modal-wide' : '' }}" id="communicationModal" aria-labelledby="communicationModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => $type === 'categories' ? 'layers' : 'mail'])</span>
            <div>
                <h3 id="communicationModalTitle" data-modal-title>{{ $addLabel }}</h3>
                <p>{{ $intro }}</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @if ($type === 'categories')
                @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name', 'required' => true])
                @include('dashboards.partials.field', ['name' => 'color', 'label' => 'Badge colour', 'type' => 'color'])
                @include('dashboards.partials.field', ['name' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'min' => 0])
            @else
                @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name', 'required' => true, 'wide' => true])
                <div class="dash-field">
                    <label for="templateEvent">When</label>
                    <select id="templateEvent" name="event_key" data-template-event>
                        @foreach ($data['events'] as $eventKey => [$eventLabel, $placeholders])
                            <option value="{{ $eventKey }}" data-placeholders="{{ implode(' ', $placeholders) }}">{{ $eventLabel }}</option>
                        @endforeach
                    </select>
                    <p class="dash-field-error" data-error-for="event_key" hidden></p>
                </div>
                <div class="dash-field">
                    <label for="templateChannel">Send by</label>
                    <select id="templateChannel" name="channel">
                        @foreach ($data['channels'] as $channel)
                            <option value="{{ $channel }}">{{ $channel === 'in_app' ? 'Bell (in app)' : ucfirst($channel) }}</option>
                        @endforeach
                    </select>
                    <p class="dash-field-error" data-error-for="channel" hidden></p>
                </div>
                @include('dashboards.partials.field', ['name' => 'subject', 'label' => 'Title / subject', 'wide' => true])
                <div class="dash-field dash-field-wide">
                    <label for="templateBody">Message</label>
                    <textarea id="templateBody" name="body" rows="4" maxlength="2000" required></textarea>
                    <p class="dash-register-note" data-template-placeholders></p>
                    <p class="dash-field-error" data-error-for="body" hidden></p>
                </div>
            @endif
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
<script src="{{ asset('js/admin-announcements.js') }}?v={{ filemtime(public_path('js/admin-announcements.js')) }}" defer></script>
@endsection
