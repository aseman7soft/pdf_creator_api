<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base form request for the public API.
 *
 * Laravel's routing pipeline renders exceptions at the innermost pipeline slice
 * (before outer middleware can translate them), so validation failures are
 * thrown as an ApiException that renders the canonical API error envelope.
 */
abstract class ApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @throws ApiException
     */
    protected function failedValidation(Validator $validator)
    {
        throw ApiException::unprocessable(
            'VALIDATION_ERROR',
            'داده‌های ارسالی نامعتبر است.',
            $validator->errors()->toArray(),
        );
    }
}
