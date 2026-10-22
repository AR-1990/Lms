@extends('layouts.dashboard')

@php
    $type = $data['type'];
    [$heading, $intro, $add] = [
        'terms' => ['Exam Terms', 'Terms of the session; the final term carries the yearly result.', 'Add Term'],
        'scales' => ['Grading Scales', 'Grade bands by percentage; the default scale is used when an exam has none.', 'Add Scale'],
        'halls' => ['Exam Halls', 'Rooms used for seating plans and their capacity.', 'Add Hall'],
        'skills' => ['Skills', 'Co-scholastic skills rated each term (reading, teamwork, sports…).', 'Add Skill'],
        'rules' => ['Alert Rules', 'When a student\'s figures cross a rule, the student is flagged "at risk" and an alert opens.', 'Add Rule'],
    ][$type];
    $performance = in_array($type, ['skills', 'rules'], true);
@endphp

@section('title', $heading)
@section('page_heading', $performance ? 'Performance' : 'Exams')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>{{ $heading }}</h1>
        <p>{{ $intro }}</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) {{ $add }}</button>
</section>

@include($performance ? 'dashboards.performance.tabs' : 'dashboards.exams.tabs', ['current' => 'admin.exams.setup.'.$type])

<section class="dash-panel" data-exam-setup="{{ $type }}" data-datatable-url="{{ url('/api/erp/exam-setup/'.$type) }}" data-resource-url="{{ url('/api/erp/exam-setup/'.$type) }}" data-session-id="{{ $data['current_session_id'] }}">
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Name</th>
                    <th class="dash-hide-md">{{ ['terms' => 'Dates', 'scales' => 'Bands', 'halls' => 'Layout', 'skills' => 'Group', 'rules' => 'Condition'][$type] }}</th>
                    <th>{{ ['terms' => 'Weight', 'scales' => 'Default', 'halls' => 'Capacity', 'skills' => 'Status', 'rules' => 'Status'][$type] }}</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal {{ $type === 'scales' ? 'dash-modal-wide' : '' }}" id="setupModal" aria-labelledby="setupTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'layers'])</span>
            <div><h3 id="setupTitle" data-modal-title>{{ $add }}</h3><p>{{ $intro }}</p></div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name', 'required' => true, 'wide' => true])
            @switch($type)
                @case('terms')
                    @include('dashboards.partials.select', ['name' => 'academic_session_id', 'label' => 'Session', 'options' => $data['sessions'], 'required' => true])
                    @include('dashboards.partials.field', ['name' => 'weightage_percent', 'label' => 'Weight in yearly result (%)', 'type' => 'number', 'min' => 0, 'max' => 100])
                    @include('dashboards.partials.field', ['name' => 'starts_on', 'label' => 'Starts on', 'type' => 'date'])
                    @include('dashboards.partials.field', ['name' => 'ends_on', 'label' => 'Ends on', 'type' => 'date'])
                    @include('dashboards.partials.field', ['name' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'min' => 0])
                    <label class="dash-switch"><input type="checkbox" name="is_final"><span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span><span>Final term</span></label>
                    @break
                @case('scales')
                    <label class="dash-switch dash-field-wide"><input type="checkbox" name="is_default"><span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span><span>Default scale</span></label>
                    @break
                @case('skills')
                    @include('dashboards.partials.select', ['name' => 'skill_group_id', 'label' => 'Group', 'options' => $data['skill_groups']])
                    @include('dashboards.partials.field', ['name' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'min' => 0])
                    <label class="dash-switch"><input type="checkbox" name="is_active" checked><span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span><span>Active</span></label>
                    @break
                @case('rules')
                    <div class="dash-field">
                        <label for="ruleMetric">Watch</label>
                        <select id="ruleMetric" name="metric">
                            @foreach ($data['metrics'] as $metric)
                                <option value="{{ $metric }}">{{ \Illuminate\Support\Str::headline($metric) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="dash-field">
                        <label for="ruleOperator">When it is</label>
                        <select id="ruleOperator" name="operator">
                            @foreach ($data['operators'] as $operator)
                                <option value="{{ $operator }}">{{ ['<' => 'below', '<=' => 'at or below', '>' => 'above', '>=' => 'at or above'][$operator] }}</option>
                            @endforeach
                        </select>
                    </div>
                    @include('dashboards.partials.field', ['name' => 'threshold', 'label' => 'Value', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'required' => true])
                    <label class="dash-switch"><input type="checkbox" name="notify_class_teacher" checked><span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span><span>Tell the class teacher</span></label>
                    <label class="dash-switch"><input type="checkbox" name="notify_parents"><span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span><span>Tell parents</span></label>
                    <label class="dash-switch"><input type="checkbox" name="is_active" checked><span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span><span>Active</span></label>
                    @break
                @default
                    @include('dashboards.partials.field', ['name' => 'capacity', 'label' => 'Capacity', 'type' => 'number', 'min' => 1, 'required' => true])
                    @include('dashboards.partials.field', ['name' => 'rows', 'label' => 'Rows', 'type' => 'number', 'min' => 1])
                    @include('dashboards.partials.field', ['name' => 'columns', 'label' => 'Columns (desks per row)', 'type' => 'number', 'min' => 1])
                    <label class="dash-switch"><input type="checkbox" name="is_active" checked><span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span><span>Active</span></label>
            @endswitch
        </div>
        @if ($type === 'scales')
            <div class="dash-line-items">
                <h4 class="dash-form-section">Grade bands</h4>
                <div data-bands></div>
                <button type="button" class="dash-btn dash-btn-ghost dash-btn-sm" data-add-band>@include('partials.dashboard-icon', ['name' => 'plus']) Add band</button>
                <p class="dash-field-error" data-error-for="items" hidden></p>
            </div>
            <template data-band-template>
                <div class="dash-line-item dash-line-item-band" data-band>
                    <input type="text" maxlength="5" placeholder="Grade" data-band-field="grade" aria-label="Grade">
                    <input type="number" step="0.01" min="0" max="100" placeholder="From %" data-band-field="min_percent" aria-label="From percent">
                    <input type="number" step="0.01" min="0" max="100" placeholder="To %" data-band-field="max_percent" aria-label="To percent">
                    <input type="number" step="0.01" min="0" placeholder="GPA" data-band-field="grade_point" aria-label="Grade point">
                    <input type="text" maxlength="100" placeholder="Remark" data-band-field="remark" aria-label="Remark">
                    <button type="button" class="dash-icon-btn" data-remove-band aria-label="Remove band">@include('partials.dashboard-icon', ['name' => 'close'])</button>
                </div>
            </template>
        @endif
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-exams.js') }}?v={{ filemtime(public_path('js/admin-exams.js')) }}" defer></script>
@endsection
