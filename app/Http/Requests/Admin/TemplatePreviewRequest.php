<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class TemplatePreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->route('template');

        return $template instanceof Template && Gate::allows('preview', $template);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'data' => ['nullable', 'string', 'max:512000'],
            'options' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
