@extends('layouts.dashboard')

@section('title', 'Appraisals')
@section('page_heading', 'HR')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Appraisals</h1>
        <p>Every staff appraisal of a launched cycle. Open one to see the scores, score it yourself, change the reviewer or reopen it.</p>
    </div>
    <a class="dash-btn dash-btn-ghost" href="{{ route('admin.hr.appraisal-cycles') }}">@include('partials.dashboard-icon', ['name' => 'calendar']) Cycles</a>
</section>

@include('dashboards.hr.tabs', ['current' => 'admin.hr.appraisals'])

@if ($data['cycles']->isEmpty())
    <div class="dash-empty-note">
        @include('partials.dashboard-icon', ['name' => 'trending-up'])
        <p>No cycle has been launched yet. Go to <a href="{{ route('admin.hr.appraisal-cycles') }}">Appraisal Cycles</a> and press Launch.</p>
    </div>
@endif

<section class="dash-panel" data-appraisals="appraisals" data-datatable-url="{{ route('erp.appraisals.datatable') }}" data-resource-url="{{ url('/api/erp/appraisals') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Staff appraisals</h2>
            <p>Missing reviewers show as “Not set” — open the appraisal to choose one.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select"><span class="dash-visually-hidden">Cycle</span>
                <select data-filter="appraisal_cycle_id">
                    @foreach ($data['cycles'] as $cycle)<option value="{{ $cycle->id }}" @selected((int) request('cycle') === $cycle->id)>{{ $cycle->name }}{{ $cycle->status === 'closed' ? ' (closed)' : '' }}</option>@endforeach
                    <option value="">All cycles</option>
                </select>
            </label>
            <label class="dash-select"><span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="">Any status</option>
                    <option value="self_review">Self-review</option>
                    <option value="draft">With reviewer</option>
                    <option value="submitted">Awaiting acknowledgement</option>
                    <option value="acknowledged">Acknowledged</option>
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Reviewer</th>
                    <th>Self</th>
                    <th>Score</th>
                    <th>Rating</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

@include('dashboards.partials.appraisal-modal')
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-appraisals.js') }}?v={{ filemtime(public_path('js/admin-appraisals.js')) }}" defer></script>
@endsection
