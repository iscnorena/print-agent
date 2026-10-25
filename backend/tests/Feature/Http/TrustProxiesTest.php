<?php

it('builds https links when a local tunnel forwards an https request', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'papeleria.ngrok-free.app'])
        ->get('/login')
        ->assertSee('action="https://papeleria.ngrok-free.app/login"', false);
});

it('ignores forwarded headers from a remote address', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'evil.example'])
        ->get('/login')
        ->assertSee('action="http://', false)
        ->assertDontSee('evil.example');
});
