<?php

namespace Database\Seeders;

use App\Models\CalendarEvent;
use App\Models\Lookup;
use Illuminate\Database\Seeder;

/**
 * Demo school calendar for the current term: public events (shown on the website), a meeting and holidays.
 */
class CalendarSeeder extends Seeder
{
    public function run(): void
    {
        $type = fn (string $name): ?int => Lookup::where('type', 'calendar_event_type')->where('name', $name)->value('id');

        foreach ([
            ['Annual Science & Tech Fair', 'Event', '2026-11-14', '2026-11-14', '09:00', '14:00', 'Main Auditorium', false, true, 'Robotics demonstrations, 3D printing showcase and student research posters.'],
            ['Inter-School Sports Gala', 'Sports', '2026-11-21', '2026-11-21', '08:30', '13:30', 'Sports Complex', false, true, 'Track events, football and basketball finals, and prize distribution.'],
            ['Parent-Teacher Meeting (Term 1)', 'Parent-Teacher Meeting', '2026-12-05', '2026-12-05', '10:00', '15:00', 'Campus Auditorium', false, true, 'Individual progress reviews for term 1.'],
            ['Quaid-e-Azam Day', 'Holiday', '2026-12-25', '2026-12-25', null, null, null, true, false, null],
            ['Winter Vacation', 'Holiday', '2026-12-26', '2027-01-03', null, null, null, true, false, null],
        ] as [$title, $typeName, $from, $to, $startTime, $endTime, $venue, $holiday, $public, $description]) {
            CalendarEvent::updateOrCreate(['title' => $title, 'starts_on' => $from], [
                'event_type_id' => $type($typeName),
                'ends_on' => $to,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'venue' => $venue,
                'is_holiday' => $holiday,
                'is_public' => $public,
                'description' => $description,
            ]);
        }
    }
}
