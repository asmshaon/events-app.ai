<?php

use App\Mail\AttendeeConfirmation;
use App\Models\Attendee;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('registers an attendee and queues a confirmation email', function () {
    Mail::fake();
    $event = Event::factory()->create();

    $this->post(route('events.rsvp', $event), [
        'name' => 'Grace Hopper',
        'email' => 'Grace@Example.com',
    ])->assertRedirect();

    $this->assertDatabaseHas('attendees', [
        'event_id' => $event->id,
        'email' => 'grace@example.com',
        'name' => 'Grace Hopper',
        'status' => 'registered',
    ]);

    Mail::assertQueued(AttendeeConfirmation::class, fn ($mail) => $mail->hasTo('grace@example.com'));
});

it('prevents duplicate registrations and does not resend the confirmation', function () {
    Mail::fake();
    $event = Event::factory()->create();

    $this->post(route('events.rsvp', $event), ['name' => 'First', 'email' => 'dup@example.com']);
    $this->post(route('events.rsvp', $event), ['name' => 'Second', 'email' => 'dup@example.com']);

    expect(Attendee::where('event_id', $event->id)->where('email', 'dup@example.com')->count())->toBe(1);
    Mail::assertQueued(AttendeeConfirmation::class, 1);
});

it('validates the RSVP input', function () {
    $event = Event::factory()->create();

    $this->post(route('events.rsvp', $event), ['name' => '', 'email' => 'not-an-email'])
        ->assertSessionHasErrors(['name', 'email']);
});
