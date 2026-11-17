<?php

namespace App\Http\Requests\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class StoreClosedDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.CarbonImmutable::today('Asia/Tokyo')->toDateString(), 'unique:closed_dates,date'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => '休業日を指定してください。',
            'date.date_format' => '日付はYYYY-MM-DDで指定してください。',
            'date.after_or_equal' => '休業日は本日以降を指定してください。',
            'date.unique' => 'この日付は既に休業日として登録されています。',
            'reason.required' => '休業理由を入力してください。',
            'reason.max' => '休業理由は255文字以内で入力してください。',
        ];
    }
}
