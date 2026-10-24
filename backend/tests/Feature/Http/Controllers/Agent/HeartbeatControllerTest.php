<?php

use App\Models\Printer;

it('updates last_seen_at of the calling printer only', function () {
    $this->freezeSecond();
    $printer = Printer::factory()->withToken('pa_secret')->create();
    $other = Printer::factory()->create();

    $this->withToken('pa_secret')
        ->postJson(route('agent.heartbeat'))
        ->assertOk()
        ->assertJsonStructure(['server_time']);

    expect($printer->fresh()->last_seen_at->equalTo(now()))->toBeTrue()
        ->and($other->fresh()->last_seen_at)->toBeNull();
});
