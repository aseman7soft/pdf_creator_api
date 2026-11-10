<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default admin user
    |--------------------------------------------------------------------------
    |
    | Database\Seeders\AdminUserSeeder creates (or repairs) the first admin
    | account from these values, so a freshly installed project always has a
    | working login for the management panel at /admin/login.
    |
    | They are read from the environment first. The fallbacks below are the
    | documented project defaults used by .env / .env.example. In production
    | always set ADMIN_PASSWORD explicitly; if it is left empty the seeder
    | generates a random password and prints it once.
    |
    */

    'default_user' => [
        'name' => env('ADMIN_NAME', 'مدیر سیستم'),
        'email' => env('ADMIN_EMAIL', 'admin@tarahsite.net'),
        'password' => env('ADMIN_PASSWORD', 'As123321_14'),
    ],

];
