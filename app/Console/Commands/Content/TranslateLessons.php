<?php

namespace App\Console\Commands\Content;

use App\Models\Content;
use App\Models\ContentTranslation;
use App\Modules\Learning\LessonTranslator;
use App\Modules\Shared\I18n\LocaleRegistry;
use Illuminate\Console\Command;
use Throwable;

/**
 * خبز ترجمات الدروس — بناءً لا وقت طلب.
 *
 * نفس منطق `i18n:translate` ولنفس الأسباب الثلاثة (الثبات، التكلفة،
 * قابلية المراجعة البشرية)، مطبَّقًا على المحتوى بدل نصوص الواجهة.
 *
 * تزايُديّ بالضرورة: الدرس يُترجَم مرة، ويُعاد فقط إن تغيّر أصله العربي
 * — وهو ما تكشفه مقارنة `source_text_hash` لا تخمين. تشغيل الأمر مرتين
 * متتاليتين لا يكلّف استدعاءً واحدًا.
 *
 * `--force` يعيد ترجمة ما ترجمه النموذج، ولا يمسّ ما راجعه إنسان:
 * الصفّ الذي صحّحه مترجم بشري أثمن من أي مخرج نموذج.
 */
final class TranslateLessons extends Command
{
    protected $signature = 'content:translate-lessons
                            {--locale=* : اللغات المطلوبة (الافتراضي: كل المفعّلة عدا لغة المصدر)}
                            {--source-key=marketing-course-% : نمط `source_key` للمحتوى المطلوب}
                            {--limit=0 : حدّ أقصى لعدد الدروس في هذا التشغيل}
                            {--force : أعد ترجمة ما ترجمه النموذج (لا يمسّ المراجَع بشريًّا)}
                            {--dry-run : اعرض ما سيُترجَم دون استدعاء النموذج}';

    protected $description = 'ترجمة دروس الأكاديمية إلى اللغات المفعّلة وحفظها كطبقة فوق الأصل';

    public function handle(LocaleRegistry $locales, LessonTranslator $translator): int
    {
        $targets = (array) $this->option('locale');
        $targets = $targets === [] ? $locales->targets() : $targets;
        $limit = (int) $this->option('limit');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $lessons = Content::query()
            ->where('source_key', 'like', (string) $this->option('source-key'))
            ->orderBy('learning_order')
            ->get();

        if ($lessons->isEmpty()) {
            $this->components->error('لا يوجد محتوى مطابق. شغّل `content:import-marketing-course` أولًا.');

            return self::FAILURE;
        }

        $exit = self::SUCCESS;

        foreach ($targets as $locale) {
            if (! $locales->isEnabled($locale) || $locale === $locales->source()) {
                $this->components->warn("تخطّي «{$locale}»: غير مفعّلة أو أنها لغة المصدر.");

                continue;
            }

            $this->components->info("اللغة: {$locale}");
            $done = 0;

            foreach ($lessons as $lesson) {
                if ($limit > 0 && $done >= $limit) {
                    $this->components->warn("بلغ الحدّ ({$limit}) — بقي ".($lessons->count() - $done).' درسًا لهذه اللغة.');

                    break;
                }

                $sourceHash = (string) $lesson->source_text_hash;
                $existing = ContentTranslation::query()
                    ->where('content_id', $lesson->getKey())
                    ->where('locale', $locale)
                    ->first();

                if ($existing?->isHumanReviewed()) {
                    $this->line("  = {$lesson->slug} — مراجَع بشريًّا، لا يُمَسّ");

                    continue;
                }

                if ($existing !== null && ! $existing->isStaleAgainst($sourceHash) && ! $force) {
                    $this->line("  = {$lesson->slug} — مترجَم ومحدَّث");

                    continue;
                }

                $reason = $existing === null ? 'جديد' : 'تقادم الأصل';

                if ($dryRun) {
                    $this->line("  ~ {$lesson->slug} — سيُترجَم ({$reason})");
                    $done++;

                    continue;
                }

                try {
                    $this->translateLesson($lesson, $locale, $sourceHash, $translator);
                    $done++;
                } catch (Throwable $exception) {
                    report($exception);
                    $this->components->error("  × {$lesson->slug} — {$exception->getMessage()}");
                    $exit = self::FAILURE;
                }
            }
        }

        return $exit;
    }

    private function translateLesson(
        Content $lesson,
        string $locale,
        string $sourceHash,
        LessonTranslator $translator,
    ): void {
        $body = $translator->translateHtml((string) $lesson->body_html, $locale, 'lesson.body');

        /*
         * الترجمة الناقصة لا تُحفظ صامتة. لو فشل ثلث عقد النص لصار الدرس
         * خليطًا من لغتين يبدو عطلًا في العرض لا حدًّا في الترجمة، فيُرفض
         * ويُعاد في تشغيل لاحق (§٤.٢: لا قياس ولا مخرج من محاولة واحدة).
         */
        $total = $body['translated'] + $body['missing'];

        if ($total > 0 && $body['missing'] / $total > 0.02) {
            throw new \RuntimeException(sprintf(
                'تعذّرت ترجمة %d من %d مقطعًا — لم تُحفظ.',
                $body['missing'],
                $total,
            ));
        }

        $meta = $translator->translateFields([
            'title' => $lesson->title,
            'excerpt' => $lesson->excerpt,
            'seo_title' => $lesson->seo_title,
            'seo_description' => $lesson->seo_description,
        ], $locale);

        $blocks = is_array($lesson->body_json) ? $translator->translateBlocks($lesson->body_json, $locale) : null;

        ContentTranslation::query()->updateOrCreate(
            ['content_id' => $lesson->getKey(), 'locale' => $locale],
            [
                'title' => $meta['title'] ?? $lesson->title,
                'excerpt' => $meta['excerpt'] ?? null,
                'body_html' => $body['html'],
                'body_json' => $blocks,
                'seo_title' => $meta['seo_title'] ?? null,
                'seo_description' => $meta['seo_description'] ?? null,
                'source_text_hash' => $sourceHash,
                'translator' => 'ai',
            ],
        );

        $this->line("  ✓ {$lesson->slug} — {$body['translated']} مقطعًا");
    }
}
