<?php

use App\Enums\ReminderType;
use App\Mail\EventReminder;
use App\Models\Attendee;
use App\Models\EmailReminder;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('queues a 3-day reminder for events a couple of days out', function () {
    Mail::fake();
    $event = Event::factory()->create(['status' => 'published', 'starts_at' => now()->addDays(2)]);
    $attendee = Attendee::factory()->for($event)->create();

    $this->artisan('events:send-reminders')->assertSuccessful();

    Mail::assertQueued(EventReminder::class, fn ($mail) => $mail->type === ReminderType::ThreeDay && $mail->hasTo($attendee->email));
    $this->assertDatabaseHas('email_reminders', ['attendee_id' => $attendee->id, 'reminder_type' => '3_day']);
});

it('queues a 24-hour reminder for events within a day', function () {
    Mail::fake();
    $event = Event::factory()->create(['status' => 'published', 'starts_at' => now()->addHours(12)]);
    $attendee = Attendee::factory()->for($event)->create();

    $this->artisan('events:send-reminders')->assertSuccessful();

    Mail::assertQueued(EventReminder::class, fn ($mail) => $mail->type === ReminderType::TwentyFourHour);
    $this->assertDatabaseHas('email_reminders', ['attendee_id' => $attendee->id, 'reminder_type' => '24_hour']);
});

it('does not remind for events outside the windows or not published', function () {
    Mail::fake();
    Attendee::factory()->for(Event::factory()->create(['status' => 'published', 'starts_at' => now()->addDays(10)]))->create();
    Attendee::factory()->for(Event::factory()->create(['status' => 'draft', 'starts_at' => now()->addDays(2)]))->create();

    $this->artisan('events:send-reminders')->assertSuccessful();

    Mail::assertNothingQueued();
});

it('is idempotent across repeated runs', function () {
    Mail::fake();
    $event = Event::factory()->create(['status' => 'published', 'starts_at' => now()->addDays(2)]);
    Attendee::factory()->for($event)->create();

    $this->artisan('events:send-reminders');
    $this->artisan('events:send-reminders');

    Mail::assertQueued(EventReminder::class, 1);
    expect(EmailReminder::count())->toBe(1);
});
