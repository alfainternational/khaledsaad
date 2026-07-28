<?php

namespace App\Modules\AiReadiness;

use App\Models\GeoPack;
use App\Models\Project;
use App\Services\Growth\GrowthSchemas;
use App\Support\AI\AIRequest;
use App\Support\AI\StructuredRunner;
use Throwable;

/**
 * مولّد حزمة الظهور للآلات (GEO/AEO).
 *
 * الفكرة: عملاء اليوم يسألون ChatGPT وPerplexity قبل أن يبحثوا في جوجل.
 * العلامة التي لا تملك محتوى قابلًا للقراءة الآلية — حقائق منظمة، أسئلة
 * وأجوبة، JSON-LD — لا تظهر في تلك الإجابات أصلًا.
 *
 * الصياغة بالنموذج، لكن الهيكل (JSON-LD وllms.txt) يُبنى حتميًا من حقائق
 * أدخلها المستخدم بنفسه: لا نترك للنموذج فرصة اختراع حقيقة عن المشروع.
 */
class GeoPackGenerator
{
    private const REQUIRED_FIELDS = [
        'description' => 'وصف المشروع',
        'value_proposition' => 'القيمة المميزة',
        'geography' => 'النطاق الجغرافي',
        'business_model' => 'نموذج العمل',
    ];

    public function __construct(private readonly StructuredRunner $runner) {}

    /**
     * بوابة الجاهزية: الحزمة تُبنى من الملف، فالملف الناقص ينتج حزمة كاذبة.
     *
     * @return array<int, string> أسماء الحقول الناقصة بلغة المستخدم.
     */
    public function missingFields(Project $project): array
    {
        $profile = $project->profile;
        $missing = [];

        foreach (self::REQUIRED_FIELDS as $field => $label) {
            if (trim((string) $profile?->{$field}) === '') {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    public function generate(Project $project): GeoPack
    {
        $facts = $this->facts($project);

        [$composed, $source] = $this->compose($facts);

        return GeoPack::updateOrCreate(
            ['project_id' => $project->id],
            [
                'facts' => $facts,
                'faq' => $composed['faq'],
                'credibility' => $composed['credibility_signals'],
                'jsonld' => $this->jsonld($facts, $composed),
                'llms_txt' => $this->llmsTxt($facts, $composed),
                'source' => $source,
                'generated_at' => now(),
            ],
        );
    }

    /**
     * الحقائق القانونية: من ملف المشروع فقط — ما أدخله المستخدم بنفسه.
     *
     * @return array<string, mixed>
     */
    private function facts(Project $project): array
    {
        $project->loadMissing(['profile', 'audiences', 'competitors']);
        $profile = $project->profile;

        return [
            'name' => $project->name,
            'industry' => $project->industry,
            'stage' => $project->stage,
            'business_model' => $profile?->business_model,
            'description' => $profile?->description,
            'geography' => $profile?->geography,
            'website' => $profile?->website,
            'value_proposition' => $profile?->value_proposition,
            'channels' => $profile?->channels ?? [],
            'audiences' => $project->audiences->pluck('name')->all(),
        ];
    }

    /**
     * الصياغة: النموذج أولًا، والأرضية الحتمية إن تعذّر — الحزمة تصدر دائمًا.
     *
     * @param  array<string, mixed>  $facts
     * @return array{0: array{summary: string, faq: array<int, array{question: string, answer: string}>, credibility_signals: array<int, string>}, 1: string}
     */
    private function compose(array $facts): array
    {
        try {
            $payload = $this->runner->run(AIRequest::json(
                messages: [
                    ['role' => 'system', 'content' => implode("\n", [
                        'أنت تجهّز محتوى علامة تجارية ليُقرأ ويُقتبس من مساعدات الذكاء الاصطناعي (ChatGPT وPerplexity وأمثالها).',
                        'القواعد:',
                        '1. أعد كائن JSON واحدًا فقط دون أي نص خارجه.',
                        '2. استخدم الحقائق المعطاة فقط — لا تخترع أرقامًا أو عملاء أو إنجازات.',
                        '3. اكتب أسئلة يسألها عميل حقيقي لمساعد ذكاء اصطناعي عن هذا النوع من الخدمات، وأجوبة مباشرة تصلح للاقتباس الحرفي.',
                        '4. إشارات المصداقية: صياغات محايدة قابلة للتحقق مما ورد في الحقائق، لا مبالغات تسويقية.',
                    ])],
                    ['role' => 'user', 'content' => "حقائق المشروع:\n".json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)],
                ],
                schema: GrowthSchemas::geoPack(),
                tier: 'standard',
                stage: 'geo_pack',
                salvage: true,
            ));

            return [$payload, 'ai'];
        } catch (Throwable) {
            return [$this->fallback($facts), 'rules'];
        }
    }

    /**
     * الأرضية الحتمية: أسئلة وأجوبة قالبية من الحقائق نفسها.
     *
     * @param  array<string, mixed>  $facts
     * @return array{summary: string, faq: array<int, array{question: string, answer: string}>, credibility_signals: array<int, string>}
     */
    private function fallback(array $facts): array
    {
        $name = $facts['name'];
        $faq = [
            [
                'question' => "ما هو {$name}؟",
                'answer' => trim(($facts['description'] ?? '').' '.($facts['value_proposition'] ?? '')) ?: "{$name} مشروع في مجال {$facts['industry']}.",
            ],
            [
                'question' => "أين يقدم {$name} خدماته؟",
                'answer' => $facts['geography'] ?: 'يخدم عملاءه حيث يتواجدون.',
            ],
            [
                'question' => "ما الذي يميز {$name}؟",
                'answer' => $facts['value_proposition'] ?: ($facts['description'] ?? "يركز {$name} على خدمة عملائه في مجال {$facts['industry']}."),
            ],
            [
                'question' => "كيف أتواصل مع {$name}؟",
                'answer' => $facts['website']
                    ? "عبر موقعه الرسمي: {$facts['website']}."
                    : 'عبر قنواته الرسمية المعلنة.',
            ],
            [
                'question' => "لمن يناسب {$name}؟",
                'answer' => $facts['audiences'] !== []
                    ? 'يخدم بالأساس: '.implode('، ', $facts['audiences']).'.'
                    : "كل من يحتاج خدمات في مجال {$facts['industry']}.",
            ],
        ];

        $signals = array_values(array_filter([
            $facts['website'] ? "موقع رسمي فعّال: {$facts['website']}" : null,
            $facts['geography'] ? "نطاق خدمة معلن: {$facts['geography']}" : null,
            $facts['business_model'] ? "نموذج عمل واضح: {$facts['business_model']}" : null,
            "تخصص معلن في مجال {$facts['industry']}",
        ]));

        return [
            'summary' => $faq[0]['answer'],
            'faq' => $faq,
            'credibility_signals' => $signals,
        ];
    }

    /**
     * JSON-LD يُبنى حتميًا من الحقائق: Organization + FAQPage.
     *
     * @param  array<string, mixed>  $facts
     * @param  array{summary: string, faq: array<int, array{question: string, answer: string}>}  $composed
     * @return array<string, mixed>
     */
    private function jsonld(array $facts, array $composed): array
    {
        $organization = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $facts['name'],
            'description' => $composed['summary'],
            'url' => $facts['website'] ?: null,
            'areaServed' => $facts['geography'] ?: null,
            'knowsAbout' => $facts['industry'] ?: null,
        ]);

        $faqPage = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn (array $entry) => [
                '@type' => 'Question',
                'name' => $entry['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $entry['answer']],
            ], $composed['faq']),
        ];

        return ['organization' => $organization, 'faq_page' => $faqPage];
    }

    /**
     * ملف llms.txt: بطاقة تعريف نصية تضعها العلامة في جذر موقعها
     * لتقرأها النماذج مباشرة.
     *
     * @param  array<string, mixed>  $facts
     * @param  array{summary: string, faq: array<int, array{question: string, answer: string}>, credibility_signals: array<int, string>}  $composed
     */
    private function llmsTxt(array $facts, array $composed): string
    {
        $lines = [
            "# {$facts['name']}",
            '',
            "> {$composed['summary']}",
            '',
            '## حقائق أساسية',
        ];

        foreach ([
            'المجال' => $facts['industry'],
            'نموذج العمل' => $facts['business_model'],
            'النطاق الجغرافي' => $facts['geography'],
            'الموقع' => $facts['website'],
        ] as $label => $value) {
            if (! empty($value)) {
                $lines[] = "- {$label}: {$value}";
            }
        }

        $lines[] = '';
        $lines[] = '## أسئلة شائعة';

        foreach ($composed['faq'] as $entry) {
            $lines[] = '';
            $lines[] = "### {$entry['question']}";
            $lines[] = $entry['answer'];
        }

        if ($composed['credibility_signals'] !== []) {
            $lines[] = '';
            $lines[] = '## إشارات موثوقية';

            foreach ($composed['credibility_signals'] as $signal) {
                $lines[] = "- {$signal}";
            }
        }

        return implode("\n", $lines);
    }
}
