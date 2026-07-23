<?php

namespace App\Contracts;

use App\Models\Project;

/**
 * مصدر رقم المقارنة الذي يراه صاحب المشروع بجانب السؤال.
 *
 * الشاشات لا تعرف من أين جاء الرقم: جدول مراجَع يدويًا، أو واجهة سوق حيّة.
 * لذلك يمكن ترقية المصدر لاحقًا دون لمس أي صفحة.
 */
interface BenchmarkProvider
{
    /**
     * @return array{text: string, source: string, is_live?: bool, fetched_at?: string}|null
     */
    public function forField(string $fieldKey, ?Project $project = null): ?array;

    /**
     * هل هذا المصدر متاح الآن؟ (مفتاح مضبوط، خدمة تعمل)
     */
    public function isAvailable(): bool;
}
