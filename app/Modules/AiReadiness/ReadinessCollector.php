<?php

namespace App\Modules\AiReadiness;

use App\Models\Project;
use App\Modules\Brain\BrainWriter;
use App\Modules\OwnedAssets\OwnedAssetsCollector;
use App\Modules\Shared\Evidence\EvidenceLevel;
use App\Modules\Shared\Sectors\Sector;

/**
 * يحوّل ما رصده التدقيق وتحليل الزحف إلى حقائق في الدماغ.
 *
 * نقطة الوصل الوحيدة بين الجمع والحساب: `Diagnosis` لا يعرف شيئًا عن HTTP،
 * وهذه الطبقة لا تعرف شيئًا عن الدرجات. ما يمرّ بينهما حقائق مفهرسة بمفاتيح
 * `AxisRegistry`.
 *
 * كل ما يُكتب هنا `measured`: مصدره صفحة حقيقية وسجل خادم حقيقي، لا وصف
 * صاحب النشاط لموقعه. هذا ما يجعل المحور ٧ قابلًا للبيع (§٥).
 */
class ReadinessCollector
{
    public function __construct(
        private readonly SiteAudit $audit,
        private readonly CrawlLogAnalyzer $crawl,
        private readonly BrainWriter $brain,
        private readonly OwnedAssetsCollector $ownedAssets,
    ) {}

    /**
     * تدقيق الموقع وكتابة نتائجه.
     */
    public function collectSiteAudit(Project $project, string $url): SiteAuditResult
    {
        // القطاع المعلن وحده يوجّه الفحص: التدقيق measured ولا يُبنى على ترجيح.
        $result = $this->audit->audit($url, Sector::declaredOrGeneral($project->sector));

        /*
         * موقع تعذّر الوصول إليه لا يكتب شيئًا. لو كتبنا صفرًا لأصبح غياب
         * الاتصال درجةً منخفضة يطاردها صاحب النشاط، والتغطية — لا الدرجة —
         * هي ما يجب أن يعكس أننا لم نستطع الفحص (§٤.٣).
         */
        foreach ($result->facts() as $key => $value) {
            $this->brain->record(
                project: $project,
                key: $key,
                value: $value,
                level: EvidenceLevel::Measured,
                sourceModule: 'AiReadiness',
                sourceReference: 'site_audit:'.$result->url,
            );
        }

        /*
         * المحور ٨ يُغذَّى من الصفحة نفسها: وسيلة الجمع المباشرة تُرصد من
         * HTML الذي جُلب لتوّه، فلا نداء شبكي ثانٍ على موقع العميل.
         *
         * قبل هذا الوصل كان `OwnedAssetsCollector` مبنيًّا بصفر مستدعين، أي
         * أن المحور الثامن لم يكن يُقاس إطلاقًا — لا لأن `owned_ratio` مؤجَّل
         * (وهو قرار معلن)، بل لأن جامعه لم يكن يُستدعى.
         */
        $this->ownedAssets->collectFromSite($project, $result->homepageHtml);

        return $result;
    }

    /**
     * تحليل سجل مرفوع وكتابة نتيجته.
     *
     * @return array<string, mixed>
     */
    public function collectCrawlLog(Project $project, string $log): array
    {
        $summary = $this->crawl->analyze($log, now()->subDays(30));

        /*
         * سجل لم يُقرأ منه شيء لا يكتب «صفر زيارة»: الرقم عندها يصف الملف
         * المرفوع لا حال الموقع، وعرضه كقياس يتّهم موقعًا قد يكون مزارًا.
         */
        if ($summary['parsed_lines'] === 0) {
            return $summary;
        }

        foreach ($this->crawl->facts($summary) as $key => $value) {
            $this->brain->record(
                project: $project,
                key: $key,
                value: $value,
                level: EvidenceLevel::Measured,
                sourceModule: 'AiReadiness',
                sourceReference: 'crawl_log',
                period: now()->subDays(30)->toDateString().'..'.now()->toDateString(),
                metadata: [
                    'parse_ratio' => $summary['parse_ratio'],
                    'bots' => array_column($summary['bots'], 'bot'),
                ],
            );
        }

        return $summary;
    }
}
