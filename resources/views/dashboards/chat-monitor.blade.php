@extends('layouts.dashboard')

@section('title', 'Chat Monitor')
@section('page_heading', 'Chat Monitor')

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Chat Monitor</h1>
        <p>Read any staff chat. Staff see no ticks, no online status and no member entry for you. Only adding or removing a group member shows a note in that group.</p>
    </div>
</section>

<div class="dash-monitor" data-monitor data-api="{{ url('/api/erp/chat-monitor') }}" data-people="{{ url('/api/erp/chat/people') }}" data-open-id="{{ $data['open_id'] }}">
    <section class="dash-panel">
        <div class="dash-panel-head">
            <div>
                <h2>All chats</h2>
                <p>Direct chats and groups, newest first.</p>
            </div>
            <select class="dash-select" data-monitor-type aria-label="Chat type">
                <option value="">All chats</option>
                <option value="direct">Direct</option>
                <option value="group">Groups</option>
            </select>
        </div>
        <div class="dash-grid-wrap">
            <table class="dash-table dash-datatable" data-monitor-grid>
                <thead>
                    <tr><th>Chat</th><th>Type</th><th>Members</th><th>Messages</th><th>Last message</th></tr>
                </thead>
            </table>
        </div>
    </section>

    <section class="dash-panel dash-monitor-reader" data-monitor-reader hidden>
        <div class="dash-panel-head">
            <div>
                <h2 data-reader-title>Chat</h2>
                <p class="dash-muted" data-reader-meta></p>
            </div>
            <button type="button" class="dash-btn dash-btn-ghost" data-reader-members hidden>@include('partials.dashboard-icon', ['name' => 'plus']) Add people</button>
        </div>
        <div class="dash-monitor-members">
            <strong data-reader-count>Members</strong>
            <ul class="dash-monitor-member-list" data-reader-member-list></ul>
        </div>
        <button type="button" class="dash-link-btn dash-monitor-older" data-reader-older hidden>Load older messages</button>
        <ol class="dash-monitor-messages" data-reader-messages></ol>
    </section>
</div>

<dialog class="dash-modal dash-form-modal" id="monitorMembersModal" aria-labelledby="monitorMembersTitle">
    <form data-members-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'users'])</span>
            <div>
                <h3 id="monitorMembersTitle">Add people</h3>
                <p>Tick the people to add. The group sees a note when someone is added.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-scroll">
            <input type="search" class="dash-input" placeholder="Search people…" data-members-search aria-label="Search people">
            <ul class="dash-monitor-people" data-members-list></ul>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Close</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Add to group</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
@include('partials.datatable-assets')
<script src="{{ asset('js/chat-monitor.js') }}?v={{ filemtime(public_path('js/chat-monitor.js')) }}" defer></script>
@endsection
