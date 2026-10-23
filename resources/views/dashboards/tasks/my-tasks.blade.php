@extends('layouts.dashboard')

@section('title', 'My Tasks')
@section('page_heading', 'Tasks')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>My Tasks</h1>
        <p>Everything assigned to you or created by you, from every board. Overdue tasks are marked red.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-task-new>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        New Task
    </button>
</section>

@include('dashboards.tasks.tabs', ['tabCurrent' => 'tasks.index'])

<section class="dash-panel" data-tasks-root data-my-tasks data-datatable-url="{{ route('erp.tasks.datatable') }}" data-api="{{ url('/api/erp') }}" data-user-id="{{ $user->id }}" data-open-id="{{ $data['open_id'] }}">
    <div class="dash-panel-head">
        <h2>Tasks</h2>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Whose</span>
                <select data-filter="scope">
                    <option value="mine">Mine</option>
                    <option value="assigned_by_me">I assigned to others</option>
                    @if ($user->hasRole('admin'))
                        <option value="all">Everyone's</option>
                    @endif
                </select>
            </label>
            <label class="dash-select">
                <span class="dash-visually-hidden">State</span>
                <select data-filter="state">
                    <option value="open">Open</option>
                    <option value="overdue">Overdue</option>
                    <option value="done">Done</option>
                    <option value="">All</option>
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Task</th>
                    <th class="dash-hide-md">Due</th>
                    <th>Status</th>
                    <th class="dash-hide-md">Assigned to</th>
                </tr>
            </thead>
        </table>
    </div>
</section>

@include('dashboards.tasks.task-modal')
@endsection

@section('extra_js')
<script src="{{ asset('js/tasks.js') }}?v={{ filemtime(public_path('js/tasks.js')) }}" defer></script>
@endsection
