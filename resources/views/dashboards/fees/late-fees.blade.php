@extends('layouts.dashboard')

@section('title', 'Late Fees & Automation')
@section('page_heading', 'Fees')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Late Fees &amp; Automation</h1>
        <p>Fee work that runs by itself: the month's challans on a day you pick, and late fines on overdue challans every night. Each fine is posted to the ledger and printed on the challan.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) Add Late Fee Rule</button>
</section>

@include('dashboards.fees.tabs', ['current' => 'accounts.fees.late-fees'])

<section class="dash-panel" data-fee-automation data-url="{{ route('erp.fees.automation') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Automatic monthly challans</h2>
            <p>On this day every month the challans for all classes of the current session are created, once. Fee staff get a notification with the total.</p>
        </div>
    </div>
    <form class="dash-form-grid dash-late-policy" novalidate>
        <div class="dash-field">
            <label for="autoDay">Generate challans on</label>
            <select id="autoDay" name="fee_auto_generate_day">
                <option value="">Off — I generate them myself</option>
                @for ($day = 1; $day <= 28; $day++)
                    <option value="{{ $day }}">Day {{ $day }} of the month</option>
                @endfor
                <option value="31">The last day of the month</option>
            </select>
            <p class="dash-field-error" data-error-for="fee_auto_generate_day" hidden></p>
        </div>
        <div class="dash-field">
            <label for="reminderDays">Remind families before the due date</label>
            <select id="reminderDays" name="fee_reminder_days">
                <option value="">Off</option>
                @foreach ([1, 2, 3, 5, 7] as $days)
                    <option value="{{ $days }}">{{ $days }} day(s) before</option>
                @endforeach
            </select>
            <p class="dash-field-error" data-error-for="fee_reminder_days" hidden></p>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div><button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save</button></div>
    </form>
</section>

<section class="dash-panel" data-fees="late-fees" data-datatable-url="{{ route('erp.fees.late-fee-rules.datatable') }}" data-resource-url="{{ url('/api/erp/fees/late-fee-rules') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Late fee rules</h2>
            <p>Checked every night after midnight. A fixed or percent fine is added once; a per-day fine grows each day until its limit. Paid challans are never fined.</p>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Rule</th>
                    <th>Fine</th>
                    <th>Applies to</th>
                    <th class="dash-hide-md">Charged so far</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="feesModal" aria-labelledby="feesModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'clock'])</span>
            <div>
                <h3 id="feesModalTitle" data-modal-title>Add late fee rule</h3>
                <p data-late-example>Fines start the day after the grace days end.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid dash-form-scroll">
            @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name (printed on the challan)', 'type' => 'text', 'required' => true, 'wide' => true, 'id' => 'lateName', 'placeholder' => 'e.g. Late payment fine'])
            <div class="dash-field">
                <label for="lateCalc">How it is worked out</label>
                <select id="lateCalc" name="calculation">
                    <option value="fixed">Fixed amount, once</option>
                    <option value="per_day">Amount for every day late</option>
                    <option value="percent">Percent of the challan, once</option>
                </select>
                <p class="dash-field-error" data-error-for="calculation" hidden></p>
            </div>
            <div class="dash-field">
                <label for="lateAmount" data-amount-label>Amount (Rs)</label>
                <input type="number" id="lateAmount" name="amount" class="form-control" min="0.01" step="0.01" required>
                <p class="dash-field-error" data-error-for="amount" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'grace_days', 'label' => 'Grace days after the due date', 'type' => 'number', 'min' => 0, 'max' => 90, 'id' => 'lateGrace'])
            @include('dashboards.partials.field', ['name' => 'max_amount', 'label' => 'Never more than (Rs, optional)', 'type' => 'number', 'min' => 0, 'step' => '0.01', 'id' => 'lateMax'])
            <div class="dash-field">
                <label for="lateType">Applies to</label>
                <select id="lateType" name="invoice_type">
                    <option value="">Every challan</option>
                    <option value="monthly">Monthly challans</option>
                    <option value="admission">Admission challans</option>
                    <option value="exam">Exam fee challans</option>
                    <option value="other">Other challans</option>
                </select>
                <p class="dash-field-error" data-error-for="invoice_type" hidden></p>
            </div>
            <div class="dash-field">
                <label for="lateHead">Fine shows as</label>
                <select id="lateHead" name="fee_head_id">
                    @foreach ($data['all_heads'] as $head)
                        <option value="{{ $head->id }}" @selected($head->code === 'LPF')>{{ $head->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="fee_head_id" hidden></p>
            </div>
            <label class="dash-switch">
                <input type="checkbox" name="is_active" checked>
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Active</span>
            </label>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-fees.js') }}?v={{ filemtime(public_path('js/admin-fees.js')) }}" defer></script>
@endsection
