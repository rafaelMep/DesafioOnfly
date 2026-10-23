<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

class DestinationSearch
{
    private const PREFIX = 'destinations.world.pt.v1';

    public function __construct(private IbgeDestinationSearch $ibge) {}

    public function search(string $query): array
    {
        $world = $this->searchWorld($query);
        if ($world['data'] !== []) {
            return [...$world, 'source' => 'open-meteo'];
        }

        // A valid empty response needs a second source too, not just HTTP failures.
        $brazil = $this->ibge->search($query);
        if ($brazil['data'] !== []) {
            return [...$brazil, 'source' => 'ibge'];
        }

        return ['data' => [], 'available' => $world['available'] && $brazil['available'], 'source' => null];
    }

    private function searchWorld(string $query): array
    {
        $query = trim(preg_replace('/\s+/u', ' ', $query));
        $key = self::PREFIX.'.'.hash('sha256', Str::lower(Str::ascii($query)));
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return ['data' => $cached, 'available' => true];
        }

        if (Cache::has(self::PREFIX.'.cooldown') || ! $this->withinBudget()) {
            return $this->fallback($key);
        }

        try {
            $payload = Http::acceptJson()->connectTimeout(2)->timeout(5)
                ->get(config('services.geocoding.url'), [
                    'name' => $query, 'count' => 8, 'language' => 'pt', 'format' => 'json',
                ])->throw()->json();
            // Empty searches normally contain only generationtime_ms, not results.
            if (! is_array($payload) || (! array_key_exists('results', $payload) && ! isset($payload['generationtime_ms']))) {
                throw new \UnexpectedValueException('Resposta de geocodificação inválida.');
            }
            $results = $payload['results'] ?? [];
            if (! is_array($results) || ! array_is_list($results)) {
                throw new \UnexpectedValueException('Lista de destinos inválida.');
            }

            $cities = [];
            $invalid = 0;
            foreach ($results as $city) {
                if (! is_array($city)) {
                    $invalid++;

                    continue;
                }
                if (! in_array($city['feature_code'] ?? '', ['PPLC', 'PPLA', 'PPLA2', 'PPLA3', 'PPLA4', 'PPLA5', 'PPL', 'PPLL'], true)) {
                    continue;
                }
                $name = $city['name'] ?? null;
                $country = $city['country'] ?? null;
                $code = $city['country_code'] ?? null;
                $id = $city['id'] ?? null;
                $state = $city['admin1'] ?? '';
                if (! is_string($name) || ! is_string($country) || ! is_string($code) || ! is_int($id) || ! is_string($state)) {
                    $invalid++;

                    continue;
                }
                $label = implode(', ', array_unique(array_filter([$name, $state, $country])));
                if (mb_strlen($label) > 150) {
                    $label = $name.', '.$country;
                }
                if (mb_strlen($label) > 150) {
                    continue;
                }
                $cities[] = ['id' => $id, 'name' => $name, 'state' => $state, 'country' => $country, 'country_code' => $code, 'label' => $label];
            }

            if ($cities === [] && $invalid > 0) {
                throw new \UnexpectedValueException('Nenhum destino válido na resposta.');
            }

            $cities = array_slice($cities, 0, 8);
            Cache::put($key, $cities, now()->addDay());
            Cache::put($key.'.stale', $cities, now()->addDays(7));

            return ['data' => $cities, 'available' => true];
        } catch (Throwable $exception) {
            report($exception);
            Cache::put(self::PREFIX.'.cooldown', true, now()->addMinute());

            return $this->fallback($key);
        }
    }

    private function withinBudget(): bool
    {
        if (config('services.geocoding.per_minute') < 1 || config('services.geocoding.per_day') < 1) {
            return false;
        }
        // Shared across users; below the hosted provider's free non-commercial limits.
        $minute = self::PREFIX.'.minute';
        $day = self::PREFIX.'.day';
        if (RateLimiter::tooManyAttempts($minute, config('services.geocoding.per_minute'))
            || RateLimiter::tooManyAttempts($day, config('services.geocoding.per_day'))) {
            return false;
        }
        RateLimiter::hit($minute, 60);
        RateLimiter::hit($day, 86400);

        return true;
    }

    private function fallback(string $key): array
    {
        $stale = Cache::get($key.'.stale');

        return ['data' => $stale ?? [], 'available' => is_array($stale)];
    }
}
