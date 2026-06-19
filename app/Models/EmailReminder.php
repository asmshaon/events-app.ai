<?php

namespace App\Models;

use Database\Factories\EmailReminderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailReminder extends Model
{
    /** @use HasFactory<EmailReminderFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /** @return BelongsTo<Attendee, $this> */
    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
