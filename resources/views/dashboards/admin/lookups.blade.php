@extends('layouts.dashboard')

@section('title', 'Lookups')
@section('page_heading', 'Lookups')

@section('extra_css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-dt@2.1.8/css/dataTables.dataTables.min.css">
@endsection

@section('content')
@php
    $types = $data['types'];
@endphp

<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Lookups</h1>
        <p>Dropdown master data used by every module. Add, rename, reorder, or deactivate options without touching code.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-lookup-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Add Option
    </button>
</section>

<div
    class="dash-lookups"
    data-lookups
    data-datatable-url="{{ route('erp.lookups.datatable') }}"
    data-resource-url="{{ route('erp.lookups.index') }}"
    data-types-url="{{ route('erp.lookups.types') }}"
>
    <aside class="dash-panel dash-lookup-types">
        <div class="dash-panel-head">
            <div>
                <h2>Lists</h2>
                <p><span data-types-count>{{ $types->count() }}</span> lists · <span data-options-count>{{ $types->sum('total') }}</span> options</p>
            </div>
        </div>
        <label class="dash-search dash-type-search">
            <span class="dash-visually-hidden">Find a list</span>
            @include('partials.dashboard-icon', ['name' => 'search'])
            <input type="search" placeholder="Find a list" data-type-search autocomplete="off">
        </label>
        <ul class="dash-type-list" data-type-list>
            <li>
                <button type="button" class="dash-type-item is-active" data-type-filter="">
                    <span class="dash-type-name">All lists</span>
                    <span class="dash-type-count">{{ $types->sum('total') }}</span>
                </button>
            </li>
            @foreach ($types as $type)
                <li>
                    <button type="button" class="dash-type-item" data-type-filter="{{ $type['type'] }}">
                        <span class="dash-type-name">{{ $type['label'] }}</span>
                        <span class="dash-type-count">{{ $type['active'] }}/{{ $type['total'] }}</span>
                    </button>
                </li>
            @endforeach
        </ul>
    </aside>

    <section class="dash-panel dash-lookup-grid">
        <div class="dash-panel-head">
            <div>
                <h2 data-grid-title>All lists</h2>
                <p data-grid-subtitle>Every option across all lists.</p>
            </div>
            <label class="dash-select dash-status-filter">
                <span class="dash-visually-hidden">Status</span>
                <select data-status-filter>
                    <option value="">All statuses</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </label>
        </div>

        <div class="dash-grid-wrap">
            <table class="dash-table dash-datatable" data-grid>
                <thead>
                    <tr>
                        <th>Option</th>
                        <th>List</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                    </tr>
                </thead>
            </table>
        </div>
    </section>
</div>

<dialog class="dash-modal dash-form-modal" id="lookupModal" aria-labelledby="lookupModalTitle">
    <form data-lookup-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'layers'])</span>
            <div>
                <h3 id="lookupModalTitle" data-modal-title>Add option</h3>
                <p data-modal-subtitle>Options appear in dropdowns as soon as they are saved.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">
                @include('partials.dashboard-icon', ['name' => 'close'])
            </button>
        </div>

        <div class="dash-form-grid">
            <div class="dash-field dash-field-wide">
                <label for="lookupType">List</label>
                <input id="lookupType" name="type" list="lookupTypeOptions" required maxlength="50" autocomplete="off" placeholder="blood_group">
                <datalist id="lookupTypeOptions" data-type-datalist>
                    @foreach ($types as $type)
                        <option value="{{ $type['type'] }}">{{ $type['label'] }}</option>
                    @endforeach
                </datalist>
                <p class="dash-field-hint" data-type-hint>Pick an existing list or type a new key in lowercase_with_underscores.</p>
                <p class="dash-field-error" data-error-for="type" hidden></p>
            </div>

            <div class="dash-field dash-field-wide">
                <label for="lookupName">Name</label>
                <input id="lookupName" name="name" required maxlength="255" autocomplete="off">
                <p class="dash-field-error" data-error-for="name" hidden></p>
            </div>

            <div class="dash-field">
                <label for="lookupCode">Code <span class="dash-optional">optional</span></label>
                <input id="lookupCode" name="code" maxlength="50" autocomplete="off">
                <p class="dash-field-error" data-error-for="code" hidden></p>
            </div>

            <div class="dash-field">
                <label for="lookupOrder">Display order</label>
                <input id="lookupOrder" name="sort_order" type="number" min="0" max="100000" step="1" value="0">
                <p class="dash-field-error" data-error-for="sort_order" hidden></p>
            </div>

            <div class="dash-field dash-field-wide">
                <label for="lookupDescription">Description <span class="dash-optional">optional</span></label>
                <textarea id="lookupDescription" name="description" maxlength="255" rows="2"></textarea>
                <p class="dash-field-error" data-error-for="description" hidden></p>
            </div>

            <label class="dash-switch dash-field-wide">
                <input type="checkbox" name="is_active" checked>
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Active — shown in dropdowns</span>
            </label>
        </div>

        <p class="dash-form-error" data-form-error hidden></p>

        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>
                @include('partials.dashboard-icon', ['name' => 'check'])
                <span data-submit-label>Save Option</span>
            </button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@2.1.8/js/dataTables.min.js" defer></script>
<script src="{{ asset('js/data-grid.js') }}?v={{ filemtime(public_path('js/data-grid.js')) }}" defer></script>
<script src="{{ asset('js/admin-lookups.js') }}?v={{ filemtime(public_path('js/admin-lookups.js')) }}" defer></script>
@endsection
