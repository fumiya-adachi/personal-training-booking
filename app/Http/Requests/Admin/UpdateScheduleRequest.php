<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'schedules' => ['required', 'array', 'size:7'],
            'schedules.*' => ['required', 'array:day_of_week,start_time,end_time,is_working'],
            'schedules.*.day_of_week' => ['required', 'integer', 'between:0,6', 'distinct'],
            'schedules.*.start_time' => ['required', 'date_format:H:i', 'regex:/^\d{2}:(00|30)$/'],
            'schedules.*.end_time' => ['required', 'date_format:H:i', 'regex:/^\d{2}:(00|30)$/'],
            'schedules.*.is_working' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $schedules = $this->input('schedules', []);
            if (! is_array($schedules)) {
                return;
            }
            foreach ($schedules as $index => $schedule) {
                if (! is_array($schedule) || empty($schedule['is_working'])) {
                    continue;
                }
                $start = $schedule['start_time'] ?? '';
                $end = $schedule['end_time'] ?? '';
                if ($start >= $end) {
                    $validator->errors()->add("schedules.{$index}.end_time", '終了時刻は開始時刻より後に設定してください。');
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'schedules.size' => '日曜日から土曜日まで7日分の予定を指定してください。',
            'schedules.*.day_of_week.distinct' => '曜日が重複しています。',
            'schedules.*.start_time.date_format' => '開始時刻はHH:mmで指定してください。',
            'schedules.*.end_time.date_format' => '終了時刻はHH:mmで指定してください。',
            'schedules.*.start_time.regex' => '開始時刻は30分刻みで指定してください。',
            'schedules.*.end_time.regex' => '終了時刻は30分刻みで指定してください。',
        ];
    }
}
