@extends('layouts.dashboard')

@section('title', 'Issue & Return')
@section('page_heading', 'Library')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Issue &amp; Return</h1>
        <p>Lend a book by its accession number to a student or staff member. Returns after the due date are fined per day; a student's fine goes on a challan. Families are reminded about overdue books.</p>
    </div>
</section>

@include('dashboards.library.tabs', ['current' => 'admin.library.issues'])

<section class="dash-panel">
    <form class="dash-form-grid dash-form-grid-3 dash-library-issue" data-issue-form data-url="{{ route('erp.library.issues.store') }}" data-borrowers-url="{{ route('erp.library.borrowers') }}" novalidate>
        @include('dashboards.partials.field', ['name' => 'accession_no', 'label' => 'Accession no', 'required' => true, 'placeholder' => 'Scan or type ACC-…', 'id' => 'issueAccession'])
        <div class="dash-field">
            <label for="issueType">Borrower</label>
            <select id="issueType" name="borrower_type">
                <option value="student">Student</option>
                <option value="employee">Staff</option>
            </select>
        </div>
        <div class="dash-field">
            <label for="issueBorrower">Name, admission no or staff code</label>
            <input id="issueBorrower" list="issueBorrowers" autocomplete="off" data-borrower-search placeholder="Type 2 letters…">
            <datalist id="issueBorrowers" data-borrower-list></datalist>
            <input type="hidden" name="borrower_id">
            <p class="dash-field-error" data-error-for="borrower_id" hidden></p>
        </div>
        @include('dashboards.partials.field', ['name' => 'due_on', 'label' => 'Due back', 'type' => 'date', 'id' => 'issueDue', 'hint' => 'Empty = the normal loan period.'])
        <p class="dash-form-error dash-field-wide" data-form-error hidden></p>
        <div><button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Issue book</button></div>
    </form>
</section>

<section class="dash-panel" data-library-issues data-datatable-url="{{ route('erp.library.issues.datatable') }}" data-resource-url="{{ url('/api/erp/library/issues') }}">
    <div class="dash-panel-head">
        <div><h2>Loans</h2><p>Return a book when it comes back.</p></div>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="issued">Out now</option>
                    <option value="overdue">Overdue</option>
                    <option value="returned">Returned</option>
                    <option value="lost">Lost</option>
                    <option value="">All</option>
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Book</th>
                    <th>Borrower</th>
                    <th class="dash-hide-md">Issued</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th>Accession no</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>
@endsection

@section('extra_js')
<script src="{{ asset('js/library.js') }}?v={{ filemtime(public_path('js/library.js')) }}" defer></script>
@endsection
