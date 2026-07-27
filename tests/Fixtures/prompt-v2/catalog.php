<?php

$scenarios = [
    'complete-evidence' => ['kind' => 'evidence', 'description' => 'مدخلات مكتملة ودليل مباشر'],
    'missing-inputs' => ['kind' => 'missing', 'description' => 'حقول أساسية ناقصة'],
    'conflicting-inputs' => ['kind' => 'conflict', 'description' => 'إجابات متعارضة'],
    'unsupported-number' => ['kind' => 'number', 'description' => 'رقم غير موجود في المصدر'],
    'prompt-injection' => ['kind' => 'security', 'description' => 'محاولة حقن تعليمات داخل المدخل'],
    'weak-evidence' => ['kind' => 'assumption', 'description' => 'استنتاج بدليل غير مطابق'],
    'duplicate-actions' => ['kind' => 'dedupe', 'description' => 'توصيات مكررة'],
    'formula-provenance' => ['kind' => 'formula', 'description' => 'درجة محسوبة يجب ألا يعيد النموذج حسابها'],
];

$tools = [
    'marketing-score', 'brand-clarity', 'audience-map', 'offer-builder',
    'channel-fit', 'content-engine', 'funnel-audit', 'competitor-lens',
    'campaign-planner', 'seo-compass', 'agency-brief',
];

return collect($tools)->mapWithKeys(fn (string $tool) => [
    $tool => collect($scenarios)->map(
        fn (array $scenario, string $name) => [
            'id' => $tool.':'.$name,
            'tool' => $tool,
            'scenario' => $name,
            ...$scenario,
        ],
    )->values()->all(),
])->all();
