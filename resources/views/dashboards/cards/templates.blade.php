@extends('layouts.dashboard')

@section('title', 'Card Templates')
@section('page_heading', 'ID Cards')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Card Templates</h1>
        <p>System templates are ready to print. Add your own for invitations, event passes or visitor cards, then open the designer to drag items onto the card.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        New Template
    </button>
</section>

@include('dashboards.cards.tabs', ['current' => 'admin.cards.templates'])

<section class="dash-panel" data-card-templates data-datatable-url="{{ route('erp.card-templates.datatable') }}" data-resource-url="{{ url('/api/erp/card-templates') }}" data-design-url="{{ url('/admin/id-cards/templates') }}">
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Template</th>
                    <th class="dash-hide-md">Size</th>
                    <th>Items</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="templateModal" aria-labelledby="templateModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'layers'])</span>
            <div>
                <h3 id="templateModalTitle" data-modal-title>New template</h3>
                <p>Only the sizes allowed for the card type can be chosen. Place items in the designer afterwards.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name', 'required' => true, 'wide' => true])
            <div class="dash-field">
                <label for="tplType">Card for</label>
                <select id="tplType" name="card_type">
                    @foreach ($data['types'] as $type)
                        <option value="{{ $type }}">{{ \Illuminate\Support\Str::headline($type) }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="card_type" hidden></p>
            </div>
            <div class="dash-field">
                <label for="tplSize">Size</label>
                <select id="tplSize" name="size_key">
                    @foreach ($data['sizes'] as $key => [$label])
                        <option value="{{ $key }}" data-types="{{ collect($data['sizes_for_type'])->filter(fn ($keys) => in_array($key, $keys, true))->keys()->implode(' ') }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="size_key" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'primary_color', 'label' => 'Band colour', 'type' => 'color'])
            @include('dashboards.partials.field', ['name' => 'text_color', 'label' => 'Text colour', 'type' => 'color'])
            @include('dashboards.partials.field', ['name' => 'back_text', 'label' => 'Back text', 'wide' => true])
            @foreach (['show_qr' => ['Show QR', true], 'is_default' => ['Default for this card type', false], 'is_active' => ['Active', true]] as $switchName => [$switchLabel, $switchOn])
                <label class="dash-switch">
                    <input type="checkbox" name="{{ $switchName }}" @checked($switchOn)>
                    <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                    <span>{{ $switchLabel }}</span>
                </label>
            @endforeach
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
<script src="{{ asset('js/admin-cards.js') }}?v={{ filemtime(public_path('js/admin-cards.js')) }}" defer></script>
@endsection
