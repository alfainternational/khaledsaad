<?php

namespace App\Modules\Competitors;

use App\Contracts\CompetitorProvider;
use App\Models\Project;

/**
 * منسّق الاكتشاف: يجلب المرشّحين من المصدر ويسجّلهم كمرشّحين لا حقائق.
 *
 * يفصل «من نكتشف» عن «كيف نخزّن»: تبديل المصدر (بحث، بيانات سوق) لا يغيّر
 * سلوك السجلّ، والمستخدم يبقى صاحب القرار النهائي في كل مرشّح.
 */
class CompetitorDiscovery
{
    public function __construct(
        private readonly CompetitorProvider $provider,
        private readonly CompetitorRegistry $registry,
    ) {}

    public function isAvailable(): bool
    {
        return $this->provider->isAvailable();
    }

    /**
     * @return int عدد المرشّحين الجدد المقترحين
     */
    public function discoverFor(Project $project): int
    {
        $candidates = $this->provider->discover($project);

        if ($candidates === []) {
            return 0;
        }

        $this->registry->suggest($project, $candidates);

        return count($candidates);
    }
}
