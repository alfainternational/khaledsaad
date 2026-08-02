<?php

namespace App\Services\Messaging;

use App\Models\PersonaPanel;

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
            'name' => $persona['name'] ?? 'شخصية',
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
