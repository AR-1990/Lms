@extends('layouts.dashboard')

@section('title', 'Announcements')
@section('page_heading', 'Announcements')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Announcements</h1>
        <p>Write once, choose who sees it, then publish now or schedule it. People get it in their notices and bell; public ones also appear on the website.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        New Announcement
    </button>
</section>

@include('dashboards.communication.tabs', ['tabCurrent' => 'admin.announcements.index'])

<section class="dash-panel" data-announcements data-datatable-url="{{ route('erp.announcements.datatable') }}" data-resource-url="{{ url('/api/erp/announcements') }}">
    <div class="dash-panel-head">
        <div>
            <h2>All announcements</h2>
            <p>Drafts are only visible here. "Reads" counts people who opened it.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="">Every status</option>
                    @foreach (\App\Models\Announcement::STATUSES as $status)
                        <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="dash-select">
                <span class="dash-visually-hidden">Category</span>
                <select data-filter="category_id">
                    <option value="">Every category</option>
                    @foreach ($data['categories'] as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Announcement</th>
                    <th class="dash-hide-md">For</th>
                    <th class="dash-hide-md">Publish</th>
                    <th>Status</th>
                    <th class="dash-hide-md">Reads</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="announcementModal" aria-labelledby="announcementModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'megaphone'])</span>
            <div>
                <h3 id="announcementModalTitle" data-modal-title>New announcement</h3>
                <p>Saved as a draft. Publish it from the list when ready.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid dash-form-scroll">
            @include('dashboards.partials.field', ['name' => 'title', 'label' => 'Title', 'required' => true, 'wide' => true])
            @include('dashboards.partials.select', ['name' => 'category_id', 'label' => 'Category', 'options' => $data['categories']])
            @include('dashboards.partials.field', ['name' => 'publish_at', 'label' => 'Publish at', 'type' => 'datetime-local', 'hint' => 'empty = when you press Publish'])
            @include('dashboards.partials.field', ['name' => 'expires_at', 'label' => 'Hide after', 'type' => 'datetime-local'])
            @include('dashboards.partials.field', ['name' => 'summary', 'label' => 'Short summary', 'type' => 'textarea', 'wide' => true, 'hint' => 'shown in lists, the bell and SMS'])
            <div class="dash-field dash-field-wide">
                <label for="announcementBody">Full text</label>
                <textarea id="announcementBody" name="body" rows="6" maxlength="50000" required></textarea>
                <p class="dash-field-error" data-error-for="body" hidden></p>
            </div>

            <fieldset class="dash-field dash-field-wide dash-audience">
                <legend>Who should see it</legend>
                <div class="dash-audience-groups">
                    @foreach (['everyone' => 'Everyone', 'all_students' => 'All students', 'all_parents' => 'All parents', 'all_employees' => 'All staff'] as $groupKey => $groupLabel)
                        <label class="dash-check-chip"><input type="checkbox" data-audience-group="{{ $groupKey }}"> <span>{{ $groupLabel }}</span></label>
                    @endforeach
                </div>
                <div class="dash-audience-lists">
                    @foreach ([
                        'class' => ['Classes', $data['classes']],
                        'section' => ['Sections', $data['sections']],
                        'department' => ['Departments', $data['departments']],
                        'role' => ['Roles', $data['roles']],
                    ] as $listKey => [$listLabel, $listOptions])
                        <label class="dash-audience-list">
                            <span>{{ $listLabel }} <span class="dash-optional">ctrl / ⌘ to pick several</span></span>
                            <select multiple size="4" data-audience-list="{{ $listKey }}">
                                @foreach ($listOptions as $option)
                                    <option value="{{ $option->id }}">{{ $listKey === 'section' ? $option->class_name.' '.$option->name : $option->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endforeach
                </div>
                <p class="dash-field-error" data-error-for="audiences" hidden></p>
            </fieldset>

            @foreach ([
                'is_public' => ['Show on the website', false],
                'is_pinned' => ['Pin to the top', false],
                'is_urgent' => ['Urgent (red banner)', false],
                'requires_acknowledgement' => ['Ask people to confirm "I have read this"', false],
                'send_push' => ['Mobile push (always shown in the portal bell)', true],
                'send_sms' => ['SMS', false],
                'send_email' => ['Email', false],
            ] as $switchName => [$switchLabel, $switchOn])
                <label class="dash-switch">
                    <input type="checkbox" name="{{ $switchName }}" @checked($switchOn)>
                    <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                    <span>{{ $switchLabel }}</span>
                </label>
            @endforeach
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save draft</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-form-modal" id="announcementStatsModal" aria-labelledby="announcementStatsTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'eye'])</span>
        <div>
            <h3 id="announcementStatsTitle" data-stats-title>Reach</h3>
            <p>People it reaches and how many have opened it.</p>
        </div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-stats dash-stats-compact" data-stats-body></div>
    <div class="dash-modal-actions">
        <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Close</button>
    </div>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-announcements.js') }}?v={{ filemtime(public_path('js/admin-announcements.js')) }}" defer></script>
@endsection
