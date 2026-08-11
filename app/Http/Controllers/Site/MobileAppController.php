<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MobileAppController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $path = (string) config('mobile.apk_path');
        abort_unless(is_file($path), 404, __('نسخة أندرويد غير متاحة مؤقتًا.'));

        return response()->download($path, 'Khaled-Saad-Growth.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
