<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Trainer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::enum(ReservationStatus::class)],
        ]);
        $today = CarbonImmutable::today('Asia/Tokyo');
        $query = Reservation::query()->with(['trainer', 'user'])->orderByDesc('starts_at');

        if (! empty($filters['date'])) {
            $start = CarbonImmutable::parse($filters['date'], 'Asia/Tokyo')->startOfDay();
            $query->where('starts_at', '>=', $start->utc())->where('starts_at', '<', $start->addDay()->utc());
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return Inertia::render('admin/reservations/Index', [
            'reservations' => $query->get()->map(fn (Reservation $reservation) => $reservation->toView()),
            'stats' => [
                'today' => Reservation::where('status', ReservationStatus::Confirmed)->where('starts_at', '>=', $today->utc())->where('starts_at', '<', $today->addDay()->utc())->count(),
                'upcoming' => Reservation::where('status', ReservationStatus::Confirmed)->where('starts_at', '>', now('UTC'))->count(),
                'clients' => User::where('role', UserRole::Client)->count(),
            ],
            'filters' => ['date' => $filters['date'] ?? '', 'status' => $filters['status'] ?? ''],
            'trainers' => Trainer::orderBy('id')->get(),
        ]);
    }
}
