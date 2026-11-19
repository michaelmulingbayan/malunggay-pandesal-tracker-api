<?php

use App\Models\Production;
use App\Models\Rider;
use App\Models\Sale;
use App\Models\StockDistribution;
use App\Models\StockReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    // A Tuesday: this week runs Mon 16 – Sun 22 November.
    Carbon::setTestNow('2026-11-17 15:00:00');
});

describe('pieces sold report', function () {
    test('daily lists the last 7 days oldest first, including days without sales', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        Sale::factory()->create(['sale_date' => '2026-11-17 08:00:00', 'quantity_sold' => 100, 'total_amount' => 300]);
        Sale::factory()->create(['sale_date' => '2026-11-17 18:30:00', 'quantity_sold' => 20, 'total_amount' => 60]);
        Sale::factory()->create(['sale_date' => '2026-11-11 07:00:00', 'quantity_sold' => 50, 'total_amount' => 150]);
        Sale::factory()->create(['sale_date' => '2026-11-10 23:59:00', 'quantity_sold' => 999]); // 8 days ago

        $this->getJson(route('api.v1.reports.pieces-sold'))
            ->assertOk()
            ->assertJsonPath('data.period', 'daily')
            ->assertJsonPath('data.from', '2026-11-11')
            ->assertJsonPath('data.to', '2026-11-17')
            ->assertJsonPath('data.pieces_sold', 170)
            ->assertJsonPath('data.total_amount', '510.00')
            ->assertJsonCount(7, 'data.points')
            ->assertJsonPath('data.points.0', ['start' => '2026-11-11', 'pieces_sold' => 50, 'total_amount' => '150.00'])
            ->assertJsonPath('data.points.1', ['start' => '2026-11-12', 'pieces_sold' => 0, 'total_amount' => '0.00'])
            ->assertJsonPath('data.points.6', ['start' => '2026-11-17', 'pieces_sold' => 120, 'total_amount' => '360.00']);
    });

    test('weekly lists the last 8 Monday to Sunday weeks, ending with this week', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        Sale::factory()->create(['sale_date' => '2026-11-16 08:00:00', 'quantity_sold' => 10, 'total_amount' => 30]);
        Sale::factory()->create(['sale_date' => '2026-11-15 20:00:00', 'quantity_sold' => 5, 'total_amount' => 15]);

        $this->getJson(route('api.v1.reports.pieces-sold', ['period' => 'weekly']))
            ->assertOk()
            ->assertJsonPath('data.period', 'weekly')
            ->assertJsonPath('data.from', '2026-09-28')
            ->assertJsonCount(8, 'data.points')
            ->assertJsonPath('data.points.6', ['start' => '2026-11-09', 'pieces_sold' => 5, 'total_amount' => '15.00'])
            ->assertJsonPath('data.points.7', ['start' => '2026-11-16', 'pieces_sold' => 10, 'total_amount' => '30.00']);
    });

    test('monthly lists the last 6 months, ending with this month', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        Sale::factory()->create(['sale_date' => '2026-11-02 08:00:00', 'quantity_sold' => 30, 'total_amount' => 90]);
        Sale::factory()->create(['sale_date' => '2026-06-01 06:00:00', 'quantity_sold' => 40, 'total_amount' => 120]);
        Sale::factory()->create(['sale_date' => '2026-05-31 20:00:00', 'quantity_sold' => 999]); // before the range

        $this->getJson(route('api.v1.reports.pieces-sold', ['period' => 'monthly']))
            ->assertOk()
            ->assertJsonPath('data.period', 'monthly')
            ->assertJsonPath('data.from', '2026-06-01')
            ->assertJsonPath('data.pieces_sold', 70)
            ->assertJsonCount(6, 'data.points')
            ->assertJsonPath('data.points.0', ['start' => '2026-06-01', 'pieces_sold' => 40, 'total_amount' => '120.00'])
            ->assertJsonPath('data.points.3.pieces_sold', 0)
            ->assertJsonPath('data.points.5', ['start' => '2026-11-01', 'pieces_sold' => 30, 'total_amount' => '90.00']);
    });

    test('the period must be daily, weekly or monthly', function () {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.reports.pieces-sold', ['period' => 'yearly']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period']);
    });

    test('riders cannot view reports', function () {
        Sanctum::actingAs(Rider::factory()->create()->user);

        $this->getJson(route('api.v1.reports.pieces-sold'))->assertForbidden();
    });
});

describe('report periods', function () {
    test('a period covers the day, its Monday to Sunday week or its month', function (string $period, string $from, string $to, int $days) {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.reports.production', ['period' => $period, 'date' => '2026-11-17']))
            ->assertOk()
            ->assertJsonPath('data.period', $period)
            ->assertJsonPath('data.date', '2026-11-17')
            ->assertJsonPath('data.from', $from)
            ->assertJsonPath('data.to', $to)
            ->assertJsonCount($days, 'data.daily');
    })->with([
        'daily' => ['daily', '2026-11-17', '2026-11-17', 1],
        'weekly' => ['weekly', '2026-11-16', '2026-11-22', 7],
        'monthly' => ['monthly', '2026-11-01', '2026-11-30', 30],
    ]);

    test('the period and date are validated', function () {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.reports.sales', ['period' => 'yearly', 'date' => '17/11/2026']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period', 'date']);
    });

    test('riders cannot open any report', function (string $report) {
        Sanctum::actingAs(Rider::factory()->create()->user);

        $this->getJson(route('api.v1.reports.'.$report))->assertForbidden();
    })->with(['production', 'sales', 'inventory', 'distribution', 'riders']);
});

describe('production report', function () {
    test('sums the batches of the week and shows how much went out to riders', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        $monday = Production::factory()->create(['production_date' => '2026-11-16', 'quantity_produced' => 300]);
        Production::factory()->create(['production_date' => '2026-11-17', 'quantity_produced' => 200]);
        Production::factory()->create(['production_date' => '2026-11-17', 'quantity_produced' => 100]);
        Production::factory()->create(['production_date' => '2026-11-15', 'quantity_produced' => 999]); // last week
        StockDistribution::factory()->for($monday)->create(['quantity_distributed' => 250, 'distribution_date' => '2026-11-16']);

        $this->getJson(route('api.v1.reports.production', ['period' => 'weekly']))
            ->assertOk()
            ->assertJsonPath('data.summary', [
                'produced' => 600,
                'batches' => 3,
                'production_days' => 2,
                'average_per_production_day' => 300,
                'distributed' => 250,
                'undistributed' => 350,
            ])
            ->assertJsonPath('data.daily.0', ['date' => '2026-11-16', 'produced' => 300])
            ->assertJsonPath('data.daily.1', ['date' => '2026-11-17', 'produced' => 300])
            ->assertJsonPath('data.daily.2.produced', 0)
            ->assertJsonCount(3, 'data.batches')
            ->assertJsonPath('data.batches.2.id', $monday->id)
            ->assertJsonPath('data.batches.2.distributed', 250);
    });
});

describe('sales report', function () {
    test('totals the month per day and per rider, best rider first', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        $ana = Rider::factory()->for(User::factory()->state(['name' => 'Ana']))->create();
        $ben = Rider::factory()->for(User::factory()->state(['name' => 'Ben']))->create();
        $anaStock = StockDistribution::factory()->for($ana)->create();
        $benStock = StockDistribution::factory()->for($ben)->create();
        Sale::factory()->for($anaStock, 'stockDistribution')->create(['sale_date' => '2026-11-02 09:00:00', 'quantity_sold' => 100, 'total_amount' => 300]);
        Sale::factory()->for($benStock, 'stockDistribution')->create(['sale_date' => '2026-11-17 10:00:00', 'quantity_sold' => 200, 'total_amount' => 700]);
        Sale::factory()->for($benStock, 'stockDistribution')->create(['sale_date' => '2026-10-31 23:00:00', 'quantity_sold' => 999]); // last month

        $this->getJson(route('api.v1.reports.sales', ['period' => 'monthly']))
            ->assertOk()
            ->assertJsonPath('data.summary', [
                'total_amount' => '1000.00',
                'pieces_sold' => 300,
                'sales_count' => 2,
                'riders_count' => 2,
                'average_price' => '3.33',
            ])
            ->assertJsonPath('data.daily.1', ['date' => '2026-11-02', 'pieces_sold' => 100, 'total_amount' => '300.00'])
            ->assertJsonPath('data.daily.16.total_amount', '700.00')
            ->assertJsonPath('data.riders.0.rider_name', 'Ben')
            ->assertJsonPath('data.riders.0.total_amount', '700.00')
            ->assertJsonPath('data.riders.1.rider_name', 'Ana');
    });
});

describe('inventory report', function () {
    test('follows the stock from production to sold, returned and still out', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        $batch = Production::factory()->create(['quantity_produced' => 500]);
        $assignment = StockDistribution::factory()->for($batch)->create(['quantity_distributed' => 400]);
        Sale::factory()->for($assignment, 'stockDistribution')->create(['quantity_sold' => 250]);
        StockReturn::factory()->for($assignment)->verified()->create(['quantity_returned' => 50]);
        StockReturn::factory()->for($assignment)->create(['quantity_returned' => 30]);
        StockReturn::factory()->for($assignment)->rejected()->create(['quantity_returned' => 20]);

        $this->getJson(route('api.v1.reports.inventory'))
            ->assertOk()
            ->assertJsonPath('data.summary', [
                'produced' => 500,
                'distributed' => 400,
                'sold' => 250,
                'returned' => 80,
                'returned_verified' => 50,
                'returned_pending' => 30,
                'returned_rejected' => 20,
                'with_riders' => 70,
                'undistributed' => 100,
                'sell_through_percent' => 63,
            ])
            ->assertJsonPath('data.daily.0', ['date' => '2026-11-17', 'produced' => 500, 'distributed' => 400, 'sold' => 250, 'returned' => 80]);
    });
});

describe('distribution report', function () {
    test('lists stock handed out per rider and per assignment', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        $ana = Rider::factory()->for(User::factory()->state(['name' => 'Ana']))->create();
        $ben = Rider::factory()->for(User::factory()->state(['name' => 'Ben']))->create();
        $anaMonday = StockDistribution::factory()->for($ana)->create(['quantity_distributed' => 100, 'distribution_date' => '2026-11-16']);
        StockDistribution::factory()->for($ana)->create(['quantity_distributed' => 150]);
        StockDistribution::factory()->for($ben)->create(['quantity_distributed' => 80]);
        Sale::factory()->for($anaMonday, 'stockDistribution')->create(['quantity_sold' => 60]);
        StockReturn::factory()->for($anaMonday)->create(['quantity_returned' => 40]);

        $this->getJson(route('api.v1.reports.distribution', ['period' => 'weekly']))
            ->assertOk()
            ->assertJsonPath('data.summary', [
                'distributed' => 330,
                'assignments' => 3,
                'riders_count' => 2,
                'sold' => 60,
                'returned' => 40,
                'with_riders' => 230,
            ])
            ->assertJsonPath('data.daily.0', ['date' => '2026-11-16', 'distributed' => 100])
            ->assertJsonPath('data.daily.1.distributed', 230)
            ->assertJsonPath('data.riders.0', [
                'rider_id' => $ana->id,
                'rider_name' => 'Ana',
                'assignments' => 2,
                'distributed' => 250,
                'sold' => 60,
                'returned' => 40,
                'remaining' => 150,
            ])
            ->assertJsonCount(3, 'data.assignments')
            ->assertJsonPath('data.assignments.2.id', $anaMonday->id)
            ->assertJsonPath('data.assignments.2.remaining', 0);
    });
});

describe('rider performance report', function () {
    test('ranks riders by sales with sell-through and a daily average', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        $ana = Rider::factory()->for(User::factory()->state(['name' => 'Ana']))->create();
        $ben = Rider::factory()->for(User::factory()->state(['name' => 'Ben']))->create();
        $anaMonday = StockDistribution::factory()->for($ana)->create(['quantity_distributed' => 100, 'distribution_date' => '2026-11-16']);
        $anaToday = StockDistribution::factory()->for($ana)->create(['quantity_distributed' => 100]);
        $benToday = StockDistribution::factory()->for($ben)->create(['quantity_distributed' => 200]);
        Sale::factory()->for($anaMonday, 'stockDistribution')->create(['quantity_sold' => 90, 'total_amount' => 270]);
        Sale::factory()->for($anaToday, 'stockDistribution')->create(['quantity_sold' => 70, 'total_amount' => 210]);
        Sale::factory()->for($benToday, 'stockDistribution')->create(['quantity_sold' => 100, 'total_amount' => 300]);

        $this->getJson(route('api.v1.reports.riders', ['period' => 'weekly']))
            ->assertOk()
            ->assertJsonPath('data.summary', [
                'riders_count' => 2,
                'assigned' => 400,
                'sold' => 260,
                'sales_amount' => '780.00',
                'sell_through_percent' => 65,
                'top_rider_name' => 'Ana',
            ])
            ->assertJsonPath('data.riders.0', [
                'rider_id' => $ana->id,
                'rider_name' => 'Ana',
                'days_active' => 2,
                'assigned' => 200,
                'sold' => 160,
                'returned' => 0,
                'remaining' => 40,
                'sales_amount' => '480.00',
                'sell_through_percent' => 80,
                'average_sold_per_day' => 80,
            ])
            ->assertJsonPath('data.riders.1.rider_name', 'Ben')
            ->assertJsonPath('data.riders.1.sell_through_percent', 50);
    });
});
