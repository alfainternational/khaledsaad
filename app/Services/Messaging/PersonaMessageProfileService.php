<?php

namespace App\Services\Messaging;

use App\Models\PersonaPanel;
use App\Support\Messaging\PersonaName;

/**
 * يحوّل كل شخصية إلى ملف مراسلة ثابت المفتاح.
 *
 * المفتاح مشتق من هوية الشخصية (اسمها ودورها) لا من ترتيبها في اللوحة:
 * إعادة بناء اللوحة تغيّر الترتيب، ولو ربطنا الرسائل بالفهرس لانتقلت
 * رسالة «الحسّاس للسعر» إلى «المتحمس» بصمت.
 */
class PersonaMessageProfileService
{
    /**
     * مفتاح ثابت لشخصية واحدة.
     *
     * @param  array<string, mixed>  $persona
     */
    public function keyFor(array $persona): string
    {
        $identity = trim(($persona['name'] ?? '').'|'.($persona['role'] ?? ''));

        return substr(hash('sha256', $identity), 0, 32);
    }

    /**
     * ملفات المراسلة مفهرسة بالمفتاح.
     *
     * @return array<string, array<string, mixed>>
     */
    public function profiles(PersonaPanel $panel): array
    {
        $profiles = [];

        foreach ($panel->personas ?? [] as $persona) {
            $key = $this->keyFor($persona);
            $profiles[$key] = $this->profile($key, $persona);
        }

        return $profiles;
    }

    /**
     * @param  array<string, mixed>  $persona
     * @return array<string, mixed>
     */
    public function profile(string $key, array $persona): array
    {
        return [
            'persona_key' => $key,
            // الاسم الأول وحده: يذهب إلى النموذج والواجهة معًا، ولقب العائلة
            // في رسالة تسويقية يبدو تنقيبًا عن الشخص لا مخاطبةً له.
            'name' => PersonaName::display($persona['name'] ?? null),
            'role' => $persona['role'] ?? null,
            'age_range' => $persona['age_range'] ?? null,
            'gender' => $persona['gender'] ?? null,
            'locations' => (array) ($persona['locations'] ?? []),
            'interests' => (array) ($persona['interests'] ?? []),
            'platforms' => (array) ($persona['platforms'] ?? []),
            'spending_level' => $persona['spending_level'] ?? null,
            'motivation' => $persona['motivation'] ?? null,
            'objection' => $persona['objection'] ?? null,
            'pains' => (array) ($persona['pains'] ?? []),
            'buying_style' => $persona['buying_style'] ?? null,
            'tone' => $persona['tone'] ?? null,
            // ما يجب تجنّبه مشتق من اعتراضها: من يشك في الوعود يزيده وعدٌ آخر شكًّا.
            'avoid' => $this->avoid($persona),
        ];
    }

    /**
     * الشخصية كما هي في اللوحة، بحثًا بالمفتاح.
     *
     * @return array<string, mixed>|null
     */
    public function findPersona(PersonaPanel $panel, string $key): ?array
    {
        foreach ($panel->personas ?? [] as $persona) {
            if ($this->keyFor($persona) === $key) {
                return $persona;
            }
        }

        return null;
    }

    /**
     * أقرب شخصية إلى عميل متوقع: تطابق المدينة أولًا ثم الاهتمامات.
     *
     * تُقترح ولا تُفرض — صاحب المشروع يعرف عميله أكثر من أي مطابقة. وحين
     * لا يتطابق شيء نعيد null بدل «أقرب واحدة على أي حال»: شخصية خاطئة
     * تعطي نبرة خاطئة، والفراغ أصدق من مطابقة بلا أساس.
     *
     * @param  array<int, string>  $interests
     */
    public function bestMatch(PersonaPanel $panel, ?string $city, array $interests): ?string
    {
        $best = null;
        $bestScore = 0;

        foreach ($panel->personas ?? [] as $persona) {
            $score = 0;

            if (filled($city) && in_array($city, (array) ($persona['locations'] ?? []), true)) {
                $score += 2;
            }

            $score += count(array_intersect($interests, (array) ($persona['interests'] ?? [])));

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $this->keyFor($persona);
            }
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>  $persona
     */
    private function avoid(array $persona): string
    {
        return match ($persona['spending_level'] ?? null) {
            'منخفض' => 'لا تبدأ بالمزايا قبل السعر، ولا تستخدم لغة الرفاهية.',
            'مرتفع' => 'لا تبدأ بالخصم — الرخص هنا يقلّل القيمة المُدرَكة.',
            default => 'لا تعِد بما لا يمكن إثباته، ولا تحشُد أكثر من فكرة واحدة.',
        };
    }
}
