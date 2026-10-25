@extends('layouts.dashboard')

@section('title', $data['student']->fullName())
@section('page_heading', 'Student Profile')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
@php
    $student = $data['student'];
    $current = $student->enrollments->firstWhere('academic_session_id', $data['current_session_id']);
    $relations = $data['lookups']['guardian_relation']->keyBy('id');
@endphp

<a href="{{ route('admin.students') }}" class="dash-back-link">
    @include('partials.dashboard-icon', ['name' => 'arrow-left'])
    Back to students
</a>

<section class="dash-card dash-profile" data-student-profile data-student-id="{{ $student->id }}" data-resource-url="{{ route('erp.students.index') }}" data-guardians-url="{{ url('/api/erp/guardians') }}" data-invoices-url="{{ route('erp.fees.invoices.index') }}">
    <div class="dash-profile-cover" aria-hidden="true"></div>
    <div class="dash-profile-body">
        <span class="dash-profile-avatar" aria-hidden="true">{{ strtoupper(substr($student->first_name, 0, 1)) }}</span>
        <div class="dash-profile-info">
            <h1>{{ $student->fullName() }}</h1>
            <p>{{ $student->admission_no }} · {{ $current ? $current->schoolClass?->name.' '.$current->section?->name : 'Not enrolled this session' }}</p>
            <div class="dash-badges">
                <span @class(['dash-badge', 'dash-badge-ok' => $student->current_status === 'active', 'dash-badge-system' => $student->current_status !== 'active'])>{{ ucfirst($student->current_status) }}</span>
                @if ($current?->roll_no)
                    <span class="dash-badge">Roll {{ $current->roll_no }}</span>
                @endif
            </div>
        </div>
    </div>
</section>

<div class="dash-grid-2">
    <section class="dash-panel">
        <div class="dash-panel-head">
            <div>
                <h2>Guardians</h2>
                <p>The primary contact gets school messages; a parent login uses their phone number.</p>
            </div>
        </div>
        <ul class="dash-list" data-guardian-list>
            @foreach ($student->guardians as $guardian)
                <li>
                    <div>
                        <strong>{{ $guardian->name }}</strong>
                        <span class="dash-muted">{{ $relations[$guardian->pivot->relation_id]->name ?? 'Guardian' }} · {{ $guardian->phone }}{{ $guardian->cnic ? ' · '.$guardian->cnic : '' }}</span>
                        <div class="dash-badges">
                            @if ($guardian->pivot->is_primary_contact)<span class="dash-badge dash-badge-brand">Primary</span>@endif
                            @if ($guardian->pivot->is_fee_payer)<span class="dash-badge">Fee payer</span>@endif
                            @if ($guardian->user_id)<span class="dash-badge dash-badge-ok">Has login</span>@endif
                        </div>
                    </div>
                    <div class="dash-actions">
                        @unless ($guardian->user_id)
                            <button type="button" class="dash-link-btn" data-parent-login="{{ $guardian->id }}" data-name="{{ $guardian->name }}">Create login</button>
                        @endunless
                        <button type="button" class="dash-action dash-action-delete" data-unlink="{{ $guardian->id }}" data-name="{{ $guardian->name }}" data-tooltip="Unlink">@include('partials.dashboard-icon', ['name' => 'eraser'])</button>
                    </div>
                </li>
            @endforeach
        </ul>
        <form class="dash-inline-form" data-guardian-form novalidate>
            <h4>Link a guardian</h4>
            <div class="dash-form-grid">
                @include('dashboards.partials.select', ['name' => 'relation_id', 'label' => 'Relation', 'options' => $data['lookups']['guardian_relation'], 'required' => true, 'id' => 'gRelation'])
                @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name', 'required' => true, 'id' => 'gName'])
                @include('dashboards.partials.field', ['name' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => true, 'id' => 'gPhone'])
                @include('dashboards.partials.field', ['name' => 'cnic', 'label' => 'CNIC', 'hint' => 'links an existing parent', 'id' => 'gCnic'])
                <label class="dash-switch dash-field-wide">
                    <input type="checkbox" name="is_primary_contact">
                    <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                    <span>Primary contact</span>
                </label>
            </div>
            <p class="dash-field-error" data-error-for="guardian" hidden></p>
            <p class="dash-form-error" data-form-error hidden></p>
            <div class="dash-inline-actions"><button type="submit" class="dash-btn dash-btn-ghost">Link guardian</button></div>
        </form>
    </section>

    <section class="dash-panel">
        <div class="dash-panel-head">
            <div>
                <h2>Details</h2>
                <p>Edit the record from the Students list.</p>
            </div>
        </div>
        <ul class="dash-kv">
            <li><span>Gender</span><strong>{{ $student->gender?->name }}</strong></li>
            <li><span>Date of birth</span><strong>{{ $student->date_of_birth?->format('d M Y') }}</strong></li>
            <li><span>B-Form</span><strong>{{ $student->b_form_no ?? '—' }}</strong></li>
            <li><span>Admitted on</span><strong>{{ $student->admission_date?->format('d M Y') }}</strong></li>
            <li><span>Address</span><strong>{{ trim(($student->address_line ?? '').' '.($student->city ?? '')) ?: '—' }}</strong></li>
            <li><span>Student login</span><strong>{{ $student->user ? $student->user->username ?? $student->user->email : 'None' }}</strong></li>
        </ul>
        @if ($current)
            <form class="dash-inline-form" data-enrollment-form data-enrollment-id="{{ $current->id }}" data-roll-no="{{ $current->roll_no }}" novalidate>
                <h4>Class &amp; section ({{ $current->academicSession?->name }})</h4>
                <div class="dash-form-grid">
                    <div class="dash-field">
                        <label for="enrolSection">Section</label>
                        <select id="enrolSection" name="section_id">
                            @foreach ($data['sections'] as $section)
                                <option value="{{ $section->id }}" data-class-id="{{ $section->class_id }}" @selected($section->id === $current->section_id)>{{ $section->class_name }} {{ $section->name }}</option>
                            @endforeach
                        </select>
                        <p class="dash-field-error" data-error-for="section_id" hidden></p>
                    </div>
                    @include('dashboards.partials.field', ['name' => 'roll_no', 'label' => 'Roll no', 'id' => 'enrolRoll'])
                </div>
                <p class="dash-form-error" data-form-error hidden></p>
                <div class="dash-inline-actions"><button type="submit" class="dash-btn dash-btn-ghost">Save class</button></div>
            </form>
        @endif
        <form class="dash-inline-form" data-status-form novalidate>
            <h4>Status</h4>
            <div class="dash-form-grid">
                <div class="dash-field">
                    <label for="studentStatus">New status</label>
                    <select id="studentStatus" name="status">
                        @foreach (\App\Models\Student::STATUSES as $status)
                            <option value="{{ $status }}" @selected($status === $student->current_status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    <p class="dash-field-error" data-error-for="status" hidden></p>
                </div>
                @include('dashboards.partials.field', ['name' => 'effective_on', 'label' => 'Effective on', 'type' => 'date', 'required' => true, 'id' => 'statusOn'])
                @include('dashboards.partials.select', ['name' => 'reason_id', 'label' => 'Reason (leaving)', 'options' => $data['lookups']['withdrawal_reason'], 'id' => 'statusReason'])
                @include('dashboards.partials.field', ['name' => 'remarks', 'label' => 'Remarks', 'id' => 'statusRemarks'])
            </div>
            <p class="dash-form-error" data-form-error hidden></p>
            <div class="dash-inline-actions"><button type="submit" class="dash-btn dash-btn-ghost">Save status</button></div>
        </form>
    </section>
</div>

<section class="dash-panel" data-student-years data-url="{{ route('erp.students.history', $student) }}">
    <div class="dash-panel-head">
        <div>
            <h2>Year by year</h2>
            <p>Each session this student studied in: class, how the year ended, published results, attendance and fees.</p>
        </div>
    </div>
    <div class="dash-table-wrap">
        <table class="dash-table dash-years-table">
            <thead><tr><th>Session</th><th>Class</th><th>Year ended</th><th>Results</th><th class="dash-col-num">Attendance</th><th class="dash-col-num">Fees billed</th><th class="dash-col-num">Paid</th><th class="dash-col-num">Due</th></tr></thead>
            <tbody data-year-rows><tr><td colspan="8" class="dash-muted">Loading…</td></tr></tbody>
        </table>
    </div>
</section>

<section class="dash-panel">
    <div class="dash-panel-head">
        <div>
            <h2>Fee challans</h2>
            <p>Receive payments from the Fees → Receipts screen.</p>
        </div>
        <span class="dash-badge dash-badge-warn" data-fee-due>…</span>
    </div>
    <div class="dash-table-wrap">
        <table class="dash-table">
            <thead><tr><th>Challan</th><th>Month</th><th class="dash-col-num">Total</th><th class="dash-col-num">Balance</th><th>Status</th></tr></thead>
            <tbody data-invoice-rows><tr><td colspan="5" class="dash-muted">Loading…</td></tr></tbody>
        </table>
    </div>
</section>

<section class="dash-panel">
    <div class="dash-panel-head">
        <div>
            <h2>History</h2>
            <p>Status changes on this record.</p>
        </div>
    </div>
    <ul class="dash-kv">
        @foreach ($student->statusHistories as $history)
            <li><span>{{ $history->effective_on?->format('d M Y') }}</span><strong>{{ ucfirst($history->to_status) }}{{ $history->reason ? ' — '.$history->reason->name : '' }}{{ $history->remarks ? ' · '.$history->remarks : '' }}</strong></li>
        @endforeach
    </ul>
</section>

@php
    $canRefund = auth()->user()->hasPermission('issue-refunds');
@endphp
<section class="dash-panel" data-student-leaving
         data-url="{{ route('erp.students.clearance', $student) }}"
         data-certificate-url="{{ route('erp.students.leaving-certificate', $student) }}"
         data-certificate-pdf-url="{{ route('erp.students.leaving-certificate.pdf', $student) }}"
         data-refunds-url="{{ url('/api/erp/fees/refunds') }}"
         data-adjust-url="{{ route('erp.fees.students.adjust-deposit', $student) }}">
    <div class="dash-panel-head">
        <div>
            <h2>Leaving &amp; clearance</h2>
            <p>When the student leaves: clear the dues, take back school items, pay back the security deposit, then issue the School Leaving Certificate.</p>
        </div>
        <span class="dash-badge" data-clear-badge>…</span>
    </div>
    <ul class="dash-leave-checks" data-clear-list></ul>
    <div class="dash-leave-actions">
        @if ($canRefund)
            <button type="button" class="dash-btn dash-btn-ghost" data-adjust-deposit hidden>@include('partials.dashboard-icon', ['name' => 'refresh']) Pay dues from deposit</button>
            <button type="button" class="dash-btn dash-btn-ghost" data-open-refund hidden>@include('partials.dashboard-icon', ['name' => 'wallet']) Refund deposit / advance</button>
        @endif
        <button type="button" class="dash-btn dash-btn-primary" data-open-certificate hidden>@include('partials.dashboard-icon', ['name' => 'check']) Issue leaving certificate</button>
        <button type="button" class="dash-btn dash-btn-ghost" data-print-certificate hidden>@include('partials.dashboard-icon', ['name' => 'printer']) Print certificate</button>
    </div>
    <div class="dash-table-wrap" data-refunds-wrap hidden>
        <table class="dash-table">
            <thead><tr><th>Refund</th><th>Paid from</th><th class="dash-col-num">Amount</th><th>Status</th><th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th></tr></thead>
            <tbody data-refund-rows></tbody>
        </table>
    </div>
</section>

@if ($canRefund)
    <dialog class="dash-modal dash-form-modal" id="refundModal" aria-labelledby="refundModalTitle">
        <form novalidate>
            <div class="dash-modal-head">
                <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'wallet'])</span>
                <div>
                    <h3 id="refundModalTitle">Pay back to the family</h3>
                    <p>Posted to the ledger as a payment voucher. A refund paid by mistake can be reversed.</p>
                </div>
                <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
            </div>
            <div class="dash-form-grid">
                <div class="dash-field dash-field-wide">
                    <label for="refundSource">Pay back from</label>
                    <select id="refundSource" name="source" required>
                        <option value="deposit">Security deposit</option>
                        <option value="advance">Unused advance fee</option>
                    </select>
                    <p class="dash-field-error" data-error-for="source" hidden></p>
                </div>
                @include('dashboards.partials.field', ['name' => 'amount', 'label' => 'Amount (Rs)', 'type' => 'number', 'required' => true, 'min' => 1, 'step' => 1, 'id' => 'refundAmount'])
                <div class="dash-field">
                    <label for="refundMethod">Paid by</label>
                    <select id="refundMethod" name="payment_method_id" required>
                        @foreach ($data['payment_methods'] as $method)
                            <option value="{{ $method->id }}">{{ $method->name }}</option>
                        @endforeach
                    </select>
                    <p class="dash-field-error" data-error-for="payment_method_id" hidden></p>
                </div>
                @include('dashboards.partials.field', ['name' => 'refunded_on', 'label' => 'Date', 'type' => 'date', 'required' => true, 'id' => 'refundDate'])
                @include('dashboards.partials.field', ['name' => 'reference_no', 'label' => 'Cheque / transfer no', 'id' => 'refundReference'])
                @include('dashboards.partials.field', ['name' => 'reason', 'label' => 'Reason', 'type' => 'textarea', 'required' => true, 'wide' => true, 'id' => 'refundReason'])
            </div>
            <p class="dash-form-error" data-form-error hidden></p>
            <div class="dash-modal-actions">
                <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
                <button type="submit" class="dash-btn dash-btn-primary">@include('partials.dashboard-icon', ['name' => 'check']) Pay refund</button>
            </div>
        </form>
    </dialog>

    <dialog class="dash-modal" id="depositAdjustModal" aria-labelledby="depositAdjustTitle">
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'refresh'])</span>
            <div>
                <h3 id="depositAdjustTitle">Pay dues from the deposit?</h3>
                <p data-adjust-text></p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="button" class="dash-btn dash-btn-primary" data-adjust-yes>@include('partials.dashboard-icon', ['name' => 'check']) Yes, pay from deposit</button>
        </div>
    </dialog>

    <dialog class="dash-modal dash-form-modal" id="refundReverseModal" aria-labelledby="refundReverseTitle">
        <form novalidate>
            <div class="dash-modal-head">
                <span class="dash-modal-icon">@include('partials.dashboard-icon', ['name' => 'refresh'])</span>
                <div>
                    <h3 id="refundReverseTitle">Reverse refund</h3>
                    <p data-reverse-text>The voucher is reversed and the money counts as held again.</p>
                </div>
                <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
            </div>
            <div class="dash-form-grid">
                @include('dashboards.partials.field', ['name' => 'reason', 'label' => 'Why is it reversed?', 'required' => true, 'wide' => true, 'id' => 'refundReverseReason'])
            </div>
            <p class="dash-form-error" data-form-error hidden></p>
            <div class="dash-modal-actions">
                <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
                <button type="submit" class="dash-btn dash-btn-primary">Reverse</button>
            </div>
        </form>
    </dialog>
@endif

<dialog class="dash-modal dash-form-modal" id="certificateModal" aria-labelledby="certificateModalTitle">
    <form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'file'])</span>
            <div>
                <h3 id="certificateModalTitle">School Leaving Certificate</h3>
                <p>The certificate number is given once and kept if you issue it again.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'issued_on', 'label' => 'Date of issue', 'type' => 'date', 'required' => true, 'id' => 'certificateDate'])
            <div class="dash-field">
                <label for="certificateConduct">Conduct <span class="dash-optional">optional</span></label>
                <select id="certificateConduct" name="conduct_id">
                    <option value="">—</option>
                    @foreach ($data['lookups']['conduct'] as $conduct)
                        <option value="{{ $conduct->id }}">{{ $conduct->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="conduct_id" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'remarks', 'label' => 'Remarks', 'type' => 'textarea', 'wide' => true, 'id' => 'certificateRemarks'])
            <p class="dash-field-error dash-field-wide" data-error-for="slc" hidden></p>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary">@include('partials.dashboard-icon', ['name' => 'check']) Issue certificate</button>
        </div>
    </form>
</dialog>

@include('dashboards.partials.documents-panel', ['owner' => 'students', 'ownerId' => $student->id, 'documentTypes' => $data['document_types']])

<dialog class="dash-modal dash-form-modal" id="parentLoginModal" aria-labelledby="parentLoginTitle">
    <form data-parent-login-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'key'])</span>
            <div>
                <h3 id="parentLoginTitle">Parent login</h3>
                <p data-parent-login-name>They will sign in with their phone number.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'wide' => true, 'id' => 'parentPassword'])
            <p class="dash-field-error dash-field-wide" data-error-for="phone" hidden></p>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary">Create login</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/data-grid.js') }}?v={{ filemtime(public_path('js/data-grid.js')) }}" defer></script>
<script src="{{ asset('js/resource-screen.js') }}?v={{ filemtime(public_path('js/resource-screen.js')) }}" defer></script>
<script src="{{ asset('js/admin-users.js') }}?v={{ filemtime(public_path('js/admin-users.js')) }}" defer></script>
<script src="{{ asset('js/admin-students.js') }}?v={{ filemtime(public_path('js/admin-students.js')) }}" defer></script>
@endsection
