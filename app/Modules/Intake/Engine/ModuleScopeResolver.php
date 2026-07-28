<?php

namespace App\Modules\Intake\Engine;

use App\Models\BlueprintModule;
use App\Models\Project;

class ModuleScopeResolver
{
    /** @return array{state:string,reason:string} */
    public function resolve(BlueprintModule $binding, Project $project, array $known = []): array
    {
        $key = $binding->module->key;
        $model = match ($known['customer_type'] ?? null) {
            'شركات', 'جهات حكومية' => 'b2b',
            'أفراد' => 'b2c',
            'أفراد وشركات' => 'mixed',
            default => $project->profile?->business_model,
        };
        $stage = match ($known['actual_stage'] ?? null) {
            'فكرة' => 'idea',
            'قبل الإطلاق' => 'launch',
            'أول عملاء', 'مبيعات غير مستقرة', 'نمو' => 'growth',
            'توسع' => 'scale',
            default => $project->stage,
        };

        return match (true) {
            $key === 'customer-b2c' && $model === 'b2b' => ['state' => 'not_applicable', 'reason' => 'المشروع يبيع للمؤسسات.'],
            $key === 'customer-b2b' && in_array($model, ['b2c', 'ecommerce'], true) => ['state' => 'not_applicable', 'reason' => 'المشروع يبيع للأفراد.'],
            $key === 'retention' && in_array($stage, ['idea', 'launch'], true) => ['state' => 'not_applicable', 'reason' => 'لا يوجد عملاء حاليون بعد.'],
            $key === 'seo-geo' && blank($project->profile?->website) => ['state' => 'deferred', 'reason' => 'لا يوجد موقع قابل للفحص.'],
            $key === 'risk-security' => ['state' => 'deferred', 'reason' => 'تحتاج الوحدة إلى تفويض ونطاق صريح.'],
            $binding->required => ['state' => 'core', 'reason' => 'وحدة أساسية في كل تشخيص.'],
            default => ['state' => 'supporting', 'reason' => 'تُفعّل عندما تغير الإجابات القرار.'],
        };
    }
}
