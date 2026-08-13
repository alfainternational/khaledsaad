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
                'group' => __('الذكاء الاصطناعي'),
                'fields' => [
                    ['key' => 'ai.default', 'label' => __('المزوّد الافتراضي'), 'type' => 'string',
                        'hint' => __('اسم المزوّد المُعتمد (مثل deepseek).')],
                    ['key' => 'ai.deepseek.api_key', 'label' => __('مفتاح DeepSeek'), 'type' => 'secret',
                        'hint' => __('مفتاح API. يُخزَّن مشفّرًا ولا يُعرض بعد الحفظ.')],
                    ['key' => 'ai.deepseek.base_url', 'label' => __('عنوان الخدمة'), 'type' => 'string',
                        'hint' => __('مثال: https://api.deepseek.com')],
                    ['key' => 'ai.deepseek.model', 'label' => __('النموذج الافتراضي'), 'type' => 'string'],
                    ['key' => 'ai.deepseek.tiers.economy', 'label' => __('نموذج الاقتصاد'), 'type' => 'string'],
                    ['key' => 'ai.deepseek.tiers.standard', 'label' => __('النموذج القياسي'), 'type' => 'string'],
                    ['key' => 'ai.deepseek.tiers.advanced', 'label' => __('النموذج المتقدّم'), 'type' => 'string'],
                ],
            ],
            [
                'group' => __('البريد الإلكتروني'),
                'fields' => [
                    ['key' => 'mail_mailer', 'label' => __('الناقل'), 'type' => 'string',
                        'hint' => __('smtp للبريد الحقيقي، log للاختبار المحلي.')],
                    ['key' => 'mail_host', 'label' => __('خادم SMTP'), 'type' => 'string',
                        'hint' => __('مثال: smtp.gmail.com. اتركه فارغًا لإبقاء الإعداد المحلي.')],
                    ['key' => 'mail_port', 'label' => __('المنفذ'), 'type' => 'int', 'hint' => __('غالبًا 587 أو 465.')],
                    ['key' => 'mail_username', 'label' => __('اسم المستخدم'), 'type' => 'string'],
                    ['key' => 'mail_password', 'label' => __('كلمة المرور'), 'type' => 'secret'],
                    ['key' => 'mail_encryption', 'label' => __('التشفير'), 'type' => 'string', 'hint' => __('tls أو ssl.')],
                    ['key' => 'mail_from_address', 'label' => __('بريد المُرسِل'), 'type' => 'string'],
                    ['key' => 'mail_from_name', 'label' => __('اسم المُرسِل'), 'type' => 'string'],
                ],
            ],
            [
                'group' => __('محرك النمو'),
                'fields' => [
                    ['key' => 'growth.watch_enabled', 'label' => __('التقرير الحي'), 'type' => 'bool',
                        'hint' => __('الفحص اليومي لتقارير المستخدمين وتنبيههم عند تغيّر مدخلاتها.')],
                    ['key' => 'growth.pulse_enabled', 'label' => __('النبض الأسبوعي'), 'type' => 'bool',
                        'hint' => __('خلاصة أسبوعية لكل مشروع تصل بالإشعار والبريد صباح الاثنين.')],
                    ['key' => 'growth.score_drift_threshold', 'label' => __('عتبة انحراف الدرجة'), 'type' => 'int',
                        'hint' => __('فرق النقاط الذي يستحق تنبيه «درجتك تغيّرت». الافتراضي 5.')],
                    ['key' => 'growth.stale_days', 'label' => __('عمر تقادم التقرير (أيام)'), 'type' => 'int',
                        'hint' => __('بعده يقترح النبض إعادة القياس. الافتراضي 45.')],
                ],
            ],
            [
                'group' => __('الفوترة'),
                'fields' => [
                    ['key' => 'billing.currency', 'label' => __('عملة الأسعار'), 'type' => 'string',
                        'hint' => __('رمز من ثلاثة أحرف (SAR مثلًا). تحويلها لعملة البوابة يُضبط داخل البوابة نفسها.')],
                ],
            ],
            [
                /*
                 * الاستقبال الصوتي. `config/services.php` يعلن أن هذه المفاتيح
                 * «تُضبط من لوحة الآدمن»، ولم تكن في الكتالوج — فلم يكن لها
                 * مكان تُضبط منه، وبقي الصوت معطّلًا بلا سبب ظاهر.
                 */
                'group' => __('الاستقبال الصوتي'),
                'fields' => [
                    ['key' => 'services.speech.key', 'label' => __('مفتاح خدمة النسخ'), 'type' => 'secret',
                        'hint' => __('بدونه لا يعمل تسجيل الإجابات صوتًا. يُخزَّن مشفّرًا ولا يُعرض بعد الحفظ.')],
                    ['key' => 'services.speech.base_url', 'label' => __('عنوان الخدمة'), 'type' => 'string',
                        'hint' => __('الافتراضي https://api.groq.com/openai/v1')],
                    ['key' => 'services.speech.model', 'label' => __('نموذج النسخ'), 'type' => 'string',
                        'hint' => __('مثال: whisper-large-v3. تبديله قرار جودة يُقاس على اللهجات الخليجية.')],
                    ['key' => 'services.speech.cost_per_minute', 'label' => __('تكلفة الدقيقة (دولار)'), 'type' => 'string',
                        'hint' => __('الافتراضي 0.00185 (سعر whisper-large-v3 على Groq: 0.111 للساعة). صفرٌ هنا يجعل كل تسجيل يُسجَّل بلا تكلفة فيختفي الصوت من الفاتورة رغم حجزه موضعًا — راجعه عند تغيير المزوّد.')],
                ],
            ],
            [
                'group' => __('أرقام السوق واكتشاف المنافسين'),
                'fields' => [
                    ['key' => 'benchmarks.live_enabled', 'label' => __('تفعيل المصدر الحيّ'), 'type' => 'bool',
                        'hint' => __('يشغّل أرقام السوق الحيّة واكتشاف المنافسين الإقليميين.')],
                    ['key' => 'benchmarks.live.login', 'label' => __('اسم مستخدم المزوّد'), 'type' => 'secret'],
                    ['key' => 'benchmarks.live.password', 'label' => __('كلمة مرور المزوّد'), 'type' => 'secret'],
                    ['key' => 'benchmarks.live.api_key', 'label' => __('مفتاح المزوّد (بديل)'), 'type' => 'secret',
                        'hint' => __('استخدم المفتاح أو اسم المستخدم وكلمة المرور، لا الاثنين.')],
                    ['key' => 'benchmarks.live.base_url', 'label' => __('عنوان مزوّد البيانات'), 'type' => 'string',
                        'hint' => __('مثال: https://api.dataforseo.com')],
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
