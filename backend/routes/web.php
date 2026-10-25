<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Customer\UploadController;
use App\Http\Controllers\Operator\PrintJobController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/print');

Route::get('subir', [UploadController::class, 'create'])->name('customer.uploads.create');
Route::post('subir', [UploadController::class, 'store'])->middleware('throttle:10,1')->name('customer.uploads.store');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::prefix('print')->name('operator.print-jobs.')->group(function (): void {
        Route::get('/', [PrintJobController::class, 'index'])->name('index');
        Route::get('jobs', [PrintJobController::class, 'list'])->name('list');
        Route::post('jobs', [PrintJobController::class, 'store'])->name('store');
        Route::post('jobs/{job}/queue', [PrintJobController::class, 'queue'])->whereNumber('job')->name('queue');
    });
});
