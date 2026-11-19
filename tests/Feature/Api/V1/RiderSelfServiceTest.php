<?php

use App\Models\PandesalPrice;
use App\Models\Production;
use App\Models\Rider;
use App\Models\Sale;
use App\Models\StockDistribution;
use App\Models\StockReturn;
use App\Models\User;
use App\StockReturnStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->rider = Rider::factory()->create();
    PandesalPrice::factory()->create(['price_per_piece' => 3.00]);
});

function assign(Rider $rider, int $quantity, ?Production $production = null): StockDistribution
{
    return StockDistribution::factory()
        ->for($production ?? Production::factory()->state(['quantity_produced' => 1000]))
        ->for($rider)
        ->create(['quantity_distributed' => $quantity]);
}

describe('my stock', function () {
    test('a rider sees today\'s assigned, sold, returned and remaining with the current price', function () {
        Sanctum::actingAs($this->rider->user);
        $first = assign($this->rider, 300);
        $second = assign($this->rider, 200);
        Sale::factory()->for($first, 'stockDistribution')->create(['quantity_sold' => 100, 'total_amount' => 300]);
        StockReturn::factory()->for($second)->create(['quantity_returned' => 20]);
        StockReturn::factory()->for($second)->rejected()->create(['quantity_returned' => 50]);
        assign(Rider::factory()->create(), 999); // someone else's stock
        StockDistribution::factory()->for($this->rider)->create(['distribution_date' => today()->subDay()]);

        $this->getJson(route('api.v1.me.stock'))
            ->assertOk()
            ->assertJsonPath('data.price_per_piece', '3.00')
            ->assertJsonPath('data.assigned', 500)
            ->assertJsonPath('data.sold', 100)
            ->assertJsonPath('data.returned', 20)
            ->assertJsonPath('data.remaining', 380)
            ->assertJsonPath('data.sales_amount', '300.00')
            ->assertJsonCount(1, 'data.sales')
            ->assertJsonCount(2, 'data.returns');
    });

    test('admins without a rider profile cannot use rider endpoints', function () {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.me.stock'))->assertForbidden();
        $this->postJson(route('api.v1.me.sales.store'), ['quantity_sold' => 1])->assertForbidden();
        $this->postJson(route('api.v1.me.returns.store'), ['quantity_returned' => 1])->assertForbidden();
    });
});

describe('selling', function () {
    test('a rider sells pieces at the current price, oldest assignment first', function () {
        Sanctum::actingAs($this->rider->user);
        $first = assign($this->rider, 100);
        $second = assign($this->rider, 200);

        $this->postJson(route('api.v1.me.sales.store'), ['quantity_sold' => 150])
            ->assertCreated()
            ->assertJsonPath('data.sold', 150)
            ->assertJsonPath('data.remaining', 150)
            ->assertJsonPath('data.sales_amount', '450.00');

        expect(Sale::where('distribution_id', $first->id)->sum('quantity_sold'))->toEqual(100)
            ->and(Sale::where('distribution_id', $second->id)->sum('quantity_sold'))->toEqual(50);
    });

    test('a rider cannot sell more than they hold', function () {
        Sanctum::actingAs($this->rider->user);
        $assignment = assign($this->rider, 100);
        StockReturn::factory()->for($assignment)->create(['quantity_returned' => 30]);

        $this->postJson(route('api.v1.me.sales.store'), ['quantity_sold' => 71])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity_sold' => 'only have 70 pcs']);

        expect(Sale::count())->toBe(0);
    });

    test('selling needs a price to be set', function () {
        PandesalPrice::query()->delete();
        Sanctum::actingAs($this->rider->user);
        assign($this->rider, 100);

        $this->postJson(route('api.v1.me.sales.store'), ['quantity_sold' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity_sold' => 'No selling price']);
    });

    test('quantity must be a positive whole number', function (mixed $quantity) {
        Sanctum::actingAs($this->rider->user);
        assign($this->rider, 100);

        $this->postJson(route('api.v1.me.sales.store'), ['quantity_sold' => $quantity])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity_sold');
    })->with([0, -3, 1.5, 'ten', null]);
});

describe('returning', function () {
    test('a rider returns unsold pieces as pending, oldest assignment first', function () {
        Sanctum::actingAs($this->rider->user);
        $first = assign($this->rider, 50);
        $second = assign($this->rider, 50);
        Sale::factory()->for($first, 'stockDistribution')->create(['quantity_sold' => 40]);

        $this->postJson(route('api.v1.me.returns.store'), ['quantity_returned' => 30])
            ->assertCreated()
            ->assertJsonPath('data.returned', 30)
            ->assertJsonPath('data.remaining', 30)
            ->assertJsonPath('data.returns.0.status', 'pending');

        expect(StockReturn::where('distribution_id', $first->id)->sum('quantity_returned'))->toEqual(10)
            ->and(StockReturn::where('distribution_id', $second->id)->sum('quantity_returned'))->toEqual(20)
            ->and(StockReturn::where('status', '!=', StockReturnStatus::Pending)->count())->toBe(0);
    });

    test('a rider cannot return more than they hold', function () {
        Sanctum::actingAs($this->rider->user);
        assign($this->rider, 20);

        $this->postJson(route('api.v1.me.returns.store'), ['quantity_returned' => 21])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity_returned' => 'only have 20 pcs']);
    });

    test('without a quantity a rider returns everything they still hold', function () {
        Sanctum::actingAs($this->rider->user);
        $first = assign($this->rider, 50);
        $second = assign($this->rider, 30);
        Sale::factory()->for($first, 'stockDistribution')->create(['quantity_sold' => 40]);

        $this->postJson(route('api.v1.me.returns.store'))
            ->assertCreated()
            ->assertJsonPath('data.returned', 40)
            ->assertJsonPath('data.remaining', 0);

        expect(StockReturn::where('distribution_id', $first->id)->sum('quantity_returned'))->toEqual(10)
            ->and(StockReturn::where('distribution_id', $second->id)->sum('quantity_returned'))->toEqual(30);
    });

    test('returning everything with nothing left is rejected', function () {
        Sanctum::actingAs($this->rider->user);
        $stock = assign($this->rider, 20);
        Sale::factory()->for($stock, 'stockDistribution')->create(['quantity_sold' => 20]);

        $this->postJson(route('api.v1.me.returns.store'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity_returned' => 'no stock left to return']);

        expect(StockReturn::count())->toBe(0);
    });
});

describe('my sales history', function () {
    test('a rider sees only their own sales from every day, newest first', function () {
        Sanctum::actingAs($this->rider->user);
        $todaysStock = assign($this->rider, 300);
        $yesterdaysStock = StockDistribution::factory()->for($this->rider)->create(['distribution_date' => today()->subDay()]);
        $yesterday = Sale::factory()->for($yesterdaysStock, 'stockDistribution')->create(['sale_date' => now()->subDay()]);
        $today = Sale::factory()->for($todaysStock, 'stockDistribution')->create(['quantity_sold' => 25, 'total_amount' => 75]);
        $someoneElses = Sale::factory()->create();

        $this->getJson(route('api.v1.me.sales.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $today->id)
            ->assertJsonPath('data.0.quantity_sold', 25)
            ->assertJsonPath('data.0.total_amount', '75.00')
            ->assertJsonPath('data.1.id', $yesterday->id)
            ->assertJsonMissing(['id' => $someoneElses->id]);
    });

    test('a rider can look at a single day', function () {
        Sanctum::actingAs($this->rider->user);
        $stock = assign($this->rider, 300);
        Sale::factory()->for($stock, 'stockDistribution')->create();
        $yesterday = Sale::factory()->for($stock, 'stockDistribution')->create(['sale_date' => now()->subDay()]);

        $this->getJson(route('api.v1.me.sales.index', ['date' => today()->subDay()->toDateString()]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $yesterday->id);

        $this->getJson(route('api.v1.me.sales.index', ['date' => 'yesterday']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);
    });

    test('admins without a rider profile have no sales history', function () {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.me.sales.index'))->assertForbidden();
    });
});
