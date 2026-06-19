<?php

namespace App\Mail;

use App\Models\Attendee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AttendeeConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Attendee $attendee) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're registered for {$this->attendee->event->displayName()}",
        );
    }

    public function content(): Content
    {
        $event = $this->attendee->event;

        return new Content(
            view: 'mail.attendee-confirmation',
            with: [
                'attendeeName' => $this->attendee->name,
                'eventName' => $event->displayName(),
                'when' => $event->startsAtLocal()?->format('l, F j, Y \a\t g:i A'),
                'timezone' => $event->timezone,
                'where' => $event->displayLocation(),
            ],
        );
    }
}
