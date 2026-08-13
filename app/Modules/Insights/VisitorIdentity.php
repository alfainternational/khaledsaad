<?php

namespace App\Modules\Insights;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * هوية الزائر بلا معرفة شخصه.
 *
 * كوكي طرف أول واحد يعيش سنة، قيمته سلسلة عشوائية لا تعني شيئًا خارج
 * قاعدتنا. لا يعبر نطاقات، ولا يُشارَك، ولا يُشترى. وظيفته الوحيدة أن
 * يجيب: «هل هذا الزائر عاد؟» — وبدونها كل زيارة تبدو زائرًا جديدًا،
 * فينهار الفرق بين موقع يزوره ألف شخص مرة وموقع يزوره مئة عشر مرات.
 *
 * عنوان IP لا يُخزَّن خامًا أبدًا. يُخزَّن مُجزَّأً بمفتاح التطبيق ليخدم
 * غرضين لا ثالث لهما: هوية احتياطية حين يُحظر الكوكي، وكشف التكرار الآلي.
 */
class VisitorIdentity
{
    public const COOKIE = 'ks_visitor';

    /** كوكي الزيارة الجارية: عمره نافذة الجلسة، ويُجدَّد مع كل نشاط. */
    public const VISIT_COOKIE = 'ks_visit';

    /** سنة كاملة بالدقائق: أقصر منها يقطع «العائد» بعد كل موسم. */
    private const LIFETIME_MINUTES = 525600;

    /**
     * معرّف الزائر من الكوكي، أو معرّف جديد إن غاب.
     *
     * @return array{id: string, is_new: bool}
     */
    public function resolve(Request $request): array
    {
        $existing = $request->cookie(self::COOKIE);

        if (is_string($existing) && preg_match('/^[A-Za-z0-9]{32,40}$/', $existing)) {
            return ['id' => $existing, 'is_new' => false];
        }

        return ['id' => Str::random(32), 'is_new' => true];
    }

    /** يُثبَّت الكوكي على الاستجابة نفسها التي أنشأته. */
    public function remember(string $visitorId): void
    {
        Cookie::queue(Cookie::make(
            self::COOKIE,
            $visitorId,
            self::LIFETIME_MINUTES,
            path: '/',
            secure: $this->secure(),
            httpOnly: true,
            sameSite: 'lax',
        ));
    }

    /** معرّف الجلسة الجارية كما يراه المتصفح (كوكي قصير بعمر النافذة). */
    public function currentVisit(Request $request): ?string
    {
        $value = $request->cookie(self::VISIT_COOKIE);

        return is_string($value) && Str::isUuid($value) ? $value : null;
    }

    public function rememberVisit(string $uuid): void
    {
        Cookie::queue(Cookie::make(
            self::VISIT_COOKIE,
            $uuid,
            (int) config('insights.session_timeout_minutes', 30),
            path: '/',
            secure: $this->secure(),
            httpOnly: true,
            sameSite: 'lax',
        ));
    }

    /**
     * بصمة العنوان: مُجزَّأة بمفتاح التطبيق، غير قابلة للعكس، ولا تُعرض.
     *
     * الملح ثابت لا يومي عن عمد: الملح اليومي يقطع الهوية الاحتياطية عند
     * منتصف الليل فيبدو الزائر نفسه زائرَين، والفائدة الأمنية منه وهمية
     * لأن من يملك الملح يمكنه توليد جدول العناوين كاملًا في الحالتين.
     */
    public function hashIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }

    private function secure(): bool
    {
        return str_starts_with((string) config('app.url'), 'https://');
    }
}
