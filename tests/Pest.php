<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Pest Configuration
|--------------------------------------------------------------------------
|
| This file configures Pest PHP for the test suite. Pest can run all
| PHPUnit tests natively while providing a cleaner syntax for new tests.
|
*/

uses(
    TestCase::class,
    RefreshDatabase::class,
)->in('Feature');

uses(
    TestCase::class,
)->in('Unit');

/*
|--------------------------------------------------------------------------
| Global Helpers
|--------------------------------------------------------------------------
*/

/**
 * Create an authenticated Sanctum user for API tests.
 */
function actingAsApiUser(?User $user = null): User
{
    $user  = $user ?? User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    test()->withHeader('Authorization', "Bearer {$token}");

    return $user;
}
