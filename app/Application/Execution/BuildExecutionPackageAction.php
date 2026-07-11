<?php

namespace App\Application\Execution;

use App\Domain\Execution\Models\ExecutionAsset;
use App\Domain\Execution\Models\ExecutionPackage;
use App\Domain\Execution\Models\ExecutionTask;
use App\Domain\Execution\Models\Recommendation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a recommendation into an actionable Execution Package (Phase ج): the problem,
 * the evidence, the decision, a ready output scaffold, owner, status and a measurement
 * plan — so a recommendation never stays as text in a report.
 */
class BuildExecutionPackageAction
{
    public function handle(Recommendation $recommendation, ?User $actor = null): ExecutionPackage
    {
        return DB::transaction(function () use ($recommendation, $actor): ExecutionPackage {
            $package = ExecutionPackage::query()->create([
                'public_id' => (string) Str::ulid(),
                'workspace_id' => $recommendation->workspace_id,
                'project_id' => $recommendation->project_id,
                'recommendation_id' => $recommendation->id,
                'title' => $recommendation->title,
                'problem' => $recommendation->title,
                'evidence' => $recommendation->evidence,
                'decision' => $recommendation->rationale,
                'measurement_plan' => 'قِس الأثر خلال 14 يوماً عبر المؤشر المرتبط بهذه التوصية (تحويل / ظهور / ثقة).',
                'owner_user_id' => $actor?->id,
                'status' => 'proposed',
                'created_by' => $actor?->id,
            ]);

            $tasks = [
                'صياغة المخرج النهائي',
                'مراجعة واعتماد المخرج',
                'تنفيذ التعديل على المصدر',
                'قياس الأثر بعد 14 يوماً',
            ];

            foreach ($tasks as $index => $title) {
                ExecutionTask::query()->create([
                    'public_id' => (string) Str::ulid(),
                    'execution_package_id' => $package->id,
                    'title' => $title,
                    'status' => 'pending',
                    'order_index' => $index,
                ]);
            }

            ExecutionAsset::query()->create([
                'public_id' => (string) Str::ulid(),
                'execution_package_id' => $package->id,
                'type' => $this->assetTypeForArea((string) $recommendation->area),
                'title' => 'مخرج جاهز للتعديل',
                'body' => $this->assetBodyFor($recommendation, $package->measurement_plan),
            ]);

            $recommendation->update(['status' => 'accepted']);

            return $package->load(['tasks', 'assets']);
        });
    }

    private function assetTypeForArea(string $area): string
    {
        return match ($area) {
            'website', 'conversion' => 'dev_brief',
            'trust', 'seo', 'ai_visibility' => 'copy',
            'social', 'content' => 'copy',
            default => 'copy',
        };
    }

    private function assetBodyFor(Recommendation $recommendation, string $measurementPlan): string
    {
        $output = match ($this->assetTypeForArea((string) $recommendation->area)) {
            'dev_brief' => [
                'نوع المخرج: موجز تعديل للموقع أو صفحة الهبوط.',
                'ما يسلّم للمطور/المصمم: عدّل موضع الإجراء الرئيسي، الرسالة، أو عنصر الثقة المرتبط بهذه التوصية.',
                'نص مقترح للاستخدام: '.$recommendation->rationale,
            ],
            default => [
                'نوع المخرج: نسخة تسويقية قابلة للتعديل.',
                'ما يسلّم للمسوّق/المحتوى: حوّل القرار إلى نص قصير يظهر في الموقع أو الإعلان أو السوشيال.',
                'نص مقترح للاستخدام: '.$recommendation->rationale,
            ],
        };

        return implode("\n\n", [
            '# '.$recommendation->title,
            "## المشكلة\n".$recommendation->title,
            "## الدليل\n".$recommendation->evidence,
            "## القرار\n".$recommendation->rationale,
            "## المخرج الجاهز للتعديل\n".implode("\n", array_map(fn (string $line): string => '- '.$line, $output)),
            "## مؤشر القياس\n".$measurementPlan,
            "## قائمة تسليم مختصرة\n".implode("\n", [
                '- راجع النص أو التعديل مع صاحب القرار.',
                '- نفّذ النسخة الأولى بدون توسيع النطاق.',
                '- قارن النتيجة قبل وبعد خلال فترة القياس.',
            ]),
        ]);
    }
}
