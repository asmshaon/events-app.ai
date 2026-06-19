<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function newUniqueId(): string
    {
        return (string) Str::uuid();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<EventImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(EventImage::class)->orderBy('sort_order');
    }

    /** @return HasMany<Attendee, $this> */
    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }

    public function displayName(): string
    {
        return $this->payload['name'] ?? 'Untitled event';
    }

    /** Start time converted into the event's own timezone for display. */
    public function startsAtLocal(): ?CarbonInterface
    {
        return $this->starts_at?->copy()->setTimezone($this->timezone ?: 'UTC');
    }

    public function displayLocation(): string
    {
        $cityCountry = collect([$this->city, $this->country])->filter()->implode(', ');

        return $cityCountry ?: ($this->address ?? 'Location to be announced');
    }
}
