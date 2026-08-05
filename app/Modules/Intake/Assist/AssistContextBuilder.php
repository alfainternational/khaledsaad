<?php

namespace App\Modules\Intake\Assist;

use App\Models\Project;
use App\Modules\Brain\BrainReader;
use App\Modules\Shared\Sectors\Sector;
use App\Modules\Shared\Text\ArabicText;

/**
 * كل ما نعرفه عن النشاط، مجموعًا مرة واحدة ليُبنى عليه المقترح.
 *
 * هذه هي النقطة التي تجعل المقترح **يخصّ هذا النشاط** لا نشاطًا عامًّا. مقترح
 * جمهور لمطعم في الرياض يجب أن يعرف أنه مطعم وأنه في الرياض وأن ميزانيته كذا
 * وأنه قال قبل ثلاثة أسئلة إنه يبيع للشركات لا للأفراد. بلا هذا السياق يكون
 * المخرج نصًّا أنيقًا لا يفرّق بين عميل وعميل — وهو ما لا يستحق استعلامًا مدفوعًا.
 *
 * المصدر هو `Brain` لا استمارة جديدة (§١٥): لا نعيد سؤال المستخدم عما يعرفه
 * النظام، ولا نبني مقترحًا يتجاهله.
 *
 * حدّ الحجم مقصود: السياق الذي يتجاوز حدًّا معقولًا يرفع تكلفة كل استعلام بلا
 * أن يرفع جودته، ويُغرق ما يهمّ في ما لا يهمّ.
 */
class AssistContextBuilder
{
    /** أطول قيمة حقيقة تُمرَّر إلى النموذج. الأطول يُقصّ بإعلان. */
    private const MAX_FACT_LENGTH = 400;

    /** أكثر عدد حقائق تُمرَّر، الأحدث أولًا. */
    private const MAX_FACTS = 40;

    public function __construct(private readonly BrainReader $brain) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Project $project): array
    {
        $project->loadMissing('profile');
        $profile = $project->profile;

        $facts = $this->brain->facts($project)
            ->take(self::MAX_FACTS)
            ->map(fn ($fact) => [
                'key' => $fact->key,
                'value' => $this->trim(ArabicText::flatten($fact->value_json['value'] ?? null)),
                /*
                 * مستوى الدليل يسافر مع الحقيقة إلى النموذج: مقترح مبنيّ على
                 * فرضية لا يجوز أن يُعرض بيقين أعلى من مصدره، والنموذج لا يعرف
                 * ذلك إن لم نخبره.
                 */
                'evidence_level' => $fact->evidence_level->value,
            ])
            ->filter(fn (array $fact) => $fact['value'] !== '')
            ->values()
            ->all();

        return [
            'business' => array_filter([
                'name' => $project->name,
                'industry' => $project->industry,
                'sector' => Sector::isSpecialized($project->sector)
                    ? Sector::label($project->sector)
                    : null,
                'stage' => $project->stage,
                'business_model' => $profile?->business_model,
                'description' => $this->trim((string) $profile?->description),
                'geography' => $profile?->geography,
                'primary_goal' => $this->trim((string) $profile?->primary_goal),
                'value_proposition' => $this->trim((string) $profile?->value_proposition),
                'monthly_budget' => $profile?->monthly_budget,
                'channels' => $profile?->channels,
            ], fn ($value) => $value !== null && $value !== '' && $value !== []),
            'known_facts' => $facts,
        ];
    }

    /**
     * بصمة السياق: ما دامت ثابتة، المخرج المخزَّن ما زال صحيحًا ولا يُدفع ثمنه
     * ثانية. تغيّرها يعني أن النشاط الذي وُصف صار نشاطًا آخر.
     *
     * @param  array<string, mixed>  $context
     */
    public function fingerprint(array $context, QuestionDescriptor $question): string
    {
        return hash('sha256', $question->fingerprint().'|'.json_encode(
            $context,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ));
    }

    private function trim(?string $value): string
    {
        $value = trim((string) $value);

        return mb_strlen($value) > self::MAX_FACT_LENGTH
            ? mb_substr($value, 0, self::MAX_FACT_LENGTH).'…'
            : $value;
    }
}
