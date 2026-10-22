{{-- One popup input. Params: name, label, type (text), required, placeholder, wide, id, step, min, max, hint. --}}
@php
    $fieldId = $id ?? 'f_'.str_replace(['.', '[', ']'], '_', $name);
@endphp
<div @class(['dash-field', 'dash-field-wide' => $wide ?? false])>
    <label for="{{ $fieldId }}">{{ $label }}@unless ($required ?? false) <span class="dash-optional">{{ $hint ?? 'optional' }}</span>@endunless</label>
    @if (($type ?? 'text') === 'textarea')
        <textarea id="{{ $fieldId }}" name="{{ $name }}" rows="2" maxlength="2000" @required($required ?? false)></textarea>
    @else
        <input
            id="{{ $fieldId }}"
            name="{{ $name }}"
            type="{{ $type ?? 'text' }}"
            autocomplete="off"
            @if (isset($placeholder)) placeholder="{{ $placeholder }}" @endif
            @if (isset($step)) step="{{ $step }}" @endif
            @if (isset($min)) min="{{ $min }}" @endif
            @if (isset($max)) max="{{ $max }}" @endif
            @required($required ?? false)
        >
    @endif
    <p class="dash-field-error" data-error-for="{{ $name }}" hidden></p>
</div>
