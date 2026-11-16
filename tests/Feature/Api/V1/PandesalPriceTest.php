<?php

use App\Models\PandesalPrice;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('admin sets the price and it becomes current', function () {
    $admin = User::factory()->admin()->create(['name' => 'Head Admin']);
    Sanctum::actingAs($admin);
    PandesalPrice::factory()->create(['price_per_piece' => 3.00]);

    $this->postJson(route('api.v1.prices.store'), ['price_per_piece' => 3.5])
        ->assertCreated()
        ->assertJsonPath('data.price_per_piece', '3.50')
        ->assertJsonPath('data.set_by.name', 'Head Admin');

    expect(PandesalPrice::current())
        ->price_per_piece->toBe('3.50')
        ->set_by->toBe($admin->id);
});

test('price history lists the newest first, so the first item is the current price', function () {
    Sanctum::actingAs(User::factory()->admin()->create());
    PandesalPrice::factory()->create(['price_per_piece' => 2.50]);
    PandesalPrice::factory()->create(['price_per_piece' => 3.00]);

    $this->getJson(route('api.v1.prices.index'))
        ->assertOk()
        ->assertJsonPath('data.0.price_per_piece', '3.00')
        ->assertJsonPath('data.1.price_per_piece', '2.50');
});

test('riders can see the price but cannot change it', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Rider]));
    PandesalPrice::factory()->create(['price_per_piece' => 3.00]);

    $this->getJson(route('api.v1.prices.index'))->assertOk()->assertJsonPath('data.0.price_per_piece', '3.00');
    $this->postJson(route('api.v1.prices.store'), ['price_per_piece' => 1])->assertForbidden();

    expect(PandesalPrice::count())->toBe(1);
});

test('the price must be a positive amount with at most two decimals', function (mixed $price) {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson(route('api.v1.prices.store'), ['price_per_piece' => $price])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('price_per_piece');
})->with([0, -2, 3.555, 'free', null]);
