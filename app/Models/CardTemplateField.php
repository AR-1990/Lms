<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One item placed on a card side, in millimetres from the top-left corner.
 */
#[Fillable(['card_template_id', 'side', 'field_key', 'custom_text', 'x_mm', 'y_mm', 'width_mm', 'height_mm', 'font_size', 'font_weight', 'color', 'align', 'sort_order'])]
class CardTemplateField extends Model
{
    use SoftDeletes;

    /**
     * Values the card printer can fill (PLAN.md §23).
     */
    public const KEYS = [
        'photo', 'full_name', 'father_name', 'admission_no', 'roll_no', 'class_section', 'employee_code', 'designation', 'department',
        'blood_group', 'date_of_birth', 'emergency_phone', 'address', 'valid_until', 'card_no', 'school_name', 'qr_code',
        'exam_name', 'exam_roll_no', 'date_sheet', 'instructions', 'signature_line', 'custom_text',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['x_mm' => 'float', 'y_mm' => 'float', 'width_mm' => 'float', 'height_mm' => 'float', 'font_size' => 'float'];
    }
}
