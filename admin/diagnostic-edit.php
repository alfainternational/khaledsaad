<?php
/**
 * صفحة تعديل ومعاينة التقرير الاستراتيجي (للمسؤول) - الإصدار 4.0
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';
require_once SITE_ROOT . '/includes/diagnostic_engine.php';

// التحقق من الصلاحيات (Admin Only)
if (!isset($_SESSION['admin_id'])) {
    redirect(url('admin/login.php'));
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    die("معرف التقرير غير موجود.");
}

$report = db()->fetchOne("SELECT * FROM diagnostic_results WHERE id = ?", [$id]);
if (!$report) {
    die("التقرير غير موجود.");
}

// معالجة الحفظ عند الإرسال
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // توليد توكن إذا كان مفقوداً
    $token = $report['report_token'];
    if (isset($_POST['regen_token']) || empty($token)) {
        $token = bin2hex(random_bytes(16));
    }

    $updatedData = [
        'full_name' => clean($_POST['full_name']),
        'company_name' => clean($_POST['company_name']),
        'overall_score' => (int)$_POST['overall_score'],
        'score' => (int)$_POST['overall_score'],
        'maturity_level' => clean($_POST['maturity_level']),
        'category' => clean($_POST['maturity_level']),
        'industry' => clean($_POST['industry']),
        'company_size' => clean($_POST['company_size']),
        'lead_source' => clean($_POST['lead_source'] ?? 'direct'),
        'benchmark_score' => (int)$_POST['benchmark_score'],
        'report_token' => $token,
        'status' => $_POST['status']
    ];
    
    // تحديث Pillars
    $pillars = [
        'strategy' => (int)$_POST['pillar_strategy'],
        'marketing' => (int)$_POST['pillar_marketing'],
        'tech' => (int)$_POST['pillar_tech']
    ];
    $updatedData['pillars_data'] = json_encode($pillars);

    try {
        db()->update('diagnostic_results', $updatedData, 'id = ?', [$id]);
        $successMsg = "تم تحديث بيانات التقرير بنجاح.";
        // إعادة تحميل البيانات
        $report = db()->fetchOne("SELECT * FROM diagnostic_results WHERE id = ?", [$id]);
    } catch (Exception $e) {
        $errorMsg = "خطأ أثناء التحديث: " . $e->getMessage();
    }
}

$pageTitle = 'تعديل ومعاينة التقرير - ' . ($report['full_name'] ?: 'عميل');
include __DIR__ . '/includes/header.php';

// بيانات المعاينة
$pillars = json_decode($report['pillars_data'] ?: '{}', true);
$answers = json_decode($report['answers'] ?: '[]', true);

// استخدام خارطة الطريق المحفوظة أو توليدها للمعاينة
if (!empty($report['roadmap_data'])) {
    $roadmap = json_decode($report['roadmap_data'], true);
} else {
    $roadmap = DiagnosticEngine::generate3TierRoadmap($answers, $report['industry'], $report['company_size'], $pillars);
}

// التلخيص الذكي
$summary = DiagnosticEngine::generateSmartSummary($report['overall_score'], $pillars, $report['industry'], $report['benchmark_score']);
?>

<div class="page-header d-flex justify-between items-center">
    <div>
        <h1>تعديل التقرير: <?= e($report['full_name'] ?: 'زائر مجهول') ?></h1>
        <p>قم بتخصيص النتائج أو تصحيح التوكن قبل وصولها للعميل</p>
    </div>
    <div class="header-actions">
        <a href="diagnostics.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-right mr-2"></i> العودة</a>
        <?php if(!empty($report['report_token'])): ?>
        <a href="<?= url('pages/view-report.php?token=' . $report['report_token']) ?>" target="_blank" class="btn btn-primary"><i class="fas fa-eye mr-2"></i> النسخة النهائية</a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($successMsg)): ?><div class="alert alert-success mt-4"><?= $successMsg ?></div><?php endif; ?>
<?php if (isset($errorMsg)): ?><div class="alert alert-danger mt-4"><?= $errorMsg ?></div><?php endif; ?>

<div class="grid" style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 30px; margin-top: 30px;">
    <!-- Edit Form -->
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-edit mr-2"></i> البيانات الأساسية</h3></div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group mb-4">
                    <label class="form-label">الاسم الكامل</label>
                    <input type="text" name="full_name" class="form-control" value="<?= e($report['full_name']) ?>">
                </div>
                <div class="form-group mb-4">
                    <label class="form-label">اسم منشأة</label>
                    <input type="text" name="company_name" class="form-control" value="<?= e($report['company_name']) ?>">
                </div>

                <div class="row mb-4" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">القطاع</label>
                        <select name="industry" class="form-control">
                            <?php 
                            $inds = ['ecommerce' => 'تجارة إلكترونية', 'services' => 'خدمات', 'tech' => 'تقنية', 'retail' => 'تجزئة', 'fmcg' => 'سلع', 'other' => 'أخرى'];
                            foreach($inds as $k => $v) echo "<option value='$k' ".($report['industry'] == $k ? 'selected' : '').">$v</option>";
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">المصدر</label>
                        <input type="text" name="lead_source" class="form-control" value="<?= e($report['lead_source'] ?? 'direct') ?>">
                    </div>
                </div>

                <div class="row mb-4" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">درجة نضج العام (%)</label>
                        <input type="number" name="overall_score" class="form-control" value="<?= $report['overall_score'] ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">مستوى النضج</label>
                        <input type="text" name="maturity_level" class="form-control" value="<?= e($report['maturity_level']) ?>">
                    </div>
                </div>

                <div class="row mb-6" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">
                    <div class="form-group"><label class="form-label">القيادة</label><input type="number" name="pillar_strategy" class="form-control" value="<?= $pillars['strategy'] ?? 0 ?>"></div>
                    <div class="form-group"><label class="form-label">التسويق</label><input type="number" name="pillar_marketing" class="form-control" value="<?= $pillars['marketing'] ?? 0 ?>"></div>
                    <div class="form-group"><label class="form-label">التقنية</label><input type="number" name="pillar_tech" class="form-control" value="<?= $pillars['tech'] ?? 0 ?>"></div>
                </div>

                <div class="form-group mb-6">
                    <label class="form-label">توكن التقرير (رابط المعاينة)</label>
                    <div class="d-flex gap-2">
                        <input type="text" readonly class="form-control" value="<?= e($report['report_token'] ?: 'لا يوجد توكن') ?>">
                        <button type="submit" name="regen_token" class="btn btn-sm btn-outline-warning" style="white-space:nowrap;">توليد جديد</button>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="form-label">الحالة</label>
                    <select name="status" class="form-control">
                        <option value="pending_review" <?= $report['status'] == 'pending_review' ? 'selected' : '' ?>>معلق (Pending)</option>
                        <option value="sent" <?= $report['status'] == 'sent' ? 'selected' : '' ?>>تم الإرسال (Sent)</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100"><i class="fas fa-save mr-2"></i> حفظ ومزامنة البيانات</button>
            </form>
        </div>
    </div>

    <!-- Live Preview -->
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-desktop mr-2"></i> معاينة حية للعميل</h3></div>
        <div class="card-body" style="background: #F3F4F6; padding: 20px;">
            <div style="background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-height: 800px; overflow-y: auto;">
                <div class="text-center mb-10">
                    <h2 style="font-size: 1.8rem; font-weight: 900;"><?= e($report['company_name'] ?: 'المنشأة') ?></h2>
                    <div style="width: 100px; height: 100px; border-radius: 50%; border: 6px solid var(--primary); display: flex; align-items: center; justify-content: center; margin: 20px auto;">
                        <span style="font-size: 1.8rem; font-weight: 900; color: var(--primary);"><?= $report['overall_score'] ?>%</span>
                    </div>
                </div>
                <div style="background: #F9FAFB; padding: 15px; border-radius: 12px; margin-bottom: 20px;">
                    <h5 class="font-bold mb-3">التحليل:</h5>
                    <p style="font-size: 0.9rem; line-height: 1.6;"><?= $summary ?></p>
                </div>
                <div class="roadmap">
                    <h5 class="font-bold mb-4">خارطة الطريق:</h5>
                    <?php foreach(['p1','p2','p3'] as $p): ?>
                        <div style="margin-bottom: 15px; padding: 10px; border-right: 3px solid var(--primary); background: #fff;">
                            <strong style="font-size: 0.8rem;">المرحلة <?= str_replace(['p1','p2','p3'],[1,2,3],$p) ?></strong>
                            <?php foreach($roadmap[$p] as $r): ?>
                                <div class="small mt-1"><strong><?= e($r['title']) ?></strong></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
