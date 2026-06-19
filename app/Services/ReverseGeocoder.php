<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Turns latitude/longitude into a human-readable location.
 *
 * Every coordinate is rounded to a grid cell and cached in `geocoded_locations`
 * so a given area is geocoded at most once — the seeder leans on this to resolve
 * its handful of city anchors instead of geocoding millions of rows. Network
 * lookups use OpenStreetMap's Nominatim; if that's disabled, unreachable, or
 * we're in the test suite, it degrades to an offline coordinate label.
 */
class ReverseGeocoder
{
    /** Decimal places used to build the dedup/cache grid key (~1.1km cells). */
    private const PRECISION = 2;

    /**
     * @return array{address: ?string, city: ?string, country: ?string, timezone: string}
     */
    public function lookup(float $lat, float $lng): array
    {
        $latKey = round($lat, self::PRECISION);
        $lngKey = round($lng, self::PRECISION);
        $timezone = $this->timezoneForLongitude($lng);

        $cached = DB::table('geocoded_locations')
            ->where('lat_key', $latKey)
            ->where('lng_key', $lngKey)
            ->first();

        if ($cached !== null) {
            return [
                'address' => $cached->address,
                'city' => $cached->city,
                'country' => $cached->country,
                'timezone' => $cached->timezone ?: $timezone,
            ];
        }

        $geocoded = $this->geocode($lat, $lng);

        // Only cache real results — caching a fallback would block a later
        // online run from ever resolving this cell to a real address.
        if ($geocoded === null) {
            return $this->fallback($lat, $lng, $timezone);
        }

        $result = [...$geocoded, 'timezone' => $timezone];

        DB::table('geocoded_locations')->insertOrIgnore([
            'lat_key' => $latKey,
            'lng_key' => $lngKey,
            'address' => $result['address'],
            'city' => $result['city'],
            'country' => $result['country'],
            'timezone' => $result['timezone'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $result;
    }

    /**
     * Hit Nominatim. Returns null (so the caller falls back without caching)
     * when geocoding is disabled, we're testing, or the request fails.
     *
     * @return array{address: string, city: ?string, country: ?string}|null
     */
    private function geocode(float $lat, float $lng): ?array
    {
        if (! config('services.geocoder.enabled') || app()->runningUnitTests()) {
            return null;
        }

        try {
            $response = Http::withHeaders(['User-Agent' => config('services.geocoder.user_agent')])
                ->timeout(10)
                ->get(rtrim((string) config('services.geocoder.url'), '/').'/reverse', [
                    'lat' => $lat,
                    'lon' => $lng,
                    'format' => 'jsonv2',
                    'accept-language' => 'en',
                    'zoom' => 12,
                ]);

            // Nominatim's usage policy caps requests at ~1/sec.
            usleep(1_100_000);

            if (! $response->successful()) {
                return null;
            }

            $address = $response->json('address', []);
            $city = $address['city'] ?? $address['town'] ?? $address['village']
                ?? $address['municipality'] ?? $address['county'] ?? null;
            $country = isset($address['country_code'])
                ? strtoupper((string) $address['country_code'])
                : null;

            return [
                'address' => $response->json('display_name') ?: $this->coordinateLabel($lat, $lng),
                'city' => $city,
                'country' => $country,
            ];
        } catch (\Throwable $e) {
            Log::warning('Reverse geocoding failed; using offline fallback.', [
                'lat' => $lat,
                'lng' => $lng,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{address: string, city: null, country: null, timezone: string}
     */
    private function fallback(float $lat, float $lng, string $timezone): array
    {
        return [
            'address' => $this->coordinateLabel($lat, $lng),
            'city' => null,
            'country' => null,
            'timezone' => $timezone,
        ];
    }

    private function coordinateLabel(float $lat, float $lng): string
    {
        return sprintf('%.4f, %.4f', $lat, $lng);
    }

    /**
     * Approximate an IANA-compatible timezone from longitude (15° ≈ 1 hour).
     * Etc/GMT zones invert the sign, hence the negation.
     */
    private function timezoneForLongitude(float $lng): string
    {
        $offset = max(-12, min(14, (int) round($lng / 15)));

        return $offset === 0 ? 'UTC' : sprintf('Etc/GMT%+d', -$offset);
    }
}
