<?php

namespace App\Console\Commands;

use App\Enums\ReminderType;
use App\Mail\EventReminder;
use App\Models\Attendee;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendEventReminders extends Command
{
    protected $signature = 'events:send-reminders';

    protected $description = 'Queue reminder emails for attendees of upcoming events (3 days and 24 hours out).';

    public function handle(): int
    {
        $now = now();

        // Non-overlapping windows so an attendee gets each reminder once, at the
        // right time: 24h covers the next day, 3-day covers days 1-3.
        $sent = 0;
        $sent += $this->dispatchWindow(ReminderType::TwentyFourHour, $now, $now->copy()->addDay());
        $sent += $this->dispatchWindow(ReminderType::ThreeDay, $now->copy()->addDay(), $now->copy()->addDays(3));

        $this->info("Queued {$sent} reminder email(s).");

        return self::SUCCESS;
    }

    /**
     * Queue reminders for registered attendees of published events starting in
     * the given window who haven't already been reminded for this lead time.
     * The email_reminders row is written first so the unique (attendee_id,
     * reminder_type) constraint keeps repeated runs idempotent.
     */
    private function dispatchWindow(ReminderType $type, CarbonInterface $from, CarbonInterface $until): int
    {
        $count = 0;

        Attendee::query()
            ->where('status', 'registered')
            ->whereHas('event', fn ($q) => $q->where('status', 'published')->whereBetween('starts_at', [$from, $until]))
            ->whereDoesntHave('reminders', fn ($q) => $q->where('reminder_type', $type->value))
            ->with('event')
            ->chunkById(500, function ($attendees) use ($type, &$count) {
                foreach ($attendees as $attendee) {
                    $attendee->reminders()->create([
                        'event_id' => $attendee->event_id,
                        'reminder_type' => $type->value,
                        'sent_at' => now(),
                    ]);

                    Mail::to($attendee->email)->queue(new EventReminder($attendee, $type));
                    $count++;
                }
            });

        return $count;
    }
}
