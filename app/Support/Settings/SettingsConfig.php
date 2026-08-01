<?php

namespace App\Support\Settings;

use App\Models\Setting;

/**
 * الجسر بين إعدادات لوحة الآدمن وملفات config.
 *
 * القاعدة: مفتاح الإعداد هو نفسه مسار config (مثل ai.deepseek.api_key).
 * عند كل طلب نطبّق ما ضبطه الآدمن فوق قيم .env، فيسري التغيير حيًّا على
 * كل مكان يقرأ config() — بوابة الذكاء، أرقام السوق، اكتشاف المنافسين —
 * دون لمس أي مستهلك ودون إعادة نشر.
 */
class SettingsConfig
{
    /**
     * كتالوج المفاتيح القابلة للإدارة من اللوحة، مجموعة للعرض.
     * value الفعلية تبقى في .env حتى يضبطها الآدمن صراحةً.
     *
     * @return array<int, array{group: string, fields: array<int, array<string, mixed>>}>
     */
    public static function catalog(): array
    {
        return [
            [
                'group' => 'الذكاء الاصطناعي',
                'fields' => [
                    ['key' => 'ai.default', 'label' => 'المزوّد الافتراضي', 'type' => 'string',
                        'hint' => 'اسم المزوّد المُعتمد (مثل deepseek).'],
                    ['key' => 'ai.deepseek.api_key', 'label' => 'مفتاح DeepSeek', 'type' => 'secret',
                        'hint' => 'مفتاح API. يُخزَّن مشفّرًا ولا يُعرض بعد الحفظ.'],
                    ['key' => 'ai.deepseek.base_url', 'label' => 'عنوان الخدمة', 'type' => 'string',
                        'hint' => 'مثال: https://api.deepseek.com'],
                    ['key' => 'ai.deepseek.model', 'label' => 'النموذج الافتراضي', 'type' => 'string'],
                    ['key' => 'ai.deepseek.tiers.economy', 'label' => 'نموذج الاقتصاد', 'type' => 'string'],
                    ['key' => 'ai.deepseek.tiers.standard', 'label' => 'النموذج القياسي', 'type' => 'string'],
                    ['key' => 'ai.deepseek.tiers.advanced', 'label' => 'النموذج المتقدّم', 'type' => 'string'],
                ],
            ],
            [
                'group' => 'البريد الإلكتروني',
                'fields' => [
                    ['key' => 'mail_mailer', 'label' => 'الناقل', 'type' => 'string',
                        'hint' => 'smtp للبريد الحقيقي، log للاختبار المحلي.'],
                    ['key' => 'mail_host', 'label' => 'خادم SMTP', 'type' => 'string',
                        'hint' => 'مثال: smtp.gmail.com. اتركه فارغًا لإبقاء الإعداد المحلي.'],
                    ['key' => 'mail_port', 'label' => 'المنفذ', 'type' => 'int', 'hint' => 'غالبًا 587 أو 465.'],
                    ['key' => 'mail_username', 'label' => 'اسم المستخدم', 'type' => 'string'],
                    ['key' => 'mail_password', 'label' => 'كلمة المرور', 'type' => 'secret'],
                    ['key' => 'mail_encryption', 'label' => 'التشفير', 'type' => 'string', 'hint' => 'tls أو ssl.'],
                    ['key' => 'mail_from_address', 'label' => 'بريد المُرسِل', 'type' => 'string'],
                    ['key' => 'mail_from_name', 'label' => 'اسم المُرسِل', 'type' => 'string'],
                ],
            ],
            [
                'group' => 'محرك النمو',
                'fields' => [
                    ['key' => 'growth.watch_enabled', 'label' => 'التقرير الحي', 'type' => 'bool',
                        'hint' => 'الفحص اليومي لتقارير المستخدمين وتنبيههم عند تغيّر مدخلاتها.'],
                    ['key' => 'growth.pulse_enabled', 'label' => 'النبض الأسبوعي', 'type' => 'bool',
                        'hint' => 'خلاصة أسبوعية لكل مشروع تصل بالإشعار والبريد صباح الاثنين.'],
                    ['key' => 'growth.score_drift_threshold', 'label' => 'عتبة انحراف الدرجة', 'type' => 'int',
                        'hint' => 'فرق النقاط الذي يستحق تنبيه «درجتك تغيّرت». الافتراضي 5.'],
                    ['key' => 'growth.stale_days', 'label' => 'عمر تقادم التقرير (أيام)', 'type' => 'int',
                        'hint' => 'بعده يقترح النبض إعادة القياس. الافتراضي 45.'],
                ],
            ],
            [
                'group' => 'الفوترة',
                'fields' => [
                    ['key' => 'billing.currency', 'label' => 'عملة الأسعار', 'type' => 'string',
                        'hint' => 'رمز من ثلاثة أحرف (SAR مثلًا). تحويلها لعملة البوابة يُضبط داخل البوابة نفسها.'],
                ],
            ],
            [
                /*
                 * الاستقبال الصوتي. `config/services.php` يعلن أن هذه المفاتيح
                 * «تُضبط من لوحة الآدمن»، ولم تكن في الكتالوج — فلم يكن لها
                 * مكان تُضبط منه، وبقي الصوت معطّلًا بلا سبب ظاهر.
                 */
                'group' => 'الاستقبال الصوتي',
                'fields' => [
                    ['key' => 'services.speech.key', 'label' => 'مفتاح خدمة النسخ', 'type' => 'secret',
                        'hint' => 'بدونه لا يعمل تسجيل الإجابات صوتًا. يُخزَّن مشفّرًا ولا يُعرض بعد الحفظ.'],
                    ['key' => 'services.speech.base_url', 'label' => 'عنوان الخدمة', 'type' => 'string',
                        'hint' => 'الافتراضي https://api.groq.com/openai/v1'],
                    ['key' => 'services.speech.model', 'label' => 'نموذج النسخ', 'type' => 'string',
                        'hint' => 'مثال: whisper-large-v3. تبديله قرار جودة يُقاس على اللهجات الخليجية.'],
                    ['key' => 'services.speech.cost_per_minute', 'label' => 'تكلفة الدقيقة (دولار)', 'type' => 'string',
                        'hint' => 'الافتراضي 0.00185 (سعر whisper-large-v3 على Groq: 0.111 للساعة). صفرٌ هنا يجعل كل تسجيل يُسجَّل بلا تكلفة فيختفي الصوت من الفاتورة رغم حجزه موضعًا — راجعه عند تغيير المزوّد.'],
                ],
            ],
            [
                'group' => 'أرقام السوق واكتشاف المنافسين',
                'fields' => [
                    ['key' => 'benchmarks.live_enabled', 'label' => 'تفعيل المصدر الحيّ', 'type' => 'bool',
                        'hint' => 'يشغّل أرقام السوق الحيّة واكتشاف المنافسين الإقليميين.'],
                    ['key' => 'benchmarks.live.login', 'label' => 'اسم مستخدم المزوّد', 'type' => 'secret'],
                    ['key' => 'benchmarks.live.password', 'label' => 'كلمة مرور المزوّد', 'type' => 'secret'],
                    ['key' => 'benchmarks.live.api_key', 'label' => 'مفتاح المزوّد (بديل)', 'type' => 'secret',
                        'hint' => 'استخدم المفتاح أو اسم المستخدم وكلمة المرور، لا الاثنين.'],
                    ['key' => 'benchmarks.live.base_url', 'label' => 'عنوان مزوّد البيانات', 'type' => 'string',
                        'hint' => 'مثال: https://api.dataforseo.com'],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>> كل الحقول مسطّحة
     */
    public static function fields(): array
    {
        return collect(self::catalog())->flatMap(fn (array $group) => $group['fields'])->all();
    }

    /**
     * تطبيق ما ضبطه الآدمن فوق config الحالي. تُستدعى مرة عند كل إقلاع.
     */
    public function apply(): void
    {
        $known = collect(self::fields())->keyBy('key');

        foreach (Setting::allCached() as $key => $value) {
            // نطبّق فقط المفاتيح المعروفة وذات القيمة، حتى لا يطغى فراغ على .env.
            if (! $known->has($key) || $value === null || $value === '') {
                continue;
            }

            // المفاتيح المسطّحة (mail_*) يتولّاها MailConfigurator بخريطته الخاصة؛
            // هنا نطبّق مسارات config الحقيقية فقط (التي تحوي نقطة).
            if (! str_contains($key, '.')) {
                continue;
            }

            config([$key => $value]);
        }
    }
}
