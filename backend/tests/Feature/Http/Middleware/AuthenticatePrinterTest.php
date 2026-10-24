<?php

use App\Models\Printer;

it('returns 401 when no token is provided', function () {
    $this->postJson(route('agent.heartbeat'))->assertUnauthorized();
});

it('returns 401 when the token is unknown', function () {
    Printer::factory()->withToken('pa_right')->create();

    $this->withToken('pa_wrong')->postJson(route('agent.heartbeat'))->assertUnauthorized();
});

it('returns 401 when the printer is disabled', function () {
    Printer::factory()->withToken('pa_secret')->disabled()->create();

    $this->withToken('pa_secret')->postJson(route('agent.heartbeat'))->assertUnauthorized();
});

it('authenticates a token issued to an active printer', function () {
    $printer = Printer::factory()->create();
    $token = $printer->issueToken();

    $this->withToken($token)->postJson(route('agent.heartbeat'))->assertOk();
});

it('does not store the plain token', function () {
    $printer = Printer::factory()->create();
    $token = $printer->issueToken();

    expect($printer->fresh()->agent_token_hash)
        ->not->toBe($token)
        ->toBe(hash('sha256', $token));
});
