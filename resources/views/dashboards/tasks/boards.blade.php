@extends('layouts.dashboard')

@section('title', 'Task Boards')
@section('page_heading', 'Tasks')

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Boards</h1>
        <p>A board per project or team, with To Do → In Progress → Review → Done columns.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-board-new>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        New Board
    </button>
</section>

@include('dashboards.tasks.tabs', ['tabCurrent' => 'tasks.boards'])

<section class="dash-board-cards" data-tasks-root data-boards data-api="{{ url('/api/erp') }}" data-board-url="{{ url('/tasks/boards') }}" data-user-id="{{ $user->id }}">
    <p class="dash-muted">Loading boards…</p>
</section>

<dialog class="dash-modal dash-form-modal" id="boardModal" aria-labelledby="boardModalTitle">
    <form data-board-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'layers'])</span>
            <div>
                <h3 id="boardModalTitle" data-board-title>New board</h3>
                <p>Members can add and move tasks; viewers only look.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid dash-form-scroll">
            @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name', 'required' => true, 'wide' => true, 'id' => 'boardName'])
            @include('dashboards.partials.field', ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'wide' => true, 'id' => 'boardDescription'])
            <div class="dash-field">
                <label for="boardVisibility">Who can see it</label>
                <select id="boardVisibility" name="visibility">
                    <option value="private">Only me and members</option>
                    <option value="department">A department</option>
                    <option value="everyone">All staff</option>
                </select>
            </div>
            @include('dashboards.partials.select', ['name' => 'department_id', 'label' => 'Department', 'options' => $data['departments'], 'id' => 'boardDepartment'])
            @include('dashboards.partials.field', ['name' => 'color', 'label' => 'Colour', 'type' => 'color', 'id' => 'boardColor'])
            <label class="dash-audience-list dash-field-wide">
                <span>Members <span class="dash-optional">ctrl / ⌘ to pick several</span></span>
                <select multiple size="5" data-board-members></select>
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
<script src="{{ asset('js/tasks.js') }}?v={{ filemtime(public_path('js/tasks.js')) }}" defer></script>
@endsection
