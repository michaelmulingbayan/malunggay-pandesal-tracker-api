<?php

use App\Models\Production;
use App\Models\Rider;
use App\Models\StockDistribution;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

describe('assigning stock', function () {
    test('admin assigns stock to a rider from today\'s production', function () {
        Sanctum::actingAs($this->admin);
        $production = Production::factory()->create(['quantity_produced' => 1000]);
        $rider = Rider::factory()->create();

        $this->postJson(route('api.v1.stock-distributions.store'), ['rider_id' => $rider->id, 'quantity' => 300])
            ->assertCreated()
            ->assertJsonPath('data.0.rider.id', $rider->id)
            ->assertJsonPath('data.0.quantity_distributed', 300)
            ->assertJsonPath('data.0.distributed_by.id', $this->admin->id)
            ->assertJsonPath('meta.produced', 1000)
            ->assertJsonPath('meta.distributed', 300)
            ->assertJsonPath('meta.available', 700);

        expect(StockDistribution::sole())
            ->production_id->toBe($production->id)
            ->rider_id->toBe($rider->id)
            ->distribution_date->toDateString()->toBe(today()->toDateString());
    });

    test('a large assignment is split across today\'s batches in order', function () {
        Sanctum::actingAs($this->admin);
        $morning = Production::factory()->create(['quantity_produced' => 400]);
        $afternoon = Production::factory()->create(['quantity_produced' => 600]);
        $rider = Rider::factory()->create();

        $this->postJson(route('api.v1.stock-distributions.store'), ['rider_id' => $rider->id, 'quantity' => 500])
            ->assertCreated()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.available', 500);

        expect(StockDistribution::where('production_id', $morning->id)->sum('quantity_distributed'))->toEqual(400)
            ->and(StockDistribution::where('production_id', $afternoon->id)->sum('quantity_distributed'))->toEqual(100);
    });

    test('cannot assign more than what is available', function () {
        Sanctum::actingAs($this->admin);
        $production = Production::factory()->create(['quantity_produced' => 500]);
        StockDistribution::factory()->for($production)->create(['quantity_distributed' => 400]);
        $rider = Rider::factory()->create();

        $this->postJson(route('api.v1.stock-distributions.store'), ['rider_id' => $rider->id, 'quantity' => 101])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity' => 'Only 100 pcs are available']);

        expect(StockDistribution::count())->toBe(1);
    });

    test('yesterday\'s production is not available today', function () {
        Sanctum::actingAs($this->admin);
        Production::factory()->daysAgo(1)->create(['quantity_produced' => 500]);
        $rider = Rider::factory()->create();

        $this->postJson(route('api.v1.stock-distributions.store'), ['rider_id' => $rider->id, 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity' => 'Record production first']);
    });

    test('inactive riders cannot be assigned stock', function () {
        Sanctum::actingAs($this->admin);
        Production::factory()->create(['quantity_produced' => 500]);
        $rider = Rider::factory()->inactive()->create();

        $this->postJson(route('api.v1.stock-distributions.store'), ['rider_id' => $rider->id, 'quantity' => 10])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rider_id' => 'inactive']);
    });

    test('rider and a positive quantity are required', function () {
        Sanctum::actingAs($this->admin);

        $this->postJson(route('api.v1.stock-distributions.store'), ['rider_id' => 999, 'quantity' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rider_id', 'quantity']);
    });

    test('riders cannot assign stock', function () {
        $rider = Rider::factory()->create();
        Sanctum::actingAs($rider->user);
        Production::factory()->create(['quantity_produced' => 500]);

        $this->postJson(route('api.v1.stock-distributions.store'), ['rider_id' => $rider->id, 'quantity' => 10])
            ->assertForbidden();
    });
});

describe('listing today\'s assignments', function () {
    test('admin sees today\'s assignments, newest first, with the stock summary', function () {
        Sanctum::actingAs($this->admin);
        $production = Production::factory()->create(['quantity_produced' => 1000]);
        $ana = Rider::factory()->for(User::factory()->state(['name' => 'Ana']))->create();
        $ben = Rider::factory()->for(User::factory()->state(['name' => 'Ben']))->create();
        StockDistribution::factory()->for($production)->for($ana)->create(['quantity_distributed' => 200]);
        StockDistribution::factory()->for($production)->for($ben)->create(['quantity_distributed' => 300]);
        StockDistribution::factory()->for(Production::factory()->daysAgo(1))->create(['distribution_date' => today()->subDay()]);

        $this->getJson(route('api.v1.stock-distributions.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.rider.name', 'Ben')
            ->assertJsonPath('data.1.rider.name', 'Ana')
            ->assertJsonPath('meta.date', today()->toDateString())
            ->assertJsonPath('meta.produced', 1000)
            ->assertJsonPath('meta.distributed', 500)
            ->assertJsonPath('meta.available', 500);
    });

    test('a specific date can be requested', function () {
        Sanctum::actingAs($this->admin);
        StockDistribution::factory()
            ->for(Production::factory()->daysAgo(1)->state(['quantity_produced' => 800]))
            ->create(['distribution_date' => today()->subDay(), 'quantity_distributed' => 150]);

        $this->getJson(route('api.v1.stock-distributions.index', ['date' => today()->subDay()->toDateString()]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.available', 650);
    });

    test('riders cannot list assignments', function () {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Rider]));

        $this->getJson(route('api.v1.stock-distributions.index'))->assertForbidden();
    });
});
