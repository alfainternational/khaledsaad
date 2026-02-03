<?php
/**
 * تقرير التوجيه الاستشاري (خاص بالأدمن) - الإصدار 5.0
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

if (!isset($_SESSION['admin_id'])) {
    redirect(url('admin/login.php'));
}

$id = (int)($_GET['id'] ?? 0);
$report = db()->fetchOne("SELECT * FROM diagnostic_results WHERE id = ?", [$id]);

if (!$report) { die("التقرير غير موجود."); }

$pageTitle = 'التقرير الاستشاري التنفيذي - ' . e($report['full_name']);
include __DIR__ . '/includes/header.php';

$pillars = json_decode($report['pillars_data'] ?? '{}', true);
$answers = json_decode($report['answers'] ?? '[]', true);
$directives = json_decode($report['consultant_report'] ?? '[]', true);
$insights = json_decode($report['insights_data'] ?? '[]', true);
$financials = json_decode($report['financial_analysis'] ?? '[]', true);
?>

<div class="page-header d-flex justify-between items-center no-print">
    <div>
        <h1>التقرير الاستشاري التنفيذي (Internal Only)</h1>
        <p>توجيهات إدارية واستشارية مبنية على تحليل بيانات العميل</p>
    </div>
    <div class="header-actions">
        <a href="diagnostics.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-right mr-2"></i> العودة</a>
        <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print mr-2"></i> طباعة التقرير</button>
    </div>
</div>

<div class="report-container" style="max-width: 1000px; margin: 0 auto; background: white; padding: 50px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
    
    <div class="report-header text-center mb-10" style="border-bottom: 2px solid #eee; padding-bottom: 30px;">
        <h2 style="font-size: 2rem; font-weight: 900; color: #1e293b;">ملف الرؤية الاستشارية لـ: <?= e($report['full_name']) ?></h2>
        <div class="mt-2 text-muted"><?= e($report['company_name']) ?> | <?= translate($report['industry']) ?> | <?= date('Y/m/d H:i') ?></div>
    </div>

    <!-- 1. Executive Summary for Advisor -->
    <div class="section mb-10">
        <h3 style="border-right: 5px solid var(--primary); padding-right: 15px; margin-bottom: 25px;"><i class="fas fa-user-tie mr-2"></i> التوجيهات الاستشارية (للأدمن فقط)</h3>
        <?php if(!empty($directives)): ?>
            <div class="directives-list">
                <?php foreach($directives as $d): ?>
                    <div style="background: #eff6ff; border-right: 4px solid #3b82f6; padding: 20px; border-radius: 12px; margin-bottom: 15px; font-size: 1.1rem; line-height: 1.7;">
                        <i class="fas fa-info-circle text-primary mr-2"></i> <?= e($d) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">لا توجد توجيهات خاصة لهذه الحالة.</p>
        <?php endif; ?>
    </div>

    <!-- 2. Financial Impact & ROI Discussion -->
    <div class="section mb-10">
        <h3 style="border-right: 5px solid #ef4444; padding-right: 15px; margin-bottom: 25px;"><i class="fas fa-money-bill-wave mr-2"></i> تحليل الهدر المالي وفرص الضياع</h3>
        <div class="grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
            <?php foreach($financials as $f): ?>
                <div class="financial-item" style="background: #fef2f2; border: 1px solid #fee2e2; padding: 20px; border-radius: 12px; position: relative; overflow: hidden;">
                    <span style="position: absolute; top: 10px; left: 10px; font-size: 0.6rem; font-weight: 900; padding: 2px 8px; border-radius: 10px; background: #ef4444; color: white;">أثر <?= $f['impact'] ?></span>
                    <strong style="color: #991b1b; display: block; margin-bottom: 10px;"><?= e($f['category']) ?></strong>
                    <div class="small text-muted mb-3"><?= e($f['description']) ?></div>
                    <div class="feedback-actions no-print d-flex gap-2 border-t pt-3">
                        <button onclick="saveAiFeedback(<?= $id ?>, '<?= e($f['category']) ?>', 'positive')" class="btn btn-xs btn-outline-success" title="دقيق ومفيد"><i class="fas fa-thumbs-up"></i></button>
                        <button onclick="saveAiFeedback(<?= $id ?>, '<?= e($f['category']) ?>', 'negative')" class="btn btn-xs btn-outline-danger" title="غير دقيق"><i class="fas fa-thumbs-down"></i></button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 3. Insights & Anomalies -->
    <div class="section mb-10">
        <h3 style="border-right: 5px solid #f59e0b; padding-right: 15px; margin-bottom: 25px;"><i class="fas fa-microscope mr-2"></i> الاستنتاجات العميقة والفجوات</h3>
        <div class="grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <?php foreach($insights as $ins): ?>
                <div style="background: #fffbeb; border: 1px solid #fde68a; padding: 20px; border-radius: 12px;">
                    <strong style="color: #92400e; display: block; margin-bottom: 10px;"><?= e($ins['insight'] ?? 'ملاحظة استراتيجية') ?></strong>
                    <div class="small text-muted mb-3"><strong>الإجراء المقترح في الجلسة:</strong> <?= e($ins['action'] ?? '-') ?></div>
                    <div class="feedback-actions no-print d-flex gap-2 border-t pt-2">
                        <button onclick="saveAiFeedback(<?= $id ?>, 'correlation_<?= md5($ins['insight']) ?>', 'positive')" class="btn btn-xs btn-outline-success"><i class="fas fa-check"></i> صحيح</button>
                        <button onclick="saveAiFeedback(<?= $id ?>, 'correlation_<?= md5($ins['insight']) ?>', 'negative')" class="btn btn-xs btn-outline-danger"><i class="fas fa-times"></i> غير منطقي</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 3. Full Question Tracking (Audit Trail) -->
    <div class="section mb-10">
        <h3 style="border-right: 5px solid #10b981; padding-right: 15px; margin-bottom: 25px;"><i class="fas fa-clipboard-list mr-2"></i> سجل اختيارات العميل (التدقيق)</h3>
        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                    <th style="padding: 12px; text-align: right;">السؤال</th>
                    <th style="padding: 12px; text-align: right;">إجابة العميل</th>
                    <th style="padding: 12px; text-align: right;">النقاط المستحقة</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($answers as $a): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 12px; width: 50%;"><?= e($a['q'] ?? '-') ?></td>
                        <td style="padding: 12px; font-weight: bold; color: #334155;"><?= e($a['a'] ?? 'مسجل بنقاط') ?></td>
                        <td style="padding: 12px; text-align: center;"><span class="badge" style="background:#e2e8f0; color:#475569;"><?= e($a['score'] ?? 0) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- 4. Score Breakdown -->
    <div class="section">
        <h3 style="border-right: 5px solid #6366f1; padding-right: 15px; margin-bottom: 25px;"><i class="fas fa-chart-bar mr-2"></i> تحليل المؤشرات التفصيلي</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; text-align: center;">
            <div style="padding: 15px; background: #f8fafc; border-radius: 12px; border-top: 4px solid #6366f1;">
                <small class="text-muted d-block">جودة العميل</small>
                <strong style="font-size: 1.2rem;"><?= $report['lead_quality_score'] ?>%</strong>
            </div>
            <?php foreach(['strategy' => 'القيادة', 'marketing' => 'النمو', 'tech' => 'التقنية', 'operations' => 'العمليات'] as $k => $l): ?>
                <div style="padding: 15px; background: #f8fafc; border-radius: 12px; border-top: 4px solid #10b981;">
                    <small class="text-muted d-block"><?= $l ?></small>
                    <strong style="font-size: 1.2rem;"><?= $pillars[$k] ?? 0 ?>%</strong>
                </div>
            <?php endforeach; ?>
            <div style="padding: 15px; background: #1e293b; color: white; border-radius: 12px;">
                <small class="opacity-70 d-block">المعدل العام</small>
                <strong style="font-size: 1.2rem;"><?= $report['overall_score'] ?>%</strong>
            </div>
        </div>
    </div>

    <div class="mt-20 p-6 text-center" style="border-top: 2px dashed #eee; font-size: 0.8rem; color: #94a3b8;">
        تم توليد هذا التقرير آلياً بواسطة وحدة "التحليل الاستراتيجي" - جميع الحقوق محفوظة لخالد سعد 2026.
    </div>
</div>

<script>
function saveAiFeedback(reportId, key, type) {
    if (!confirm('سيتم تسجيل رأيك لتحسين دقة محرك التحليل مستقبلاً، هل تود المتابعة؟')) return;
    
    fetch('<?= url('api/ai_feedback.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ report_id: reportId, correlation_key: key, type: type })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert('شكراً لك! تم تسجيل تقييمك بنجاح وسيدخل في تحسين المنظومة القادمة.');
            location.reload();
        } else {
            alert('فشل تسجيل التقييم: ' + res.message);
        }
    });
}
</script>

<style>
@media print {
    body { background: white !important; padding: 0 !important; }
    .report-container { box-shadow: none !important; border: none !important; width: 100% !important; max-width: 100% !important; margin: 0 !important; padding: 20px !important; }
}
</style>

<?php if (isset($_GET['download']) && $_GET['download'] === 'pdf'): ?>
<script>
    window.onload = function() {
        setTimeout(function() {
            window.print();
        }, 1000);
    }
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
