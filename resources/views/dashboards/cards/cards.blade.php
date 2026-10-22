@extends('layouts.dashboard')

@section('title', 'ID Cards')
@section('page_heading', 'ID Cards')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>ID Cards</h1>
        <p>Issue cards to a section or to all staff, then tick and print. Each card's QR opens a public check page.</p>
    </div>
    <div class="dash-head-actions">
        <button type="button" class="dash-btn dash-btn-ghost" data-print-selected disabled>
            @include('partials.dashboard-icon', ['name' => 'check'])
            Print Selected
        </button>
        <button type="button" class="dash-btn dash-btn-primary" data-open-issue>
            @include('partials.dashboard-icon', ['name' => 'plus'])
            Issue Cards
        </button>
    </div>
</section>

@include('dashboards.cards.tabs', ['current' => 'admin.cards.index'])

<section class="dash-panel" data-cards data-datatable-url="{{ route('erp.id-cards.datatable') }}" data-resource-url="{{ url('/api/erp/id-cards') }}" data-print-url="{{ route('erp.id-cards.print') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Issued cards</h2>
            <p>A lost card is reissued as a new card; the old QR then shows "replaced".</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Holder</span>
                <select data-filter="holder_type">
                    <option value="">Students &amp; staff</option>
                    <option value="student">Students</option>
                    <option value="employee">Staff</option>
                </select>
            </label>
            <label class="dash-select">
                <span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="active">Active</option>
                    @foreach (array_diff(\App\Models\IdCard::STATUSES, ['active']) as $status)
                        <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                    @endforeach
                    <option value="">All</option>
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th class="dash-col-check"><input type="checkbox" data-check-all aria-label="Select all on this page"></th>
                    <th>Holder</th>
                    <th class="dash-hide-md">Template</th>
                    <th class="dash-hide-md">Valid until</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="issueModal" aria-labelledby="issueTitle">
    <form data-issue-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'key'])</span>
            <div>
                <h3 id="issueTitle">Issue ID cards</h3>
                <p>Holders who already have an active card are skipped.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            <div class="dash-field">
                <label for="issueHolder">For</label>
                <select id="issueHolder" name="holder_type">
                    <option value="student">Students</option>
                    <option value="employee">All current staff</option>
                </select>
                <p class="dash-field-error" data-error-for="holder_type" hidden></p>
            </div>
            <div class="dash-field" data-student-only>
                <label for="issueSection">Section <span class="dash-optional">empty = whole school</span></label>
                <select id="issueSection" name="section_id">
                    <option value="">All sections</option>
                    @foreach ($data['sections'] as $section)
                        <option value="{{ $section->id }}">{{ $section->class_name }} {{ $section->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="section_id" hidden></p>
            </div>
            <div class="dash-field">
                <label for="issueTemplate">Template <span class="dash-optional">default if empty</span></label>
                <select id="issueTemplate" name="card_template_id">
                    <option value="">Default template</option>
                    @foreach ($data['templates'] as $template)
                        <option value="{{ $template->id }}" data-card-type="{{ $template->card_type }}">{{ $template->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="card_template_id" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'valid_until', 'label' => 'Valid until', 'type' => 'date', 'hint' => 'students: end of session'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary">@include('partials.dashboard-icon', ['name' => 'check']) Issue</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-form-modal" id="cardStatusModal" aria-labelledby="cardStatusTitle">
    <form data-status-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'alert'])</span>
            <div>
                <h3 id="cardStatusTitle" data-status-title>Card</h3>
                <p>The QR check page shows the new status at once.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            <div class="dash-field">
                <label for="cardStatus">Mark as</label>
                <select id="cardStatus" name="status">
                    <option value="lost">Lost</option>
                    <option value="revoked">Revoked</option>
                    <option value="expired">Expired</option>
                </select>
            </div>
            @include('dashboards.partials.field', ['name' => 'reason', 'label' => 'Reason', 'id' => 'cardStatusReason'])
            <label class="dash-switch dash-field-wide">
                <input type="checkbox" name="reissue">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Issue a replacement card now</span>
            </label>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary">@include('partials.dashboard-icon', ['name' => 'check']) Save</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-cards.js') }}?v={{ filemtime(public_path('js/admin-cards.js')) }}" defer></script>
@endsection
