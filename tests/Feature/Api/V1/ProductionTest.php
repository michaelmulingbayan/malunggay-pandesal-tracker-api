<?php

use App\Models\Production;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

describe('production history', function () {
    test('admin sees production history newest first with who recorded it', function () {
        $admin = User::factory()->admin()->create(['name' => 'Head Admin']);
        Sanctum::actingAs($admin);
        Production::factory()->daysAgo(2)->for($admin, 'recorder')->create(['quantity_produced' => 300]);
        Production::factory()->for($admin, 'recorder')->create(['quantity_produced' => 900]);
        Production::factory()->daysAgo(1)->for($admin, 'recorder')->create(['quantity_produced' => 600]);

        $response = $this->getJson(route('api.v1.productions.index'))->assertOk();

        expect($response->json('data.*.quantity_produced'))->toBe([900, 600, 300])
            ->and($response->json('data.0.production_date'))->toBe(today()->toDateString())
            ->and($response->json('data.0.recorded_by.name'))->toBe('Head Admin')
            ->and($response->json('meta.total'))->toBe(3);
    });

    test('history is paginated', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        Production::factory()->count(5)->create();

        $this->getJson(route('api.v1.productions.index', ['per_page' => 2]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.last_page', 3);
    });

    test('riders cannot view production history', function () {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Rider]));

        $this->getJson(route('api.v1.productions.index'))->assertForbidden();
    });

    test('guests cannot view production history', function () {
        $this->getJson(route('api.v1.productions.index'))->assertUnauthorized();
    });
});

describe('recording production', function () {
    test('admin records today\'s production', function () {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->postJson(route('api.v1.productions.store'), [
            'production_date' => today()->toDateString(),
            'quantity_produced' => 1200,
            'remarks' => 'Morning batch',
        ])
            ->assertCreated()
            ->assertJsonPath('data.quantity_produced', 1200)
            ->assertJsonPath('data.remarks', 'Morning batch')
            ->assertJsonPath('data.recorded_by.id', $admin->id);

        expect(Production::sole())
            ->recorded_by->toBe($admin->id)
            ->production_date->toDateString()->toBe(today()->toDateString());
    });

    test('remarks are optional', function () {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(route('api.v1.productions.store'), [
            'production_date' => today()->toDateString(),
            'quantity_produced' => 500,
        ])->assertCreated()->assertJsonPath('data.remarks', null);
    });

    test('a past date can be recorded', function () {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(route('api.v1.productions.store'), [
            'production_date' => today()->subDay()->toDateString(),
            'quantity_produced' => 500,
        ])->assertCreated();
    });

    test('a future date is rejected', function () {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(route('api.v1.productions.store'), [
            'production_date' => today()->addDay()->toDateString(),
            'quantity_produced' => 500,
        ])->assertUnprocessable()->assertJsonValidationErrors('production_date');
    });

    test('today is based on the application timezone, not UTC', function () {
        config(['app.timezone' => 'Asia/Manila']);
        date_default_timezone_set('Asia/Manila');
        // 7:00 AM in Manila is still the previous day in UTC.
        Date::setTestNow(Date::parse('2026-11-17 07:00:00', 'Asia/Manila'));
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(route('api.v1.productions.store'), [
            'production_date' => '2026-11-17',
            'quantity_produced' => 500,
        ])->assertCreated();
    });

    test('quantity must be a positive whole number', function (mixed $quantity) {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(route('api.v1.productions.store'), [
            'production_date' => today()->toDateString(),
            'quantity_produced' => $quantity,
        ])->assertUnprocessable()->assertJsonValidationErrors('quantity_produced');
    })->with([0, -5, 12.5, 'many', null]);

    test('riders cannot record production', function () {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Rider]));

        $this->postJson(route('api.v1.productions.store'), [
            'production_date' => today()->toDateString(),
            'quantity_produced' => 500,
        ])->assertForbidden();

        expect(Production::count())->toBe(0);
    });
});
