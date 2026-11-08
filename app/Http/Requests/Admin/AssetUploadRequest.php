<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AssetUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Asset::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = max(1, (int) setting('security.max_upload_kb', config('pdf.max_upload_kb', 5120)));

        return [
            'file' => ['required', 'file', 'max:'.$maxKb],
            'type' => ['required', 'string', 'in:font,image,css,other'],
            'name' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxKb = max(1, (int) setting('security.max_upload_kb', config('pdf.max_upload_kb', 5120)));

        return [
            'file.max' => 'حجم فایل نباید بیشتر از '.$maxKb.' کیلوبایت باشد.',
            'file.required' => 'انتخاب فایل الزامی است.',
            'type.in' => 'نوع فایل انتخاب‌شده معتبر نیست.',
        ];
    }
}
