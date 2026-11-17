<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $today = CarbonImmutable::today(config('booking.timezone'));
        return [
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today->format('Y-m-d'), 'before_or_equal:'.$today->addDays(config('booking.horizon_days'))->format('Y-m-d')],
            'time' => ['required', 'regex:/^(?:[01][0-9]|2[0-3]):(?:00|30)$/'],
            'trainer_id' => ['nullable', 'integer', Rule::exists('trainers', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => '予約日を選択してください。',
            'date.date_format' => '予約日を正しく選択してください。',
            'date.after_or_equal' => '本日以降の日付を選択してください。',
            'date.before_or_equal' => '予約は90日先まで受け付けています。',
            'time.required' => '予約時間を選択してください。',
            'time.regex' => '予約時間は30分刻みで選択してください。',
            'trainer_id.exists' => '選択したトレーナーは現在予約を受け付けていません。',
            'notes.max' => 'ご要望は1000文字以内で入力してください。',
        ];
    }
}
