@extends('layouts.dashboard')

@section('title', 'Fee Structures')
@section('page_heading', 'Fees')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Fee Structures</h1>
        <p>One structure per class per session. Changes apply to challans raised after saving.</p>
    </div>
    <div class="dash-head-actions">
        <button type="button" class="dash-btn dash-btn-ghost" data-open-copy>
            @include('partials.dashboard-icon', ['name' => 'layers'])
            Copy From Session
        </button>
        <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
            @include('partials.dashboard-icon', ['name' => 'plus'])
            Add Structure
        </button>
    </div>
</section>

@include('dashboards.fees.tabs', ['current' => 'accounts.fees.structures'])

<section class="dash-panel" data-fees="structures" data-datatable-url="{{ route('erp.fees.structures.datatable') }}" data-resource-url="{{ url('/api/erp/fees/structures') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Structures</h2>
            <p>Monthly and admission totals count mandatory lines only.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Session</span>
                <select data-filter="academic_session_id">
                    @foreach ($data['sessions'] as $session)
                        <option value="{{ $session->id }}" @selected($session->id === $data['current_session_id'])>{{ $session->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Class</th>
                    <th class="dash-hide-md">Lines</th>
                    <th>Monthly</th>
                    <th class="dash-hide-md">On admission</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="feesModal" aria-labelledby="feesModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'layers'])</span>
            <div>
                <h3 id="feesModalTitle" data-modal-title>Add structure</h3>
                <p>Amounts in rupees. Leave months empty for a monthly head to charge every month.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid dash-form-grid-3">
            <div class="dash-field">
                <label for="structureSession">Session</label>
                <select id="structureSession" name="academic_session_id">
                    @foreach ($data['sessions'] as $session)
                        <option value="{{ $session->id }}" @selected($session->id === $data['current_session_id'])>{{ $session->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="academic_session_id" hidden></p>
            </div>
            @include('dashboards.partials.select', ['name' => 'class_id', 'label' => 'Class', 'options' => $data['classes'], 'required' => true])
            @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name', 'required' => true, 'placeholder' => 'Grade 5 fee 2026-27'])
            <label class="dash-switch dash-field-wide">
                <input type="checkbox" name="is_active" checked>
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Active</span>
            </label>
        </div>

        <div class="dash-line-items" data-line-items>
            <h4 class="dash-form-section">Lines</h4>
            <div data-lines></div>
            <button type="button" class="dash-btn dash-btn-ghost dash-btn-sm" data-add-line>@include('partials.dashboard-icon', ['name' => 'plus']) Add line</button>
            <p class="dash-field-error" data-error-for="items" hidden></p>
        </div>

        <template data-line-template>
            <div class="dash-line-item dash-line-item-structure" data-line>
                <select data-line-field="fee_head_id" aria-label="Fee head">
                    @foreach ($data['fee_heads'] as $head)
                        <option value="{{ $head->id }}">{{ $head->name }}</option>
                    @endforeach
                </select>
                <select data-line-field="charge_on" aria-label="Charged">
                    @foreach (\App\Models\FeeStructureItem::CHARGE_ON as $chargeOn)
                        <option value="{{ $chargeOn }}">{{ \Illuminate\Support\Str::headline($chargeOn) }}</option>
                    @endforeach
                </select>
                <input type="number" min="1" step="0.01" placeholder="Amount" data-line-field="amount" aria-label="Amount">
                <input type="number" min="1" max="31" placeholder="Due day" data-line-field="due_day" aria-label="Due day of month">
                <label class="dash-check"><input type="checkbox" data-line-field="is_mandatory" checked> Mandatory</label>
                <label class="dash-check"><input type="checkbox" data-line-field="applies_to_new_students_only"> New only</label>
                <button type="button" class="dash-icon-btn" data-remove-line aria-label="Remove line">@include('partials.dashboard-icon', ['name' => 'close'])</button>
                <div class="dash-months" aria-label="Months">
                    @foreach (['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'] as $index => $month)
                        <label class="dash-month"><input type="checkbox" value="{{ $index + 1 }}" data-month><span>{{ $month }}</span></label>
                    @endforeach
                </div>
            </div>
        </template>

        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Structure</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-form-modal" id="copyModal" aria-labelledby="copyTitle">
    <form data-copy-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'layers'])</span>
            <div>
                <h3 id="copyTitle">Copy structures</h3>
                <p>Classes that already have a structure in the target session are skipped.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.select', ['name' => 'from_session_id', 'label' => 'From session', 'options' => $data['sessions'], 'required' => true, 'id' => 'copyFrom'])
            @include('dashboards.partials.select', ['name' => 'to_session_id', 'label' => 'To session', 'options' => $data['sessions'], 'required' => true, 'id' => 'copyTo'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary">@include('partials.dashboard-icon', ['name' => 'check']) Copy</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-fees.js') }}?v={{ filemtime(public_path('js/admin-fees.js')) }}" defer></script>
@endsection
