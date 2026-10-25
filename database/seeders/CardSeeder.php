<?php

namespace Database\Seeders;

use App\Models\CardTemplate;
use Illuminate\Database\Seeder;

/**
 * Built-in system templates in four looks (classic, wave, elegant, modern) for every card type: vertical
 * student and staff ID cards (CR80), A6 admit cards, an A5 invitation, an event pass and a visitor
 * card. They cannot be deleted; schools restyle them in the card designer. Seeding only adds
 * templates that are missing (matched by name), so design changes are kept.
 */
class CardSeeder extends Seeder
{
    /**
     * Field rows: [side, key, custom text, x, y, width, height, font pt, weight, colour, align].
     * PRIMARY is replaced with the template's band colour.
     *
     * @var array<int, array<int, mixed>>
     */
    private const ID_FRONT = [
        ['front', 'school_name', null, 2, 3, 50, null, 7.5, 'bold', '#ffffff', 'center'],
        ['front', 'photo', null, 17, 19, 20, 24, 8, 'normal', null, 'left'],
        ['front', 'full_name', null, 2, 45, 50, null, 9.5, 'bold', 'PRIMARY', 'center'],
        ['front', 'qr_code', null, 36.5, 55.5, 15, 15, 8, 'normal', null, 'left'],
        ['front', 'father_name', null, 3.5, 61, 32, null, 6, 'normal', null, 'left'],
        ['front', 'blood_group', null, 3.5, 67, 32, null, 6, 'normal', null, 'left'],
        ['front', 'valid_until', null, 3.5, 71.5, 32, null, 6, 'normal', '#6b7280', 'left'],
        ['front', 'card_no', null, 2, 80.6, 50, null, 5.5, 'bold', '#ffffff', 'center'],
        ['back', 'school_name', null, 2, 1.6, 50, null, 6, 'bold', '#ffffff', 'center'],
        ['back', 'custom_text', 'TERMS OF USE', 4, 10, 46, null, 6.5, 'bold', 'PRIMARY', 'left'],
        ['back', 'custom_text', 'This card is not transferable. Carry it at all times on campus and show it when asked.', 4, 14.5, 46, null, 5.6, 'normal', '#374151', 'left'],
        ['back', 'emergency_phone', null, 4, 30, 46, null, 6.5, 'bold', null, 'left'],
        ['back', 'address', null, 4, 35, 46, 12, 5.8, 'normal', '#374151', 'left'],
        ['back', 'signature_line', 'Principal', 27, 63, 23, null, 5.8, 'normal', null, 'center'],
    ];

    /**
     * @var array<int, array<int, mixed>>
     */
    private const STUDENT_FRONT = [
        ['front', 'custom_text', 'STUDENT IDENTITY CARD', 2, 10.4, 50, null, 5.4, 'bold', '#fde68a', 'center'],
        ['front', 'class_section', null, 2, 50.5, 50, null, 7, 'bold', '#a16207', 'center'],
        ['front', 'admission_no', null, 3.5, 56.5, 32, null, 6, 'normal', null, 'left'],
    ];

    /**
     * @var array<int, array<int, mixed>>
     */
    private const STAFF_FRONT = [
        ['front', 'custom_text', 'STAFF IDENTITY CARD', 2, 10.4, 50, null, 5.4, 'bold', '#fde68a', 'center'],
        ['front', 'designation', null, 2, 50.5, 50, null, 7, 'bold', '#a16207', 'center'],
        ['front', 'employee_code', null, 3.5, 56.5, 32, null, 6, 'normal', null, 'left'],
    ];

    /**
     * Modern ID back: rules of use, the gate-attendance note, emergency contact, logo and signature.
     *
     * @var array<int, array<int, mixed>>
     */
    private const ID_BACK_MODERN = [
        ['back', 'school_name', null, 2, 1.6, 50, null, 6, 'bold', '#ffffff', 'center'],
        ['back', 'custom_text', 'TERMS OF USE', 4, 10, 46, null, 6.5, 'bold', 'PRIMARY', 'left'],
        ['back', 'custom_text', 'This card is not transferable. Carry it at all times on campus and show it when asked.', 4, 14.5, 46, null, 5.6, 'normal', '#374151', 'left'],
        ['back', 'custom_text', 'Scan the QR on the front at the gate kiosk to mark attendance.', 4, 23, 46, null, 5.6, 'bold', '#a16207', 'left'],
        ['back', 'emergency_phone', null, 4, 31, 46, null, 6.5, 'bold', null, 'left'],
        ['back', 'address', null, 4, 36, 46, 12, 5.8, 'normal', '#374151', 'left'],
        ['back', 'school_logo', null, 4, 58, 11, 11, 8, 'normal', null, 'left'],
        ['back', 'signature_line', 'Principal', 27, 63, 23, null, 5.8, 'normal', null, 'center'],
    ];

    /**
     * Modern ID front: logo and school in the angled header, the photo overlapping its edge, and a large
     * QR for the gate kiosk. ROLE is replaced with the card's role line.
     *
     * @var array<int, array<int, mixed>>
     */
    private const MODERN_FRONT = [
        ['front', 'school_logo', null, 2.5, 2.4, 8.5, 8.5, 8, 'normal', null, 'left'],
        ['front', 'school_name', null, 12, 2.4, 40, null, 6.2, 'bold', '#ffffff', 'left'],
        ['front', 'custom_text', 'ROLE', 12, 8.3, 40, null, 4.8, 'bold', '#fde68a', 'left'],
        ['front', 'photo', null, 16, 14.5, 22, 26, 8, 'normal', null, 'left'],
        ['front', 'full_name', null, 2, 43, 50, null, 9.5, 'bold', 'PRIMARY', 'center'],
        ['front', 'qr_code', null, 34.5, 53, 17, 17, 8, 'normal', null, 'left'],
        ['front', 'custom_text', 'Scan at gate', 34.5, 70.6, 17, null, 4.5, 'normal', '#6b7280', 'center'],
        ['front', 'father_name', null, 3.5, 68, 31, null, 5.3, 'normal', null, 'left'],
        ['front', 'blood_group', null, 3.5, 59, 31, null, 5.3, 'normal', null, 'left'],
        ['front', 'valid_until', null, 3.5, 63.5, 31, null, 5.3, 'normal', '#6b7280', 'left'],
        ['front', 'card_no', null, 2, 80.6, 50, null, 5.5, 'bold', '#ffffff', 'center'],
    ];

    /**
     * @var array<int, array<int, mixed>>
     */
    private const MODERN_STUDENT = [
        ['front', 'class_section', null, 2, 48.3, 50, null, 7, 'bold', '#a16207', 'center'],
        ['front', 'admission_no', null, 3.5, 54.5, 31, null, 5.3, 'normal', null, 'left'],
    ];

    /**
     * @var array<int, array<int, mixed>>
     */
    private const MODERN_STAFF = [
        ['front', 'designation', null, 2, 48.3, 50, null, 7, 'bold', '#a16207', 'center'],
        ['front', 'employee_code', null, 3.5, 54.5, 31, null, 5.3, 'normal', null, 'left'],
    ];

    /**
     * @var array<int, array<int, mixed>>
     */
    private const ADMIT = [
        ['front', 'school_name', null, 6, 4, 136, null, 12, 'bold', '#ffffff', 'center'],
        ['front', 'custom_text', 'ADMIT CARD', 6, 11.5, 136, null, 8, 'bold', '#fde68a', 'center'],
        ['front', 'photo', null, 117, 23, 24, 29, 8, 'normal', null, 'left'],
        ['front', 'exam_name', null, 8, 23, 104, null, 10, 'bold', 'PRIMARY', 'left'],
        ['front', 'full_name', null, 8, 30, 104, null, 9, 'bold', null, 'left'],
        ['front', 'father_name', null, 8, 35.5, 104, null, 7.5, 'normal', null, 'left'],
        ['front', 'class_section', null, 8, 40.5, 104, null, 7.5, 'normal', null, 'left'],
        ['front', 'admission_no', null, 8, 45.5, 104, null, 7.5, 'normal', null, 'left'],
        ['front', 'exam_roll_no', null, 8, 50.5, 104, null, 9, 'bold', '#a16207', 'left'],
        ['front', 'date_sheet', null, 8, 57, 132, 27, 6.5, 'normal', null, 'left'],
        ['front', 'signature_line', 'Controller of Examinations', 104, 86, 36, null, 6.5, 'normal', null, 'center'],
        ['back', 'school_name', null, 6, 1.6, 136, null, 8, 'bold', '#ffffff', 'center'],
        ['back', 'custom_text', 'INSTRUCTIONS', 8, 13, 132, null, 9, 'bold', 'PRIMARY', 'left'],
        ['back', 'instructions', null, 8, 19, 132, 60, 7.5, 'normal', '#374151', 'left'],
    ];

    /**
     * @var array<int, array<int, mixed>>
     */
    private const INVITATION = [
        ['front', 'school_name', null, 10, 6, 190, null, 16, 'bold', '#ffffff', 'center'],
        ['front', 'custom_text', 'INVITATION', 10, 15.5, 190, null, 9, 'bold', '#fde68a', 'center'],
        ['front', 'custom_text', 'You are cordially invited to the', 10, 42, 190, null, 12, 'normal', 'PRIMARY', 'center'],
        ['front', 'custom_text', 'Annual Prize Distribution Ceremony', 10, 53, 190, null, 22, 'bold', 'PRIMARY', 'center'],
        ['front', 'custom_text', 'Saturday, 15 November 2026  ·  10:00 AM', 10, 72, 190, null, 11, 'bold', '#a16207', 'center'],
        ['front', 'custom_text', 'School Main Auditorium', 10, 80, 190, null, 10, 'normal', '#4b5563', 'center'],
        ['front', 'custom_text', 'Kindly confirm your attendance with the school office.', 10, 96, 190, null, 8.5, 'normal', '#6b7280', 'center'],
        ['front', 'signature_line', 'Principal', 150, 112, 45, null, 8, 'normal', null, 'center'],
    ];

    /**
     * @var array<int, array<int, mixed>>
     */
    private const EVENT_PASS = [
        ['front', 'school_name', null, 2, 3, 50, null, 7.5, 'bold', '#ffffff', 'center'],
        ['front', 'custom_text', 'EVENT PASS', 2, 10.4, 50, null, 5.4, 'bold', '#fde68a', 'center'],
        ['front', 'custom_text', 'Annual Sports Day 2026', 2, 24, 50, null, 10, 'bold', 'PRIMARY', 'center'],
        ['front', 'custom_text', 'ADMIT ONE', 2, 33, 50, null, 7, 'bold', '#a16207', 'center'],
        ['front', 'custom_text', 'Date: 20 December 2026', 4, 44, 46, null, 6.5, 'normal', null, 'left'],
        ['front', 'custom_text', 'Time: 8:30 AM onwards', 4, 49, 46, null, 6.5, 'normal', null, 'left'],
        ['front', 'custom_text', 'Venue: School Sports Ground', 4, 54, 46, null, 6.5, 'normal', null, 'left'],
        ['front', 'signature_line', 'Organiser', 15, 66, 24, null, 5.8, 'normal', null, 'center'],
        ['back', 'school_name', null, 2, 1.6, 50, null, 6, 'bold', '#ffffff', 'center'],
    ];

    /**
     * @var array<int, array<int, mixed>>
     */
    private const VISITOR = [
        ['front', 'school_name', null, 2, 2.2, 81.6, null, 7, 'bold', '#ffffff', 'center'],
        ['front', 'custom_text', 'VISITOR', 2, 14, 81.6, null, 18, 'bold', 'PRIMARY', 'center'],
        ['front', 'custom_text', 'Name: ______________________________', 6, 27, 74, null, 7, 'normal', null, 'left'],
        ['front', 'custom_text', 'Purpose: ____________________________', 6, 32.5, 74, null, 7, 'normal', null, 'left'],
        ['front', 'custom_text', 'Host / Department: ___________________', 6, 38, 74, null, 7, 'normal', null, 'left'],
        ['front', 'custom_text', 'Date: ______________', 6, 43.5, 74, null, 7, 'normal', null, 'left'],
        ['front', 'card_no', null, 2, 50.5, 81.6, null, 5, 'bold', '#ffffff', 'center'],
        ['back', 'school_name', null, 2, 0.8, 81.6, null, 5.5, 'bold', '#ffffff', 'center'],
    ];

    public function run(): void
    {
        $idBack = 'If found, please return to the school office.';
        $staffBack = 'This card is school property. If found, please return it to the school office.';
        $admitBack = 'Bring this admit card to every paper. Mobile phones are not allowed in the exam hall.';

        // [type, name, size, theme, band colour, back text, fields]
        $designs = [
            ['student', 'Student ID — Modern Navy (system)', 'cr80_portrait', 'modern', '#1d2b45', $idBack, [...self::MODERN_FRONT, ...self::MODERN_STUDENT, ...self::ID_BACK_MODERN]],
            ['student', 'Student ID Card (system)', 'cr80_portrait', 'classic', '#1d2b45', $idBack, [...self::ID_FRONT, ...self::STUDENT_FRONT]],
            ['student', 'Student ID — Wave Emerald (system)', 'cr80_portrait', 'wave', '#065f46', $idBack, [...self::ID_FRONT, ...self::STUDENT_FRONT]],
            ['student', 'Student ID — Elegant Maroon (system)', 'cr80_portrait', 'elegant', '#7f1d1d', $idBack, [...self::ID_FRONT, ...self::STUDENT_FRONT]],
            ['employee', 'Staff ID — Modern Teal (system)', 'cr80_portrait', 'modern', '#134e4a', $staffBack, [...self::MODERN_FRONT, ...self::MODERN_STAFF, ...self::ID_BACK_MODERN]],
            ['employee', 'Staff ID Card (system)', 'cr80_portrait', 'classic', '#1d2b45', $staffBack, [...self::ID_FRONT, ...self::STAFF_FRONT]],
            ['employee', 'Staff ID — Wave Royal Blue (system)', 'cr80_portrait', 'wave', '#1e3a8a', $staffBack, [...self::ID_FRONT, ...self::STAFF_FRONT]],
            ['employee', 'Staff ID — Elegant Teal (system)', 'cr80_portrait', 'elegant', '#134e4a', $staffBack, [...self::ID_FRONT, ...self::STAFF_FRONT]],
            ['admit_card', 'Admit Card (system)', 'a6_landscape', 'classic', '#1d2b45', $admitBack, self::ADMIT],
            ['admit_card', 'Admit Card — Wave Indigo (system)', 'a6_landscape', 'wave', '#312e81', $admitBack, self::ADMIT],
            ['invitation', 'Invitation — Elegant Maroon (system)', 'a5_landscape', 'elegant', '#7f1d1d', null, self::INVITATION],
            ['event_pass', 'Event Pass — Wave Purple (system)', 'cr80_portrait', 'wave', '#4c1d95', 'Entry only with this pass. Please keep it with you during the event.', self::EVENT_PASS],
            ['visitor', 'Visitor Card — Classic Slate (system)', 'cr80_landscape', 'classic', '#334155', 'Please wear this card at all times on campus and return it at the gate when leaving.', self::VISITOR],
        ];

        foreach ($designs as [$type, $name, $size, $theme, $color, $backText, $fields]) {
            if (CardTemplate::where('card_type', $type)->where('name', $name)->exists()) {
                continue;
            }

            [, $width, $height] = CardTemplate::SIZES[$size];
            $template = CardTemplate::create([
                'name' => $name,
                'card_type' => $type,
                'size_key' => $size,
                'theme' => $theme,
                'width_mm' => $width,
                'height_mm' => $height,
                'orientation' => $width > $height ? 'landscape' : 'portrait',
                'primary_color' => $color,
                'text_color' => '#1f2937',
                'show_qr' => in_array($type, ['student', 'employee'], true),
                'back_text' => $backText,
                'is_default' => ! CardTemplate::where('card_type', $type)->where('is_default', true)->exists(),
                'is_system' => true,
                'is_active' => true,
            ]);

            foreach ($fields as $position => $field) {
                $field[9] = $field[9] === 'PRIMARY' ? $color : $field[9];
                $field[2] = $field[2] === 'ROLE' ? ($type === 'student' ? 'STUDENT IDENTITY CARD' : 'STAFF IDENTITY CARD') : $field[2];
                $template->fields()->create([
                    ...array_combine(['side', 'field_key', 'custom_text', 'x_mm', 'y_mm', 'width_mm', 'height_mm', 'font_size', 'font_weight', 'color', 'align'], $field),
                    'sort_order' => $position,
                ]);
            }
        }
    }
}
