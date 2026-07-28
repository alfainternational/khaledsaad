<?php

namespace App\Modules\Shared\Evidence;

use BackedEnum;

/**
 * مترجم المفردات القديمة إلى تدرّج الدليل الواحد.
 *
 * الغرض مؤقت بحكم التصميم: يُستعمل أثناء نقل الخدمات إلى app/Modules،
 * ثم يبقى فقط عند حدود القراءة من أعمدة لم تُحوَّل بعد. لا يُستدعى من كود
 * جديد — الكود الجديد يكتب EvidenceLevel مباشرة.
 *
 * المفردات المترجَمة:
 *   high / medium / low          → consultation_answers, brain_facts, evidences
 *   measured / estimated / unknown → AgencyOperationalFile
 *   عدد 0–100                     → Recommendation, ConsultationInference
 *   is_assumption منطقي           → Finding
 *
 * قاعدة الترجمة عند الشك: الأضعف. رفع مستوى بالخطأ يعرض فرضية كحقيقة،
 * وخفضه بالخطأ يعرض حقيقة بتحفّظ زائد. الضرر الأول أكبر بكثير.
 *
 * **هذا المترجم لا يُنتج Measured أبدًا.** لا واحدة من المفردات القديمة كانت
 * تتعقّب استقلال المصدر عن كلام المستخدم، و«ثقة عالية» فيها تعني غالبًا
 * إجابة واثقة عن النفس لا رصدًا مستقلًا. القياس يأتي حصرًا من الجامعات
 * الجديدة (SiteAudit وCrawlLogAnalyzer وما شابههما) التي ترى مصدرًا خارج
 * المستخدم. ترقية إجابة إلى قياس تخالف §١٥ مباشرة.
 */
class EvidenceMapper
{
    /**
     * عتبة الدرجة الرقمية التي تُعتبر فوقها البيانات محسوبة لا مفترضة.
     *
     * 70 ليست رقمًا مقدسًا، لكنها العتبة التي كانت ConsultationReportGate
     * تعامل ما دونها كتوصية ضعيفة. تُوحَّد هنا بدل تكرارها.
     */
    private const DERIVED_THRESHOLD = 70;

    /**
     * ترجمة أي قيمة ثقة قديمة، أيًّا كانت مفردتها.
     */
    public function map(mixed $legacy): EvidenceLevel
    {
        if ($legacy instanceof EvidenceLevel) {
            return $legacy;
        }

        if (is_bool($legacy)) {
            // is_assumption: صحيح يعني فرضية صريحة.
            return $legacy ? EvidenceLevel::Inferred : EvidenceLevel::Derived;
        }

        if (is_int($legacy) || is_float($legacy)) {
            return $this->fromScore((float) $legacy);
        }

        if ($legacy instanceof BackedEnum) {
            $legacy = $legacy->value;
        }

        if (! is_string($legacy)) {
            return EvidenceLevel::Inferred;
        }

        return $this->fromWord($legacy);
    }

    /**
     * درجة رقمية 0–100.
     *
     * لا تُرقَّى أي درجة إلى Measured: الرقم يعبّر عن ثقة في استنتاج، لا عن
     * وجود مصدر مستقل. القياس يأتي من المصدر لا من الدرجة.
     */
    private function fromScore(float $score): EvidenceLevel
    {
        return $score >= self::DERIVED_THRESHOLD
            ? EvidenceLevel::Derived
            : EvidenceLevel::Inferred;
    }

    /**
     * كلمة ثقة قديمة.
     *
     * حتى القيمة الحرفية 'measured' من AgencyOperationalFile تنزل إلى Derived:
     * ذلك العمود كان يعني «رقم أدخله المستخدم» لا «رقم رصدناه».
     */
    private function fromWord(string $word): EvidenceLevel
    {
        return match (mb_strtolower(trim($word))) {
            'measured', 'derived', 'estimated', 'high', 'medium' => EvidenceLevel::Derived,
            default => EvidenceLevel::Inferred,
        };
    }
}
