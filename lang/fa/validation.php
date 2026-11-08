<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Persian validation messages
|--------------------------------------------------------------------------
*/

return [

    'after' => 'فیلد :attribute باید تاریخی بعد از :date باشد.',
    'after_or_equal' => 'فیلد :attribute باید تاریخی بعد از :date یا برابر با آن باشد.',
    'alpha' => 'فیلد :attribute فقط می‌تواند شامل حروف باشد.',
    'alpha_dash' => 'فیلد :attribute فقط می‌تواند شامل حروف، اعداد، خط تیره و زیرخط باشد.',
    'alpha_num' => 'فیلد :attribute فقط می‌تواند شامل حروف و اعداد باشد.',
    'array' => 'فیلد :attribute باید یک آرایه باشد.',
    'before' => 'فیلد :attribute باید تاریخی قبل از :date باشد.',
    'before_or_equal' => 'فیلد :attribute باید تاریخی قبل از :date یا برابر با آن باشد.',
    'between' => [
        'array' => 'فیلد :attribute باید بین :min و :max آیتم داشته باشد.',
        'file' => 'فیلد :attribute باید بین :min و :max کیلوبایت باشد.',
        'numeric' => 'فیلد :attribute باید بین :min و :max باشد.',
        'string' => 'فیلد :attribute باید بین :min و :max کاراکتر باشد.',
    ],
    'boolean' => 'فیلد :attribute باید درست یا غلط باشد.',
    'confirmed' => 'تکرار فیلد :attribute مطابقت ندارد.',
    'current_password' => 'رمز عبور فعلی صحیح نیست.',
    'date' => 'فیلد :attribute تاریخ معتبری نیست.',
    'date_equals' => 'فیلد :attribute باید برابر با تاریخ :date باشد.',
    'date_format' => 'فیلد :attribute با قالب :format مطابقت ندارد.',
    'declined' => 'فیلد :attribute باید رد شود.',
    'different' => 'فیلد :attribute و :other باید متفاوت باشند.',
    'digits' => 'فیلد :attribute باید :digits رقم باشد.',
    'digits_between' => 'فیلد :attribute باید بین :min و :max رقم باشد.',
    'distinct' => 'فیلد :attribute مقدار تکراری دارد.',
    'email' => 'فیلد :attribute باید یک ایمیل معتبر باشد.',
    'ends_with' => 'فیلد :attribute باید با یکی از مقادیر :values پایان یابد.',
    'enum' => 'مقدار انتخاب‌شده برای :attribute معتبر نیست.',
    'exists' => 'مقدار انتخاب‌شده برای :attribute معتبر نیست.',
    'file' => 'فیلد :attribute باید یک فایل باشد.',
    'filled' => 'فیلد :attribute باید مقدار داشته باشد.',
    'gt' => [
        'array' => 'فیلد :attribute باید بیشتر از :value آیتم داشته باشد.',
        'file' => 'فیلد :attribute باید بزرگ‌تر از :value کیلوبایت باشد.',
        'numeric' => 'فیلد :attribute باید بزرگ‌تر از :value باشد.',
        'string' => 'فیلد :attribute باید بزرگ‌تر از :value کاراکتر باشد.',
    ],
    'gte' => [
        'array' => 'فیلد :attribute باید :value آیتم یا بیشتر داشته باشد.',
        'file' => 'فیلد :attribute باید بزرگ‌تر یا برابر :value کیلوبایت باشد.',
        'numeric' => 'فیلد :attribute باید بزرگ‌تر یا برابر :value باشد.',
        'string' => 'فیلد :attribute باید بزرگ‌تر یا برابر :value کاراکتر باشد.',
    ],
    'image' => 'فیلد :attribute باید یک تصویر باشد.',
    'in' => 'مقدار انتخاب‌شده برای :attribute معتبر نیست.',
    'in_array' => 'فیلد :attribute باید در :other وجود داشته باشد.',
    'integer' => 'فیلد :attribute باید عدد صحیح باشد.',
    'ip' => 'فیلد :attribute باید یک آدرس IP معتبر باشد.',
    'ipv4' => 'فیلد :attribute باید یک آدرس IPv4 معتبر باشد.',
    'ipv6' => 'فیلد :attribute باید یک آدرس IPv6 معتبر باشد.',
    'json' => 'فیلد :attribute باید یک رشته JSON معتبر باشد.',
    'lt' => [
        'array' => 'فیلد :attribute باید کمتر از :value آیتم داشته باشد.',
        'file' => 'فیلد :attribute باید کوچک‌تر از :value کیلوبایت باشد.',
        'numeric' => 'فیلد :attribute باید کمتر از :value باشد.',
        'string' => 'فیلد :attribute باید کمتر از :value کاراکتر باشد.',
    ],
    'lte' => [
        'array' => 'فیلد :attribute نباید بیشتر از :value آیتم داشته باشد.',
        'file' => 'فیلد :attribute باید کوچک‌تر یا برابر :value کیلوبایت باشد.',
        'numeric' => 'فیلد :attribute باید کوچک‌تر یا برابر :value باشد.',
        'string' => 'فیلد :attribute باید کوچک‌تر یا برابر :value کاراکتر باشد.',
    ],
    'max' => [
        'array' => 'فیلد :attribute نباید بیشتر از :max آیتم داشته باشد.',
        'file' => 'فیلد :attribute نباید بزرگ‌تر از :max کیلوبایت باشد.',
        'numeric' => 'فیلد :attribute نباید بزرگ‌تر از :max باشد.',
        'string' => 'فیلد :attribute نباید بیشتر از :max کاراکتر باشد.',
    ],
    'mimes' => 'فیلد :attribute باید فایلی از نوع :values باشد.',
    'mimetypes' => 'فیلد :attribute باید فایلی از نوع :values باشد.',
    'min' => [
        'array' => 'فیلد :attribute باید حداقل :min آیتم داشته باشد.',
        'file' => 'فیلد :attribute باید حداقل :min کیلوبایت باشد.',
        'numeric' => 'فیلد :attribute باید حداقل :min باشد.',
        'string' => 'فیلد :attribute باید حداقل :min کاراکتر باشد.',
    ],
    'missing' => 'فیلد :attribute نباید وجود داشته باشد.',
    'not_in' => 'مقدار انتخاب‌شده برای :attribute معتبر نیست.',
    'not_regex' => 'قالب فیلد :attribute معتبر نیست.',
    'numeric' => 'فیلد :attribute باید عدد باشد.',
    'password' => [
        'letters' => 'فیلد :attribute باید حداقل یک حرف داشته باشد.',
        'mixed' => 'فیلد :attribute باید حداقل یک حرف بزرگ و یک حرف کوچک داشته باشد.',
        'numbers' => 'فیلد :attribute باید حداقل یک عدد داشته باشد.',
        'symbols' => 'فیلد :attribute باید حداقل یک نماد داشته باشد.',
        'uncompromised' => 'فیلد :attribute در داده‌های لو رفته دیده شده است. لطفاً مقدار دیگری انتخاب کنید.',
    ],
    'present' => 'فیلد :attribute باید وجود داشته باشد.',
    'prohibited' => 'فیلد :attribute ممنوع است.',
    'prohibited_if' => 'فیلد :attribute وقتی :other برابر :value است ممنوع می‌باشد.',
    'prohibited_unless' => 'فیلد :attribute مگر اینکه :other در :values باشد، ممنوع است.',
    'prohibits' => 'فیلد :attribute مانع از وجود :other می‌شود.',
    'regex' => 'قالب فیلد :attribute معتبر نیست.',
    'required' => 'فیلد :attribute الزامی است.',
    'required_array_keys' => 'فیلد :attribute باید شامل کلیدهای :values باشد.',
    'required_if' => 'فیلد :attribute وقتی :other برابر :value است الزامی می‌باشد.',
    'required_if_accepted' => 'فیلد :attribute وقتی :other پذیرفته شده الزامی است.',
    'required_unless' => 'فیلد :attribute مگر اینکه :other در :values باشد، الزامی است.',
    'required_with' => 'فیلد :attribute وقتی :values وجود دارد الزامی است.',
    'required_with_all' => 'فیلد :attribute وقتی :values وجود دارد الزامی است.',
    'required_without' => 'فیلد :attribute وقتی :values وجود ندارد الزامی است.',
    'required_without_all' => 'فیلد :attribute وقتی هیچ‌یک از :values وجود ندارد الزامی است.',
    'same' => 'فیلد :attribute و :other باید یکسان باشند.',
    'size' => [
        'array' => 'فیلد :attribute باید شامل :size آیتم باشد.',
        'file' => 'فیلد :attribute باید :size کیلوبایت باشد.',
        'numeric' => 'فیلد :attribute باید برابر :size باشد.',
        'string' => 'فیلد :attribute باید :size کاراکتر باشد.',
    ],
    'starts_with' => 'فیلد :attribute باید با یکی از مقادیر :values شروع شود.',
    'string' => 'فیلد :attribute باید یک رشته متنی باشد.',
    'timezone' => 'فیلد :attribute باید یک منطقه زمانی معتبر باشد.',
    'unique' => 'مقدار :attribute قبلاً استفاده شده است.',
    'uploaded' => 'بارگذاری فیلد :attribute ناموفق بود.',
    'url' => 'فیلد :attribute باید یک آدرس اینترنتی معتبر باشد.',
    'uuid' => 'فیلد :attribute باید یک UUID معتبر باشد.',

    /*
    |--------------------------------------------------------------------------
    | Custom messages
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'slug' => [
            'regex' => 'اسلاگ فقط می‌تواند شامل حروف کوچک انگلیسی، اعداد و خط تیره باشد.',
        ],
        'code' => [
            'regex' => 'کد پروژه فقط می‌تواند شامل حروف کوچک انگلیسی، اعداد و خط تیره باشد.',
        ],
        'html' => [
            'not_regex' => 'قالب شامل کد PHP یا دستورات غیرمجاز است و ذخیره نشد.',
        ],
        'assets' => [
            'mimes' => 'نوع فایل انتخاب‌شده مجاز نیست. فقط فرمت‌های اعلام‌شده پذیرفته می‌شوند.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Field names
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'name' => 'نام',
        'label' => 'برچسب',
        'email' => 'ایمیل',
        'password' => 'رمز عبور',
        'password_confirmation' => 'تکرار رمز عبور',
        'current_password' => 'رمز عبور فعلی',
        'role' => 'نقش',
        'is_active' => 'وضعیت فعال',
        'slug' => 'اسلاگ',
        'category' => 'دسته‌بندی',
        'description' => 'توضیحات',
        'locale' => 'زبان',
        'html' => 'قالب HTML',
        'css' => 'استایل CSS',
        'header_html' => 'هدر HTML',
        'footer_html' => 'فوتر HTML',
        'page_size' => 'اندازه صفحه',
        'orientation' => 'جهت صفحه',
        'margins' => 'حاشیه‌ها',
        'pdf_options' => 'تنظیمات PDF',
        'code' => 'کد',
        'rate_limit_per_minute' => 'سقف نرخ (در دقیقه)',
        'monthly_quota' => 'سقف ماهانه',
        'templates' => 'قالب‌های مجاز',
        'expires_at' => 'تاریخ انقضا',
        'rate_override' => 'سقف نرخ اختصاصی',
        'type' => 'نوع',
        'is_required' => 'الزامی',
        'default_value' => 'مقدار پیش‌فرض',
        'validation_rules' => 'قواعد اعتبارسنجی',
        'min_length' => 'حداقل طول',
        'max_length' => 'حداکثر طول',
        'min_value' => 'حداقل مقدار',
        'max_value' => 'حداکثر مقدار',
        'sort_order' => 'ترتیب',
        'file' => 'فایل',
        'note' => 'توضیح نسخه',
        'data' => 'داده‌های نمونه',
        'days' => 'تعداد روز',
        'level' => 'سطح',
        'search' => 'جستجو',
        'status' => 'وضعیت',
        'project_id' => 'پروژه',
        'template_id' => 'قالب',
        'date_from' => 'از تاریخ',
        'date_to' => 'تا تاریخ',
        'new_password' => 'رمز عبور جدید',
        'new_password_confirmation' => 'تکرار رمز عبور جدید',
    ],

];
