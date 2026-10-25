<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates an operator who can log in', function () {
    $this->artisan('user:create', ['name' => 'Mostrador', 'email' => 'Mostrador@Papeleria.test'])
        ->expectsQuestion('Password (min. 8 characters)', 'secreto123')
        ->expectsQuestion('Repeat the password', 'secreto123')
        ->assertSuccessful();

    $user = User::sole();
    expect($user)
        ->name->toBe('Mostrador')
        ->email->toBe('mostrador@papeleria.test')
        ->and(Hash::check('secreto123', $user->password))->toBeTrue();
});

it('changes the password of an existing user after confirmation', function () {
    User::factory()->create(['email' => 'mostrador@papeleria.test', 'password' => 'vieja1234']);

    $this->artisan('user:create', ['name' => 'Mostrador', 'email' => 'mostrador@papeleria.test'])
        ->expectsConfirmation('User mostrador@papeleria.test already exists. Change its password?', 'yes')
        ->expectsQuestion('Password (min. 8 characters)', 'nueva1234')
        ->expectsQuestion('Repeat the password', 'nueva1234')
        ->assertSuccessful();

    expect(User::count())->toBe(1)
        ->and(Hash::check('nueva1234', User::sole()->password))->toBeTrue();
});

it('rejects a short password', function () {
    $this->artisan('user:create', ['name' => 'Mostrador', 'email' => 'mostrador@papeleria.test'])
        ->expectsQuestion('Password (min. 8 characters)', 'corta')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

it('rejects passwords that do not match', function () {
    $this->artisan('user:create', ['name' => 'Mostrador', 'email' => 'mostrador@papeleria.test'])
        ->expectsQuestion('Password (min. 8 characters)', 'secreto123')
        ->expectsQuestion('Repeat the password', 'secreto124')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

it('rejects an invalid email', function () {
    $this->artisan('user:create', ['name' => 'Mostrador', 'email' => 'mostrador'])->assertFailed();
});
