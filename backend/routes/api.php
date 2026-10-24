<?php

use App\Http\Controllers\Agent\HeartbeatController;
use App\Http\Controllers\Agent\JobController;
use App\Http\Middleware\AuthenticatePrinter;
use Illuminate\Support\Facades\Route;

Route::prefix('agent')
    ->name('agent.')
    ->middleware(AuthenticatePrinter::class)
    ->group(function (): void {
        Route::get('jobs/next', [JobController::class, 'next'])->name('jobs.next');
        Route::get('jobs/{job}/file', [JobController::class, 'file'])->whereNumber('job')->name('jobs.file');
        Route::post('jobs/{job}/status', [JobController::class, 'updateStatus'])->whereNumber('job')->name('jobs.status');
        Route::post('heartbeat', HeartbeatController::class)->name('heartbeat');
    });
