<?php

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the events listing shell with filter metadata', function () {
    Event::factory()->create(['country' => 'US', 'city' => 'Austin']);

    $this->get(route('events.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/Index')
            ->has('statuses', 4)
            ->has('filterOptions.countries')
            ->has('filterOptions.cities')
        );
});

it('renders the Visual 1 page with filter metadata', function () {
    Event::factory()->create(['country' => 'GB', 'city' => 'London']);

    $this->get(route('events.visual1'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/VisualOne')
            ->has('statuses', 4)
            ->has('filterOptions.countries', 1)
            ->where('filterOptions.cities.0.city', 'London')
        );
});

it('returns a json page of enriched event cards with load stats', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);
    Event::factory()->for($user)->create([
        'type' => 'concert',
        'status' => 'published',
        'starts_at' => '2026-07-01 18:00:00',
        'timezone' => 'Etc/GMT+5',
        'city' => 'New York',
        'country' => 'US',
        'payload' => ['name' => 'Synthwave Night', 'pricing' => ['min_price' => 49.5]],
    ]);

    $this->getJson(route('events.data'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'name', 'type', 'status', 'starts_at', 'timezone', 'city', 'country', 'address', 'price', 'image_url', 'user']],
            'current_page',
            'last_page',
            'total',
            'stats' => ['ms', 'bytes'],
        ])
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.name', 'Synthwave Night')
        ->assertJsonPath('data.0.city', 'New York')
        ->assertJsonPath('data.0.price', 49.5)
        ->assertJsonPath('data.0.user.name', 'Ada Lovelace');
});

it('filters the data endpoint by status', function () {
    Event::factory()->create(['status' => 'published']);
    Event::factory()->create(['status' => 'cancelled']);

    $this->getJson(route('events.data', ['status' => 'cancelled']))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.status', 'cancelled');
});

it('filters the data endpoint by date range', function () {
    Event::factory()->create(['starts_at' => '2026-01-15 12:00:00']);
    Event::factory()->create(['starts_at' => '2026-06-15 12:00:00']);
    Event::factory()->create(['starts_at' => '2026-12-15 12:00:00']);

    $this->getJson(route('events.data', ['from' => '2026-05-01', 'to' => '2026-07-01']))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.starts_at', fn ($value) => str_starts_with($value, '2026-06-15'));
});

it('filters the data endpoint by country and city', function () {
    Event::factory()->create(['country' => 'US', 'city' => 'Austin']);
    Event::factory()->create(['country' => 'US', 'city' => 'Denver']);
    Event::factory()->create(['country' => 'GB', 'city' => 'London']);

    $this->getJson(route('events.data', ['country' => 'US', 'city' => 'Denver']))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.city', 'Denver');
});

it('rejects an invalid status filter', function () {
    $this->getJson(route('events.data', ['status' => 'bogus']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('serves a stored image url, falling back to a placeholder', function () {
    $withImage = Event::factory()->create(['starts_at' => '2026-02-01 10:00:00']);
    $withImage->images()->create(['path' => 'events/custom/poster.jpg', 'sort_order' => 0]);

    $withoutImage = Event::factory()->create(['starts_at' => '2026-01-01 10:00:00']);

    $response = $this->getJson(route('events.data'))->assertOk();

    // Ordered by starts_at desc, so the imaged event comes first.
    $response->assertJsonPath('data.0.id', $withImage->id)
        ->assertJsonPath('data.0.image_url', '/storage/events/custom/poster.jpg')
        ->assertJsonPath('data.1.id', $withoutImage->id)
        ->assertJsonPath('data.1.image_url', '/storage/events/placeholders/event-1.svg');
});

it('shows an event detail page with its images loaded', function () {
    $event = Event::factory()->withImages(2)->create([
        'payload' => ['name' => 'Global Tech Summit', 'location' => ['lat' => 1.5, 'lng' => 2.5]],
    ]);

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/Show')
            ->where('event.id', $event->id)
            ->where('event.payload.name', 'Global Tech Summit')
            ->has('event.images', 2)
        );
});

it('renders the Visual 2 timeline page with filter metadata', function () {
    Event::factory()->create(['country' => 'JP', 'city' => 'Tokyo']);

    $this->get(route('events.visual2'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/VisualTwo')
            ->has('statuses', 4)
            ->has('filterOptions.countries', 1)
            ->where('filterOptions.cities.0.city', 'Tokyo')
        );
});

it('renders the dashboard', function () {
    $this->get(route('dashboard'))->assertOk();
});
