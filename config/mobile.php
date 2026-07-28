<?php

return [
    'version' => env('MOBILE_APP_VERSION', '1.0.2'),
    'build' => (int) env('MOBILE_APP_BUILD', 3),
    'android_package' => 'net.khaledsaad.ksgrowth_mobile',
    'ios_bundle' => 'net.khaledsaad.ksgrowthMobile',
    'apk_path' => public_path('downloads/khaledsaad-growth.apk'),
];
