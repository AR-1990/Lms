@extends('layouts.dashboard')

@section('title', 'Library Books')
@section('page_heading', 'Library')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Books</h1>
        <p>The catalogue. Each physical book is a copy with its own accession number — write it on the book's spine label and scan or type it when lending.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) Add Book</button>
</section>

@include('dashboards.library.tabs', ['current' => 'admin.library.books'])

<section class="dash-panel">
    <form class="dash-form-grid dash-form-grid-3 dash-library-rules" data-library-rules data-url="{{ route('erp.library.settings') }}" novalidate>
        @include('dashboards.partials.field', ['name' => 'library_loan_days', 'label' => 'Loan period (days)', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 90, 'id' => 'libLoanDays'])
        @include('dashboards.partials.field', ['name' => 'library_max_books', 'label' => 'Books at a time', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 20, 'id' => 'libMaxBooks'])
        @include('dashboards.partials.field', ['name' => 'library_fine_per_day', 'label' => 'Late fine per day (Rs)', 'type' => 'number', 'required' => true, 'min' => 0, 'step' => '1', 'id' => 'libFine'])
        <div><button type="submit" class="dash-btn dash-btn-ghost" data-submit>Save rules</button></div>
    </form>
</section>

<section class="dash-panel" data-library-books data-datatable-url="{{ route('erp.library.books.datatable') }}" data-resource-url="{{ url('/api/erp/library/books') }}" data-copy-url="{{ url('/api/erp/library/copies') }}">
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Book</th>
                    <th class="dash-hide-md">Category</th>
                    <th class="dash-hide-md">Shelf</th>
                    <th class="dash-col-num">Available</th>
                    <th>Author</th>
                    <th>ISBN</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="bookModal" aria-labelledby="bookModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'book'])</span>
            <div>
                <h3 id="bookModalTitle" data-modal-title>Add book</h3>
                <p>Copies get the next accession numbers automatically.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-scroll">
            <div class="dash-form-grid">
                @include('dashboards.partials.field', ['name' => 'title', 'label' => 'Title', 'required' => true, 'wide' => true])
                @include('dashboards.partials.field', ['name' => 'author', 'label' => 'Author'])
                @include('dashboards.partials.select', ['name' => 'category_id', 'label' => 'Category', 'options' => $data['categories']])
                @include('dashboards.partials.field', ['name' => 'isbn', 'label' => 'ISBN'])
                @include('dashboards.partials.field', ['name' => 'publisher', 'label' => 'Publisher'])
                @include('dashboards.partials.field', ['name' => 'edition', 'label' => 'Edition'])
                @include('dashboards.partials.field', ['name' => 'shelf', 'label' => 'Shelf', 'placeholder' => 'A-3'])
                @include('dashboards.partials.field', ['name' => 'copies', 'label' => 'Copies to add', 'type' => 'number', 'min' => 0, 'max' => 200, 'hint' => 'New copies only; existing copies are listed under Copies.'])
                <label class="dash-switch">
                    <input type="checkbox" name="is_active" checked>
                    <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                    <span>In use</span>
                </label>
                @include('dashboards.partials.field', ['name' => 'description', 'label' => 'Notes', 'type' => 'textarea', 'wide' => true])
            </div>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-modal-wide" id="copiesModal" aria-labelledby="copiesTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'layers'])</span>
        <div>
            <h3 id="copiesTitle" data-copies-title>Copies</h3>
            <p>Mark a copy damaged, lost or withdrawn to stop lending it.</p>
        </div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-form-scroll">
        <div class="dash-table-wrap"><table class="dash-table">
            <thead><tr><th>Accession no</th><th>With</th><th>Status</th></tr></thead>
            <tbody data-copies-rows></tbody>
        </table></div>
    </div>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/library.js') }}?v={{ filemtime(public_path('js/library.js')) }}" defer></script>
@endsection
