<?php

namespace App\Modules\Reporting\Templates;

use App\Models\Objective;
use App\Models\RecommendationTemplate;
use App\Models\TemplateGap;
use App\Modules\Intake\Fields\FieldDirectory;
use Illuminate\Support\Arr;

/**
 * يحوّل هدفًا إلى ورقة عمل جاهزة بلغة صاحبها.
 *
 * ─── اللغة ───
 *
 * كان التوقيع `string $locale = 'ar'` والمستدعي لا يمرّر شيئًا، وكل القوالب
 * مبذورة بالعربية. فأي تشغيل بغير العربية لم يكن يجد قالبًا **أبدًا**، فتصل
 * كل توصياته موسومة «غير جاهزة للتنفيذ». عطلٌ لا يظهر في السجل ولا في
 * اختبار، ويظهر في الشاشة التي لا يفتحها من بنى النظام.
 *
 * الآن: اللغة تُمرَّر، والصفّ يُطلب بها، وإن لم يوجد يُرجَع إلى لغة المصدر.
 * والنصّ المخزَّن هو المفتاح العربي، فيمرّ على `__()` قبل الربط — قبل، لا
 * بعد: النائب المربوط يحمل قيمة صاحبه، فترجمة النصّ بعد الربط تبحث عن مفتاح
 * لا وجود له في الكتالوج.
 *
 * ─── الفجوة ───
 *
 * الربط الذي لا يجد جوابًا كان يُسقط الورقة كلها أو يترك `{key}` خامًا في
 * وجه القارئ. صار يترك علامةً معلنة ويسجّل مفتاح الحقل في `gaps`، فيقدر
 * صاحب النشاط أن يفتح السؤال ويجيب. الفجوة تُعلن ولا تُخفى، والإعلان بلا
 * باب ليس إعلانًا بل اعتذار.
 */
class TemplateResolver
{
    public function __construct(private readonly FieldDirectory $fields) {}

    /** @param array<string, mixed> $context */
    public function forObjective(string $objectiveId, array $context, ?string $locale = null): ?ResolvedTemplate
    {
        $objective = Objective::query()
            ->where('slug', $objectiveId)
            ->where('active', true)
            ->first();

        if ($objective === null) {
            return null;
        }

        $locale = $locale ?: app()->getLocale();
        $template = $this->template($objective, $locale);

        if ($template === null) {
            $this->recordGap($objective->id, []);

            return null;
        }

        $values = [];
        $gapKeys = [];

        foreach ($template->bindings as $binding) {
            $value = $this->transform(data_get($context, $binding->answer_key), $binding->transform);

            if ($value !== null && $value !== '') {
                $values[$binding->field_key] = $value;

                continue;
            }

            $gapKeys[] = $binding->field_key;
        }

        $missingRequired = collect($template->required_context ?? [])
            ->reject(fn (string $key) => array_key_exists($key, $values))
            ->values()
            ->all();

        /*
         * السياق الإلزاميّ وحده يُسقط الورقة، وهو اسم النشاط لا غير: ورقةٌ
         * لا تحمل اسم صاحبها لا تُسلَّم لموظف ولا لوكالة. وما عداه يُعلَن.
         */
        if ($missingRequired !== []) {
            $this->recordGap($objective->id, $missingRequired);

            return null;
        }

        $body = $this->translate($template->body ?? [], $locale);
        $gaps = $this->fields->describeMany($gapKeys);
        $body = $this->bind($body, $values, $gapKeys, $locale);

        return new ResolvedTemplate(
            templateId: $template->id,
            objectiveId: $objective->slug,
            kind: $template->kind,
            title: __($template->title, [], $locale),
            blocks: Arr::wrap($body['blocks'] ?? []),
            tips: array_values(array_map('strval', Arr::wrap($body['tips'] ?? []))),
            isHypothesis: $template->is_hypothesis,
            version: $template->version,
            locale: $locale,
            gaps: $gaps,
        );
    }

    /**
     * صفّ القالب باللغة المطلوبة، وإلا بلغة المصدر.
     *
     * الرجوع إلى المصدر ليس تساهلًا: البديل أن يرى الفرنسيّ «غير جاهزة
     * للتنفيذ» بدل ورقة عربية يفهم منها البنية على الأقل. والترجمة الناقصة
     * تُعرض عربيةً وتُعدّ في `i18n:audit`، فلا تختفي بصمت.
     */
    private function template(Objective $objective, string $locale): ?RecommendationTemplate
    {
        $query = fn (string $code) => $objective->templates()
            ->with('bindings')
            ->where('active', true)
            ->where('locale', $code)
            ->latest('version')
            ->first();

        $template = $query($locale);
        $source = (string) config('locales.source', 'ar');

        return $template ?? ($locale === $source ? null : $query($source));
    }

    private function transform(mixed $value, string $transform): mixed
    {
        return match ($transform) {
            'csv' => is_array($value) ? implode('، ', array_filter(array_map('strval', $value))) : trim((string) $value),
            'first' => is_array($value) ? ($value[0] ?? null) : $value,
            default => is_scalar($value) ? trim((string) $value) : null,
        };
    }

    /**
     * ترجمة نصوص الجسد قبل ربط النواب.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function translate(array $body, string $locale): array
    {
        $walk = function (mixed $item) use (&$walk, $locale): mixed {
            if (is_array($item)) {
                return array_map($walk, $item);
            }

            return is_string($item) && trim($item) !== '' ? __($item, [], $locale) : $item;
        };

        return $walk($body);
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  array<string, mixed>  $bindings
     * @param  array<int, string>  $gapKeys
     * @return array<string, mixed>
     */
    private function bind(array $value, array $bindings, array $gapKeys, string $locale): array
    {
        $blank = __('— لم تُجب عن هذا بعد', [], $locale);

        $replace = function (mixed $item) use (&$replace, $bindings, $gapKeys, $blank): mixed {
            if (is_array($item)) {
                return array_map($replace, $item);
            }

            if (! is_string($item)) {
                return $item;
            }

            return preg_replace_callback('/\{([a-z0-9_.-]+)\}/i', function (array $match) use ($bindings, $gapKeys, $blank): string {
                if (array_key_exists($match[1], $bindings)) {
                    return (string) $bindings[$match[1]];
                }

                /*
                 * النائب الذي لا جواب له يُستبدل بعلامة مقروءة لا يُترك خامًا:
                 * `{best_customer}` في ورقة تُطبع وتُسلَّم تبدو عطلًا في النظام،
                 * لا سؤالًا ينتظر جوابه.
                 */
                return in_array($match[1], $gapKeys, true) ? $blank : $match[0];
            }, $item) ?? $item;
        };

        return $replace($value);
    }

    /** @param array<int, string> $missing */
    private function recordGap(int $objectiveId, array $missing): void
    {
        $gap = TemplateGap::firstOrNew(['objective_id' => $objectiveId]);
        $gap->occurrences = $gap->exists ? $gap->occurrences + 1 : 1;
        $gap->last_seen_at = now();
        $gap->missing_context = $missing;
        $gap->save();
    }
}
