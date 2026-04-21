<?php

namespace App\Support\AI;

use App\Domain\AI\Models\AITemplate;

final class StudioOutputQualityGuard
{
    public function __construct(
        private readonly ?StudioTemplateOutputValidator $validator = null,
        private readonly ?StudioTemplateContractRegistry $registry = null,
    ) {}

    /**
     * @return list<string>
     */
    public function issuesFor(string $output, ?array $contract = null, ?AITemplate $template = null): array
    {
        return $this->validator()->issuesFor($output, $contract, $template);
    }

    public function systemPrompt(AITemplate $template): string
    {
        $baseRole = is_string($template->system_role) && trim($template->system_role) !== ''
            ? trim($template->system_role)
            : 'أنت خبير تسويق وكتابة إعلانية. تنتج نصوصاً جاهزة للنشر أو الإرسال أو الإلقاء، لا أوصافاً عامة لما يجب فعله.';
        $definition = $this->registry()->definitionFor($template);
        $definitionOfDone = collect($definition['definition_of_done'] ?? [])
            ->map(fn (string $line): string => '- '.$line)
            ->implode("\n");
        $strategicRequirements = collect($definition['strategic_requirements'] ?? [])
            ->map(fn (string $line): string => '- '.$line)
            ->implode("\n");
        $genericRedFlags = collect($definition['generic_red_flags'] ?? [])
            ->map(fn (string $line): string => '- '.$line)
            ->implode("\n");
        $forbidden = collect($definition['forbidden_fragments'] ?? [])
            ->map(fn (string $line): string => '- '.$line)
            ->implode("\n");

        return implode("\n\n", [
            $baseRole,
            '[سياسة جودة ملزمة]',
            'أنت لا تكتب مسودات عامة ولا تقارير مدرسية. اكتب ملفات تسليم وكالة قابلة للتنفيذ.',
            'ممنوع استخدام لغة مستقبلية أو فضفاضة مثل: سوف نفعل، سيتم العمل، ينبغي تحسين، يمكن التركيز.',
            'إذا طلب القالب إعلاناً أو رسالة أو بريداً أو سكربتاً أو نص صفحة، فاكتب النص الكامل الجاهز للنسخ.',
            'إذا طلب القالب قسماً استراتيجياً، فاكتب قرارات واضحة وحدوداً ومعايير قياس ومسؤوليات تنفيذ، لا كلاماً إنشائياً.',
            'استخدم عناوين Markdown بصيغة ## عنوان القسم.',
            'إذا كانت المدخلات ناقصة، اذكر ذلك في قسم مستقل بعنوان ## افتراضات أو بيانات ناقصة، ولا تملأ الفراغ بحشو.',
            $definitionOfDone !== '' ? "[تعريف الإنجاز لهذا القالب]\n".$definitionOfDone : null,
            $strategicRequirements !== '' ? "[متطلبات التفكير الاستراتيجي]\n".$strategicRequirements : null,
            $genericRedFlags !== '' ? "[أمثلة مرفوضة أو Commodity يجب تجنبها]\n".$genericRedFlags : null,
            $forbidden !== '' ? "[ممنوعات خاصة بهذا القالب]\n".$forbidden : null,
        ]);
    }

    /**
     * @param  list<string>  $issues
     */
    public function revisionPrompt(
        string $originalPrompt,
        string $originalOutput,
        array $issues,
        AITemplate $template,
    ): string {
        $contractSections = collect($template->output_contract_json['sections'] ?? [])
            ->filter(fn (mixed $title): bool => is_string($title) && trim($title) !== '')
            ->map(fn (mixed $title): string => '- '.trim((string) $title))
            ->implode("\n");
        $definition = $this->registry()->definitionFor($template);
        $definitionOfDone = collect($definition['definition_of_done'] ?? [])
            ->map(fn (string $line): string => '- '.$line)
            ->implode("\n");
        $strategicRequirements = collect($definition['strategic_requirements'] ?? [])
            ->map(fn (string $line): string => '- '.$line)
            ->implode("\n");
        $genericRedFlags = collect($definition['generic_red_flags'] ?? [])
            ->map(fn (string $line): string => '- '.$line)
            ->implode("\n");
        $forbidden = collect($definition['forbidden_fragments'] ?? [])
            ->map(fn (string $line): string => '- '.$line)
            ->implode("\n");

        return implode("\n\n", array_filter([
            'المسودة التالية مرفوضة من ناحية الجودة. أعد كتابة الملف من الصفر، ولا تكتفِ بتحسينات طفيفة.',
            "أسباب الرفض:\n- ".implode("\n- ", $issues),
            $contractSections !== '' ? "الأقسام الإلزامية التي يجب تغطيتها:\n".$contractSections : null,
            $definitionOfDone !== '' ? "تعريف الإنجاز الذي يجب أن يتحقق:\n".$definitionOfDone : null,
            $strategicRequirements !== '' ? "متطلبات التفكير الاستراتيجي التي تجاهلتها المسودة:\n".$strategicRequirements : null,
            $genericRedFlags !== '' ? "أمثلة Commodity أو صياغات مرفوضة يجب عدم تكرارها:\n".$genericRedFlags : null,
            $forbidden !== '' ? "أشياء ممنوعة تماماً في هذا القالب:\n".$forbidden : null,
            'قواعد الإعادة: اكتب ملفاً تنفيذياً فعلياً، زد الخصوصية، زد النصوص الجاهزة، وقلل أي جملة تفسيرية أو مستقبلية.',
            '[المهمة الأصلية]',
            $originalPrompt,
            '[المسودة المرفوضة]',
            $originalOutput,
        ]));
    }

    private function validator(): StudioTemplateOutputValidator
    {
        return $this->validator ?? app(StudioTemplateOutputValidator::class);
    }

    private function registry(): StudioTemplateContractRegistry
    {
        return $this->registry ?? app(StudioTemplateContractRegistry::class);
    }
}
