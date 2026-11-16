<?php

use App\Models\Production;
use App\Models\Sale;
use App\Models\StockDistribution;
use App\Models\StockReturn;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('admin sees today\'s produced, distributed and returned totals', function () {
    Sanctum::actingAs(User::factory()->admin()->create());
    $production = Production::factory()->create(['quantity_produced' => 1000]);
    $first = StockDistribution::factory()->for($production)->create(['quantity_distributed' => 400]);
    $second = StockDistribution::factory()->for($production)->create(['quantity_distributed' => 300]);
    StockReturn::factory()->for($first)->verified()->create(['quantity_returned' => 30]);
    StockReturn::factory()->for($second)->create(['quantity_returned' => 20]);
    StockReturn::factory()->for($second)->rejected()->create(['quantity_returned' => 99]);
    Sale::factory()->for($first, 'stockDistribution')->create(['quantity_sold' => 300]);
    Sale::factory()->for($second, 'stockDistribution')->create(['quantity_sold' => 200]);

    // Another day's activity must not be counted.
    StockDistribution::factory()
        ->for(Production::factory()->daysAgo(1))
        ->has(StockReturn::factory(), 'stockReturns')
        ->create(['distribution_date' => today()->subDay()]);

    $this->getJson(route('api.v1.inventory'))
        ->assertOk()
        ->assertExactJson(['data' => [
            'date' => today()->toDateString(),
            'produced' => 1000,
            'distributed' => 700,
            'sold' => 500,
            'returned' => 50,
            'returned_verified' => 30,
            'returned_pending' => 20,
            'with_riders' => 150,
            'undistributed' => 300,
        ]]);
});

test('a specific date can be requested', function () {
    Sanctum::actingAs(User::factory()->admin()->create());
    Production::factory()->daysAgo(2)->create(['quantity_produced' => 500]);

    $this->getJson(route('api.v1.inventory', ['date' => today()->subDays(2)->toDateString()]))
        ->assertOk()
        ->assertJsonPath('data.produced', 500)
        ->assertJsonPath('data.distributed', 0);
});

test('riders cannot view inventory', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Rider]));

    $this->getJson(route('api.v1.inventory'))->assertForbidden();
});
