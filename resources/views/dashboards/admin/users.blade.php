@extends('layouts.dashboard')

@section('title', 'Users')
@section('page_heading', 'User Directory')

@section('content')
@php
    $users = $data['users'];
    $filters = $data['filters'];
    $isFiltered = filled($filters['search']) || filled($filters['role']);
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
        <strong class="dash-stat-value" data-count-up="{{ $data['counts']['total'] }}">{{ number_format($data['counts']['total']) }}</strong>
    </article>
    <article class="dash-stat dash-stat--violet">
        <span class="dash-stat-icon">@include('partials.dashboard-icon', ['name' => 'key'])</span>
        <span class="dash-stat-label">Roles</span>
        <strong class="dash-stat-value" data-count-up="{{ $data['counts']['active_roles'] }}">{{ number_format($data['counts']['active_roles']) }}</strong>
    </article>
    <article class="dash-stat dash-stat--green">
        <span class="dash-stat-icon">@include('partials.dashboard-icon', ['name' => 'search'])</span>
        <span class="dash-stat-label">{{ $isFiltered ? 'Matching' : 'Listed' }}</span>
        <strong class="dash-stat-value" data-count-up="{{ $users->total() }}">{{ number_format($users->total()) }}</strong>
    </article>
</section>

<section class="dash-panel">
    <form method="GET" action="{{ route('admin.users') }}" class="dash-filters" role="search">
        <label class="dash-search">
            <span class="dash-visually-hidden">Search users</span>
            @include('partials.dashboard-icon', ['name' => 'search'])
            <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search by name or email" autocomplete="off">
        </label>

        <label class="dash-select">
            <span class="dash-visually-hidden">Filter by role</span>
            <select name="role" data-auto-submit>
                <option value="">All roles</option>
                @foreach ($data['roles'] as $role)
                    <option value="{{ $role->slug }}" @selected($filters['role'] === $role->slug)>{{ $role->name }}</option>
                @endforeach
            </select>
        </label>

        <button type="submit" class="dash-btn dash-btn-ghost">Filter</button>

        @if ($isFiltered)
            <a href="{{ route('admin.users') }}" class="dash-filters-reset">Clear</a>
        @endif
    </form>

    <div class="dash-table-wrap">
        <table class="dash-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Roles</th>
                    <th class="dash-hide-sm">Joined</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $account)
                    <tr>
                        <td>
                            <a href="{{ route('admin.users.show', $account) }}" class="dash-person">
                                <span class="dash-person-avatar" aria-hidden="true">{{ strtoupper(substr($account->name, 0, 1)) }}</span>
                                <span>
                                    <span class="dash-person-name">{{ $account->name }}</span>
                                    <span class="dash-person-meta">{{ $account->email }}</span>
                                </span>
                            </a>
                        </td>
                        <td>
                            <div class="dash-badges">
                                @forelse ($account->roles as $accountRole)
                                    <span class="dash-badge dash-badge-brand">{{ $accountRole->name }}</span>
                                @empty
                                    <span class="dash-badge">Unassigned</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="dash-hide-sm">{{ $account->created_at?->format('d M Y') }}</td>
                        <td class="dash-col-actions">
                            @include('dashboards.admin.users.actions', ['account' => $account])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <div class="dash-empty">
                                @include('partials.dashboard-icon', ['name' => 'inbox'])
                                <strong>{{ $isFiltered ? 'No users match these filters' : 'No users yet' }}</strong>
                                <span>{{ $isFiltered ? 'Try a different name, email or role.' : 'Add the first account to get started.' }}</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links('partials.dashboard-pagination') }}
</section>
@endsection
