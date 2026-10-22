<?php

namespace Database\Seeders;

use App\Models\CardTemplate;
use Illuminate\Database\Seeder;

/**
 * Built-in professional system templates: vertical student and staff ID cards (CR80) and an A6
 * admit card. They cannot be deleted; schools restyle them in the card designer. Seeding never
 * overwrites a system template that already exists, so design changes are kept.
 */
class CardSeeder extends Seeder
{
    /**
     * Field rows: [side, key, custom text, x, y, width, height, font pt, weight, colour, align].
     *
     * @var array<int, array<int, mixed>>
     */
    private const ID_FRONT = [
        ['front', 'school_name', null, 2, 3, 50, null, 7.5, 'bold', '#ffffff', 'center'],
        ['front', 'photo', null, 17, 19, 20, 24, 8, 'normal', null, 'left'],
        ['front', 'full_name', null, 2, 45, 50, null, 9.5, 'bold', '#1d2b45', 'center'],
        ['front', 'qr_code', null, 36.5, 55.5, 15, 15, 8, 'normal', null, 'left'],
        ['front', 'father_name', null, 3.5, 61, 32, null, 6, 'normal', null, 'left'],
        ['front', 'blood_group', null, 3.5, 65.5, 32, null, 6, 'normal', null, 'left'],
        ['front', 'valid_until', null, 3.5, 70, 32, null, 6, 'normal', '#6b7280', 'left'],
        ['front', 'card_no', null, 2, 80.6, 50, null, 5.5, 'bold', '#ffffff', 'center'],
        ['back', 'school_name', null, 2, 1.6, 50, null, 6, 'bold', '#ffffff', 'center'],
        ['back', 'custom_text', 'TERMS OF USE', 4, 10, 46, null, 6.5, 'bold', '#1d2b45', 'left'],
        ['back', 'custom_text', 'This card is not transferable. Carry it at all times on campus and show it when asked.', 4, 14.5, 46, null, 5.6, 'normal', '#374151', 'left'],
        ['back', 'emergency_phone', null, 4, 30, 46, null, 6.5, 'bold', null, 'left'],
        ['back', 'address', null, 4, 35, 46, 12, 5.8, 'normal', '#374151', 'left'],
        ['back', 'signature_line', 'Principal', 27, 63, 23, null, 5.8, 'normal', null, 'center'],
    ];

    public function run(): void
    {
        $layouts = [
            ['student', 'Student ID Card (system)', 'cr80_portrait', 'If found, please return to the school office.', [
                ...self::ID_FRONT,
                ['front', 'custom_text', 'STUDENT IDENTITY CARD', 2, 10.4, 50, null, 5.4, 'bold', '#fde68a', 'center'],
                ['front', 'class_section', null, 2, 50.5, 50, null, 7, 'bold', '#b45309', 'center'],
                ['front', 'admission_no', null, 3.5, 56.5, 32, null, 6, 'normal', null, 'left'],
            ]],
            ['employee', 'Staff ID Card (system)', 'cr80_portrait', 'This card is school property. If found, please return it to the school office.', [
                ...self::ID_FRONT,
                ['front', 'custom_text', 'STAFF IDENTITY CARD', 2, 10.4, 50, null, 5.4, 'bold', '#fde68a', 'center'],
                ['front', 'designation', null, 2, 50.5, 50, null, 7, 'bold', '#b45309', 'center'],
                ['front', 'employee_code', null, 3.5, 56.5, 32, null, 6, 'normal', null, 'left'],
            ]],
            ['admit_card', 'Admit Card (system)', 'a6_landscape', 'Bring this admit card to every paper. Mobile phones are not allowed in the exam hall.', [
                ['front', 'school_name', null, 6, 4, 136, null, 12, 'bold', '#ffffff', 'center'],
                ['front', 'custom_text', 'ADMIT CARD', 6, 11.5, 136, null, 8, 'bold', '#fde68a', 'center'],
                ['front', 'photo', null, 117, 23, 24, 29, 8, 'normal', null, 'left'],
                ['front', 'exam_name', null, 8, 23, 104, null, 10, 'bold', '#1d2b45', 'left'],
                ['front', 'full_name', null, 8, 30, 104, null, 9, 'bold', null, 'left'],
                ['front', 'father_name', null, 8, 35.5, 104, null, 7.5, 'normal', null, 'left'],
                ['front', 'class_section', null, 8, 40.5, 104, null, 7.5, 'normal', null, 'left'],
                ['front', 'admission_no', null, 8, 45.5, 104, null, 7.5, 'normal', null, 'left'],
                ['front', 'exam_roll_no', null, 8, 50.5, 104, null, 9, 'bold', '#b45309', 'left'],
                ['front', 'date_sheet', null, 8, 57, 132, 31, 6.5, 'normal', null, 'left'],
                ['front', 'signature_line', 'Controller of Examinations', 104, 90, 36, null, 6.5, 'normal', null, 'center'],
                ['back', 'school_name', null, 6, 1.6, 136, null, 8, 'bold', '#ffffff', 'center'],
                ['back', 'custom_text', 'INSTRUCTIONS', 8, 13, 132, null, 9, 'bold', '#1d2b45', 'left'],
                ['back', 'instructions', null, 8, 19, 132, 60, 7.5, 'normal', '#374151', 'left'],
            ]],
        ];

        foreach ($layouts as [$type, $name, $size, $backText, $fields]) {
            if (CardTemplate::where('card_type', $type)->where('is_system', true)->exists()) {
                continue;
            }

            [, $width, $height] = CardTemplate::SIZES[$size];
            $template = CardTemplate::where('card_type', $type)->where('is_default', true)->first() ?? new CardTemplate;
            $template->fill([
                'name' => $name,
                'card_type' => $type,
                'size_key' => $size,
                'width_mm' => $width,
                'height_mm' => $height,
                'orientation' => $width > $height ? 'landscape' : 'portrait',
                'primary_color' => '#1d2b45',
                'text_color' => '#1f2937',
                'show_qr' => $type !== 'admit_card',
                'back_text' => $backText,
                'is_default' => true,
                'is_system' => true,
                'is_active' => true,
            ])->save();

            $template->fields()->delete();

            foreach ($fields as $position => $field) {
                $template->fields()->create([
                    ...array_combine(['side', 'field_key', 'custom_text', 'x_mm', 'y_mm', 'width_mm', 'height_mm', 'font_size', 'font_weight', 'color', 'align'], $field),
                    'sort_order' => $position,
                ]);
            }
        }
    }
}
