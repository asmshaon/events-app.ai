<?php

namespace App\Services;

use App\Mail\AttendeeConfirmation;
use App\Models\Attendee;
use App\Models\Event;
use Illuminate\Support\Facades\Mail;

class AttendeeService
{
    /**
     * Register interest in an event. Idempotent per (event, email): a repeat
     * registration returns the existing attendee without re-sending mail. The
     * confirmation email is queued only for a genuinely new registration.
     *
     * @param  array{name: string, email: string}  $data
     * @return array{attendee: Attendee, created: bool}
     */
    public function register(Event $event, array $data): array
    {
        $attendee = $event->attendees()->firstOrCreate(
            ['email' => $data['email']],
            ['name' => $data['name'], 'status' => 'registered'],
        );

        if ($attendee->wasRecentlyCreated) {
            $attendee->setRelation('event', $event);
            Mail::to($attendee->email)->queue(new AttendeeConfirmation($attendee));
        }

        return ['attendee' => $attendee, 'created' => $attendee->wasRecentlyCreated];
    }
}
