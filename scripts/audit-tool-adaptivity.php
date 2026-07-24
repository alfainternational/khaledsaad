<?php

use Illuminate\Contracts\Console\Kernel;

/*
 * مدقّق تكيّف الأدوات: يحاكي سياقات مشاريع مختلفة على تعريفات الأدوات
 * ويطبع كم سؤالًا يراه كل نوع مشروع في كل خطوة.
 *
 * الغرض منه حراسة قاعدتين لا يكشفهما الاختبار الوحدوي بسهولة:
 * 1) ألا يبقى نوع مشروع بخطوة فارغة أو بأسئلة أقل من أن تكفي للقياس.
 * 2) ألا توجد قاعدة تقييم لحقل غير موجود (فتسقط من الميزان صامتة).
 *
 * التشغيل: php scripts/audit-tool-adaptivity.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/**
 * نفس منطق ToolField::isVisible بلا قاعدة بيانات.
 *
 * @param  array<string, mixed>|null  $conditions
 * @param  array<string, mixed>  $context
 */
function visible(?array $conditions, array $context): bool
{
    foreach ($conditions ?? [] as $key => $expected) {
        $actual = $context[$key] ?? null;
        $allowed = is_array($expected) ? $expected : [$expected];
        $excluded = [];
        $required = [];

        foreach ($allowed as $value) {
            if (is_string($value) && str_starts_with($value, '!')) {
                $excluded[] = substr($value, 1);

                continue;
            }

            $required[] = $value;
        }

        if (in_array($actual, $excluded, true)) {
            return false;
        }

        if ($required !== [] && ! in_array($actual, $required, true)) {
            return false;
        }
    }

    return true;
}

$profiles = [
    'فكرة (بلا نوع)' => ['project.stage' => 'idea', 'project.maturity' => 'early', 'project.has_website' => 'no', 'project.budget_band' => 'none', 'project.sector' => 'general'],
    'إطلاق خدمات' => ['project.stage' => 'launch', 'project.maturity' => 'early', 'project.business_model' => 'services', 'project.has_website' => 'no', 'project.budget_band' => 'small', 'project.sector' => 'services'],
    'متجر شغّال' => ['project.stage' => 'growth', 'project.maturity' => 'operating', 'project.business_model' => 'b2c', 'project.has_website' => 'yes', 'project.budget_band' => 'medium', 'project.sector' => 'ecommerce'],
    'خدمات B2B' => ['project.stage' => 'growth', 'project.maturity' => 'operating', 'project.business_model' => 'b2b', 'project.has_website' => 'yes', 'project.budget_band' => 'medium', 'project.sector' => 'services'],
    'اشتراك SaaS' => ['project.stage' => 'scale', 'project.maturity' => 'operating', 'project.business_model' => 'saas', 'project.has_website' => 'yes', 'project.budget_band' => 'large', 'project.sector' => 'saas'],
    'نشاط محلي' => ['project.stage' => 'growth', 'project.maturity' => 'operating', 'project.business_model' => 'b2c', 'project.has_website' => 'no', 'project.budget_band' => 'small', 'project.sector' => 'local'],
    // الضيف يحصل على السياق المحايد لا على الفراغ — مطابق لـ
    // ProjectContextResolver::neutral(): مشروع شغّال بلا نوع معلن.
    'ضيف (محايد)' => ['project.stage' => 'growth', 'project.maturity' => 'operating', 'project.has_website' => 'no', 'project.budget_band' => 'unknown', 'project.sector' => 'general'],
];

$problems = 0;

foreach (glob(__DIR__.'/../database/data/tools/*.php') as $path) {
    $tool = require $path;
    $fields = collect($tool['fields']);
    $ruleFields = collect($tool['version']['scoring_rules']['rules'] ?? [])->pluck('field');

    echo PHP_EOL.'== '.$tool['key'].' ('.$fields->count().' حقلًا) =='.PHP_EOL;

    $orphans = $ruleFields->diff($fields->pluck('key'));

    if ($orphans->isNotEmpty()) {
        echo '  ✗ قواعد تقييم بلا حقول: '.$orphans->implode('، ').PHP_EOL;
        $problems++;
    }

    foreach ($profiles as $label => $context) {
        $seen = $fields->filter(fn (array $field) => visible($field['visible_when'] ?? null, $context));
        $required = $seen->filter(fn (array $field) => ($field['required'] ?? true) === true)->count();
        $steps = $seen->groupBy('step')->keys()->sort()->values()->implode(',');
        $scored = $ruleFields->intersect($seen->pluck('key'))->count();

        echo sprintf('  %-18s أسئلة:%2d (إلزامي %2d) · خطوات:%-8s · قواعد فعّالة:%d', $label, $seen->count(), $required, $steps, $scored).PHP_EOL;

        if ($seen->isEmpty()) {
            echo '     ✗ لا أسئلة إطلاقًا لهذا النوع.'.PHP_EOL;
            $problems++;
        } elseif ($scored < 3 && $ruleFields->isNotEmpty()) {
            echo '     ✗ قواعد التقييم الفعّالة أقل من ثلاث — الدرجة تفقد معناها.'.PHP_EOL;
            $problems++;
        }
    }
}

echo PHP_EOL.($problems === 0 ? '✓ كل الأدوات متكيّفة سليمة.' : "✗ {$problems} مشكلة تحتاج معالجة.").PHP_EOL;

exit($problems === 0 ? 0 : 1);
