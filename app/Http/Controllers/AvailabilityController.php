<?php

namespace App\Http\Controllers;

use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AvailabilityController extends Controller
{
    public function __invoke(Request $request, AvailabilityService $availability): JsonResponse
    {
        [$start, $trainerId] = self::filters($request);

        return response()->json($availability->calendar($start, $trainerId));
    }

    public static function filters(Request $request): array
    {
        $today = CarbonImmutable::today(config('booking.timezone'));
        $data = $request->validate([
            'start' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$today->format('Y-m-d'), 'before_or_equal:'.$today->addDays(config('booking.horizon_days'))->format('Y-m-d')],
            'trainer_id' => ['nullable', 'integer', Rule::exists('trainers', 'id')->where('is_active', true)],
        ], [
            'start.date_format' => '表示開始日を正しく指定してください。',
            'start.after_or_equal' => '本日以降の日付を指定してください。',
            'start.before_or_equal' => '表示できるのは90日先までです。',
            'trainer_id.exists' => 'トレーナーを選び直してください。',
        ]);

        return [$data['start'] ?? $today->format('Y-m-d'), isset($data['trainer_id']) ? (int) $data['trainer_id'] : null];
    }
}
