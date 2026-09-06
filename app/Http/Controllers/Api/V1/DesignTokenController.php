<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * توكنز التصميم للعميل.
 *
 * الفائدة العملية: تغيير لون أو مسافة يصل إلى التطبيق **بلا مراجعة
 * متجر**. بدونه يبقى كل تعديل بصري رهينة دورة إصدار تستغرق أيامًا،
 * فتتباعد هوية الويب عن هوية التطبيق بلا أن يقصد أحد ذلك.
 */
final class DesignTokenController extends Controller
{
    public function index(): JsonResponse
    {
        $path = base_path('design/tokens.json');

        if (! is_file($path)) {
            return response()->json(['error' => [
                'code' => 'tokens_unavailable',
                'kind' => 'ours',
                'title' => __('تعذّر تحميل هوية الواجهة'),
                'message' => __('سنعيدها قريبًا؛ التطبيق يعمل بآخر نسخة محفوظة لديه.'),
                'user_action' => null,
            ]], 503);
        }

        /** @var array<string, mixed> $tokens */
        $tokens = json_decode((string) file_get_contents($path), true) ?: [];

        // البصمة تسمح للعميل بألّا يعيد التنزيل إن لم يتغيّر شيء.
        $etag = '"'.md5_file($path).'"';

        return response()
            ->json([
                'data' => $tokens,
                'meta' => [
                    'version' => $tokens['version'] ?? null,
                    'server_time' => now()->toIso8601String(),
                ],
            ])
            ->setEtag($etag)
            ->setPublic();
    }
}
