<?php

namespace Database\Factories;

use App\Enums\ReminderType;
use App\Models\Attendee;
use App\Models\EmailReminder;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailReminder>
 */
class EmailReminderFactory extends Factory
{
    protected $model = EmailReminder::class;

    public function definition(): array
    {
        return [
            'attendee_id' => Attendee::factory(),
            'event_id' => Event::factory(),
            'reminder_type' => ReminderType::ThreeDay->value,
            'sent_at' => now(),
        ];
    }
}
