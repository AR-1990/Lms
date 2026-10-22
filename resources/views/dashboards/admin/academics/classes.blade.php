@extends('layouts.dashboard')

@section('title', 'Classes')
@section('page_heading', 'Academics')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Classes</h1>
        <p>Grade levels in promotion order. Each class points to the class its students move up to.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Add Class
    </button>
</section>

@include('dashboards.admin.academics.tabs', ['current' => 'admin.academics.classes'])

<section class="dash-panel" data-academics="classes" data-datatable-url="{{ route('erp.classes.datatable') }}" data-resource-url="{{ route('erp.classes.index') }}">
    <div class="dash-panel-head">
        <div>
            <h2>All classes</h2>
            <p>A class with sections or curriculum can be made inactive but not deleted.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Status</span>
                <select data-filter="is_active">
                    <option value="">All statuses</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Class</th>
                    <th class="dash-hide-md">Wing</th>
                    <th class="dash-hide-md">Promotes to</th>
                    <th>Sections</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="academicsModal" aria-labelledby="academicsModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'layers'])</span>
            <div>
                <h3 id="academicsModalTitle" data-modal-title>Add class</h3>
                <p>Order decides the promotion ladder and report sorting.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>

        <div class="dash-form-grid">
            <div class="dash-field">
                <label for="className">Name</label>
                <input id="className" name="name" required maxlength="100" autocomplete="off" placeholder="Grade 5">
                <p class="dash-field-error" data-error-for="name" hidden></p>
            </div>
            <div class="dash-field">
                <label for="classCode">Code <span class="dash-optional">optional</span></label>
                <input id="classCode" name="code" maxlength="20" autocomplete="off" class="dash-uppercase" placeholder="G5">
                <p class="dash-field-error" data-error-for="code" hidden></p>
            </div>
            <div class="dash-field">
                <label for="classOrder">Order</label>
                <input id="classOrder" name="level_order" type="number" min="0" max="32000" required>
                <p class="dash-field-error" data-error-for="level_order" hidden></p>
            </div>
            <div class="dash-field">
                <label for="classWing">Wing <span class="dash-optional">optional</span></label>
                <select id="classWing" name="wing_id">
                    <option value="">No wing</option>
                    @foreach ($data['wings'] as $wing)
                        <option value="{{ $wing->id }}">{{ $wing->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="wing_id" hidden></p>
            </div>
            <div class="dash-field dash-field-wide">
                <label for="classNext">Promotes to <span class="dash-optional">empty for the final class</span></label>
                <select id="classNext" name="next_class_id">
                    <option value="">Leaves school / final class</option>
                    @foreach ($data['all_classes'] as $class)
                        <option value="{{ $class->id }}">{{ $class->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="next_class_id" hidden></p>
            </div>
            <label class="dash-switch dash-field-wide">
                <input type="checkbox" name="is_active" checked>
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Active</span>
            </label>
        </div>

        <p class="dash-form-error" data-form-error hidden></p>

        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Class</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-academics.js') }}?v={{ filemtime(public_path('js/admin-academics.js')) }}" defer></script>
@endsection
