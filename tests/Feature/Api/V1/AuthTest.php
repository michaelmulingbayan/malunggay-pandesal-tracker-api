<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('active user can log in and receives a token', function () {
    $user = User::factory()->create(['email' => 'rider@example.com']);

    $response = $this->postJson(route('api.v1.auth.login'), [
        'email' => 'rider@example.com',
        'password' => 'password',
        'device_name' => 'Pixel 8',
    ]);

    $response->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.role', 'rider')
        ->assertJsonMissingPath('user.password');

    expect($response->json('token'))->toBeString()->not->toBeEmpty();
    expect($user->tokens()->sole())
        ->name->toBe('Pixel 8')
        ->abilities->toBe(['rider']);
});

test('issued token authenticates subsequent requests', function () {
    User::factory()->create(['email' => 'rider@example.com']);

    $token = $this->postJson(route('api.v1.auth.login'), [
        'email' => 'rider@example.com',
        'password' => 'password',
        'device_name' => 'Pixel 8',
    ])->json('token');

    $this->withToken($token)
        ->getJson(route('api.v1.auth.me'))
        ->assertOk()
        ->assertJsonPath('data.email', 'rider@example.com');
});

test('login fails with wrong password', function () {
    User::factory()->create(['email' => 'rider@example.com']);

    $this->postJson(route('api.v1.auth.login'), [
        'email' => 'rider@example.com',
        'password' => 'wrong-password',
        'device_name' => 'Pixel 8',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    expect(PersonalAccessToken::count())->toBe(0);
});

test('login fails for unknown email with the same error', function () {
    $this->postJson(route('api.v1.auth.login'), [
        'email' => 'nobody@example.com',
        'password' => 'password',
        'device_name' => 'Pixel 8',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('inactive user cannot log in', function () {
    User::factory()->inactive()->create(['email' => 'rider@example.com']);

    $this->postJson(route('api.v1.auth.login'), [
        'email' => 'rider@example.com',
        'password' => 'password',
        'device_name' => 'Pixel 8',
    ])->assertForbidden();

    expect(PersonalAccessToken::count())->toBe(0);
});

test('login requires email, password and device name', function () {
    $this->postJson(route('api.v1.auth.login'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password', 'device_name']);
});

test('login is rate limited after five attempts', function () {
    User::factory()->create(['email' => 'rider@example.com']);

    foreach (range(1, 5) as $attempt) {
        $this->postJson(route('api.v1.auth.login'), [
            'email' => 'rider@example.com',
            'password' => 'wrong-password',
            'device_name' => 'Pixel 8',
        ])->assertUnprocessable();
    }

    $this->postJson(route('api.v1.auth.login'), [
        'email' => 'rider@example.com',
        'password' => 'password',
        'device_name' => 'Pixel 8',
    ])->assertTooManyRequests();
});

test('logout revokes only the current token', function () {
    $user = User::factory()->create();
    $currentToken = $user->createToken('Pixel 8')->plainTextToken;
    $user->createToken('Tablet');

    $this->withToken($currentToken)
        ->postJson(route('api.v1.auth.logout'))
        ->assertNoContent();

    expect($user->tokens()->pluck('name')->all())->toBe(['Tablet']);
});

test('me endpoint returns the authenticated user', function () {
    $user = User::factory()->admin()->create();
    Sanctum::actingAs($user);

    $this->getJson(route('api.v1.auth.me'))
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.role', 'admin');
});

test('guests cannot access protected endpoints', function () {
    $this->getJson(route('api.v1.auth.me'))->assertUnauthorized();
    $this->postJson(route('api.v1.auth.logout'))->assertUnauthorized();
});
