@extends('layouts.dashboard')

@section('title', 'Chat')
@section('page_heading', 'Staff Chat')

@section('content')
<section class="dash-chat" data-chat data-api="{{ url('/api/erp/chat') }}" data-user-id="{{ $user->id }}" data-user-name="{{ $user->name }}" data-open-id="{{ $data['open_id'] }}">
    <aside class="dash-chat-list" aria-label="Chats">
        <div class="dash-chat-list-head">
            <h1>Chats</h1>
            <div class="dash-chat-list-actions">
                <button type="button" class="dash-icon-btn" data-chat-new data-tooltip="New chat" aria-label="New chat">@include('partials.dashboard-icon', ['name' => 'plus'])</button>
                <button type="button" class="dash-icon-btn" data-chat-new-group data-tooltip="New group" aria-label="New group">@include('partials.dashboard-icon', ['name' => 'users'])</button>
            </div>
        </div>
        <label class="dash-chat-search">
            @include('partials.dashboard-icon', ['name' => 'search'])
            <span class="dash-visually-hidden">Search chats and messages</span>
            <input type="search" placeholder="Search chats or messages" data-chat-search autocomplete="off">
        </label>
        <div class="dash-chat-filter" role="tablist">
            <button type="button" class="is-active" data-chat-view="chats" role="tab" aria-selected="true">Chats</button>
            <button type="button" data-chat-view="archived" role="tab" aria-selected="false">Archived</button>
        </div>
        <ul class="dash-chat-conversations" data-chat-conversations><li class="dash-muted">Loading…</li></ul>
    </aside>

    <section class="dash-chat-thread" data-chat-thread>
        <div class="dash-chat-empty" data-chat-empty>
            @include('partials.dashboard-icon', ['name' => 'chat'])
            <p>Pick a chat on the left, or start a new one.</p>
        </div>
        <header class="dash-chat-header" data-chat-header hidden>
            <button type="button" class="dash-icon-btn dash-chat-back" data-chat-back aria-label="Back to chats">@include('partials.dashboard-icon', ['name' => 'arrow-left'])</button>
            <span class="dash-chat-avatar" data-chat-avatar></span>
            <div class="dash-chat-title">
                <strong data-chat-name></strong>
                <span data-chat-status></span>
            </div>
            <div class="dash-chat-header-actions">
                <button type="button" class="dash-icon-btn" data-chat-pin aria-label="Pin chat" data-tooltip="Pin">📌</button>
                <button type="button" class="dash-icon-btn" data-chat-mute aria-label="Mute chat" data-tooltip="Mute">🔔</button>
                <button type="button" class="dash-icon-btn" data-chat-archive aria-label="Archive chat" data-tooltip="Archive">🗄</button>
                <button type="button" class="dash-icon-btn" data-chat-info aria-label="Group info" data-tooltip="Group info" hidden>ⓘ</button>
            </div>
        </header>
        <ol class="dash-chat-messages" data-chat-messages aria-live="polite" hidden></ol>
        <div class="dash-chat-typing" data-chat-typing hidden></div>
        <div class="dash-chat-bar" data-chat-bar hidden>
            <span data-chat-bar-text></span>
            <button type="button" class="dash-icon-btn" data-chat-bar-cancel aria-label="Cancel">×</button>
        </div>
        <ul class="dash-chat-mentions" data-chat-mention-list hidden></ul>
        <form class="dash-chat-composer" data-chat-composer hidden>
            <label class="dash-icon-btn dash-chat-attach" data-tooltip="Attach files" aria-label="Attach files">
                @include('partials.dashboard-icon', ['name' => 'paperclip'])
                <input type="file" multiple data-chat-files accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip">
            </label>
            <div class="dash-chat-emoji" data-chat-emoji>
                <button type="button" class="dash-icon-btn dash-chat-emoji-btn" data-chat-emoji-toggle aria-label="Emoji" aria-expanded="false" data-tooltip="Emoji">😊</button>
                <div class="dash-chat-emoji-panel" data-chat-emoji-panel hidden>
                    <div class="dash-chat-emoji-tabs" data-chat-emoji-tabs></div>
                    <div class="dash-chat-emoji-grid" data-chat-emoji-grid></div>
                </div>
            </div>
            <div class="dash-chat-input">
                <div class="dash-chat-file-chips" data-chat-file-chips></div>
                <textarea rows="1" maxlength="5000" placeholder="Type a message" data-chat-input aria-label="Message"></textarea>
            </div>
            <button type="submit" class="dash-chat-send" aria-label="Send">@include('partials.dashboard-icon', ['name' => 'send'])</button>
        </form>
    </section>
</section>

<dialog class="dash-modal dash-form-modal" id="chatPeopleModal" aria-labelledby="chatPeopleTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'chat'])</span>
        <div>
            <h3 id="chatPeopleTitle" data-people-title>New chat</h3>
            <p data-people-hint>Choose a colleague.</p>
        </div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-form-grid">
        <div class="dash-field dash-field-wide" data-group-fields hidden>
            <label for="chatGroupName">Group name</label>
            <input id="chatGroupName" maxlength="120" data-group-name>
            <p class="dash-field-error" data-error-for="name" hidden></p>
        </div>
        <label class="dash-switch dash-field-wide" data-group-fields hidden>
            <input type="checkbox" data-group-admins-only>
            <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
            <span>Only admins can post (announcement group)</span>
        </label>
        <div class="dash-field dash-field-wide">
            <input type="search" placeholder="Search people" data-people-search aria-label="Search people">
            <ul class="dash-chat-people" data-people-list></ul>
            <p class="dash-field-error" data-error-for="member_ids" hidden></p>
        </div>
    </div>
    <div class="dash-modal-actions">
        <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
        <button type="button" class="dash-btn dash-btn-primary" data-people-confirm hidden>@include('partials.dashboard-icon', ['name' => 'check']) <span data-people-confirm-label>Create group</span></button>
    </div>
</dialog>

<dialog class="dash-modal dash-form-modal" id="chatForwardModal" aria-labelledby="chatForwardTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'send'])</span>
        <div>
            <h3 id="chatForwardTitle">Forward to…</h3>
            <p>Pick up to 10 chats.</p>
        </div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <ul class="dash-chat-people dash-chat-forward" data-forward-list></ul>
    <div class="dash-modal-actions">
        <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
        <button type="button" class="dash-btn dash-btn-primary" data-forward-confirm>@include('partials.dashboard-icon', ['name' => 'send']) Forward</button>
    </div>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/chat.js') }}?v={{ filemtime(public_path('js/chat.js')) }}" defer></script>
@endsection
