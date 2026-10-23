@extends('layouts.dashboard')

@section('title', $data['board']->name)
@section('page_heading', 'Tasks')

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>{{ $data['board']->name }}</h1>
        <p>{{ $data['board']->description ?: 'Drag cards between columns. Changes by others appear here live.' }}</p>
    </div>
    <div class="dash-head-actions">
        <span class="dash-live-dot" data-live-status hidden>Live</span>
        <button type="button" class="dash-btn dash-btn-primary" data-task-new>
            @include('partials.dashboard-icon', ['name' => 'plus'])
            New Task
        </button>
    </div>
</section>

@include('dashboards.tasks.tabs', ['tabCurrent' => 'tasks.boards'])

<section class="dash-kanban" data-tasks-root data-kanban data-board-id="{{ $data['board']->id }}" data-api="{{ url('/api/erp') }}" data-user-id="{{ $user->id }}">
    <p class="dash-muted">Loading the board…</p>
</section>

@include('dashboards.tasks.task-modal')
@endsection

@section('extra_js')
<script src="{{ asset('js/tasks.js') }}?v={{ filemtime(public_path('js/tasks.js')) }}" defer></script>
@endsection
