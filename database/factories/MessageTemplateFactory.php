<?php

namespace Database\Factories;

use App\Models\MessageTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageTemplate>
 */
class MessageTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Absent alert',
            'channel' => 'in_app',
            'event_key' => 'absent_alert',
            'subject' => 'Absent today',
            'body' => '{student_name} ({class_section}) was marked absent on {date}.',
            'is_active' => true,
        ];
    }
}
