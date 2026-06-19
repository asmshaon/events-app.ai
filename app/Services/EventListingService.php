<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

/**
 * Query/shape logic for the event listing consumed by the browse pages.
 *
 * Keeps the controller thin: it builds the filtered, paginated query against the
 * promoted columns (never dragging the full `payload` over the wire) and maps
 * each row into a lightweight card DTO for the frontend.
 */
class EventListingService
{
    /** @var list<string> */
    public const STATUSES = ['draft', 'published', 'cancelled', 'sold_out'];

    private const PER_PAGE = 50;

    /** Served to events that have no images of their own (only a seeded subset do). */
    private const PLACEHOLDER_IMAGE = 'events/placeholders/event-1.svg';

    private const FILTER_OPTIONS_TTL = 3600;

    /**
     * @param  array{status?: ?string, from?: ?string, to?: ?string, country?: ?string, city?: ?string}  $filters
     * @return array{0: LengthAwarePaginator<int, array<string, mixed>>, 1: array{ms: int, bytes: int}}
     */
    public function paginate(array $filters): array
    {
        $start = microtime(true);

        $paginator = Event::query()
            ->with(['user:id,name', 'images'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('starts_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('starts_at', '<=', $to))
            ->when($filters['country'] ?? null, fn ($q, $country) => $q->where('country', $country))
            ->when($filters['city'] ?? null, fn ($q, $city) => $q->where('city', $city))
            ->orderByDesc('starts_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // through() maps the page's items in place without tripping the
        // paginator's model-typed setCollection().
        $paginator->through(fn (Event $event) => $this->mapCard($event));

        $stats = [
            'ms' => (int) round((microtime(true) - $start) * 1000),
            'bytes' => strlen((string) json_encode($paginator->items())),
        ];

        return [$paginator, $stats];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapCard(Event $event): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $event->payload ?? [];
        $firstImage = $event->images->first();

        return [
            'id' => $event->id,
            'name' => $payload['name'] ?? null,
            'type' => $event->type,
            'status' => $event->status,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'timezone' => $event->timezone,
            'city' => $event->city,
            'country' => $event->country,
            'address' => $event->address,
            'price' => isset($payload['pricing']['min_price']) ? (float) $payload['pricing']['min_price'] : null,
            // Root-relative so it resolves against the current host:port (the app
            // is port-mapped in Docker, so an APP_URL-based absolute URL would 404).
            'image_url' => '/storage/'.($firstImage->path ?? self::PLACEHOLDER_IMAGE),
            'user' => $event->user ? ['id' => $event->user->id, 'name' => $event->user->name] : null,
        ];
    }

    /**
     * Distinct country/city pairs for the filter dropdowns. The set is small and
     * seed-stable, so it's cached rather than scanned on every request.
     *
     * @return array{countries: array<int, string>, cities: array<int, array{country: string, city: string}>}
     */
    public function filterOptions(): array
    {
        return Cache::remember('events:filter-options', self::FILTER_OPTIONS_TTL, function () {
            $pairs = Event::query()
                ->select('country', 'city')
                ->whereNotNull('country')
                ->whereNotNull('city')
                ->distinct()
                ->orderBy('country')
                ->orderBy('city')
                ->get();

            return [
                'countries' => $pairs->pluck('country')->unique()->map(fn ($c) => (string) $c)->values()->all(),
                'cities' => $pairs->map(fn ($p) => ['country' => (string) $p->country, 'city' => (string) $p->city])->values()->all(),
            ];
        });
    }
}
