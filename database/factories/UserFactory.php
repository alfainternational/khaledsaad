<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),

            /*
             * مسار الأعمال مفتوح افتراضًا لأن المستخدم الحقيقي يختار مساره عند
             * التسجيل — فالمستخدم بلا مسار حالةٌ خاصة لا القاعدة.
             *
             * وبدون هذا كان `EnsureExperienceAccess` يردّ كل اختبار يطلب مسار
             * `app/*` بـ302 إلى شاشة التفعيل قبل أن يصل إلى المتحكّم، فتقيس
             * ٩٧ حالةً البوابةَ لا السلوك الذي كُتبت له.
             *
             * `initial_experience` يبقى فارغًا عمدًا: `selectInitial()` يرفض
             * الكتابة فوق قيمة موجودة، فملؤه هنا كان سيكسر كل اختبار يفعّل
             * مساره صراحةً. والبوابة لا تقرأ إلا العمود أدناه.
             */
            'business_experience_enabled_at' => now(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * مستخدم لم يختر مسارًا بعد — لاختبار البوابة نفسها.
     */
    public function withoutExperience(): static
    {
        return $this->state(fn (array $attributes) => [
            'business_experience_enabled_at' => null,
            'learning_experience_enabled_at' => null,
        ]);
    }
}
