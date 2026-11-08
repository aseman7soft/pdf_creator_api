<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\ApiProject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ApiProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        if ($project instanceof ApiProject) {
            return Gate::allows('update', $project);
        }

        return Gate::allows('create', ApiProject::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ApiProject|null $project */
        $project = $this->route('project');

        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:60',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('api_projects', 'code')->ignore($project?->getKey()),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'monthly_quota' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'templates' => ['nullable', 'array', 'max:200'],
            'templates.*' => ['integer', 'exists:templates,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'کد پروژه فقط می‌تواند شامل حروف کوچک انگلیسی، اعداد و خط تیره باشد.',
            'templates.*.exists' => 'قالب انتخاب‌شده معتبر نیست.',
        ];
    }
}
