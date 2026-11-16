<?php

use App\Models\Rider;
use App\Models\StockDistribution;
use App\Models\StockReturn;
use App\Models\User;
use App\StockReturnStatus;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

describe('submitting a return', function () {
    test('a rider returns unsold stock from their own assignment', function () {
        $rider = Rider::factory()->create();
        $distribution = StockDistribution::factory()->for($rider)->create(['quantity_distributed' => 300]);
        Sanctum::actingAs($rider->user);

        $this->postJson(route('api.v1.stock-returns.store'), [
            'distribution_id' => $distribution->id,
            'quantity_returned' => 40,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.quantity_returned', 40)
            ->assertJsonPath('data.rider.id', $rider->id)
            ->assertJsonPath('data.verified_by', null);
    });

    test('a rider cannot return stock assigned to someone else', function () {
        $distribution = StockDistribution::factory()->create();
        Sanctum::actingAs(Rider::factory()->create()->user);

        $this->postJson(route('api.v1.stock-returns.store'), [
            'distribution_id' => $distribution->id,
            'quantity_returned' => 10,
        ])->assertForbidden();
    });

    test('an admin can record a return for any rider', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        $distribution = StockDistribution::factory()->create(['quantity_distributed' => 100]);

        $this->postJson(route('api.v1.stock-returns.store'), [
            'distribution_id' => $distribution->id,
            'quantity_returned' => 10,
        ])->assertCreated();
    });

    test('cannot return more than was assigned, counting earlier returns but not rejected ones', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        $distribution = StockDistribution::factory()->create(['quantity_distributed' => 100]);
        StockReturn::factory()->for($distribution)->create(['quantity_returned' => 60]);
        StockReturn::factory()->for($distribution)->rejected()->create(['quantity_returned' => 50]);

        $this->postJson(route('api.v1.stock-returns.store'), [
            'distribution_id' => $distribution->id,
            'quantity_returned' => 41,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity_returned' => 'Only 40 pcs']);

        $this->postJson(route('api.v1.stock-returns.store'), [
            'distribution_id' => $distribution->id,
            'quantity_returned' => 40,
        ])->assertCreated();
    });

    test('distribution and a positive quantity are required', function () {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(route('api.v1.stock-returns.store'), ['distribution_id' => 999, 'quantity_returned' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['distribution_id', 'quantity_returned']);
    });
});

describe('listing returns', function () {
    test('admin sees pending returns first, then the newest', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        $oldVerified = StockReturn::factory()->verified()->create(['return_date' => now()->subDays(2)]);
        $pending = StockReturn::factory()->create(['return_date' => now()->subDay()]);
        $newVerified = StockReturn::factory()->verified()->create(['return_date' => now()]);

        $this->getJson(route('api.v1.stock-returns.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $pending->id)
            ->assertJsonPath('data.1.id', $newVerified->id)
            ->assertJsonPath('data.2.id', $oldVerified->id)
            ->assertJsonPath('data.1.verified_by.id', $newVerified->verified_by);
    });

    test('returns can be filtered by status', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        StockReturn::factory()->count(2)->create();
        StockReturn::factory()->verified()->create();

        $this->getJson(route('api.v1.stock-returns.index', ['status' => 'pending']))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    test('riders cannot list all returns', function () {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Rider]));

        $this->getJson(route('api.v1.stock-returns.index'))->assertForbidden();
    });
});

describe('verifying a return', function () {
    test('admin verifies a pending return', function () {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $stockReturn = StockReturn::factory()->create();

        $this->postJson(route('api.v1.stock-returns.verification.store', $stockReturn))
            ->assertOk()
            ->assertJsonPath('data.status', 'verified')
            ->assertJsonPath('data.verified_by.id', $admin->id);

        expect($stockReturn->fresh())
            ->status->toBe(StockReturnStatus::Verified)
            ->verified_by->toBe($admin->id);
    });

    test('an already verified return cannot be verified again', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        $stockReturn = StockReturn::factory()->verified()->create();
        $originalVerifier = $stockReturn->verified_by;

        $this->postJson(route('api.v1.stock-returns.verification.store', $stockReturn))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        expect($stockReturn->fresh()->verified_by)->toBe($originalVerifier);
    });

    test('riders cannot verify returns', function () {
        $stockReturn = StockReturn::factory()->create();
        Sanctum::actingAs($stockReturn->stockDistribution->rider->user);

        $this->postJson(route('api.v1.stock-returns.verification.store', $stockReturn))->assertForbidden();

        expect($stockReturn->fresh()->status)->toBe(StockReturnStatus::Pending);
    });
});
