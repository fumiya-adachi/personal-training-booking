<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTrainerRequest;
use App\Models\Trainer;
use App\Models\TrainerSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TrainerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/trainers/Index', ['trainers' => Trainer::orderBy('id')->get()]);
    }

    public function store(StoreTrainerRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $trainer = Trainer::create($request->validated());
            foreach (range(0, 6) as $day) {
                TrainerSchedule::create(['trainer_id' => $trainer->id, 'day_of_week' => $day, 'start_time' => '09:00', 'end_time' => '19:00', 'is_working' => $day !== 1]);
            }
        });

        return back()->with('success', 'トレーナーを追加しました。');
    }

    public function update(StoreTrainerRequest $request, Trainer $trainer): RedirectResponse
    {
        DB::transaction(function () use ($request, $trainer): void {
            Trainer::whereKey($trainer->id)->lockForUpdate()->firstOrFail()->update($request->validated());
        });

        return back()->with('success', 'トレーナー情報を更新しました。');
    }
}
