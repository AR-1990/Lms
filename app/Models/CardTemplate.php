<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'campus_id', 'name', 'card_type', 'orientation', 'size_key', 'width_mm', 'height_mm', 'front_background_path', 'back_background_path',
    'primary_color', 'text_color', 'show_qr', 'show_barcode', 'back_text', 'signature_image_path', 'is_default', 'is_system', 'is_active',
])]
class CardTemplate extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    /**
     * Card kinds. ID cards and admit cards are filled from student / employee / exam data;
     * invitation, event pass and visitor cards are free designs with custom text.
     */
    public const TYPES = ['student', 'employee', 'admit_card', 'invitation', 'event_pass', 'visitor'];

    /**
     * Printable sizes: [label, width mm, height mm].
     *
     * @var array<string, array{0: string, 1: float, 2: float}>
     */
    public const SIZES = [
        'cr80_portrait' => ['ID card — vertical (54 × 85.6 mm)', 54, 85.6],
        'cr80_landscape' => ['ID card — horizontal (85.6 × 54 mm)', 85.6, 54],
        'a6_portrait' => ['A6 — vertical (105 × 148 mm)', 105, 148],
        'a6_landscape' => ['A6 — horizontal (148 × 105 mm)', 148, 105],
        'a5_landscape' => ['A5 — horizontal (210 × 148 mm)', 210, 148],
    ];

    /**
     * Sizes each card type may use; student and employee ID cards are always vertical CR80.
     *
     * @var array<string, array<int, string>>
     */
    public const SIZES_FOR_TYPE = [
        'student' => ['cr80_portrait'],
        'employee' => ['cr80_portrait'],
        'admit_card' => ['a6_landscape', 'a6_portrait'],
        'invitation' => ['a6_landscape', 'a6_portrait', 'a5_landscape'],
        'event_pass' => ['cr80_portrait', 'cr80_landscape', 'a6_portrait'],
        'visitor' => ['cr80_portrait', 'cr80_landscape'],
    ];

    /**
     * Items the designer offers for each card type.
     *
     * @var array<string, array<int, string>>
     */
    public const KEYS_FOR_TYPE = [
        'student' => ['photo', 'full_name', 'father_name', 'admission_no', 'roll_no', 'class_section', 'blood_group', 'date_of_birth', 'emergency_phone', 'address', 'valid_until', 'card_no', 'school_name', 'qr_code', 'signature_line', 'custom_text'],
        'employee' => ['photo', 'full_name', 'father_name', 'employee_code', 'designation', 'department', 'blood_group', 'date_of_birth', 'emergency_phone', 'address', 'valid_until', 'card_no', 'school_name', 'qr_code', 'signature_line', 'custom_text'],
        'admit_card' => ['photo', 'full_name', 'father_name', 'admission_no', 'class_section', 'exam_name', 'exam_roll_no', 'date_sheet', 'instructions', 'school_name', 'signature_line', 'custom_text'],
        'invitation' => ['school_name', 'signature_line', 'custom_text'],
        'event_pass' => ['school_name', 'signature_line', 'custom_text'],
        'visitor' => ['school_name', 'card_no', 'signature_line', 'custom_text'],
    ];

    protected string $activityModule = 'id_cards';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['width_mm' => 'float', 'height_mm' => 'float', 'show_qr' => 'boolean', 'show_barcode' => 'boolean', 'is_default' => 'boolean', 'is_system' => 'boolean', 'is_active' => 'boolean'];
    }

    public function fields(): HasMany
    {
        return $this->hasMany(CardTemplateField::class)->orderBy('sort_order');
    }

    /**
     * Free designs are printed in copies instead of being issued to students or staff.
     */
    public function isCustom(): bool
    {
        return in_array($this->card_type, ['invitation', 'event_pass', 'visitor'], true);
    }
}
