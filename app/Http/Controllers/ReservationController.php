<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Models\Reservation;
use App\Models\Trainer;
use App\Services\AvailabilityService;
use App\Services\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function create(Request $request, AvailabilityService $availability): Response
    {
        [$start, $trainerId] = AvailabilityController::filters($request);

        return Inertia::render('reservations/Create', [
            'trainers' => Trainer::query()->where('is_active', true)->orderBy('id')->get(),
            'calendar' => $availability->calendar($start, $trainerId),
            'selectedTrainerId' => $trainerId,
            'session' => ['name' => config('booking.name'), 'duration' => config('booking.duration'), 'price' => config('booking.price')],
            'today' => CarbonImmutable::today(config('booking.timezone'))->format('Y-m-d'),
            'maxDate' => CarbonImmutable::today(config('booking.timezone'))->addDays(config('booking.horizon_days'))->format('Y-m-d'),
        ]);
    }

    public function index(Request $request): Response
    {
        return Inertia::render('reservations/Index', [
            'reservations' => $request->user()->reservations()->with(['trainer', 'user'])->orderByDesc('starts_at')->get()->map->toView()->values(),
        ]);
    }

    public function store(StoreReservationRequest $request, ReservationService $service): RedirectResponse
    {
        $reservation = $service->create($request->user(), $request->validated());

        return to_route('reservations.show', $reservation)->with('success', 'ご予約を承りました。予約確認メールをお送りします。');
    }

    public function show(Reservation $reservation): Response
    {
        Gate::authorize('view', $reservation);

        return Inertia::render('reservations/Show', ['reservation' => $reservation->toView()]);
    }

    public function destroy(Reservation $reservation, ReservationService $service): RedirectResponse
    {
        Gate::authorize('delete', $reservation);
        $service->cancel($reservation);

        return to_route('reservations.index')->with('success', '予約をキャンセルしました。');
    }
}
