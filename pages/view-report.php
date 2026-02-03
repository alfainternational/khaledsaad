<?php
/**
 * صفحة عرض التقرير الاستراتيجي الذكي للعميل
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_once SITE_ROOT . '/includes/diagnostic_engine.php';

$token = $_GET['token'] ?? '';
if (empty($token)) {
    redirect(url('index.php'));
}

$report = db()->fetchOne("SELECT * FROM diagnostic_results WHERE report_token = ?", [$token]);

if (!$report) {
    die("التقرير غير موجود أو تم حذفه.");
}

$pageTitle = 'تقرير نضج الأعمال الاستراتيجي - ' . ($report['full_name'] ?: 'عميلنا العزيز');
include SITE_ROOT . '/includes/header.php';

// معالجة البيانات
$pillars = json_decode($report['pillars_data'] ?? '[]', true);
$answers = json_decode($report['answers'] ?? '[]', true);

// إذا كانت خارطة الطريق موجودة مسبقاً (v4.0) نستخدمها، وإلا نولدها (v3.1)
if (!empty($report['roadmap_data'])) {
    $roadmap = json_decode($report['roadmap_data'], true);
} else {
    // محرك قديم أو استدعاء للمحرك الحالي
    require_once SITE_ROOT . '/includes/diagnostic_engine.php';
    $roadmap = DiagnosticEngine::generate3TierRoadmap($answers, $report['industry'], $report['company_size'], $pillars);
}

// تلخيص ذكي
$summary = "";
if (!empty($report['pillars_data'])) {
    require_once SITE_ROOT . '/includes/diagnostic_engine.php';
    $summary = DiagnosticEngine::generateSmartSummary($report['overall_score'], $pillars, $report['industry'], $report['benchmark_score']);
}
?>

<style>
.report-view-section { padding: 100px 0; background: var(--bg-secondary); }
.report-header { text-align: center; margin-bottom: 60px; }
.score-circle {
    width: 180px; height: 180px;
    border-radius: 50%;
    margin: 0 auto;
    display: flex; align-items: center; justify-content: center;
    flex-direction: column;
    background: white;
    border: 10px solid var(--primary);
    box-shadow: var(--shadow-lg);
}
.report-card { background: white; border-radius: 24px; padding: 40px; box-shadow: var(--shadow-sm); margin-bottom: 30px; }
.roadmap-item {
    background: #F9FAFB; border-right: 4px solid var(--primary);
    padding: 20px; border-radius: 12px; margin-bottom: 15px;
}
@media print {
    header, footer, .no-print { display: none !important; }
    body { background: white !important; }
    .report-view-section { padding: 0; }
}
</style>

<section class="report-view-section">
    <div class="container">
        <div class="no-print mb-8">
            <button onclick="window.print()" class="btn btn-outline-primary"><i class="fas fa-print mr-2"></i> حفظ كـ PDF</button>
        </div>

        <div class="report-header">
            <span class="pill mb-4"><?= $report['company_name'] ?></span>
            <h1 class="display-1 mb-8">التقرير الاستراتيجي للمنشأة</h1>
            
            <div class="score-circle">
                <div class="h1 font-black mb-0" style="color:var(--primary); font-size: 3.5rem;"><?= $report['overall_score'] ?>%</div>
                <div class="font-bold text-sm">مؤشر النضج SBMI</div>
            </div>
            
            <h3 class="mt-8 text-primary"><?= $report['maturity_level'] ?></h3>
        </div>

        <div class="row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 40px;">
            <div class="report-card">
                <h3 class="mb-6"><i class="fas fa-chart-line text-primary mr-2"></i> الخلاصة التنفيذية</h3>
                <div style="line-height: 1.8; font-size: 1.1rem; color: #334155;">
                    <?php if(!empty($report['ai_narrative'])): ?>
                        <div class="p-4 mb-6 rounded-xl bg-blue-50 border-r-4 border-blue-500 italic" style="background:#f0f7ff;">
                            <?= nl2br(e($report['ai_narrative'])) ?>
                        </div>
                    <?php endif; ?>
                    <?= $summary ?>
                </div>
                
                <?php if(!empty($report['swot_analysis'])): 
                    $swot = json_decode($report['swot_analysis'], true); ?>
                    <div class="mt-8 border-t pt-8">
                        <h4 class="mb-4 text-sm uppercase opacity-60">تحليل ركائزك الحالية:</h4>
                        <?php foreach(['strategy' => 'القيادة', 'marketing' => 'النمو', 'tech' => 'التقنية', 'operations' => 'العمليات'] as $pk => $pl): 
                             if(!isset($swot[$pk])) continue; ?>
                            <div class="mb-4 p-4 rounded-xl bg-gray-50 border border-gray-100">
                                <strong class="d-block mb-3 text-primary border-b pb-2"><?= $pl ?></strong>
                                <div class="grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                    <div class="small">
                                        <div class="text-success font-bold"><i class="fas fa-plus-circle mr-1"></i> نقاط القوة والمزايا:</div>
                                        <ul style="padding-right: 15px; margin-top: 5px;">
                                            <?php foreach(array_merge($swot[$pk]['strengths'] ?? [], $swot[$pk]['pros'] ?? []) as $s) echo "<li>$s</li>"; ?>
                                        </ul>
                                    </div>
                                    <div class="small">
                                        <div class="text-danger font-bold"><i class="fas fa-minus-circle mr-1"></i> نقاط الضعف والمخاطر:</div>
                                        <ul style="padding-right: 15px; margin-top: 5px;">
                                            <?php foreach(array_merge($swot[$pk]['weaknesses'] ?? [], $swot[$pk]['cons'] ?? []) as $w) echo "<li>$w</li>"; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="report-card">
                <h3 class="mb-6"><i class="fas fa-clipboard-check text-primary mr-2"></i> سجل اختياراتك</h3>
                <div style="max-height: 600px; overflow-y: auto; padding-right: 10px;">
                    <?php foreach($answers as $ans): ?>
                        <div class="mb-4 pb-4 border-b last:border-0 border-gray-50">
                            <div class="text-xs text-muted mb-1"><?= e($ans['q']) ?></div>
                            <div class="font-bold text-sm" style="color:#1e293b;"><?= e($ans['a'] ?? 'مسجل بنقاط') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-8 pt-6 border-t">
                    <h4 class="mb-4"><i class="fas fa-chart-pie text-primary mr-2"></i> درجاتك التفصيلية</h4>
                    <?php 
                    foreach(['strategy' => 'القيادة', 'marketing' => 'النمو', 'tech' => 'التقنية', 'operations' => 'العمليات'] as $key => $label): 
                        $val = $pillars[$key] ?? 0; ?>
                        <div class="mb-4">
                            <div class="d-flex justify-between mb-1">
                                <span class="text-sm"><?= $label ?></span>
                                <span class="text-sm font-bold"><?= $val ?>%</span>
                            </div>
                            <div style="height: 6px; background: #f1f5f9; border-radius: 3px; overflow:hidden;">
                                <div style="height:100%; width: <?= $val ?>%; background: var(--primary);"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Roadmap -->
        <h2 class="text-center mb-10"><i class="fas fa-rocket text-primary mr-2"></i> خارطة الطريق التنفيذية 2026</h2>
        
        <div class="row" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
            <!-- Phase 1 -->
            <div class="report-card" style="border-top: 6px solid #EF4444;">
                <h4 class="mb-6">المرحلة 1: التدفق النقدي</h4>
                <?php foreach($roadmap['p1'] as $item): ?>
                <div class="roadmap-item">
                    <div class="font-bold mb-1"><?= $item['title'] ?></div>
                    <p class="small text-muted mb-0"><?= $item['advice'] ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Phase 2 -->
            <div class="report-card" style="border-top: 6px solid #F59E0B;">
                <h4 class="mb-6">المرحلة 2: الأنظمة</h4>
                <?php foreach($roadmap['p2'] as $item): ?>
                <div class="roadmap-item" style="border-right-color: #F59E0B;">
                    <div class="font-bold mb-1"><?= $item['title'] ?></div>
                    <p class="small text-muted mb-0"><?= $item['advice'] ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Phase 3 -->
            <div class="report-card" style="border-top: 6px solid #10B981;">
                <h4 class="mb-6">المرحلة 3: الريادة</h4>
                <?php foreach($roadmap['p3'] as $item): ?>
                <div class="roadmap-item" style="border-right-color: #10B981;">
                    <div class="font-bold mb-1"><?= $item['title'] ?></div>
                    <p class="small text-muted mb-0"><?= $item['advice'] ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="report-card text-center mt-10 no-print" style="background: var(--gradient-primary); color: white;">
            <h2 class="text-white mb-4">هل ترغب في البدء فوراً؟</h2>
            <p class="mb-8 opacity-80">بصفتي خبير تحول رقمي، سأساعدك في سد الفجوات المكتشفة وتطبيق الأتمتة في صلب عملياتك.</p>
            <a href="<?= url('pages/contact.php') ?>" class="btn btn-white btn-lg">احجز جلستك الاستشارية المجانية</a>
        </div>
    </div>
</section>

<?php if (isset($_GET['download']) && $_GET['download'] === 'pdf'): ?>
<script>
    window.onload = function() {
        setTimeout(function() {
            window.print();
        }, 1000);
    }
</script>
<?php endif; ?>

<?php include SITE_ROOT . '/includes/footer.php'; ?>
