@extends('layouts.dashboard')

@section('title', 'Roles')
@section('page_heading', 'Roles & Permissions')

@section('content')
<section class="dash-page-intro">
    <h1>Roles</h1>
    <p>Review system roles, assigned users, and permission coverage.</p>
</section>

<section class="dash-stats">
    <article class="dash-stat dash-stat--violet">
        <span class="dash-stat-icon">@include('partials.dashboard-icon', ['name' => 'key'])</span>
        <span class="dash-stat-label">Roles</span>
        <strong class="dash-stat-value" data-count-up="{{ $data['counts']['roles'] }}">{{ $data['counts']['roles'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-icon">@include('partials.dashboard-icon', ['name' => 'shield'])</span>
        <span class="dash-stat-label">Permissions</span>
        <strong class="dash-stat-value" data-count-up="{{ $data['counts']['permissions'] }}">{{ $data['counts']['permissions'] }}</strong>
    </article>
    <article class="dash-stat dash-stat--blue">
        <span class="dash-stat-icon">@include('partials.dashboard-icon', ['name' => 'grid'])</span>
        <span class="dash-stat-label">Groups</span>
        <strong class="dash-stat-value" data-count-up="{{ $data['permission_groups']->count() }}">{{ $data['permission_groups']->count() }}</strong>
    </article>
    <article class="dash-stat dash-stat--green">
        <span class="dash-stat-icon">@include('partials.dashboard-icon', ['name' => 'check'])</span>
        <span class="dash-stat-label">Access Model</span>
        <strong class="dash-stat-value dash-stat-value-sm">RBAC</strong>
    </article>
</section>

<div class="dash-grid-2">
    <section class="dash-panel">
        <div class="dash-panel-head">
            <div>
                <h2>Role Directory</h2>
                <p>Users and permissions per role</p>
            </div>
        </div>
        <div class="dash-table-wrap">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Users</th>
                        <th>Permissions</th>
                        <th>Type</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['roles'] as $role)
                        <tr>
                            <td>
                                <strong>{{ $role->name }}</strong>
                                <span class="dash-muted">{{ $role->slug }}</span>
                            </td>
                            <td>{{ $role->users_count }}</td>
                            <td>{{ $role->permissions->count() }}</td>
                            <td>
                                <span @class(['dash-badge', 'dash-badge-ok' => $role->is_system])>
                                    {{ $role->is_system ? 'System' : 'Custom' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="dash-panel">
        <div class="dash-panel-head">
            <div>
                <h2>Permission Groups</h2>
                <p>Grouped by module</p>
            </div>
        </div>
        <ul class="dash-list">
            @foreach ($data['permission_groups'] as $group => $permissions)
                <li>
                    <div>
                        <strong>{{ ucfirst($group) }}</strong>
                        <span class="dash-muted">{{ $permissions->pluck('name')->join(', ') }}</span>
                    </div>
                    <span class="dash-badge dash-badge-brand">{{ $permissions->count() }}</span>
                </li>
            @endforeach
        </ul>
    </section>
</div>
@endsection
