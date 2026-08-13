<?php

namespace App\Modules\Diagnosis;

use App\Models\ToolVersion;

/**
 * يترجم مخرجات `DeterministicScorer` إلى شرح يقرأه صاحب النشاط.
 *
 * السبب: الدرجة الحتمية تعرف الحقل والوزن والمعامل، ولا تعرف نص السؤال ولا
 * نص الخيار الذي اختاره المستخدم — وهي عمدًا لا تعرفه، لأن ربطها بجداول
 * الأسئلة يسلبها نقاءها وقابلية إعادة حسابها. فيتم الربط هنا، خارج الحساب.
 *
 * ملاحظة أمانة (§٤.١): الأوزان نفسها `inferred` — تقدير منهجي محرَّر بيد،
 * لا معايرة على بيانات. هذا الصنف يشرح كيف طُبِّقت، ولا يزعم أنها مُعايَرة.
 */
class ScoreExplainer
{
    /**
     * سُلّم الثقل المستعمل فعلًا في الأدوات الإحدى عشرة.
     *
     * ليس اشتقاقًا لاحقًا بل وصفٌ لما في ملفات القواعد: 110 قاعدة، أوزانها كلها
     * أرقام زوجية بين 8 و22 متجمّعة حول 14–16. نُعلن السلّم بدل تأليف تبرير
     * منفصل لكل رقم، لأن تبريرًا يُكتب بعد اختيار الرقم تلفيق يحوّل الفرضية
     * إلى حقيقة في عين القارئ (§١٥).
     */
    public const SCALE_NOTE = 'نرتّب ثقل البنود على أربع درجات: مصيري (20 فأكثر)، وحاسم (16–18)، ومؤثر (12–14)، ومساند (10 فأقل). موضع البند على هذا السلّم حكم منهجي منّا نراجعه، لا رقم مشتقّ من بيانات حملات.';

    /**
     * @param  array<string, mixed>  $result  مخرج DeterministicScorer::score
     * @return array<string, mixed>
     */
    public function explain(ToolVersion $version, array $result): array
    {
        $fields = $version->fields()->get()->keyBy('key');

        $weights = array_map(
            static fn (array $row) => (float) ($row['weight'] ?? 0),
            $result['breakdown'] ?? [],
        );
        $ruleCount = count($weights);

        foreach ($result['breakdown'] ?? [] as $index => $row) {
            $field = $fields->get($row['field'] ?? '');
            $weight = (float) ($row['weight'] ?? 0);

            // الرتبة حقيقة محسوبة لا رأي: كم بندًا يثقل عليه في هذه الأداة.
            $result['breakdown'][$index]['weight_tier'] = $this->tier($weight);
            $result['breakdown'][$index]['weight_rank'] = 1 + count(array_filter(
                $weights,
                static fn (float $other) => $other > $weight,
            ));
            $result['breakdown'][$index]['weight_rank_of'] = $ruleCount;

            $options = is_array($field?->options) ? $field->options : [];
            $labels = [];

            foreach ($options as $option) {
                if (isset($option['value'])) {
                    $labels[(string) $option['value']] = (string) ($option['label'] ?? $option['value']);
                }
            }

            $result['breakdown'][$index]['question'] = $field?->label;
            $result['breakdown'][$index]['why'] = $field?->why;
            $result['breakdown'][$index]['answer_label'] = $this->answerLabel($row['value'] ?? '', $labels);

            foreach ($row['scale'] ?? [] as $position => $step) {
                $result['breakdown'][$index]['scale'][$position]['label'] = $labels[$step['key']] ?? $step['key'];
            }
        }

        foreach ($result['excluded'] ?? [] as $index => $row) {
            $result['excluded'][$index]['question'] = $fields->get($row['field'] ?? '')?->label;
        }

        return $result;
    }

    private function tier(float $weight): string
    {
        return match (true) {
            $weight >= 20 => __('مصيري'),
            $weight >= 16 => __('حاسم'),
            $weight >= 12 => __('مؤثر'),
            default => __('مساند'),
        };
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function answerLabel(mixed $value, array $labels): string
    {
        if (is_array($value)) {
            if ($value === []) {
                return __('لم تُجب');
            }

            return implode('، ', array_map(
                fn (mixed $item) => $labels[(string) $item] ?? (string) $item,
                $value,
            ));
        }

        $value = trim((string) $value);

        if ($value === '') {
            return __('لم تُجب');
        }

        return $labels[$value] ?? $value;
    }
}
