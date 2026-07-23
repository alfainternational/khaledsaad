<?php

namespace App\Services\Competitors;

use App\Models\Project;
use App\Models\ProjectCompetitor;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * سجلّ المنافسين: تقسيم عمل حسب الطبقة، لا خلط.
 *
 * القاعدة الحاكمة (كما في انضباط أرقام السوق):
 * - المحلي: المستخدم يسميه فهو يقين — نخزّنه مؤكدًا. أثره الأعلى، فيقود التحليل.
 * - الإقليمي/العالمي: نقترحه كمرشّح لا كحقيقة، ولا يصير منافسًا حتى يؤكده المستخدم.
 *
 * لماذا لا نكتشف المحليين آليًا: أغلبهم بلا بصمة رقمية (واتساب/إنستغرام)،
 * فالبحث الآلي يجد الأكبر والأبعد ويُفوّت جار المستخدم الذي يأخذ عملاءه.
 */
class CompetitorRegistry
{
    /**
     * حفظ منافسين سمّاهم المستخدم بنفسه — محليون مؤكدون افتراضًا.
     *
     * @return array<int, ProjectCompetitor>
     */
    public function rememberNamed(Project $project, string $rawNames, string $tier = ProjectCompetitor::TIER_LOCAL): array
    {
        $saved = [];

        foreach ($this->parseNames($rawNames) as $name) {
            $saved[] = ProjectCompetitor::updateOrCreate(
                ['project_id' => $project->id, 'name' => $name],
                [
                    'source' => ProjectCompetitor::SOURCE_NAMED,
                    'tier' => $tier,
                    // ما لم يستبعده المستخدم صراحةً يبقى كما سمّاه: مؤكدًا.
                    'status' => ProjectCompetitor::STATUS_CONFIRMED,
                    'url' => $this->extractUrl($name),
                ],
            );
        }

        return $saved;
    }

    /**
     * تسجيل مرشّحين اكتشفناهم — بانتظار تأكيد المستخدم، لا يُعرضون كحقيقة.
     *
     * @param  array<int, array{name: string, url?: ?string, tier?: string, note?: ?string}>  $candidates
     */
    public function suggest(Project $project, array $candidates): void
    {
        foreach ($candidates as $candidate) {
            $name = trim($candidate['name'] ?? '');

            if ($name === '') {
                continue;
            }

            // لا نُرقّي مرشّحًا فوق منافس سمّاه المستخدم، ولا نُعيد إحياء مستبعَد.
            $existing = ProjectCompetitor::where('project_id', $project->id)->where('name', $name)->first();

            if ($existing !== null) {
                continue;
            }

            ProjectCompetitor::create([
                'project_id' => $project->id,
                'name' => $name,
                'source' => ProjectCompetitor::SOURCE_SUGGESTED,
                'tier' => $candidate['tier'] ?? ProjectCompetitor::TIER_REGIONAL,
                'status' => ProjectCompetitor::STATUS_CANDIDATE,
                'url' => $candidate['url'] ?? null,
                'note' => $candidate['note'] ?? null,
            ]);
        }
    }

    public function confirm(ProjectCompetitor $competitor): void
    {
        $competitor->forceFill(['status' => ProjectCompetitor::STATUS_CONFIRMED])->save();
    }

    public function dismiss(ProjectCompetitor $competitor): void
    {
        $competitor->forceFill(['status' => ProjectCompetitor::STATUS_DISMISSED])->save();
    }

    /**
     * صورة المنافسين للتقرير: المؤكدون أولًا (محلي ثم إقليمي)، ثم مرشّحون للتأكيد.
     *
     * @return array{confirmed: array<int, array<string, mixed>>, candidates: array<int, array<string, mixed>>, has_local: bool}
     */
    public function forReport(Project $project): array
    {
        $all = $project->competitors()
            ->where('status', '!=', ProjectCompetitor::STATUS_DISMISSED)
            ->get();

        $confirmed = $all->where('status', ProjectCompetitor::STATUS_CONFIRMED)
            ->sortBy(fn (ProjectCompetitor $c) => $c->tierWeight())
            ->values();

        $candidates = $all->where('status', ProjectCompetitor::STATUS_CANDIDATE)->values();

        return [
            'confirmed' => $confirmed->map(fn (ProjectCompetitor $c) => $this->present($c))->all(),
            'candidates' => $candidates->map(fn (ProjectCompetitor $c) => $this->present($c))->all(),
            'has_local' => $confirmed->contains(fn (ProjectCompetitor $c) => $c->tier === ProjectCompetitor::TIER_LOCAL),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ProjectCompetitor $competitor): array
    {
        return [
            'id' => $competitor->id,
            'name' => $competitor->name,
            'url' => $competitor->url,
            'tier' => $competitor->tier,
            'tier_label' => match ($competitor->tier) {
                ProjectCompetitor::TIER_LOCAL => 'محلي',
                ProjectCompetitor::TIER_REGIONAL => 'إقليمي',
                default => 'عالمي',
            },
            'source' => $competitor->source,
        ];
    }

    /**
     * تفكيك نص حر إلى أسماء منافسين: أسطر، فواصل، «و» العاطفة، أو نقاط.
     *
     * @return Collection<int, string>
     */
    private function parseNames(string $raw): Collection
    {
        return Str::of($raw)
            ->replaceMatches('/[\n\r،؛;•\-\|]+/u', ',')
            ->explode(',')
            ->map(fn (string $part) => trim($part))
            ->map(fn (string $part) => Str::of($part)->replaceMatches('/^\s*و\s+/u', '')->trim()->toString())
            ->filter(fn (string $part) => mb_strlen($part) >= 2)
            ->unique()
            ->take(10)
            ->values();
    }

    private function extractUrl(string $name): ?string
    {
        if (preg_match('#https?://\S+#', $name, $m)) {
            return $m[0];
        }

        // معرّف إنستغرام/تويتر شائع في تسمية المنافسين المحليين.
        if (preg_match('/^@([A-Za-z0-9._]+)$/', trim($name), $m)) {
            return "https://instagram.com/{$m[1]}";
        }

        return null;
    }
}
