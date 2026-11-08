<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\ApiRequestLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class PurgeLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('delete', ApiRequestLog::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'days.required' => 'تعداد روز برای پاک‌سازی الزامی است.',
            'days.min' => 'حداقل یک روز را انتخاب کنید.',
        ];
    }
}
