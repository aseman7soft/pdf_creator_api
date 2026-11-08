<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

/**
 * Filters for GET /templates.
 */
class ListTemplatesRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:60'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.max' => 'عبارت جستجو حداکثر ۱۰۰ کاراکتر است.',
            'category.max' => 'دسته‌بندی حداکثر ۶۰ کاراکتر است.',
            'page.integer' => 'شماره صفحه باید عدد باشد.',
            'page.min' => 'شماره صفحه باید حداقل ۱ باشد.',
            'per_page.integer' => 'تعداد در هر صفحه باید عدد باشد.',
            'per_page.min' => 'تعداد در هر صفحه باید حداقل ۱ باشد.',
            'per_page.max' => 'تعداد در هر صفحه حداکثر ۱۰۰ است.',
        ];
    }

    public function searchTerm(): ?string
    {
        $term = $this->input('search') ?? $this->input('q');

        return is_string($term) && trim($term) !== '' ? trim($term) : null;
    }

    public function category(): ?string
    {
        $category = $this->input('category');

        return is_string($category) && trim($category) !== '' ? trim($category) : null;
    }

    public function perPage(): int
    {
        return max(1, min(100, (int) $this->input('per_page', 20)));
    }

    public function pageNumber(): int
    {
        return max(1, (int) $this->input('page', 1));
    }
}
