{{-- Print-ready A4 sheets: each card's front and back side by side, cut lines around every card. Positions are in mm (computed in CardService::renderSheet). --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; }
        .sheet { position: relative; width: 210mm; height: 297mm; page-break-after: always; overflow: hidden; }
        .sheet:last-child { page-break-after: auto; }
        .face { position: absolute; overflow: hidden; background: #ffffff; border: 0.2mm dashed #9ca3af; }
        .decor { position: absolute; left: 0; top: 0; }
        .item { position: absolute; line-height: 1.2; }
        .item img { width: 100%; height: 100%; }
        .item img.logo { width: auto; height: auto; max-width: 100%; max-height: 100%; }
        .photo { border: 0.6mm solid #ffffff; background: #e5e7eb; }
        .photo-frame { position: absolute; border: 0.35mm solid #c9a227; }
        .signature { border-top: 0.25mm solid #374151; padding-top: 0.6mm; text-align: center; }
        .sheet-table { width: 100%; border-collapse: collapse; }
        .sheet-table th { text-align: left; font-weight: bold; border-bottom: 0.3mm solid #9ca3af; padding: 0.4mm 0.8mm; }
        .sheet-table td { border-bottom: 0.15mm solid #e5e7eb; padding: 0.4mm 0.8mm; }
        .back-text { position: absolute; left: 4mm; right: 4mm; color: #4b5563; text-align: center; }
    </style>
</head>
<body>
@foreach ($sheets as $sheet)
    <div class="sheet">
        @foreach ($sheet as $face)
            @php($template = $face['template'])
            @php($w = $template->width_mm)
            @php($h = $template->height_mm)
            @php($foot = round($h * ($face['side'] === 'front' ? 0.07 : 0.045), 1))
            <div class="face" style="left: {{ $face['x'] }}mm; top: {{ $face['y'] }}mm; width: {{ $w }}mm; height: {{ $h }}mm; color: {{ $template->text_color }};">
                <img class="decor" src="{{ $face['decor'] }}" style="width: {{ $w }}mm; height: {{ $h }}mm;" alt="">

                @foreach ($template->fields->where('side', $face['side']) as $field)
                    @php($value = $field->field_key === 'custom_text' ? $field->custom_text : ($face['values'][$field->field_key] ?? null))
                    @php($box = 'left: '.$field->x_mm.'mm; top: '.$field->y_mm.'mm;'.($field->width_mm ? ' width: '.$field->width_mm.'mm;' : '').($field->height_mm ? ' height: '.$field->height_mm.'mm;' : ''))
                    @php($text = 'font-size: '.$field->font_size.'pt; font-weight: '.$field->font_weight.'; text-align: '.$field->align.';'.($field->color ? ' color: '.$field->color.';' : ''))
                    @if ($field->field_key === 'photo')
                        <div class="photo-frame" style="left: {{ $field->x_mm - 0.9 }}mm; top: {{ $field->y_mm - 0.9 }}mm; width: {{ $field->width_mm + 1.1 }}mm; height: {{ $field->height_mm + 1.1 }}mm;"></div>
                        <div class="item photo" style="{{ $box }}">@if ($value)<img src="{{ $value }}" alt="">@endif</div>
                    @elseif ($field->field_key === 'school_logo')
                        @if ($value)<div class="item" style="{{ $box }} text-align: center;"><img class="logo" src="{{ $value }}" alt=""></div>@endif
                    @elseif ($field->field_key === 'qr_code')
                        @if ($value)<div class="item" style="{{ $box }}"><img src="{{ $value }}" alt=""></div>@endif
                    @elseif ($field->field_key === 'signature_line')
                        <div class="item signature" style="{{ $box }} {{ $text }}">{{ $field->custom_text ?: 'Principal' }}</div>
                    @elseif ($field->field_key === 'date_sheet')
                        @if (! empty($value))
                            <div class="item" style="{{ $box }} {{ $text }}">
                                <table class="sheet-table">
                                    <tr><th>Subject</th><th>Date</th><th>Time</th></tr>
                                    @foreach ($value as $paper)
                                        <tr><td>{{ $paper['subject'] }}</td><td>{{ $paper['date'] }}</td><td>{{ $paper['time'] }}</td></tr>
                                    @endforeach
                                </table>
                            </div>
                        @endif
                    @elseif (filled($value))
                        <div class="item" style="{{ $box }} {{ $text }}">{{ $value }}</div>
                    @endif
                @endforeach

                @if ($face['side'] === 'back' && $template->back_text)
                    <div class="back-text" style="bottom: {{ round($foot * 1.7 + 1.2, 1) }}mm; font-size: {{ $h > 100 ? 7.5 : 5.5 }}pt;">{{ $template->back_text }}</div>
                @endif
            </div>
        @endforeach
    </div>
@endforeach
</body>
</html>
