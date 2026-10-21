@extends('layouts.dashboard')

@section('title', 'Roles')
@section('page_heading', 'Roles & Permissions')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Roles</h1>
        <p>Each role is a set of permissions. Give users roles to decide which portals and pages they can open.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Add Role
    </button>
</section>

<section class="dash-stats dash-stats-3">
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
        <span class="dash-stat-icon">@include('partials.dashboard-icon', ['name' => 'layers'])</span>
        <span class="dash-stat-label">Modules</span>
        <strong class="dash-stat-value" data-count-up="{{ $data['counts']['groups'] }}">{{ $data['counts']['groups'] }}</strong>
    </article>
</section>

<section
    class="dash-panel"
    data-roles
    data-datatable-url="{{ route('erp.roles.datatable') }}"
    data-resource-url="{{ route('erp.roles.index') }}"
    data-total-permissions="{{ $data['counts']['permissions'] }}"
>
    <div class="dash-panel-head">
        <div>
            <h2>All roles</h2>
            <p>Built-in roles can be edited but not deleted. A role in use must be removed from its users first.</p>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Role</th>
                    <th>Users</th>
                    <th>Permissions</th>
                    <th class="dash-hide-md">Type</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="roleModal" aria-labelledby="roleModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'shield'])</span>
            <div>
                <h3 id="roleModalTitle" data-modal-title>Add role</h3>
                <p>Tick what people with this role are allowed to do.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>

        <div class="dash-form-grid">
            <div class="dash-field">
                <label for="roleName">Name</label>
                <input id="roleName" name="name" required maxlength="100" autocomplete="off" placeholder="e.g. Librarian">
                <p class="dash-field-error" data-error-for="name" hidden></p>
            </div>
            <div class="dash-field">
                <label for="roleSlug">Slug <span class="dash-optional">from the name if empty</span></label>
                <input id="roleSlug" name="slug" maxlength="100" autocomplete="off" placeholder="librarian">
                <p class="dash-field-error" data-error-for="slug" hidden></p>
            </div>
            <div class="dash-field dash-field-wide">
                <label for="roleDescription">Description <span class="dash-optional">optional</span></label>
                <input id="roleDescription" name="description" maxlength="255" autocomplete="off">
                <p class="dash-field-error" data-error-for="description" hidden></p>
            </div>

            <div class="dash-field dash-field-wide">
                <div class="dash-perm-head">
                    <span class="settings-label">Permissions</span>
                    <span class="dash-muted" data-perm-count></span>
                </div>
                <p class="dash-perm-locked" data-perm-locked hidden>
                    @include('partials.dashboard-icon', ['name' => 'shield'])
                    The Administrator role always has full access to everything.
                </p>
                <div class="dash-perm-matrix" data-perm-matrix>
                    @foreach ($data['permission_groups'] as $group => $permissions)
                        <fieldset class="dash-perm-group">
                            <legend>
                                <label class="dash-perm-all">
                                    <input type="checkbox" data-perm-group="{{ $group }}">
                                    <span>{{ \Illuminate\Support\Str::headline($group) }}</span>
                                </label>
                            </legend>
                            @foreach ($permissions as $permission)
                                <label class="dash-perm-option" @if ($permission->description) title="{{ $permission->description }}" @endif>
                                    <input type="checkbox" name="permissions" value="{{ $permission->slug }}" data-group="{{ $group }}">
                                    <span>{{ $permission->name }}</span>
                                </label>
                            @endforeach
                        </fieldset>
                    @endforeach
                </div>
                <p class="dash-field-error" data-error-for="permissions" hidden></p>
            </div>
        </div>

        <p class="dash-form-error" data-form-error hidden></p>

        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Role</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-roles.js') }}?v={{ filemtime(public_path('js/admin-roles.js')) }}" defer></script>
@endsection
