<?php

use App\Models\Printer;

it('is online only while heartbeats are recent', function (?int $secondsAgo, bool $online) {
    config(['printing.agent_offline_after_seconds' => 150]);
    $printer = Printer::factory()->make([
        'last_seen_at' => $secondsAgo === null ? null : now()->subSeconds($secondsAgo),
    ]);

    expect($printer->isOnline())->toBe($online);
})->with([
    'never seen' => [null, false],
    'just now' => [5, true],
    'just before the limit' => [149, true],
    'past the limit' => [151, false],
]);
