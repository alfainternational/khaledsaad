<?php

namespace App\Contracts;

use App\Models\Project;

/**
 * مصدر اكتشاف المنافسين المرشّحين (إقليمي/عالمي).
 *
 * المحلي لا يُكتشف — يسمّيه المستخدم. هذا المصدر للطبقة الأبعد فقط، وما
 * يعيده مرشّحات لا حقائق: لا يصير منافسًا حتى يؤكده المستخدم.
 */
interface CompetitorProvider
{
    /**
     * @return array<int, array{name: string, url?: ?string, tier?: string, note?: ?string}>
     */
    public function discover(Project $project): array;

    public function isAvailable(): bool;
}
