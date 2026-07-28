<?php

namespace Tests\Feature;

use App\Modules\AiReadiness\CrawlLogAnalyzer;
use App\Modules\Diagnosis\Axis;
use App\Modules\Diagnosis\AxisRegistry;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * تقرير الزحف: هل أنا مرئي للنماذج أصلًا؟
 *
 * سجلات ثابتة بصيغة Combined، وهي ما يخرج فعلًا من cPanel وApache.
 */
class CrawlLogAnalysisTest extends TestCase
{
    private const AGENT_GPT = 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; GPTBot/1.1; +https://openai.com/gptbot';

    private const AGENT_HUMAN = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0';

    #[Test]
    public function it_counts_only_ai_bots_not_human_traffic(): void
    {
        $log = $this->line('/', 200, self::AGENT_GPT)
            ."\n".$this->line('/products', 200, self::AGENT_HUMAN)
            ."\n".$this->line('/about', 200, 'PerplexityBot/1.0');

        $summary = app(CrawlLogAnalyzer::class)->analyze($log);

        $this->assertSame(2, $summary['total_visits']);
        $this->assertEqualsCanonicalizing(
            ['GPTBot', 'Perplexity'],
            array_column($summary['bots'], 'bot'),
        );
    }

    #[Test]
    public function a_bot_that_arrived_and_was_refused_is_the_most_valuable_row(): void
    {
        $log = $this->line('/', 200, self::AGENT_GPT)
            ."\n".$this->line('/catalog', 403, self::AGENT_GPT)
            ."\n".$this->line('/pricing', 404, self::AGENT_GPT);

        $summary = app(CrawlLogAnalyzer::class)->analyze($log);

        // «جاء ولم يقرأ» يكشف صفحات يظنها صاحب النشاط مرئية وهي ليست كذلك.
        $this->assertSame(2, $summary['bots'][0]['blocked']);
        $this->assertEqualsCanonicalizing(
            ['/catalog', '/pricing'],
            array_column($summary['blocked'], 'path'),
        );
    }

    #[Test]
    public function an_empty_log_reports_zero_visits_without_pretending_to_know_more(): void
    {
        $summary = app(CrawlLogAnalyzer::class)->analyze('');

        $this->assertSame(0, $summary['total_visits']);
        $this->assertSame([], $summary['bots']);
        $this->assertSame(0.0, $summary['parse_ratio']);
    }

    #[Test]
    public function an_unreadable_log_declares_its_own_quality(): void
    {
        $log = "سطر غير مفهوم\n".$this->line('/', 200, self::AGENT_GPT)."\nنص آخر";

        $summary = app(CrawlLogAnalyzer::class)->analyze($log);

        // سجل نصف مقروء ينتج تقريرًا نصف صادق. إخفاء ذلك يجعل «صفر زيارة»
        // تبدو حكمًا على الموقع لا على الملف المرفوع.
        $this->assertSame(1, $summary['parsed_lines']);
        $this->assertSame(2, $summary['unparsed_lines']);
        $this->assertSame(0.3333, $summary['parse_ratio']);
    }

    #[Test]
    public function arabic_paths_do_not_split_lines_in_the_middle_of_a_character(): void
    {
        /*
         * `\R` بلا مُعدِّل يونيكود يطابق البايت 0x85، وهو بايت الاستمرار في
         * حروف عربية شائعة (م = D9 85). السطر الواحد كان ينشطر إلى ثلاثة،
         * فتتضخم الأسطر غير المقروءة ويبدو السجل تالفًا وهو سليم.
         *
         * منتج عربي بالكامل: هذا الاختبار يمنع عودة العطل.
         */
        $log = $this->line('/منتجات/عسل-سدر', 200, self::AGENT_GPT);

        $summary = app(CrawlLogAnalyzer::class)->analyze($log);

        $this->assertSame(1, $summary['total_visits']);
        $this->assertSame(0, $summary['unparsed_lines']);
        $this->assertSame('/منتجات/عسل-سدر', $summary['top_paths'][0]['path']);
    }

    #[Test]
    public function a_log_with_invalid_bytes_is_still_read(): void
    {
        // وكلاء المستخدم يحملون أحيانًا بايتات غير صالحة. الاعتماد على
        // مُعدِّل يونيكود كان سيجعل السجل كله يُقرأ كأنه فارغ.
        $log = $this->line('/', 200, self::AGENT_GPT)."\n".chr(0xFF).chr(0xFE).'ضجيج';

        $summary = app(CrawlLogAnalyzer::class)->analyze($log);

        $this->assertSame(1, $summary['total_visits']);
        $this->assertSame(1, $summary['unparsed_lines']);
    }

    #[Test]
    public function the_window_excludes_older_visits(): void
    {
        $log = $this->line('/', 200, self::AGENT_GPT, now()->subDays(60))
            ."\n".$this->line('/new', 200, self::AGENT_GPT, now()->subDays(3));

        $summary = app(CrawlLogAnalyzer::class)->analyze($log, now()->subDays(30));

        $this->assertSame(1, $summary['total_visits']);
        $this->assertSame('/new', $summary['top_paths'][0]['path']);
    }

    #[Test]
    public function the_fact_it_writes_is_the_key_the_axis_reads(): void
    {
        $analyzer = app(CrawlLogAnalyzer::class);
        $summary = $analyzer->analyze($this->line('/', 200, self::AGENT_GPT));

        $keys = app(AxisRegistry::class)
            ->keysFor(Axis::AiReadiness);

        foreach (array_keys($analyzer->facts($summary)) as $key) {
            $this->assertContains($key, $keys, "المفتاح {$key} لا يقرأه المحور ٧.");
        }
    }

    private function line(string $path, int $status, string $agent, ?Carbon $at = null): string
    {
        $stamp = ($at ?? now())->format('d/M/Y:H:i:s O');

        return sprintf(
            '66.249.66.1 - - [%s] "GET %s HTTP/1.1" %d 1234 "-" "%s"',
            $stamp, $path, $status, $agent,
        );
    }
}
