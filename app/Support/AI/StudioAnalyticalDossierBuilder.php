<?php

namespace App\Support\AI;

use App\Contracts\AiGatewayInterface;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\WorkspaceData\Models\WorkspaceData;
use App\Support\Dashboard\ContentLocaleCatalog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StudioAnalyticalDossierBuilder
{
    private const DOSSIER_KEY = 'studio.analysis_dossier';

    public function __construct(
        private readonly AiGatewayInterface $aiGateway,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function build(Workspace $workspace, ?Project $project, array $context): array
    {
        $fingerprint = sha1(json_encode($this->fingerprintPayload($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

        $cached = WorkspaceData::query()->where([
            'workspace_id' => $workspace->id,
            'project_id' => $project?->id,
            'key' => self::DOSSIER_KEY,
        ])->first();

        $payload = is_array($cached?->value_json) ? $cached->value_json : [];
        if (($payload['context_fingerprint'] ?? null) === $fingerprint) {
            return $payload;
        }

        $analysis = $this->analyze($context);
        $guideMarkdown = $this->guideMarkdown($analysis);

        $payload = [
            'context_fingerprint' => $fingerprint,
            'compiled_at' => now()->toDateTimeString(),
            'analysis' => $analysis,
            'guide_markdown' => $guideMarkdown,
        ];

        WorkspaceData::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'project_id' => $project?->id,
                'key' => self::DOSSIER_KEY,
            ],
            [
                'value_json' => $payload,
            ],
        );

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function analyze(array $context): array
    {
        $aiAnalysis = $this->analyzeWithAi($context);

        return $aiAnalysis ?? $this->fallbackAnalysis($context);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    private function analyzeWithAi(array $context): ?array
    {
        try {
            $response = $this->aiGateway->generateText(
                $this->analysisPrompt($context),
                implode("\n", [
                    'أنت محلل شخصيات وسلوك شراء ونبرة محتوى لوكالات التسويق.',
                    'مهمتك بناء ملف تحليلي دقيق يصلح كمرجع إلزامي للكتابة الإعلانية والبراند.',
                    'حلّل التفضيلات الضمنية والصريحة من الملاحظات والتعليقات والأدوات، ولا تكرر البيانات حرفياً دون تفسير.',
                    'أعد JSON فقط بدون أي نص إضافي.',
                ]),
            );
        } catch (\Throwable $exception) {
            Log::info('Studio analytical dossier AI build failed, falling back.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! is_string($response) || trim($response) === '') {
            return null;
        }

        $decoded = json_decode($this->cleanJson($response), true);
        if (! is_array($decoded)) {
            return null;
        }

        return $this->normalizeAiAnalysis($decoded, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function analysisPrompt(array $context): string
    {
        $notes = collect([
            ...($context['client_notes'] ?? []),
            ...collect($context['approval_notes'] ?? [])->pluck('note')->all(),
            ...collect($context['comment_notes'] ?? [])->pluck('body')->all(),
        ])->filter()->values()->all();

        $toolSignals = collect($context['tool_summaries'] ?? [])
            ->map(function (array $summary): string {
                return implode(' | ', array_filter([
                    $summary['tool_code'] ?? null,
                    $summary['headline'] ?? null,
                    $summary['text'] ?? null,
                    implode(' / ', $summary['bullets'] ?? []),
                ]));
            })
            ->filter()
            ->values()
            ->all();

        $runSignals = collect($context['tool_runs'] ?? [])
            ->map(function (array $run): string {
                return implode(' | ', array_filter([
                    $run['tool_code'] ?? null,
                    $run['headline'] ?? null,
                    implode(' / ', $run['insights'] ?? []),
                    implode(' / ', $run['next_actions'] ?? []),
                ]));
            })
            ->filter()
            ->values()
            ->all();

        $profile = $context['workspace_profile'] ?? [];
        $journey = $context['journey_snapshot'] ?? [];

        return implode("\n\n", array_filter([
            'حلّل هذه البيانات لتستنتج شخصية العميل، أسلوبه المفضل، حساسيته من الصياغات، وما يجب أن يُستخدم أو يُمنع في الكتابة.',
            'بيانات الملف الأساسية: '.implode(' | ', array_filter([
                'workspace: '.($context['workspace']['name'] ?? ''),
                'project: '.($context['project']['name'] ?? ''),
                'client: '.($context['client']['name'] ?? ''),
                'persona: '.($profile['persona'] ?? ''),
                'audience: '.($profile['audience'] ?? ''),
                'goal: '.($profile['primary_goal'] ?? ''),
                'country: '.($profile['country'] ?? ''),
                'content_locale: '.($profile['content_locale'] ?? ''),
                'current_stage: '.($journey['current_stage'] ?? ''),
                'current_step: '.($journey['current_step'] ?? ''),
            ])),
            $notes !== [] ? "الملاحظات البشرية الصريحة:\n- ".implode("\n- ", $notes) : null,
            $toolSignals !== [] ? "إشارات الأدوات السابقة:\n- ".implode("\n- ", $toolSignals) : null,
            $runSignals !== [] ? "إشارات تشغيل الأدوات:\n- ".implode("\n- ", $runSignals) : null,
            <<<'JSON'
أعد JSON بهذا الشكل فقط:
{
  "client_personality_summary": "فقرة تفسيرية تلخص طبيعة العميل وكيف يتخذ القرار",
  "voice_and_tone": {
    "dialect": "اللهجة الأنسب",
    "register": "رسمي / مهني / قريب / مباشر ...",
    "style": "النمط الكتابي الأنسب",
    "pace": "مختصر / متوسط / تفصيلي",
    "persuasion_style": "كيف يُقنع"
  },
  "decision_drivers": {
    "trust_builders": ["..."],
    "decision_triggers": ["..."],
    "objections_or_fears": ["..."],
    "aversion_triggers": ["..."]
  },
  "content_preferences": {
    "preferred_angles": ["..."],
    "preferred_patterns": ["..."],
    "avoided_patterns": ["..."]
  },
  "brand_dictionary": {
    "preferred_keywords": ["..."],
    "preferred_phrases": ["..."],
    "phrases_to_avoid": ["..."],
    "cta_patterns": ["..."]
  },
  "execution_rules": ["..."],
  "strategic_summary": "خلاصة تنفيذية تلزم من يكتب أي نص لاحقاً"
}
JSON,
        ]));
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function guideMarkdown(array $analysis): string
    {
        $voice = is_array($analysis['voice_and_tone'] ?? null) ? $analysis['voice_and_tone'] : [];
        $drivers = is_array($analysis['decision_drivers'] ?? null) ? $analysis['decision_drivers'] : [];
        $preferences = is_array($analysis['content_preferences'] ?? null) ? $analysis['content_preferences'] : [];
        $dictionary = is_array($analysis['brand_dictionary'] ?? null) ? $analysis['brand_dictionary'] : [];

        return implode("\n\n", array_filter([
            '## ملف عميلك باختصار',
            $this->paragraphSection('### من هو عميلك', $analysis['client_personality_summary'] ?? ''),
            $this->bulletSection('### كيف نكلّمه (اللهجة والأسلوب)', array_filter([
                $voice['dialect'] ?? null,
                $voice['register'] ?? null,
                $voice['style'] ?? null,
                $voice['pace'] ?? null,
                $voice['persuasion_style'] ?? null,
            ])),
            $this->bulletSection('### ما الذي يكسب ثقته ويدفعه للشراء', array_merge(
                $this->normalizeStringList($drivers['trust_builders'] ?? []),
                $this->normalizeStringList($drivers['decision_triggers'] ?? []),
            )),
            $this->bulletSection('### مخاوفه واعتراضاته وما نتجنّبه', array_merge(
                $this->normalizeStringList($drivers['objections_or_fears'] ?? []),
                $this->normalizeStringList($drivers['aversion_triggers'] ?? []),
                $this->normalizeStringList($preferences['avoided_patterns'] ?? []),
            )),
            $this->bulletSection('### الأفكار والزوايا التي تناسبه', array_merge(
                $this->normalizeStringList($preferences['preferred_angles'] ?? []),
                $this->normalizeStringList($preferences['preferred_patterns'] ?? []),
            )),
            $this->bulletSection('### الكلمات التي نستخدمها معه', array_merge(
                $this->prefixedList('كلمات مفضلة: ', $dictionary['preferred_keywords'] ?? []),
                $this->prefixedList('عبارات مفضلة: ', $dictionary['preferred_phrases'] ?? []),
                $this->prefixedList('تجنب: ', $dictionary['phrases_to_avoid'] ?? []),
                $this->prefixedList('جملة دعوة مناسبة: ', $dictionary['cta_patterns'] ?? []),
            )),
            $this->bulletSection('### قواعد مهمة عند الكتابة له', $this->normalizeStringList($analysis['execution_rules'] ?? [])),
            $this->paragraphSection('### الخلاصة', $analysis['strategic_summary'] ?? ''),
        ]));
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function normalizeAiAnalysis(array $decoded, array $context): array
    {
        $fallback = $this->fallbackAnalysis($context);

        return [
            'client_personality_summary' => $this->normalizeString($decoded['client_personality_summary'] ?? $fallback['client_personality_summary']),
            'voice_and_tone' => $this->normalizeVoiceAndTone($decoded['voice_and_tone'] ?? [], $fallback['voice_and_tone']),
            'decision_drivers' => $this->normalizeDecisionDrivers($decoded['decision_drivers'] ?? [], $fallback['decision_drivers']),
            'content_preferences' => $this->normalizeContentPreferences($decoded['content_preferences'] ?? [], $fallback['content_preferences']),
            'brand_dictionary' => $this->normalizeBrandDictionary($decoded['brand_dictionary'] ?? [], $fallback['brand_dictionary']),
            'execution_rules' => $this->normalizeStringList($decoded['execution_rules'] ?? $fallback['execution_rules']),
            'strategic_summary' => $this->normalizeString($decoded['strategic_summary'] ?? $fallback['strategic_summary']),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function fallbackAnalysis(array $context): array
    {
        $profile = $context['workspace_profile'] ?? [];
        $locale = (string) ($profile['content_locale'] ?? 'ar_modern_fusha');
        $localeLabel = ContentLocaleCatalog::label($locale);
        $localeInstruction = ContentLocaleCatalog::promptInstruction($locale);

        $noteCorpus = collect([
            ...($context['client_notes'] ?? []),
            ...collect($context['approval_notes'] ?? [])->pluck('note')->all(),
            ...collect($context['comment_notes'] ?? [])->pluck('body')->all(),
            ...collect($context['tool_summaries'] ?? [])->pluck('headline')->all(),
            ...collect($context['tool_summaries'] ?? [])->pluck('text')->all(),
            ...collect($context['tool_runs'] ?? [])->flatMap(fn (array $run): array => $run['insights'] ?? [])->all(),
        ])
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->values();

        $preferredTraits = $this->detectTraits($noteCorpus->all(), [
            'مباشر' => 'أسلوب مباشر',
            'واضح' => 'صياغة واضحة',
            'مهني' => 'نبرة مهنية',
            'رسمي' => 'سجل لغوي رسمي',
            'مختصر' => 'إيقاع مختصر',
            'تفصيلي' => 'تفصيل محسوب عند التوضيح',
            'هادئ' => 'نبرة هادئة',
            'بسيط' => 'لغة بسيطة غير معقدة',
            'فاخر' => 'أسلوب راقٍ',
            'عملي' => 'طرح عملي قابل للتنفيذ',
        ]);

        $avoidPatterns = $this->extractNegativeSignals($noteCorpus->all());
        $trustBuilders = $this->detectTraits($noteCorpus->all(), [
            'ثقة' => 'بناء الثقة قبل البيع',
            'ضمان' => 'وجود ضمان أو تخفيف مخاطرة',
            'دليل' => 'إثبات أو برهان واضح',
            'نتيجة' => 'التركيز على النتيجة',
            'قياس' => 'وجود معيار قياس',
            'واضح' => 'وضوح الفائدة منذ البداية',
            'مباشر' => 'شرح مباشر بلا التفاف',
        ]);

        $preferredKeywords = $this->topKeywords($noteCorpus->all(), [
            'العرض',
            'النتيجة',
            'الثقة',
            'الوضوح',
            'الحجوزات',
            'الرسائل',
            'العميل',
            'المشروع',
        ]);

        $preferredAngles = array_values(array_unique(array_filter([
            str_contains($noteCorpus->implode(' '), 'نتيجة') ? 'البدء بالنتيجة قبل التفاصيل' : null,
            str_contains($noteCorpus->implode(' '), 'ثقة') || str_contains($noteCorpus->implode(' '), 'ضمان') ? 'تعزيز الثقة بأدلة أو ضمانات' : null,
            str_contains($noteCorpus->implode(' '), 'مباشر') || str_contains($noteCorpus->implode(' '), 'واضح') ? 'طرح مباشر يوضح الفائدة سريعاً' : null,
            'ربط كل رسالة بسبب شراء واضح لا بزخرفة لغوية',
        ])));

        $summary = implode(' ', array_filter([
            'العميل يبدو ميالاً إلى خطاب '.$localeLabel.' مع تركيز على '.$this->implodeOrDefault($preferredTraits, 'الوضوح والانضباط في الطرح').'.',
            $avoidPatterns !== [] ? 'يرفض خصوصاً: '.$this->implodeOrDefault($avoidPatterns, '').'.' : null,
            $trustBuilders !== [] ? 'أقوى ما يدفعه للقبول هو: '.$this->implodeOrDefault($trustBuilders, '').'.' : null,
        ]));

        return [
            'client_personality_summary' => $summary !== '' ? $summary : 'العميل يحتاج خطاباً منضبطاً يوازن بين الوضوح والثقة وقابلية التطبيق.',
            'voice_and_tone' => [
                'dialect' => $localeLabel,
                'register' => $this->implodeOrDefault($preferredTraits, 'مهني واضح'),
                'style' => $localeInstruction,
                'pace' => in_array('إيقاع مختصر', $preferredTraits, true) ? 'مختصر محسوب' : 'متوسط يميل إلى الوضوح',
                'persuasion_style' => $trustBuilders !== [] ? 'الإقناع عبر الدليل والنتيجة وتخفيف المخاطرة' : 'الإقناع عبر وضوح الفائدة وربطها بالنتيجة',
            ],
            'decision_drivers' => [
                'trust_builders' => $trustBuilders !== [] ? $trustBuilders : ['وضوح العرض', 'إثبات النتيجة', 'تخفيف المخاطرة'],
                'decision_triggers' => [
                    'رسالة تبدأ بالفائدة العملية',
                    'وعد قابل للقياس أو التحقق',
                ],
                'objections_or_fears' => $avoidPatterns !== [] ? $avoidPatterns : ['الوعود العامة أو المبالغ فيها', 'اللغة الفضفاضة غير المرتبطة بنتيجة'],
                'aversion_triggers' => $avoidPatterns !== [] ? $avoidPatterns : ['التضخيم الدعائي', 'الحشو غير التنفيذي'],
            ],
            'content_preferences' => [
                'preferred_angles' => $preferredAngles,
                'preferred_patterns' => array_values(array_unique(array_filter([
                    in_array('صياغة واضحة', $preferredTraits, true) ? 'عناوين مباشرة تشرح المقصود بسرعة' : null,
                    in_array('أسلوب مباشر', $preferredTraits, true) ? 'جمل قصيرة تحسم الفكرة بلا لف' : null,
                    'كل فقرة يجب أن تنتهي بفائدة أو خطوة تالية',
                ]))),
                'avoided_patterns' => $avoidPatterns !== [] ? $avoidPatterns : ['الجمل العامة من نوع سوف نعمل وسوف نحسن', 'الزخرفة اللفظية بلا معنى بيعي'],
            ],
            'brand_dictionary' => [
                'preferred_keywords' => $preferredKeywords !== [] ? $preferredKeywords : ['وضوح', 'نتيجة', 'ثقة', 'قرار', 'قياس'],
                'preferred_phrases' => [
                    'رسالة واضحة تقود إلى خطوة عملية',
                    'نتيجة يمكن تتبعها وقياسها',
                ],
                'phrases_to_avoid' => $avoidPatterns !== [] ? $avoidPatterns : ['الأفضل على الإطلاق', 'حلول مبتكرة بلا توضيح', 'وعود غير قابلة للإثبات'],
                'cta_patterns' => [
                    'ابدأ بخطوة واضحة ومباشرة',
                    'اطلب مراجعة عملية أو اتصالاً محدداً بزمن',
                ],
            ],
            'execution_rules' => array_values(array_unique(array_filter([
                'التزم باللهجة: '.$localeLabel,
                'ابدأ كل نص بالنتيجة أو الفائدة الأوضح للعميل.',
                'اربط أي وعد بدليل أو مثال أو تخفيف مخاطرة.',
                'تجنب الحشو والصيغ المستقبلية العامة.',
                $avoidPatterns !== [] ? 'لا تستخدم تعبيرات أو أنماطاً تذكّر بـ: '.$this->implodeOrDefault($avoidPatterns, '') : null,
            ]))),
            'strategic_summary' => 'هذا العميل لا يحتاج محتوى منمقاً بقدر ما يحتاج خطاباً واضحاً، منضبطاً، ومبنياً على الثقة والنتيجة. أي نص لاحق يجب أن يخدم القرار لا الإبهار.',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function fingerprintPayload(array $context): array
    {
        return Arr::only($context, [
            'workspace',
            'project',
            'client',
            'workspace_profile',
            'journey_snapshot',
            'readiness_snapshot',
            'tool_summaries',
            'tool_contexts',
            'tool_runs',
            'client_notes',
            'approval_notes',
            'comment_notes',
        ]);
    }

    private function cleanJson(string $text): string
    {
        $cleaned = trim($text);
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned) ?? $cleaned;

        return preg_replace('/\s*```$/', '', $cleaned) ?? $cleaned;
    }

    /**
     * @param  mixed  $value
     */
    private function normalizeString(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    /**
     * @param  mixed  $value
     * @param  array<string, string>  $fallback
     * @return array<string, string>
     */
    private function normalizeVoiceAndTone(mixed $value, array $fallback): array
    {
        if (! is_array($value)) {
            return $fallback;
        }

        return [
            'dialect' => $this->normalizeString($value['dialect'] ?? $fallback['dialect']),
            'register' => $this->normalizeString($value['register'] ?? $fallback['register']),
            'style' => $this->normalizeString($value['style'] ?? $fallback['style']),
            'pace' => $this->normalizeString($value['pace'] ?? $fallback['pace']),
            'persuasion_style' => $this->normalizeString($value['persuasion_style'] ?? $fallback['persuasion_style']),
        ];
    }

    /**
     * @param  mixed  $value
     * @param  array<string, array<int, string>>  $fallback
     * @return array<string, array<int, string>>
     */
    private function normalizeDecisionDrivers(mixed $value, array $fallback): array
    {
        if (! is_array($value)) {
            return $fallback;
        }

        return [
            'trust_builders' => $this->normalizeStringList($value['trust_builders'] ?? $fallback['trust_builders']),
            'decision_triggers' => $this->normalizeStringList($value['decision_triggers'] ?? $fallback['decision_triggers']),
            'objections_or_fears' => $this->normalizeStringList($value['objections_or_fears'] ?? $fallback['objections_or_fears']),
            'aversion_triggers' => $this->normalizeStringList($value['aversion_triggers'] ?? $fallback['aversion_triggers']),
        ];
    }

    /**
     * @param  mixed  $value
     * @param  array<string, array<int, string>>  $fallback
     * @return array<string, array<int, string>>
     */
    private function normalizeContentPreferences(mixed $value, array $fallback): array
    {
        if (! is_array($value)) {
            return $fallback;
        }

        return [
            'preferred_angles' => $this->normalizeStringList($value['preferred_angles'] ?? $fallback['preferred_angles']),
            'preferred_patterns' => $this->normalizeStringList($value['preferred_patterns'] ?? $fallback['preferred_patterns']),
            'avoided_patterns' => $this->normalizeStringList($value['avoided_patterns'] ?? $fallback['avoided_patterns']),
        ];
    }

    /**
     * @param  mixed  $value
     * @param  array<string, array<int, string>>  $fallback
     * @return array<string, array<int, string>>
     */
    private function normalizeBrandDictionary(mixed $value, array $fallback): array
    {
        if (! is_array($value)) {
            return $fallback;
        }

        return [
            'preferred_keywords' => $this->normalizeStringList($value['preferred_keywords'] ?? $fallback['preferred_keywords']),
            'preferred_phrases' => $this->normalizeStringList($value['preferred_phrases'] ?? $fallback['preferred_phrases']),
            'phrases_to_avoid' => $this->normalizeStringList($value['phrases_to_avoid'] ?? $fallback['phrases_to_avoid']),
            'cta_patterns' => $this->normalizeStringList($value['cta_patterns'] ?? $fallback['cta_patterns']),
        ];
    }

    /**
     * @param  mixed  $values
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return collect($values)
            ->map(fn (mixed $value): string => is_string($value) ? trim($value) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $lines
     * @param  array<string, string>  $map
     * @return array<int, string>
     */
    private function detectTraits(array $lines, array $map): array
    {
        $joined = implode(' ', $lines);

        return collect($map)
            ->filter(fn (string $label, string $needle): bool => mb_stripos($joined, $needle) !== false)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $lines
     * @return array<int, string>
     */
    private function extractNegativeSignals(array $lines): array
    {
        $signals = [];

        foreach ($lines as $line) {
            if (! preg_match_all('/لا\s+(?:يريد|تحب|يحب|يفضل|نريد|تريد)\s+([^\.!\n،]+)/u', $line, $matches)) {
                continue;
            }

            foreach ($matches[1] as $match) {
                $cleaned = trim($match);
                if ($cleaned !== '') {
                    $signals[] = 'تجنب '.$cleaned;
                }
            }
        }

        return collect($signals)->unique()->values()->all();
    }

    /**
     * @param  array<int, string>  $lines
     * @param  array<int, string>  $seed
     * @return array<int, string>
     */
    private function topKeywords(array $lines, array $seed = []): array
    {
        $text = mb_strtolower(implode(' ', $lines));
        $tokens = preg_split('/[\s\p{P}\p{S}]+/u', $text) ?: [];
        $stopWords = [
            'هذا', 'هذه', 'ذلك', 'التي', 'الذي', 'على', 'من', 'إلى', 'في', 'عن', 'مع', 'ثم', 'كما',
            'لكن', 'بعد', 'قبل', 'حتى', 'عند', 'عبر', 'حول', 'غير', 'جداً', 'جدا', 'لان', 'لأن',
            'يجب', 'يكون', 'يمكن', 'هناك', 'كانت', 'يحتاج', 'تحتاج', 'العميل', 'المشروع', 'الرسالة',
        ];

        $counts = [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '' || mb_strlen($token) < 4 || in_array($token, $stopWords, true)) {
                continue;
            }

            $counts[$token] = ($counts[$token] ?? 0) + 1;
        }

        arsort($counts);

        return collect(array_keys($counts))
            ->merge($seed)
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    private function prefixedList(string $prefix, array $values): array
    {
        return collect($this->normalizeStringList($values))
            ->map(fn (string $value): string => $prefix.$value)
            ->all();
    }

    /**
     * @param  array<int, string>  $items
     */
    private function bulletSection(string $title, array $items): ?string
    {
        $items = $this->normalizeStringList($items);
        if ($items === []) {
            return null;
        }

        return $title."\n".collect($items)->map(fn (string $item): string => '- '.$item)->implode("\n");
    }

    private function paragraphSection(string $title, string $text): ?string
    {
        $text = trim($text);

        return $text !== '' ? $title."\n".$text : null;
    }

    /**
     * @param  array<int, string>  $values
     */
    private function implodeOrDefault(array $values, string $default): string
    {
        $values = $this->normalizeStringList($values);

        return $values !== [] ? implode('، ', $values) : $default;
    }
}
