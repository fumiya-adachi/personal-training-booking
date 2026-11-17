<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrainerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'specialty' => ['required', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'color' => ['required', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => '名前', 'specialty' => '得意分野', 'bio' => '紹介文', 'color' => 'カラー', 'is_active' => '公開状態'];
    }

    public function messages(): array
    {
        return ['required' => ':attributeを入力してください。', 'max' => ':attributeは:max文字以内で入力してください。', 'color.regex' => 'カラーは#から始まる6桁のカラーコードで指定してください。'];
    }
}
