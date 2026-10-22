<?php

namespace App\Http\Resources;

use App\Models\CardTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CardTemplate
 */
class CardTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->only(['id', 'name', 'card_type', 'orientation', 'size_key', 'width_mm', 'height_mm', 'primary_color', 'text_color', 'show_qr', 'back_text', 'is_default', 'is_system', 'is_active']),
            'size_label' => CardTemplate::SIZES[$this->size_key][0] ?? null,
            'fields' => $this->whenLoaded('fields', fn (): array => $this->fields->map(fn ($field): array => $field->only([
                'id', 'side', 'field_key', 'custom_text', 'x_mm', 'y_mm', 'width_mm', 'height_mm', 'font_size', 'font_weight', 'color', 'align',
            ]))->all()),
        ];
    }
}
