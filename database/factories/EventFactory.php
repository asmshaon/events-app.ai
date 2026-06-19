<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventImage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $type = fake()->randomElement(['concert', 'conference', 'meetup', 'workshop', 'festival', 'sports', 'networking', 'exhibition']);
        $lat = fake()->latitude();
        $lng = fake()->longitude();
        $startsAt = fake()->numberBetween(strtotime('-1 year'), strtotime('+1 year'));
        $endsAt = $startsAt + 7200;

        return [
            'user_id' => User::factory(),
            'type' => $type,
            'status' => fake()->randomElement(['draft', 'published', 'cancelled', 'sold_out']),
            'created_time' => $startsAt,
            'latitude' => $lat,
            'longitude' => $lng,
            'starts_at' => date('Y-m-d H:i:s', $startsAt),
            'ends_at' => date('Y-m-d H:i:s', $endsAt),
            'timezone' => fake()->timezone(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => fake()->countryCode(),
            'payload' => [
                'name' => rtrim(ucwords(fake()->sentence(3)), '.'),
                'category' => $type,
                'venue' => ['name' => fake()->company(), 'capacity' => fake()->numberBetween(20, 50000)],
                'location' => ['lat' => $lat, 'lng' => $lng],
                'schedule' => ['starts_at' => $startsAt, 'ends_at' => $startsAt + 7200],
                'pricing' => ['currency' => 'USD', 'min_price' => fake()->randomFloat(2, 0, 250)],
            ],
        ];
    }

    /**
     * Attach locally-served images to the event (ordered by sort_order).
     */
    public function withImages(int $count = 2): static
    {
        return $this->has(
            EventImage::factory()
                ->count($count)
                ->state(new Sequence(fn (Sequence $sequence) => ['sort_order' => $sequence->index])),
            'images',
        );
    }
}
