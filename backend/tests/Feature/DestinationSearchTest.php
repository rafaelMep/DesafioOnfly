<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DestinationSearchTest extends TestCase
{
    use RefreshDatabase;

    private function london(): array
    {
        return ['results' => [[
            'id' => 2643743, 'name' => 'Londres', 'admin1' => 'Inglaterra',
            'country' => 'Reino Unido', 'country_code' => 'GB', 'feature_code' => 'PPLC',
        ]]];
    }

    private function brazil(): array
    {
        return [
            ['municipio-id' => 3146107, 'municipio-nome' => 'Ouro Preto', 'UF-sigla' => 'MG', 'UF-nome' => 'Minas Gerais'],
            ['municipio-id' => 3106200, 'municipio-nome' => 'Belo Horizonte', 'UF-sigla' => 'MG', 'UF-nome' => 'Minas Gerais'],
        ];
    }

    public function test_search_requires_authentication_and_valid_query(): void
    {
        Http::preventStrayRequests();
        $this->getJson('/api/destinations?q=Londres')->assertUnauthorized();
        $this->actingAs(User::factory()->create(), 'api')->getJson('/api/destinations?q=a')->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_international_search_uses_portuguese_and_reuses_normalized_cache(): void
    {
        Http::fake(['*' => Http::response($this->london())]);
        $this->actingAs(User::factory()->create(), 'api')
            ->getJson('/api/destinations?q=LONDRES')->assertOk()
            ->assertJsonPath('available', true)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.label', 'Londres, Inglaterra, Reino Unido')
            ->assertJsonPath('data.0.country_code', 'GB');
        $this->getJson('/api/destinations?q=londres')->assertOk();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['language'] === 'pt' && $request['name'] === 'LONDRES');
    }

    public function test_provider_failure_is_non_blocking_and_has_global_cooldown(): void
    {
        Http::fake(['*' => Http::response([], 503)]);
        $this->actingAs(User::factory()->create(), 'api');
        foreach (['Londres', 'Paris'] as $query) {
            $this->getJson('/api/destinations?q='.$query)->assertOk()->assertExactJson(['data' => [], 'available' => false, 'source' => null]);
        }
        Http::assertSentCount(2);
    }

    public function test_stale_results_remain_available_if_provider_fails(): void
    {
        Http::fake(['*' => Http::response($this->london())]);
        $this->actingAs(User::factory()->create(), 'api')->getJson('/api/destinations?q=Londres')->assertOk();
        $this->travel(25)->hours();
        Http::fake(['*' => Http::response([], 503)]);
        $this->getJson('/api/destinations?q=Londres')->assertOk()->assertJsonPath('available', true)
            ->assertJsonPath('data.0.label', 'Londres, Inglaterra, Reino Unido');
    }

    public function test_malformed_response_does_not_block_free_text_creation(): void
    {
        Http::fake(['*' => Http::response(['unexpected' => 'payload'])]);
        $this->actingAs(User::factory()->create(), 'api')
            ->getJson('/api/destinations?q=Paris')->assertOk()->assertJsonPath('available', false);
        $this->postJson('/api/travel-orders', [
            'destination' => 'Paris, França', 'departure_date' => '2099-01-10', 'return_date' => '2099-01-15',
        ])->assertCreated()->assertJsonPath('data.destination', 'Paris, França');
    }

    public function test_no_matches_is_distinct_from_unavailable_and_is_cached(): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response(['generationtime_ms' => 0.1]),
            'servicodados.ibge.gov.br/*' => Http::response($this->brazil()),
        ]);
        $this->actingAs(User::factory()->create(), 'api');
        foreach ([1, 2] as $attempt) {
            $this->getJson('/api/destinations?q=xyz123')->assertOk()->assertExactJson(['data' => [], 'available' => true, 'source' => null]);
        }
        Http::assertSentCount(2);
    }

    public function test_shared_request_budget_protects_the_free_provider(): void
    {
        config(['services.geocoding.per_minute' => 1]);
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response($this->london()),
            'servicodados.ibge.gov.br/*' => Http::response($this->brazil()),
        ]);
        $this->actingAs(User::factory()->create(), 'api')->getJson('/api/destinations?q=Londres')->assertOk();
        $this->getJson('/api/destinations?q=Paris')->assertOk()->assertJsonPath('available', false);
        $this->getJson('/api/destinations?q=Londres')->assertOk()->assertJsonPath('available', true);
        Http::assertSentCount(2);
    }

    public function test_non_city_results_are_filtered_and_missing_regions_are_supported(): void
    {
        $city = $this->london()['results'][0];
        unset($city['admin1']);
        Http::fake(['*' => Http::response(['results' => [
            ['feature_code' => 'ADM1', 'name' => 'Inglaterra'], $city,
        ]])]);
        $this->actingAs(User::factory()->create(), 'api')->getJson('/api/destinations?q=Londres')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.label', 'Londres, Reino Unido');
    }

    public function test_ibge_fills_empty_world_results_and_ignores_accents(): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response(['results' => []]),
            'servicodados.ibge.gov.br/*' => Http::response($this->brazil()),
        ]);
        $this->actingAs(User::factory()->create(), 'api')->getJson('/api/destinations?q=OURO%20PRETO')
            ->assertOk()->assertJsonPath('source', 'ibge')->assertJsonPath('available', true)
            ->assertJsonPath('data.0.label', 'Ouro Preto, MG, Brasil')
            ->assertJsonMissingPath('data.0.search')->assertJsonMissingPath('data.0.normalized_name');
        $this->getJson('/api/destinations?q=ouro%20preto')->assertOk()->assertJsonPath('source', 'ibge');
        Http::assertSentCount(2);
    }

    public function test_ibge_is_independent_of_world_provider_failure_and_cooldown(): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response([], 503),
            'servicodados.ibge.gov.br/*' => Http::response($this->brazil()),
        ]);
        $this->actingAs(User::factory()->create(), 'api')->getJson('/api/destinations?q=Ouro%20Preto')
            ->assertOk()->assertJsonPath('source', 'ibge')->assertJsonPath('data.0.country_code', 'BR');
        $this->getJson('/api/destinations?q=Belo')->assertOk()->assertJsonPath('data.0.name', 'Belo Horizonte');
        Http::assertSentCount(2);
    }

    public function test_ibge_still_works_when_world_budget_is_exhausted(): void
    {
        config(['services.geocoding.per_minute' => 0]);
        Http::fake(['servicodados.ibge.gov.br/*' => Http::response($this->brazil())]);
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create(), 'api')->getJson('/api/destinations?q=Ouro%20Preto')
            ->assertOk()->assertJsonPath('source', 'ibge')->assertJsonPath('available', true);
        Http::assertSentCount(1);
    }

    public function test_stale_ibge_catalog_survives_failure_of_both_providers(): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response([], 503),
            'servicodados.ibge.gov.br/*' => Http::response($this->brazil()),
        ]);
        $this->actingAs(User::factory()->create(), 'api')->getJson('/api/destinations?q=Ouro%20Preto')->assertOk();
        $this->travel(25)->hours();
        Http::fake(['*' => Http::response([], 503)]);
        $this->getJson('/api/destinations?q=Ouro%20Preto')->assertOk()
            ->assertJsonPath('source', 'ibge')->assertJsonPath('data.0.label', 'Ouro Preto, MG, Brasil');
    }

    public function test_bad_world_row_does_not_discard_valid_cities_or_trigger_fallback(): void
    {
        $response = $this->london();
        $response['results'][] = ['feature_code' => 'PPL', 'name' => 'Incomplete city'];
        Http::fake(['geocoding-api.open-meteo.com/*' => Http::response($response)]);
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create(), 'api')->getJson('/api/destinations?q=Londres')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('source', 'open-meteo');
        Http::assertSentCount(1);
    }
}
