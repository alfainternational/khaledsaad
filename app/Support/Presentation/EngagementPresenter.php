<?php

namespace App\Support\Presentation;

use App\Models\ToolRun;
use App\Services\Tools\ToolEngagement;

/**
 * يحوّل حالة التعامل إلى وجهة قابلة للنقر.
 *
 * الويب يحتاج رابطًا، والتطبيق يحتاج اسم شاشة ومعرّفًا. الاثنان يُشتقان
 * من نفس الحالة هنا، فلا يفترقان في السلوك.
 */
class EngagementPresenter
{
    /**
     * @param  array<string, mixed>  $engagement
     * @return array<string, mixed>
     */
    public function decorate(array $engagement, string $toolKey): array
    {
        return [
            ...$engagement,
            'url' => $this->url($engagement, $toolKey),
            // للتطبيق: الشاشة المقصودة بدل الرابط.
            'target' => match ($engagement['state']) {
                ToolEngagement::STATE_DRAFT => 'wizard',
                ToolEngagement::STATE_RUNNING => 'status',
                ToolEngagement::STATE_READY => $engagement['report_id'] !== null ? 'report' : 'status',
                default => 'tool',
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $engagement
     */
    private function url(array $engagement, string $toolKey): string
    {
        $uuid = $engagement['run_uuid'] ?? null;

        return match ($engagement['state']) {
            ToolEngagement::STATE_DRAFT => route('app.runs.review', $uuid),
            ToolEngagement::STATE_RUNNING => route('app.runs.status', $uuid),
            ToolEngagement::STATE_READY => $engagement['report_id'] !== null
                ? route('app.reports.show', $engagement['report_id'])
                : route('app.runs.status', $uuid),
            default => route('app.tools.show', $toolKey),
        };
    }

    /**
     * بطاقة تشغيل غير مكتمل كما تظهر في «أكمل ما بدأته».
     *
     * @return array<string, mixed>
     */
    public function resumeCard(ToolRun $run, array $engagement): array
    {
        return [
            'run_uuid' => $run->uuid,
            'tool_key' => $run->toolVersion->tool->key,
            'tool_title' => $run->toolVersion->tool->title,
            'project_name' => $run->project?->name,
            'project_slug' => $run->project?->slug,
            'state' => $engagement['state'],
            'label' => $engagement['label'],
            'hint' => $engagement['hint'],
            'percent' => $engagement['percent'],
            'url' => $this->url($engagement, $run->toolVersion->tool->key),
            'started_at' => $run->created_at?->toIso8601String(),
        ];
    }
}
