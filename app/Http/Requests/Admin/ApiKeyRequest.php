<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\ApiProject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        if (! $project instanceof ApiProject) {
            return false;
        }

        return Gate::allows('manageKeys', $project);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expires_at.after' => 'تاریخ انقضا باید بعد از امروز باشد.',
        ];
    }
}
