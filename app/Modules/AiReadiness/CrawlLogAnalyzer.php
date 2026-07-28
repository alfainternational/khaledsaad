<?php

namespace App\Modules\AiReadiness;

use Illuminate\Support\Carbon;

/**
 * تحليل سجلات الخادم: أي بوت زار، متى، أي صفحات، وما استُبعد.
 *
 * يجيب على سؤال واحد يسبق كل شيء: هل أنا مرئي للنماذج أصلًا؟ موقع لم يزره
 * بوت قط لن يظهر في أي إجابة مهما حسّن محتواه — وهذه معلومة لا يعرفها صاحب
 * النشاط ولا يستطيع أن يقولها لنا، ولذلك تُقاس ولا تُستنتج.
 *
 * المدخل ملف سجل مرفوع (صيغة Combined الشائعة في cPanel وApache وNginx).
 * الموصّلات المباشرة تأتي لاحقًا؛ الرفع يعمل اليوم بلا انتظار أحد.
 */
class CrawlLogAnalyzer
{
    /**
     * البوتات التي يهمّنا رصدها، بأنماط تطابق ما تكتبه في User-Agent.
     */
    private const BOTS = [
        'GPTBot' => 'GPTBot',
        'ChatGPT-User' => 'ChatGPT',
        'OAI-SearchBot' => 'OpenAI Search',
        'PerplexityBot' => 'Perplexity',
        'ClaudeBot' => 'Claude',
        'anthropic-ai' => 'Claude',
        'Google-Extended' => 'Google AI',
        'Bingbot' => 'Bing',
        'CCBot' => 'Common Crawl',
        'Applebot' => 'Applebot',
    ];

    /**
     * صيغة Combined: الآيبي ثم الهوية ثم المستخدم ثم [التاريخ] ثم "الطلب"
     * ثم الحالة ثم الحجم ثم "المُحيل" ثم "الوكيل".
     */
    private const LINE = '/^(\S+) \S+ \S+ \[([^\]]+)\] "(\S+) ([^" ]*)[^"]*" (\d{3}) \S+ "[^"]*" "([^"]*)"/';

    /**
     * @return array<string, mixed>
     */
    public function analyze(string $log, ?Carbon $since = null): array
    {
        $visits = [];
        $unparsed = 0;
        $lines = 0;

        /*
         * الفواصل مسمّاة صراحةً لا `\R`.
         *
         * `\R` بلا `u` يعمل على البايتات فيطابق 0x85 داخل حروف عربية
         * (م = D9 85) ويشطر السطر في منتصف حرف. وإضافة `u` تعالج ذلك لكنها
         * تفتح أسوأ منه: سجل فيه بايت غير صالح — وهو شائع في وكلاء المستخدم —
         * يجعل preg_split يعيد false، فتصير النتيجة «صفر زيارة» صامتة تُقرأ
         * كحكم على الموقع.
         *
         * التسمية الصريحة تعمل على الحالين: نص سليم أو ملف فيه ضجيج ثنائي.
         */
        foreach (preg_split('/\r\n|\r|\n/', $log) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }

            $lines++;

            if (! preg_match(self::LINE, $line, $match)) {
                $unparsed++;

                continue;
            }

            $bot = $this->identify($match[6]);

            if ($bot === null) {
                continue;
            }

            $at = $this->parseDate($match[2]);

            if ($at === null || ($since !== null && $at->lt($since))) {
                continue;
            }

            $visits[] = [
                'bot' => $bot,
                'path' => $match[4],
                'status' => (int) $match[5],
                'at' => $at,
            ];
        }

        return $this->summarize($visits, $lines, $unparsed, $since);
    }

    /**
     * @param  array<int, array<string, mixed>>  $visits
     * @return array<string, mixed>
     */
    private function summarize(array $visits, int $lines, int $unparsed, ?Carbon $since): array
    {
        $byBot = [];
        $byPath = [];
        $blocked = [];

        foreach ($visits as $visit) {
            $bot = $visit['bot'];
            $byBot[$bot] ??= ['bot' => $bot, 'visits' => 0, 'last_seen' => null, 'blocked' => 0];
            $byBot[$bot]['visits']++;

            if ($byBot[$bot]['last_seen'] === null || $visit['at']->gt($byBot[$bot]['last_seen'])) {
                $byBot[$bot]['last_seen'] = $visit['at'];
            }

            /*
             * ٤xx و٥xx على زيارة بوت ليست تفصيلًا تقنيًّا: البوت جاء ولم يقرأ.
             * هذا «ما استُبعد» في تقرير الزحف، وهو أثمن ما فيه — لأنه يكشف
             * صفحات يظنها صاحب النشاط مرئية وهي ليست كذلك.
             */
            if ($visit['status'] >= 400) {
                $byBot[$bot]['blocked']++;
                $blocked[] = ['bot' => $bot, 'path' => $visit['path'], 'status' => $visit['status']];
            }

            $byPath[$visit['path']] ??= ['path' => $visit['path'], 'visits' => 0];
            $byPath[$visit['path']]['visits']++;
        }

        usort($byBot, fn (array $a, array $b) => $b['visits'] <=> $a['visits']);
        usort($byPath, fn (array $a, array $b) => $b['visits'] <=> $a['visits']);

        return [
            'total_visits' => count($visits),
            'bots' => array_map(
                fn (array $row) => $row + ['last_seen' => $row['last_seen']?->toIso8601String()],
                array_values($byBot),
            ),
            'top_paths' => array_slice(array_values($byPath), 0, 20),
            'blocked' => array_slice($blocked, 0, 50),
            'window_start' => $since?->toIso8601String(),

            /*
             * جودة المدخل تُعلَن: سجل نصف مقروء ينتج تقريرًا نصف صادق، وإخفاء
             * ذلك يجعل «صفر زيارة» تبدو حكمًا على الموقع لا على الملف.
             */
            'parsed_lines' => $lines - $unparsed,
            'unparsed_lines' => $unparsed,
            'parse_ratio' => $lines > 0 ? round(($lines - $unparsed) / $lines, 4) : 0.0,
        ];
    }

    /**
     * الحقائق كما تُكتب في الدماغ.
     *
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    public function facts(array $summary): array
    {
        return ['ai_bot_visits_30d' => $summary['total_visits']];
    }

    private function identify(string $userAgent): ?string
    {
        foreach (self::BOTS as $needle => $label) {
            if (stripos($userAgent, $needle) !== false) {
                return $label;
            }
        }

        return null;
    }

    private function parseDate(string $raw): ?Carbon
    {
        try {
            return Carbon::createFromFormat('d/M/Y:H:i:s O', $raw) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
