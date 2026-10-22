{{-- One popup dropdown from a collection with id + name. Params: name, label, options, required, wide, id, empty, withCode (prefix the code, e.g. accounts). --}}
@php
    $fieldId = $id ?? 'f_'.str_replace(['.', '[', ']'], '_', $name);
@endphp
<div @class(['dash-field', 'dash-field-wide' => $wide ?? false])>
    <label for="{{ $fieldId }}">{{ $label }}@unless ($required ?? false) <span class="dash-optional">optional</span>@endunless</label>
    <select id="{{ $fieldId }}" name="{{ $name }}" @required($required ?? false)>
        @unless ($required ?? false)
            <option value="">{{ $empty ?? '—' }}</option>
        @endunless
        @foreach ($options as $option)
            <option value="{{ $option->id }}">{{ ($withCode ?? false) && $option->code ? $option->code.' '.$option->name : $option->name }}</option>
        @endforeach
    </select>
    <p class="dash-field-error" data-error-for="{{ $name }}" hidden></p>
</div>
