@extends('layouts.dashboard')

@section('title', 'Vouchers')
@section('page_heading', 'Accounts')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
@php
    $canDraft = $user->hasPermission('manage-vouchers');
    $canPost = $user->hasPermission('post-vouchers');
@endphp

<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Vouchers</h1>
        <p>Journal, receipt, payment and contra vouchers. Drafts can change; posted vouchers are only reversed.</p>
    </div>
    @if ($canDraft)
        <button type="button" class="dash-btn dash-btn-primary" data-voucher-create>
            @include('partials.dashboard-icon', ['name' => 'plus'])
            New Voucher
        </button>
    @endif
</section>

@include('dashboards.accounting.tabs', ['current' => 'accounts.vouchers'])

<section
    class="dash-panel"
    data-vouchers
    data-can-draft="{{ $canDraft ? '1' : '0' }}"
    data-can-post="{{ $canPost ? '1' : '0' }}"
    data-datatable-url="{{ route('erp.ledger.vouchers.datatable') }}"
    data-resource-url="{{ url('/api/erp/ledger/vouchers') }}"
>
    <div class="dash-panel-head">
        <div>
            <h2>All vouchers</h2>
            <p>Open a voucher to see its lines, post it, or reverse it.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="">All statuses</option>
                    <option value="draft">Draft</option>
                    <option value="posted">Posted</option>
                    <option value="reversed">Reversed</option>
                </select>
            </label>
            <label class="dash-select">
                <span class="dash-visually-hidden">Type</span>
                <select data-filter="voucher_type_id">
                    <option value="">All types</option>
                    @foreach ($data['voucher_types'] as $type)
                        <option value="{{ $type->id }}">{{ $type->code }} — {{ $type->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Voucher</th>
                    <th class="dash-hide-md">Narration</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

{{-- Voucher editor (create / edit draft) --}}
<dialog class="dash-modal dash-form-modal dash-modal-wide" id="voucherModal" aria-labelledby="voucherModalTitle">
    <form data-voucher-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'pen'])</span>
            <div>
                <h3 id="voucherModalTitle" data-voucher-title>New voucher</h3>
                <p>Enter amounts in rupees. Debits must equal credits.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>

        <div class="dash-form-grid">
            <div class="dash-field">
                <label for="voucherType">Type</label>
                <select id="voucherType" name="voucher_type_id" required>
                    @foreach ($data['voucher_types'] as $type)
                        <option value="{{ $type->id }}">{{ $type->code }} — {{ $type->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="voucher_type_id" hidden></p>
            </div>
            <div class="dash-field">
                <label for="voucherDate">Date</label>
                <input id="voucherDate" name="entry_date" type="date" required>
                <p class="dash-field-error" data-error-for="entry_date" hidden></p>
            </div>
            <div class="dash-field dash-field-wide">
                <label for="voucherNarration">Narration <span class="dash-optional">optional</span></label>
                <input id="voucherNarration" name="narration" maxlength="1000" autocomplete="off" placeholder="What is this voucher for?">
                <p class="dash-field-error" data-error-for="narration" hidden></p>
            </div>

            <div class="dash-field dash-field-wide">
                <div class="dash-voucher-lines">
                    <div class="dash-voucher-row dash-voucher-row-head" aria-hidden="true">
                        <span>Account</span><span>Debit</span><span>Credit</span><span>Line note</span><span></span>
                    </div>
                    <div data-lines></div>
                    <div class="dash-voucher-row dash-voucher-row-foot">
                        <button type="button" class="dash-btn dash-btn-ghost dash-btn-sm" data-line-add>@include('partials.dashboard-icon', ['name' => 'plus']) Add line</button>
                        <strong data-total="debit">Rs 0.00</strong>
                        <strong data-total="credit">Rs 0.00</strong>
                        <span class="dash-voucher-difference" data-difference></span>
                        <span></span>
                    </div>
                </div>
                <p class="dash-field-error" data-error-for="lines" hidden></p>
            </div>
        </div>

        <template data-line-template>
            <div class="dash-voucher-row" data-line>
                <select name="account_id" aria-label="Account" required>
                    <option value="">Choose account…</option>
                    @foreach ($data['postable_accounts'] as $account)
                        <option value="{{ $account->id }}">{{ $account->code }} {{ $account->name }}</option>
                    @endforeach
                </select>
                <input name="debit" type="number" min="0" step="0.01" inputmode="decimal" aria-label="Debit" placeholder="0.00">
                <input name="credit" type="number" min="0" step="0.01" inputmode="decimal" aria-label="Credit" placeholder="0.00">
                <input name="description" maxlength="255" aria-label="Line note" autocomplete="off">
                <button type="button" class="dash-action dash-action-delete" data-line-remove data-tooltip="Remove line" aria-label="Remove line">@include('partials.dashboard-icon', ['name' => 'close'])</button>
            </div>
        </template>

        <p class="dash-form-error" data-form-error hidden></p>

        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Draft</button>
        </div>
    </form>
</dialog>

{{-- Voucher detail (view / post / reverse / delete) --}}
<dialog class="dash-modal dash-form-modal dash-modal-wide" id="voucherViewModal" aria-labelledby="voucherViewTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'book'])</span>
        <div>
            <h3 id="voucherViewTitle" data-view-title>Voucher</h3>
            <p data-view-subtitle></p>
        </div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-detail-body" data-view-body></div>
    <div class="dash-reverse-box" data-reverse-box hidden>
        <div class="dash-field">
            <label for="reverseReason">Why is this voucher being reversed?</label>
            <input id="reverseReason" maxlength="200" autocomplete="off" data-reverse-reason>
            <p class="dash-field-error" data-reverse-error hidden></p>
        </div>
    </div>
    <div class="dash-modal-actions" data-view-actions></div>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-vouchers.js') }}?v={{ filemtime(public_path('js/admin-vouchers.js')) }}" defer></script>
@endsection
