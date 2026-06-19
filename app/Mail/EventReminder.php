<?php

namespace App\Mail;

use App\Enums\ReminderType;
use App\Models\Attendee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Attendee $attendee,
        public ReminderType $type,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reminder: {$this->attendee->event->displayName()} is in {$this->type->label()}",
        );
    }

    public function content(): Content
    {
        $event = $this->attendee->event;

        return new Content(
            view: 'mail.event-reminder',
            with: [
                'attendeeName' => $this->attendee->name,
                'eventName' => $event->displayName(),
                'leadTime' => $this->type->label(),
                'when' => $event->startsAtLocal()?->format('l, F j, Y \a\t g:i A'),
                'timezone' => $event->timezone,
                'where' => $event->displayLocation(),
            ],
        );
    }
}
