@extends('layouts.dashboard')

@section('title', 'Users')
@section('page_heading', 'User Directory')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
@php
    $counts = $data['counts'];
@endphp

<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Users</h1>
        <p>Create, review, update and remove portal accounts across campus.</p>
    </div>
    <a href="{{ route('admin.users.create') }}" class="dash-btn dash-btn-primary">
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Add User
    </a>
</section>

<section class="dash-stats dash-stats-3">
    <article class="dash-stat">
        <span class="dash-stat-icon">@include('partials.dashboard-icon', ['name' => 'users'])</span>
        <span class="dash-stat-label">Total Users</span>
        <strong class="dash-stat-value" data-count-up="{{ $counts['total'] }}">{{ number_format($counts['total']) }}</strong>
    </article>
    <article class="dash-stat dash-stat--green">
        <span class="dash-stat-icon">@include('partials.dashboard-icon', ['name' => 'check'])</span>
        <span class="dash-stat-label">Active</span>
        <strong class="dash-stat-value" data-count-up="{{ $counts['active'] }}">{{ number_format($counts['active']) }}</strong>
    </article>
    <article class="dash-stat dash-stat--violet">
        <span class="dash-stat-icon">@include('partials.dashboard-icon', ['name' => 'key'])</span>
        <span class="dash-stat-label">Roles</span>
        <strong class="dash-stat-value" data-count-up="{{ $counts['roles'] }}">{{ number_format($counts['roles']) }}</strong>
    </article>
</section>

<section
    class="dash-panel"
    data-users
    data-datatable-url="{{ route('erp.users.datatable') }}"
    data-resource-url="{{ route('erp.users.index') }}"
    data-page-url="{{ route('admin.users') }}"
>
    <div class="dash-panel-head">
        <div>
            <h2>All accounts</h2>
            <p>Search by name, email, username or phone.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Filter by role</span>
                <select data-role-filter>
                    <option value="">All roles</option>
                    @foreach ($data['roles'] as $role)
                        <option value="{{ $role->slug }}">{{ $role->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="dash-select dash-status-filter">
                <span class="dash-visually-hidden">Status</span>
                <select data-status-filter>
                    <option value="">All statuses</option>
                    <option value="1">Active</option>
                    <option value="0">Disabled</option>
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Sign-in</th>
                    <th>Roles</th>
                    <th class="dash-hide-md">Status</th>
                    <th class="dash-hide-md">Last login</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-users.js') }}?v={{ filemtime(public_path('js/admin-users.js')) }}" defer></script>
@endsection
