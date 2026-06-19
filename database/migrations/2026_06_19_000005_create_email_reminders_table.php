<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks which reminder emails have been sent so the scheduler stays
     * idempotent: the unique (attendee_id, reminder_type) pair guarantees a
     * given attendee is reminded at most once per window (3_day / 24_hour),
     * even if the scheduled command runs repeatedly.
     */
    public function up(): void
    {
        Schema::create('email_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendee_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('event_id')->constrained()->cascadeOnDelete();
            $table->string('reminder_type'); // 3_day | 24_hour
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['attendee_id', 'reminder_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_reminders');
    }
};
