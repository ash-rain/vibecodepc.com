<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('factory creates a valid user with default attributes', function () {
    $user = User::factory()->create();

    expect($user->name)->not->toBeEmpty()
        ->and($user->email)->not->toBeEmpty()
        ->and($user->password)->not->toBeEmpty()
        ->and($user->email_verified_at)->toBeInstanceOf(DateTime::class)
        ->and($user->remember_token)->not->toBeEmpty();
});

it('factory unverified state creates user with unverified email', function () {
    $user = User::factory()->unverified()->create();

    expect($user->email_verified_at)->toBeNull()
        ->and($user->name)->not->toBeEmpty()
        ->and($user->email)->not->toBeEmpty();
});

it('factory creates user with custom attributes', function () {
    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    expect($user->name)->toBe('John Doe')
        ->and($user->email)->toBe('john@example.com');
});

it('password is hashed when created via factory', function () {
    $user = User::factory()->create([
        'password' => 'plaintext_password',
    ]);

    $freshUser = User::find($user->id);

    expect(Hash::check('plaintext_password', $freshUser->password))->toBeTrue()
        ->and($freshUser->password)->not->toBe('plaintext_password');
});

it('password is hidden from serialization', function () {
    $user = User::factory()->create();

    $array = $user->toArray();

    expect(isset($array['password']))->toBeFalse()
        ->and(isset($array['remember_token']))->toBeFalse();
});

it('email_verified_at is cast to datetime', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    expect($user->email_verified_at)->toBeInstanceOf(DateTime::class);
});

it('can authenticate with correct password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('correct_password'),
    ]);

    expect(Hash::check('correct_password', $user->password))->toBeTrue()
        ->and(Hash::check('wrong_password', $user->password))->toBeFalse();
});

it('factory creates multiple users with unique emails', function () {
    $users = User::factory()->count(5)->create();

    $emails = $users->pluck('email')->toArray();

    expect($users)->toHaveCount(5)
        ->and(count(array_unique($emails)))->toBe(5);
});

it('user can be retrieved by email', function () {
    User::factory()->create(['email' => 'find@example.com']);

    $found = User::where('email', 'find@example.com')->first();

    expect($found)->not->toBeNull()
        ->and($found->email)->toBe('find@example.com');
});

it('factory default password is consistent', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    expect($user1->password)->toBe($user2->password);
});

it('user attributes can be updated', function () {
    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
    ]);

    $user->update([
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
    ]);

    $freshUser = $user->fresh();

    expect($freshUser->name)->toBe('Updated Name')
        ->and($freshUser->email)->toBe('updated@example.com');
});

it('user can be deleted', function () {
    $user = User::factory()->create();
    $id = $user->id;

    $user->delete();

    expect(User::find($id))->toBeNull();
});

it('factory creates user with remember_token', function () {
    $user = User::factory()->create();

    expect($user->remember_token)->not->toBeEmpty()
        ->and(strlen($user->remember_token))->toBe(10);
});

it('email must be unique', function () {
    User::factory()->create(['email' => 'duplicate@example.com']);

    expect(fn () => User::factory()->create(['email' => 'duplicate@example.com']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('name is required', function () {
    expect(fn () => User::factory()->create([
        'name' => null,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('user can be queried by name', function () {
    User::factory()->create(['name' => 'Test User']);

    $found = User::where('name', 'Test User')->first();

    expect($found)->not->toBeNull()
        ->and($found->name)->toBe('Test User');
});

it('user created_at and updated_at are timestamps', function () {
    $user = User::factory()->create();

    expect($user->created_at)->toBeInstanceOf(DateTime::class)
        ->and($user->updated_at)->toBeInstanceOf(DateTime::class);
});

it('user can be updated with new password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('old_password'),
    ]);

    $user->update([
        'password' => Hash::make('new_password'),
    ]);

    $freshUser = $user->fresh();

    expect(Hash::check('new_password', $freshUser->password))->toBeTrue()
        ->and(Hash::check('old_password', $freshUser->password))->toBeFalse();
});
