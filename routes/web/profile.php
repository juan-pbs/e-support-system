<?php

use App\Http\Controllers\Shared\GoogleCalendarController;
use App\Http\Controllers\Shared\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('integraciones/google-calendar')->name('google-calendar.')->group(function () {
        Route::get('/connect', [GoogleCalendarController::class, 'redirect'])->name('redirect');
        Route::get('/callback', [GoogleCalendarController::class, 'callback'])->name('callback');
        Route::post('/disconnect', [GoogleCalendarController::class, 'disconnect'])->name('disconnect');
        Route::post('/toggle', [GoogleCalendarController::class, 'toggle'])->name('toggle');
        Route::post('/sync-now', [GoogleCalendarController::class, 'syncNow'])->name('sync-now');
    });
});
