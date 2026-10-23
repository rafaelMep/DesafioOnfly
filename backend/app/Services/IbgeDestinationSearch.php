<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class IbgeDestinationSearch
{
    private const KEY = 'destinations.ibge.catalog.v2';

    public function search(string $query): array
    {
        $term = $this->normalize($query);
        if ($term === '') {
            return ['data' => [], 'available' => true];
        }
        $catalog = $this->catalog();
        if ($catalog === null) {
            return ['data' => [], 'available' => false];
        }
        $tokens = explode(' ', $term);
        $matches = collect($catalog)->filter(function (array $city) use ($tokens) {
            foreach ($tokens as $token) {
                if (! str_contains($city['search'], $token)) {
                    return false;
                }
            }

            return true;
        })->sortBy(fn (array $city) => [
            $city['normalized_name'] === $term ? 0 : (str_starts_with($city['normalized_name'], $term) ? 1 : 2),
            $city['normalized_name'], $city['state'],
        ])->take(8)->map(fn (array $city) => array_diff_key($city, ['search' => true, 'normalized_name' => true]))->values();

        return ['data' => $matches->all(), 'available' => true];
    }

    private function catalog(): ?array
    {
        $cached = Cache::get(self::KEY);
        if (is_array($cached)) {
            return $cached;
        }
        if (Cache::has(self::KEY.'.cooldown')) {
            return Cache::get(self::KEY.'.stale');
        }
        try {
            $payload = Http::acceptJson()->connectTimeout(2)->timeout(5)
                ->get(config('services.ibge.url'), ['view' => 'nivelado'])->throw()->json();
            if (! is_array($payload) || ! array_is_list($payload) || $payload === []) {
                throw new \UnexpectedValueException('Catálogo IBGE inválido.');
            }
            $cities = [];
            foreach ($payload as $city) {
                if (! is_array($city)) {
                    continue;
                }
                $name = $city['municipio-nome'] ?? null;
                $state = $city['UF-sigla'] ?? null;
                $id = $city['municipio-id'] ?? null;
                if (! is_string($name) || ! is_string($state) || ! is_int($id)) {
                    continue;
                }
                $cities[] = [
                    'id' => $id, 'name' => $name, 'state' => $state, 'country' => 'Brasil', 'country_code' => 'BR',
                    'label' => $name.', '.$state.', Brasil',
                    'normalized_name' => $this->normalize($name),
                    'search' => $this->normalize($name.' '.$state.' '.($city['UF-nome'] ?? '').' Brasil'),
                ];
            }
            if ($cities === []) {
                throw new \UnexpectedValueException('Catálogo IBGE sem municípios válidos.');
            }
            Cache::put(self::KEY, $cities, now()->addDay());
            Cache::put(self::KEY.'.stale', $cities, now()->addDays(7));

            return $cities;
        } catch (Throwable $exception) {
            report($exception);
            Cache::put(self::KEY.'.cooldown', true, now()->addMinute());

            return Cache::get(self::KEY.'.stale');
        }
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($value))));
    }
}
