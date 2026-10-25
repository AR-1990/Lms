<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampus;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'campus_id', 'name', 'card_type', 'orientation', 'size_key', 'theme', 'width_mm', 'height_mm', 'front_background_path', 'back_background_path',
    'primary_color', 'text_color', 'show_qr', 'show_barcode', 'back_text', 'signature_image_path', 'is_default', 'is_system', 'is_active',
])]
class CardTemplate extends Model
{
    use BelongsToCampus, LogsActivity, SoftDeletes, TracksAuthor;

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
     * Card looks drawn by decorSvg(): key => label.
     *
     * @var array<string, string>
     */
    public const THEMES = ['classic' => 'Classic bands', 'wave' => 'Curved wave', 'elegant' => 'Elegant diagonal', 'modern' => 'Modern angled header'];

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
        'student' => ['photo', 'full_name', 'father_name', 'admission_no', 'roll_no', 'class_section', 'blood_group', 'date_of_birth', 'emergency_phone', 'address', 'valid_until', 'card_no', 'school_name', 'school_logo', 'qr_code', 'signature_line', 'custom_text'],
        'employee' => ['photo', 'full_name', 'father_name', 'employee_code', 'designation', 'department', 'blood_group', 'date_of_birth', 'emergency_phone', 'address', 'valid_until', 'card_no', 'school_name', 'school_logo', 'qr_code', 'signature_line', 'custom_text'],
        'admit_card' => ['photo', 'full_name', 'father_name', 'admission_no', 'class_section', 'exam_name', 'exam_roll_no', 'date_sheet', 'instructions', 'school_name', 'school_logo', 'signature_line', 'custom_text'],
        'invitation' => ['school_name', 'school_logo', 'signature_line', 'custom_text'],
        'event_pass' => ['school_name', 'school_logo', 'signature_line', 'custom_text'],
        'visitor' => ['school_name', 'school_logo', 'card_no', 'signature_line', 'custom_text'],
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

    /**
     * Background artwork for one side (header, gold accent, footer, watermark) as an SVG data URI.
     * The PDF and the designer both draw it, so they always look the same. Header and footer heights
     * match the field layouts: 18% / 7% of the height on the front, 8% / 4.5% on the back.
     */
    public function decorSvg(string $side): string
    {
        $w = (float) $this->width_mm;
        $h = (float) $this->height_mm;
        $band = round($h * ($side === 'front' ? 0.18 : 0.08), 2);
        $foot = round($h * ($side === 'front' ? 0.07 : 0.045), 2);
        $p = $this->primary_color ?: '#1d2b45';
        $gold = '#c9a227';
        $n = fn (float $value): string => (string) round($value, 2);
        $ring = min($w, $h);

        $shapes = match ($this->theme) {
            // Angled header the photo overlaps, gold edge, light sheen and a deep footer; plain bands on the back.
            'modern' => $side === 'front'
                ? '<rect width="'.$n($w).'" height="'.$n($h).'" fill="#ffffff"/>'
                    .'<circle cx="'.$n($w * 0.5).'" cy="'.$n($h * 0.66).'" r="'.$n($ring * 0.42).'" fill="'.$p.'" fill-opacity="0.03"/>'
                    .'<polygon points="0,0 '.$n($w).',0 '.$n($w).','.$n($h * 0.22).' 0,'.$n($h * 0.31).'" fill="'.$p.'"/>'
                    .'<polygon points="'.$n($w * 0.42).',0 '.$n($w).',0 '.$n($w).','.$n($h * 0.15).'" fill="#ffffff" fill-opacity="0.08"/>'
                    .'<polygon points="0,'.$n($h * 0.31).' '.$n($w).','.$n($h * 0.22).' '.$n($w).','.$n($h * 0.22 + 0.9).' 0,'.$n($h * 0.31 + 0.9).'" fill="'.$gold.'"/>'
                    .'<rect y="'.$n($h * 0.31 + 0.9).'" width="1.1" height="'.$n($h * 0.69 - 0.9 - $foot).'" fill="'.$p.'" fill-opacity="0.85"/>'
                    .'<rect y="'.$n($h - $foot - 0.6).'" width="'.$n($w).'" height="0.6" fill="'.$gold.'"/>'
                    .'<rect y="'.$n($h - $foot).'" width="'.$n($w).'" height="'.$n($foot).'" fill="'.$p.'"/>'
                : '<rect width="'.$n($w).'" height="'.$n($h).'" fill="#ffffff"/>'
                    .'<rect width="'.$n($w).'" height="'.$n($band).'" fill="'.$p.'"/>'
                    .'<rect y="'.$n($band).'" width="'.$n($w).'" height="0.6" fill="'.$gold.'"/>'
                    .'<polygon points="'.$n($w).','.$n($h * 0.62).' '.$n($w).','.$n($h - $foot).' '.$n($w * 0.45).','.$n($h - $foot).'" fill="'.$p.'" fill-opacity="0.05"/>'
                    .'<rect y="'.$n($h - $foot).'" width="'.$n($w).'" height="'.$n($foot).'" fill="'.$p.'"/>',
            'wave' => '<rect width="'.$n($w).'" height="'.$n($h).'" fill="#ffffff"/>'
                .'<path d="M0 '.$n($h * 0.62).' C'.$n($w * 0.35).' '.$n($h * 0.52).' '.$n($w * 0.65).' '.$n($h * 0.72).' '.$n($w).' '.$n($h * 0.6).' V'.$n($h).' H0 Z" fill="'.$p.'" fill-opacity="0.035"/>'
                .'<path d="M0 0 H'.$n($w).' V'.$n($band * 1.3).' C'.$n($w * 0.7).' '.$n($band * 0.95).' '.$n($w * 0.35).' '.$n($band * 1.5).' 0 '.$n($band * 1.18).' Z" fill="'.$p.'" fill-opacity="0.22"/>'
                .'<path d="M0 0 H'.$n($w).' V'.$n($band * 1.02).' C'.$n($w * 0.7).' '.$n($band * 0.82).' '.$n($w * 0.35).' '.$n($band * 1.22).' 0 '.$n($band * 0.98).' Z" fill="'.$p.'"/>'
                .'<path d="M0 '.$n($band * 1.06).' C'.$n($w * 0.35).' '.$n($band * 1.3).' '.$n($w * 0.7).' '.$n($band * 0.9).' '.$n($w).' '.$n($band * 1.1).'" fill="none" stroke="'.$gold.'" stroke-width="'.$n(max(0.6, $h * 0.009)).'"/>'
                .'<circle cx="'.$n($w * 0.9).'" cy="'.$n($band * 0.25).'" r="'.$n($band * 0.55).'" fill="#ffffff" fill-opacity="0.07"/>'
                .'<circle cx="'.$n($w * 0.08).'" cy="'.$n($band * 0.1).'" r="'.$n($band * 0.35).'" fill="#ffffff" fill-opacity="0.06"/>'
                .'<path d="M0 '.$n($h - $foot * 1.35).' C'.$n($w * 0.3).' '.$n($h - $foot * 1.9).' '.$n($w * 0.65).' '.$n($h - $foot * 0.9).' '.$n($w).' '.$n($h - $foot * 1.5).' V'.$n($h).' H0 Z" fill="'.$gold.'"/>'
                .'<path d="M0 '.$n($h - $foot * 1.05).' C'.$n($w * 0.3).' '.$n($h - $foot * 1.6).' '.$n($w * 0.65).' '.$n($h - $foot * 0.6).' '.$n($w).' '.$n($h - $foot * 1.2).' V'.$n($h).' H0 Z" fill="'.$p.'"/>',
            'elegant' => '<rect width="'.$n($w).'" height="'.$n($h).'" fill="#fffdf7"/>'
                .'<rect x="'.$n($w / 2 - $ring * 0.22).'" y="'.$n($h * 0.55 - $ring * 0.22).'" width="'.$n($ring * 0.44).'" height="'.$n($ring * 0.44).'" fill="none" stroke="'.$p.'" stroke-opacity="0.05" stroke-width="'.$n($ring * 0.02).'" transform="rotate(45 '.$n($w / 2).' '.$n($h * 0.55).')"/>'
                .'<polygon points="0,0 '.$n($w).',0 '.$n($w).','.$n($band * 0.94).' 0,'.$n($band * 1.2).'" fill="'.$p.'"/>'
                .'<polygon points="0,'.$n($band * 1.2).' '.$n($w).','.$n($band * 0.94).' '.$n($w).','.$n($band * 0.94 + $h * 0.012).' 0,'.$n($band * 1.2 + $h * 0.012).'" fill="'.$gold.'"/>'
                .'<polygon points="0,'.$n($band * 1.2 + $h * 0.024).' '.$n($w).','.$n($band * 0.94 + $h * 0.024).' '.$n($w).','.$n($band * 0.94 + $h * 0.029).' 0,'.$n($band * 1.2 + $h * 0.029).'" fill="'.$p.'" fill-opacity="0.35"/>'
                .'<polygon points="0,'.$n($h - $foot).' '.$n($w).','.$n($h - $foot * 1.5).' '.$n($w).','.$n($h).' 0,'.$n($h).'" fill="'.$p.'"/>'
                .'<polygon points="0,'.$n($h - $foot - $h * 0.012).' '.$n($w).','.$n($h - $foot * 1.5 - $h * 0.012).' '.$n($w).','.$n($h - $foot * 1.5).' 0,'.$n($h - $foot).'" fill="'.$gold.'"/>'
                .'<rect x="'.$n($w * 0.03).'" y="'.$n($w * 0.03).'" width="'.$n($w * 0.94).'" height="'.$n($h - $w * 0.06).'" fill="none" stroke="'.$gold.'" stroke-opacity="0.45" stroke-width="0.25"/>',
            default => '<rect width="'.$n($w).'" height="'.$n($h).'" fill="#ffffff"/>'
                .'<circle cx="'.$n($w / 2).'" cy="'.$n($h * 0.56).'" r="'.$n($ring * 0.36).'" fill="none" stroke="'.$p.'" stroke-opacity="0.05" stroke-width="'.$n($ring * 0.03).'"/>'
                .'<circle cx="'.$n($w / 2).'" cy="'.$n($h * 0.56).'" r="'.$n($ring * 0.24).'" fill="none" stroke="'.$p.'" stroke-opacity="0.04" stroke-width="'.$n($ring * 0.015).'"/>'
                .'<rect width="'.$n($w).'" height="'.$n($band).'" fill="'.$p.'"/>'
                .'<rect y="'.$n($band).'" width="'.$n($w).'" height="'.$n(max(0.7, $h * 0.011)).'" fill="'.$gold.'"/>'
                .'<rect y="'.$n($h - $foot - max(0.5, $h * 0.007)).'" width="'.$n($w).'" height="'.$n(max(0.5, $h * 0.007)).'" fill="'.$gold.'"/>'
                .'<rect y="'.$n($h - $foot).'" width="'.$n($w).'" height="'.$n($foot).'" fill="'.$p.'"/>'
                .'<rect x="1.2" y="'.$n($band + 2).'" width="'.$n($w - 2.4).'" height="'.$n($h - $band - $foot - 4).'" rx="1.2" fill="none" stroke="'.$gold.'" stroke-opacity="0.35" stroke-width="0.25"/>',
        };

        return 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$n($w).' '.$n($h).'" preserveAspectRatio="none">'.$shapes.'</svg>');
    }
}
