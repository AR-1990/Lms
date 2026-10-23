@extends('layouts.dashboard')

@section('title', 'Reports')
@section('page_heading', 'Reports')

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Reports</h1>
        <p>Finance, fees, students, staff and operations — pick a report, set the period, then print or download it as CSV.</p>
    </div>
</section>

<div class="dash-reports" data-reports data-api="{{ url('/api/erp/reports') }}">
    <aside class="dash-panel dash-report-list" aria-label="Reports">
        <label class="dash-report-search">
            <span class="dash-visually-hidden">Find a report</span>
            <input type="search" placeholder="Find a report…" data-report-search autocomplete="off">
        </label>
        @foreach ($data['catalogue'] as $group)
            <div class="dash-report-group" data-report-group>
                <h2>{{ $group['group'] }}</h2>
                <ul>
                    @foreach ($group['reports'] as $report)
                        <li>
                            <button type="button" data-report="{{ $report['key'] }}" data-filters="{{ implode(' ', $report['filters']) }}" data-search="{{ strtolower($report['title'].' '.$report['description'].' '.$group['group']) }}">
                                <strong>{{ $report['title'] }}</strong>
                                <span>{{ $report['description'] }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
        <p class="dash-muted" data-report-none hidden>No report matches.</p>
    </aside>

    <section class="dash-panel dash-report-view" data-report-view aria-live="polite">
        <div class="dash-report-empty" data-report-empty>
            @include('partials.dashboard-icon', ['name' => 'chart'])
            <strong>Choose a report</strong>
            <span>Every figure is worked out live from the records, so it is always up to date.</span>
        </div>

        <div data-report-body hidden>
            <div class="dash-panel-head">
                <div>
                    <h2 data-report-title></h2>
                    <p data-report-description></p>
                </div>
                <div class="dash-head-actions">
                    <button type="button" class="dash-btn dash-btn-ghost" data-report-export="csv">Export CSV</button>
                    <button type="button" class="dash-btn dash-btn-primary" data-report-export="pdf">@include('partials.dashboard-icon', ['name' => 'book']) Print</button>
                </div>
            </div>

            <form class="dash-report-filters" data-report-filters novalidate>
                <label data-filter-for="from"><span>From</span><input type="date" name="from"></label>
                <label data-filter-for="to"><span>To</span><input type="date" name="to"></label>
                <label data-filter-for="as_of"><span>As of</span><input type="date" name="as_of"></label>
                <label data-filter-for="class_id"><span>Class</span>
                    <select name="class_id">
                        <option value="">All classes</option>
                        @foreach ($data['classes'] as $class)<option value="{{ $class->id }}">{{ $class->name }}</option>@endforeach
                    </select>
                </label>
                <label data-filter-for="group_by"><span>Show by</span>
                    <select name="group_by">
                        <option value="day">Day</option>
                        <option value="method">Payment method</option>
                        <option value="class">Class</option>
                    </select>
                </label>
                <div class="dash-report-quick" data-filter-for="from">
                    <button type="button" class="dash-chip" data-range="month">This month</button>
                    <button type="button" class="dash-chip" data-range="last-month">Last month</button>
                    <button type="button" class="dash-chip" data-range="year">This year</button>
                </div>
            </form>

            <p class="dash-muted dash-report-period" data-report-period></p>
            <div class="dash-report-summary" data-report-summary></div>
            <ul class="dash-bars dash-report-chart" data-report-chart></ul>
            <div class="dash-grid-wrap">
                <table class="dash-table dash-report-table" data-report-table></table>
            </div>
            <div class="dash-report-pager" data-report-pager hidden>
                <button type="button" class="dash-btn dash-btn-ghost" data-page="prev">‹ Previous</button>
                <span data-page-info></span>
                <button type="button" class="dash-btn dash-btn-ghost" data-page="next">Next ›</button>
            </div>
        </div>
    </section>
</div>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-reports.js') }}?v={{ filemtime(public_path('js/admin-reports.js')) }}" defer></script>
@endsection
