{{-- The user's own sticky notes as paper notes. Params: $limit (dashboard widget, null = full page), $view (active / done / archived). Data: /api/erp/sticky-notes. --}}
@php
    $limit = $limit ?? 8;
    $view = $view ?? 'active';
@endphp
<section class="dash-panel dash-sticky-board" data-sticky-board data-url="{{ url('/api/erp/sticky-notes') }}" data-limit="{{ $limit }}" data-view="{{ $view }}">
    <div class="dash-panel-head">
        <div>
            <h2>@include('partials.dashboard-icon', ['name' => 'pin']) My notes</h2>
            <p>Only you can see these. Drag to reorder.</p>
        </div>
        <div class="dash-head-actions">
            @if ($limit)
                <a class="dash-link-btn" href="{{ route('my-notes') }}">View all</a>
            @endif
            @if ($view === 'active')
                <button type="button" class="dash-btn dash-btn-primary" data-sticky-add>@include('partials.dashboard-icon', ['name' => 'plus']) Note</button>
            @endif
        </div>
    </div>
    <div class="dash-sticky-grid" data-sticky-grid>
        <p class="dash-muted">Loading notes…</p>
    </div>
</section>

<dialog class="dash-modal dash-form-modal dash-sticky-editor" id="stickyEditor" aria-labelledby="stickyEditorTitle">
    <form data-sticky-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'pen'])</span>
            <div>
                <h3 id="stickyEditorTitle">Note</h3>
                <p data-sticky-status>Saved as you type.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-sticky-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid dash-form-scroll">
            <div class="dash-field dash-field-wide">
                <label for="stickyTitle">Heading <span class="dash-optional">optional</span></label>
                <input id="stickyTitle" name="title" maxlength="120" autocomplete="off">
            </div>
            <div class="dash-field dash-field-wide">
                <label for="stickyBody">Note</label>
                <textarea id="stickyBody" name="body" rows="4" maxlength="5000" placeholder="Call Ali's father about fee…"></textarea>
                <p class="dash-field-error" data-error-for="body" hidden></p>
            </div>
            <div class="dash-field dash-field-wide">
                <span class="dash-label">Colour</span>
                <div class="dash-sticky-swatches" data-sticky-swatches></div>
            </div>
            <div class="dash-field">
                <label for="stickyRemind">Remind me at <span class="dash-optional">optional</span></label>
                <input id="stickyRemind" name="remind_at" type="datetime-local">
                <p class="dash-field-error" data-error-for="remind_at" hidden></p>
            </div>
            <label class="dash-switch">
                <input type="checkbox" name="is_pinned">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Pin to the top</span>
            </label>
            <div class="dash-field dash-field-wide">
                <span class="dash-label">Checklist <span class="dash-optional">optional</span></span>
                <ul class="dash-sticky-checklist" data-sticky-items></ul>
                <button type="button" class="dash-link-btn" data-sticky-add-item>+ Add item</button>
            </div>
        </div>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-sticky-delete>@include('partials.dashboard-icon', ['name' => 'eraser']) Delete</button>
            <button type="button" class="dash-btn dash-btn-primary" data-sticky-close>@include('partials.dashboard-icon', ['name' => 'check']) Done editing</button>
        </div>
    </form>
</dialog>
<script src="{{ asset('js/sticky-notes.js') }}?v={{ filemtime(public_path('js/sticky-notes.js')) }}" defer></script>
