@extends('layouts.dashboard')

@php($template = $data['template'])

@section('title', 'Card Designer')
@section('page_heading', 'ID Cards')

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>{{ $template->name }}</h1>
        <p>Drag items from the left onto the card, move them with the mouse (arrow keys nudge 0.5 mm), and set size and style on the right. Size: {{ \App\Models\CardTemplate::SIZES[$template->size_key][0] ?? '' }}.</p>
    </div>
    <div class="dash-head-actions">
        <a href="{{ route('admin.cards.templates') }}" class="dash-btn dash-btn-ghost">@include('partials.dashboard-icon', ['name' => 'arrow-left']) Templates</a>
        <button type="button" class="dash-btn dash-btn-ghost" data-preview>@include('partials.dashboard-icon', ['name' => 'eye']) Preview PDF</button>
        @if ($template->isCustom())
            <label class="dash-select"><span class="dash-visually-hidden">Copies</span><input type="number" min="1" max="300" value="10" data-copies aria-label="Copies" style="width: 5rem;"></label>
            <button type="button" class="dash-btn dash-btn-ghost" data-print-copies>@include('partials.dashboard-icon', ['name' => 'check']) Print Copies</button>
        @endif
        <button type="button" class="dash-btn dash-btn-primary" data-save-design>@include('partials.dashboard-icon', ['name' => 'check']) Save Design</button>
    </div>
</section>

@include('dashboards.cards.tabs', ['current' => 'admin.cards.templates'])

<section class="dash-designer" data-designer data-template-url="{{ url('/api/erp/card-templates/'.$template->id) }}">
    <aside class="dash-panel dash-designer-palette">
        <h2>Items</h2>
        <p class="dash-muted">Drag onto the card.</p>
        <ul>
            @foreach ($data['keys'] as $key)
                <li draggable="true" class="dash-chip" data-palette-key="{{ $key }}" data-sample="{{ $data['samples'][$key] ?? '' }}">{{ \Illuminate\Support\Str::headline($key) }}</li>
            @endforeach
        </ul>
    </aside>

    <div class="dash-panel dash-designer-stage">
        <div class="dash-designer-sides" role="tablist">
            <button type="button" class="dash-btn dash-btn-sm dash-btn-primary" data-side="front" role="tab" aria-selected="true">Front</button>
            <button type="button" class="dash-btn dash-btn-sm dash-btn-ghost" data-side="back" role="tab" aria-selected="false">Back</button>
        </div>
        <div class="dash-designer-canvas-wrap">
            <div class="dash-designer-canvas" data-canvas tabindex="0" aria-label="Card canvas"></div>
        </div>
        <p class="dash-muted dash-designer-hint">The coloured bands and gold line are part of every card; the band colour is set in the template settings.</p>
    </div>

    <aside class="dash-panel dash-designer-props" data-props>
        <h2>Selected item</h2>
        <p class="dash-muted" data-props-empty>Click an item on the card.</p>
        <div class="dash-form-grid" data-props-form hidden>
            <div class="dash-field dash-field-wide" data-prop-text-wrap>
                <label for="propText">Text</label>
                <input id="propText" data-prop="custom_text" maxlength="255">
            </div>
            <div class="dash-field"><label for="propX">X (mm)</label><input id="propX" type="number" step="0.5" data-prop="x_mm"></div>
            <div class="dash-field"><label for="propY">Y (mm)</label><input id="propY" type="number" step="0.5" data-prop="y_mm"></div>
            <div class="dash-field"><label for="propW">Width (mm)</label><input id="propW" type="number" step="0.5" min="1" data-prop="width_mm"></div>
            <div class="dash-field"><label for="propH">Height (mm)</label><input id="propH" type="number" step="0.5" min="1" data-prop="height_mm"></div>
            <div class="dash-field"><label for="propSize">Font (pt)</label><input id="propSize" type="number" step="0.5" min="4" max="40" data-prop="font_size"></div>
            <div class="dash-field"><label for="propColor">Colour</label><input id="propColor" type="color" data-prop="color"></div>
            <div class="dash-field">
                <label for="propWeight">Weight</label>
                <select id="propWeight" data-prop="font_weight"><option value="normal">Normal</option><option value="bold">Bold</option></select>
            </div>
            <div class="dash-field">
                <label for="propAlign">Align</label>
                <select id="propAlign" data-prop="align"><option value="left">Left</option><option value="center">Center</option><option value="right">Right</option></select>
            </div>
            <div class="dash-inline-actions dash-field-wide"><button type="button" class="dash-btn dash-btn-danger dash-btn-sm" data-remove-item>Remove item</button></div>
        </div>
    </aside>
</section>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-card-designer.js') }}?v={{ filemtime(public_path('js/admin-card-designer.js')) }}" defer></script>
@endsection
