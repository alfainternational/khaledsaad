<?php

namespace App\Modules\Measurement;

use App\Modules\AiReadiness\Models\PresenceProbe;
use App\Modules\AiReadiness\Models\PresenceRun;
use App\Modules\Shared\Metrics\MetricKey;
use Illuminate\Support\Collection;

/**
 * المقاييس الأربعة للحضور في إجابات النماذج (§١٢).
 *
 * تُحسب من `presence_probes` وحدها — لا شبكة ولا نموذج. هذا ما يجعل الرقم
 * قابلًا لإعادة الإنتاج من لقطة قاعدة بيانات، وما يجعل إعادة التصنيف ممكنة
 * لاحقًا بلا إعادة دفع: النص الخام محفوظ، والحساب فوقه.
 *
 * المعادلات مأخوذة حرفيًّا من §١٢ ولا تُقرَّب ولا تُبسَّط:
 *   mention_rate   = المحاولات التي ذُكرت فيها العلامة ÷ (الأسئلة × المحاولات)
 *   share_of_voice = ذكر العلامة ÷ مجموع ذكر كل العلامات
 *   consistency    = ظهور السؤال الواحد ÷ محاولاته
 *   citation_rate  = مرات ربط الموقع كمصدر ÷ مرات الذكر
 */
class PresenceMetrics
{
    /**
     * @return array<string, mixed>
     */
    public function forRun(PresenceRun $run, string $brand): array
    {
        $probes = $run->probes()->where('status', PresenceProbe::STATUS_OK)->get();
        $total = $probes->count();

        if ($total === 0) {
            return $this->empty($run);
        }

        $mentions = $probes->where('brand_mentioned', true);
        $mentionCount = $mentions->count();

        return [
            /*
             * المقام هو المحاولات الناجحة فعلًا لا المخطَّطة: محاولة فشل فيها
             * المزوّد ليست «لم تُذكر فيها العلامة» — هي محاولة لم تقع (§١٢).
             */
            MetricKey::MENTION_RATE => $this->ratio($mentionCount, $total),
            MetricKey::SHARE_OF_VOICE => $this->shareOfVoice($probes, $brand),

            // الاستشهاد يُنسب إلى الذكر لا إلى المحاولات: «كم مرة ذُكرت ومعك
            // رابطك» سؤال مختلف عن «كم مرة ذُكرت».
            MetricKey::CITATION_RATE => $mentionCount === 0
                ? null
                : $this->ratio($mentions->where('site_cited', true)->count(), $mentionCount),

            'per_question' => $this->perQuestion($probes),

            /*
             * الأساس يسافر مع الرقم دائمًا (§١٣): «١٢٪» بلا «من ٦٢ محاولة»
             * رقم لا يمكن تصديقه ولا تكذيبه.
             */
            'basis' => [
                'questions' => $probes->pluck('question_key')->unique()->count(),
                'attempts_per_question' => $run->attempts_per_question,
                'successful_attempts' => $total,
                'planned_attempts' => $run->questions_count * $run->attempts_per_question,
                'provider' => $run->provider,
                'model' => $run->model,
                'measured_at' => $run->completed_at?->toIso8601String(),
            ],
            'publishable' => $run->isPublishable(),
        ];
    }

    /**
     * `consistency` لكل سؤال: ظهور السؤال الواحد ÷ محاولاته.
     *
     * سؤالان بمعدّل ذكر ٥٠٪ قد يكونان مختلفين تمامًا: أحدهما ظهر في نصف
     * المحاولات لكل سؤال، والآخر ظهر دائمًا في سؤال وغاب دائمًا عن آخر.
     * الثاني قابل للعلاج والأول تذبذب — والفرق لا يظهر إلا هنا.
     *
     * @param  Collection<int, PresenceProbe>  $probes
     * @return array<int, array<string, mixed>>
     */
    private function perQuestion(Collection $probes): array
    {
        return $probes
            ->groupBy('question_key')
            ->map(fn ($group, $key) => [
                'question_key' => $key,
                'question' => $group->first()->question,
                'attempts' => $group->count(),
                'mentions' => $group->where('brand_mentioned', true)->count(),
                MetricKey::CONSISTENCY => $this->ratio(
                    $group->where('brand_mentioned', true)->count(),
                    $group->count(),
                ),
            ])
            ->sortBy(MetricKey::CONSISTENCY)
            ->values()
            ->all();
    }

    /**
     * حصة الصوت: ذكر العلامة ÷ مجموع ذكر كل العلامات.
     *
     * المقام هنا هو السوق كله لا محاولاتك، ولذلك يمنع §١٢ عرضه بجانب
     * `mention_rate` بلا تسميتين ظاهرتين: النسبتان تُقرآن متشابهتين ومقاماهما
     * مختلفان تمامًا، فيقرأ صاحب النشاط موقعه معكوسًا.
     *
     * @param  Collection<int, PresenceProbe>  $probes
     */
    private function shareOfVoice(Collection $probes, string $brand): ?float
    {
        $all = 0;
        $mine = 0;

        foreach ($probes as $probe) {
            foreach ((array) $probe->brands_mentioned as $mentioned) {
                $all++;

                if ($this->sameBrand((string) $mentioned, $brand)) {
                    $mine++;
                }
            }
        }

        // لم تُذكر أي علامة في أي محاولة: لا حصة صفر بل لا سوق مرصود أصلًا.
        return $all === 0 ? null : $this->ratio($mine, $all);
    }

    private function sameBrand(string $mentioned, string $brand): bool
    {
        return mb_strtolower(trim($mentioned)) === mb_strtolower(trim($brand));
    }

    private function ratio(int $numerator, int $denominator): float
    {
        return $denominator === 0 ? 0.0 : round($numerator / $denominator, 4);
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(PresenceRun $run): array
    {
        return [
            MetricKey::MENTION_RATE => null,
            MetricKey::SHARE_OF_VOICE => null,
            MetricKey::CITATION_RATE => null,
            'per_question' => [],
            'basis' => [
                'questions' => 0,
                'attempts_per_question' => $run->attempts_per_question,
                'successful_attempts' => 0,
                'planned_attempts' => $run->questions_count * $run->attempts_per_question,
                'provider' => $run->provider,
                'model' => $run->model,
                'measured_at' => null,
            ],
            'publishable' => false,
        ];
    }
}
