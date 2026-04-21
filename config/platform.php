<?php

return [
    'admin' => [
        'email' => env('ADMIN_EMAIL', 'admin@khaledsaad.local'),
        'name' => env('ADMIN_NAME', 'Super Admin'),
        // Same default as DemoPlatformSeeder so local admin login matches documented demo password.
        'password' => env('ADMIN_PASSWORD', 'Demo@123456'),
    ],
];
