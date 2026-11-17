<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ReservationController::class, 'create'])->name('home');
Route::get('/reservations/create', [ReservationController::class, 'create'])->name('reservations.create');
Route::get('/availability', AvailabilityController::class)->name('availability');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:6,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
    Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store')->middleware('throttle:30,1');
    Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
    Route::delete('/reservations/{reservation}', [ReservationController::class, 'destroy'])->name('reservations.destroy');

    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/', fn () => to_route('admin.reservations.index'))->name('index');
        Route::get('/reservations', [Admin\ReservationController::class, 'index'])->name('reservations.index');
        Route::get('/trainers', [Admin\TrainerController::class, 'index'])->name('trainers.index');
        Route::post('/trainers', [Admin\TrainerController::class, 'store'])->name('trainers.store');
        Route::patch('/trainers/{trainer}', [Admin\TrainerController::class, 'update'])->name('trainers.update');
        Route::get('/schedules', [Admin\ScheduleController::class, 'index'])->name('schedules.index');
        Route::put('/schedules/{trainer}', [Admin\ScheduleController::class, 'update'])->name('schedules.update');
        Route::put('/closed-weekdays', [Admin\ScheduleController::class, 'updateClosedWeekdays'])->name('closed-weekdays.update');
        Route::post('/closed-dates', [Admin\ScheduleController::class, 'storeClosedDate'])->name('closed-dates.store');
        Route::delete('/closed-dates/{closedDate}', [Admin\ScheduleController::class, 'destroyClosedDate'])->name('closed-dates.destroy');
    });
});
