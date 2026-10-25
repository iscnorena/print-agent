<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'mostrador@papeleria.test', 'password' => 'secreto123']);
});

it('logs in with valid credentials and goes to the print page', function () {
    $this->post('/login', ['email' => 'mostrador@papeleria.test', 'password' => 'secreto123'])
        ->assertRedirect(route('operator.print-jobs.index'));

    $this->assertAuthenticatedAs($this->user);
});

it('rejects a wrong password', function () {
    $this->post('/login', ['email' => 'mostrador@papeleria.test', 'password' => 'otra-cosa'])
        ->assertSessionHasErrors(['email' => 'Correo o contraseña incorrectos.']);

    $this->assertGuest();
});

it('blocks login after five attempts in a minute', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['email' => 'mostrador@papeleria.test', 'password' => 'otra-cosa']);
    }

    $this->post('/login', ['email' => 'mostrador@papeleria.test', 'password' => 'secreto123'])
        ->assertTooManyRequests();

    $this->assertGuest();
});

it('logs out', function () {
    $this->actingAs($this->user)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('sends logged-in users away from the login page', function () {
    $this->actingAs($this->user)
        ->get(route('login'))
        ->assertRedirect();
});
