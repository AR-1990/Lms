@extends('layouts.dashboard')

@section('title', $data['account']->name)
@section('page_heading', 'User Profile')

@section('content')
@php
    $account = $data['account'];
    $permissionGroups = $account->roles
        ->flatMap->permissions
        ->unique('id')
        ->sortBy('name')
        ->groupBy('group');
@endphp

<a href="{{ route('admin.users') }}" class="dash-back-link">
    @include('partials.dashboard-icon', ['name' => 'arrow-left'])
    Back to users
</a>

<section class="dash-card dash-profile">
    <div class="dash-profile-cover" aria-hidden="true"></div>
    <div class="dash-profile-body">
        <span class="dash-profile-avatar" aria-hidden="true">{{ strtoupper(substr($account->name, 0, 1)) }}</span>
        <div class="dash-profile-info">
            <h1>{{ $account->name }}</h1>
            <p>
                @include('partials.dashboard-icon', ['name' => 'mail'])
                {{ $account->email }}
            </p>
            <div class="dash-badges">
                @unless ($account->is_active)
                    <span class="dash-badge dash-badge-system">Disabled</span>
                @endunless
                @forelse ($account->roles as $accountRole)
                    <span class="dash-badge dash-badge-brand">{{ $accountRole->name }}</span>
                @empty
                    <span class="dash-badge">Unassigned</span>
                @endforelse
            </div>
        </div>
        <div class="dash-profile-actions">
            <a href="{{ route('admin.users.edit', $account) }}" class="dash-btn dash-btn-primary">
                @include('partials.dashboard-icon', ['name' => 'pen'])
                Edit
            </a>
            @unless ($account->is($user))
                <button
                    type="button"
                    class="dash-btn dash-btn-danger"
                    data-user-delete
                    data-name="{{ $account->name }}"
                    data-delete-url="{{ route('erp.users.destroy', $account) }}"
                    data-redirect="{{ route('admin.users') }}"
                >
                    @include('partials.dashboard-icon', ['name' => 'eraser'])
                    Delete
                </button>
            @endunless
        </div>
    </div>
</section>

<div class="dash-grid-2">
    <section class="dash-panel">
        <div class="dash-panel-head">
            <div>
                <h2>Access &amp; Permissions</h2>
                <p>Everything this account can do, through its roles.</p>
            </div>
            <span class="dash-badge dash-badge-brand">{{ $permissionGroups->flatten()->count() }} permissions</span>
        </div>
        @if ($permissionGroups->isEmpty())
            <div class="dash-empty">
                @include('partials.dashboard-icon', ['name' => 'shield'])
                <strong>No permissions</strong>
                <span>Assign a role to grant access.</span>
            </div>
        @else
            <ul class="dash-list">
                @foreach ($permissionGroups as $group => $permissions)
                    <li>
                        <div>
                            <strong>{{ ucfirst($group) }}</strong>
                            <div class="dash-chips">
                                @foreach ($permissions as $permission)
                                    <span class="dash-chip">{{ $permission->name }}</span>
                                @endforeach
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="dash-panel">
        <div class="dash-panel-head">
            <div>
                <h2>Account Details</h2>
                <p>Sign-in and record information.</p>
            </div>
        </div>
        <ul class="dash-kv">
            <li><span>User ID</span><strong>#{{ $account->id }}</strong></li>
            <li><span>Username</span><strong>{{ $account->username ?? '—' }}</strong></li>
            <li><span>Phone</span><strong>{{ $account->phone ?? '—' }}</strong></li>
            <li><span>Campus</span><strong>{{ $account->campus?->name ?? '—' }}</strong></li>
            <li>
                <span>Status</span>
                <strong>
                    @if ($account->is_active)
                        <span class="dash-badge dash-badge-ok">Active</span>
                    @else
                        <span class="dash-badge dash-badge-system">Disabled</span>
                    @endif
                </strong>
            </li>
            <li><span>Last login</span><strong>{{ $account->last_login_at?->diffForHumans() ?? 'Never' }}</strong></li>
            <li><span>Member since</span><strong>{{ $account->created_at?->format('d M Y') }}</strong></li>
            <li>
                <span>Email verified</span>
                <strong>
                    @if ($account->email_verified_at)
                        <span class="dash-badge dash-badge-ok">Verified</span>
                    @else
                        <span class="dash-badge dash-badge-warn">Pending</span>
                    @endif
                </strong>
            </li>
        </ul>
    </section>
</div>

@include('dashboards.partials.documents-panel', ['owner' => 'users', 'ownerId' => $account->id, 'documentTypes' => $data['documentTypes']])
@endsection

@section('extra_js')
<script src="{{ asset('js/data-grid.js') }}?v={{ filemtime(public_path('js/data-grid.js')) }}" defer></script>
<script src="{{ asset('js/admin-users.js') }}?v={{ filemtime(public_path('js/admin-users.js')) }}" defer></script>
@endsection
