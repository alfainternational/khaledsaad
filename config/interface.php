<?php

return [
    'version' => 'v2',
    'families' => [
        'public' => ['resources/views/home.blade.php', 'resources/views/site'],
        'auth' => ['resources/views/auth'],
        'workspace' => ['resources/views/app'],
        'admin' => ['resources/views/admin'],
        'reports' => ['resources/views/reports', 'resources/views/agency-reports'],
        'flutter' => ['mobile/lib'],
    ],
    'excluded_blade_segments' => ['/components/', '/partials/', '/vendor/'],
];
