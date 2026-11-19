<?php

use App\Models\PandesalPrice;
use App\Models\Rider;
use App\Models\Sale;
use App\Models\StockDistribution;
use App\Models\StockReturn;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

describe('recording a sale', function () {
    test('a rider records a sale at the current set price and the server computes the total', function () {
        PandesalPrice::factory()->create(['price_per_piece' => 3.00]);
        PandesalPrice::factory()->create(['price_per_piece' => 3.50]);
        $rider = Rider::factory()->create();
        $distribution = StockDistribution::factory()->for($rider)->create(['quantity_distributed' => 300]);
        Sanctum::actingAs($rider->user);

        $this->postJson(route('api.v1.sales.store'), [
            'distribution_id' => $distribution->id,
            'quantity_sold' => 120,
            'total_amount' => 1, // ignored: the client cannot set the total
        ])
            ->assertCreated()
            ->assertJsonPath('data.quantity_sold', 120)
            ->assertJsonPath('data.price_per_piece', '3.50')
            ->assertJsonPath('data.total_amount', '420.00')
            ->assertJsonPath('data.rider.id', $rider->id);
    });

    test('a rider cannot choose their own price', function () {
        PandesalPrice::factory()->create(['price_per_piece' => 3.00]);
        $rider = Rider::factory()->create();
        $distribution = StockDistribution::factory()->for($rider)->create();
        Sanctum::actingAs($rider->user);

        $this->postJson(route('api.v1.sales.store'), [
            'distribution_id' => $distribution->id,
            'quantity_sold' => 10,
            'price_per_piece' => 1.00,
        ])->assertUnprocessable()->assertJsonValidationErrors('price_per_piece');

        expect(Sale::count())->toBe(0);
    });

    test('an admin can override the price for a single sale', function () {
        PandesalPrice::factory()->create(['price_per_piece' => 3.00]);
        Sanctum::actingAs(User::factory()->admin()->create());
        $distribution = StockDistribution::factory()->create(['quantity_distributed' => 100]);

        $this->postJson(route('api.v1.sales.store'), [
            'distribution_id' => $distribution->id,
            'quantity_sold' => 10,
            'price_per_piece' => 2.50,
        ])->assertCreated()->assertJsonPath('data.total_amount', '25.00');
    });

    test('a sale cannot be recorded before any price is set', function () {
        $rider = Rider::factory()->create();
        $distribution = StockDistribution::factory()->for($rider)->create();
        Sanctum::actingAs($rider->user);

        $this->postJson(route('api.v1.sales.store'), [
            'distribution_id' => $distribution->id,
            'quantity_sold' => 10,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price_per_piece' => 'No selling price has been set']);
    });

    test('changing the price does not change earlier sales', function () {
        PandesalPrice::factory()->create(['price_per_piece' => 3.00]);
        $rider = Rider::factory()->create();
        $distribution = StockDistribution::factory()->for($rider)->create(['quantity_distributed' => 300]);
        Sanctum::actingAs($rider->user);

        $this->postJson(route('api.v1.sales.store'), ['distribution_id' => $distribution->id, 'quantity_sold' => 100])
            ->assertCreated();
        PandesalPrice::factory()->create(['price_per_piece' => 4.00]);
        $this->postJson(route('api.v1.sales.store'), ['distribution_id' => $distribution->id, 'quantity_sold' => 100])
            ->assertCreated();

        expect(Sale::orderBy('id')->pluck('total_amount')->all())->toBe(['300.00', '400.00']);
    });

    test('a rider cannot sell from someone else\'s stock', function () {
        $distribution = StockDistribution::factory()->create();
        Sanctum::actingAs(Rider::factory()->create()->user);

        $this->postJson(route('api.v1.sales.store'), [
            'distribution_id' => $distribution->id,
            'quantity_sold' => 10,
        ])->assertForbidden();
    });

    test('cannot sell more than the rider still holds after returns and earlier sales', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        $distribution = StockDistribution::factory()->create(['quantity_distributed' => 100]);
        StockReturn::factory()->for($distribution)->create(['quantity_returned' => 20]);
        StockReturn::factory()->for($distribution)->rejected()->create(['quantity_returned' => 50]);
        Sale::factory()->for($distribution, 'stockDistribution')->create(['quantity_sold' => 30]);

        $this->postJson(route('api.v1.sales.store'), [
            'distribution_id' => $distribution->id,
            'quantity_sold' => 51,
            'price_per_piece' => 3,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity_sold' => 'Only 50 pcs']);
    });

    test('an admin override price must be a positive amount with at most two decimals', function (mixed $price) {
        Sanctum::actingAs(User::factory()->admin()->create());
        $distribution = StockDistribution::factory()->create();

        $this->postJson(route('api.v1.sales.store'), [
            'distribution_id' => $distribution->id,
            'quantity_sold' => 1,
            'price_per_piece' => $price,
        ])->assertUnprocessable()->assertJsonValidationErrors('price_per_piece');
    })->with([0, -1, 3.555, 'cheap']);
});

describe('sales summary', function () {
    test('admin sees today\'s totals and a per-rider breakdown, highest sales first', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        $ana = Rider::factory()->for(User::factory()->state(['name' => 'Ana']))->create();
        $ben = Rider::factory()->for(User::factory()->state(['name' => 'Ben']))->create();
        $anaStock = StockDistribution::factory()->for($ana)->create();
        $benStock = StockDistribution::factory()->for($ben)->create();

        Sale::factory()->for($anaStock, 'stockDistribution')->create(['quantity_sold' => 100, 'price_per_piece' => 3, 'total_amount' => 300]);
        Sale::factory()->for($anaStock, 'stockDistribution')->create(['quantity_sold' => 50, 'price_per_piece' => 3, 'total_amount' => 150]);
        Sale::factory()->for($benStock, 'stockDistribution')->create(['quantity_sold' => 200, 'price_per_piece' => 3.5, 'total_amount' => 700]);
        Sale::factory()->for($benStock, 'stockDistribution')->create(['sale_date' => now()->subDay(), 'total_amount' => 999]);

        $this->getJson(route('api.v1.sales.summary'))
            ->assertOk()
            ->assertJsonPath('data.date', today()->toDateString())
            ->assertJsonPath('data.total_amount', '1150.00')
            ->assertJsonPath('data.pieces_sold', 350)
            ->assertJsonPath('data.riders_count', 2)
            ->assertJsonPath('data.riders.0.rider_name', 'Ben')
            ->assertJsonPath('data.riders.0.total_amount', '700.00')
            ->assertJsonPath('data.riders.0.pieces_sold', 200)
            ->assertJsonPath('data.riders.1.rider_name', 'Ana')
            ->assertJsonPath('data.riders.1.total_amount', '450.00')
            ->assertJsonPath('data.riders.1.sales_count', 2);
    });

    test('a day without sales returns zero totals', function () {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.sales.summary'))
            ->assertOk()
            ->assertJsonPath('data.total_amount', '0.00')
            ->assertJsonPath('data.pieces_sold', 0)
            ->assertJsonPath('data.riders_count', 0)
            ->assertJsonPath('data.riders', []);
    });

    test('riders cannot view the sales summary', function () {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Rider]));

        $this->getJson(route('api.v1.sales.summary'))->assertForbidden();
    });
});

describe('listing sales', function () {
    test('admin sees every sale with its rider, newest first', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        $ana = Rider::factory()->for(User::factory()->state(['name' => 'Ana']))->create();
        $ben = Rider::factory()->for(User::factory()->state(['name' => 'Ben']))->create();
        $older = Sale::factory()->for(StockDistribution::factory()->for($ana))->create(['sale_date' => now()->subHour()]);
        $newer = Sale::factory()->for(StockDistribution::factory()->for($ben))->create(['quantity_sold' => 40, 'total_amount' => 120]);

        $this->getJson(route('api.v1.sales.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.0.rider.name', 'Ben')
            ->assertJsonPath('data.0.quantity_sold', 40)
            ->assertJsonPath('data.0.total_amount', '120.00')
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('data.1.rider.name', 'Ana')
            ->assertJsonPath('meta.total', 2);
    });

    test('sales can be filtered by day and by rider', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        $ana = Rider::factory()->create();
        $anaStock = StockDistribution::factory()->for($ana)->create();
        $todaysSale = Sale::factory()->for($anaStock, 'stockDistribution')->create();
        $yesterdaysSale = Sale::factory()->for($anaStock, 'stockDistribution')->create(['sale_date' => now()->subDay()]);
        $someoneElsesSale = Sale::factory()->create();

        $this->getJson(route('api.v1.sales.index', ['date' => today()->subDay()->toDateString()]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $yesterdaysSale->id);

        $this->getJson(route('api.v1.sales.index', ['rider_id' => $ana->id]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing(['id' => $someoneElsesSale->id]);

        $this->getJson(route('api.v1.sales.index', ['rider_id' => $ana->id, 'date' => today()->toDateString()]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $todaysSale->id);
    });

    test('filters must be a valid day and an existing rider', function () {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.sales.index', ['date' => '17-11-2026', 'rider_id' => 999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date', 'rider_id']);
    });

    test('riders cannot list everyone\'s sales', function () {
        Sanctum::actingAs(Rider::factory()->create()->user);

        $this->getJson(route('api.v1.sales.index'))->assertForbidden();
    });
});
