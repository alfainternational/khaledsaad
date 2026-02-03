<?php
/**
 * Client Dashboard
 * لوحة تحكم العميل الرئيسية
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'لوحة التحكم';

// Get stats
try {
    $clientId = $_SESSION['client_id'];

    // Upcoming consultations
    $upcomingConsultations = db()->fetchAll(
        "SELECT c.*, s.name as service_name
         FROM consultations c
         LEFT JOIN services s ON c.service_id = s.id
         WHERE c.client_id = ? AND c.scheduled_at >= NOW() AND c.status NOT IN ('cancelled', 'completed')
         ORDER BY c.scheduled_at ASC
         LIMIT 3",
        [$clientId]
    );

    // Past consultations count
    $pastConsultationsCount = db()->fetchOne(
        "SELECT COUNT(*) as c FROM consultations WHERE client_id = ? AND status = 'completed'",
        [$clientId]
    )['c'] ?? 0;

    // Diagnostic results count
    $diagnosticsCount = db()->fetchOne(
        "SELECT COUNT(*) as c FROM diagnostic_results WHERE client_id = ? AND completed_at IS NOT NULL",
        [$clientId]
    )['c'] ?? 0;

    // Saved posts count
    $savedPostsCount = db()->fetchOne(
        "SELECT COUNT(*) as c FROM saved_posts WHERE client_id = ?",
        [$clientId]
    )['c'] ?? 0;

    // Recent saved posts
    $recentSavedPosts = db()->fetchAll(
        "SELECT p.* FROM blog_posts p
         INNER JOIN saved_posts sp ON p.id = sp.post_id
         WHERE sp.client_id = ? AND p.status = 'published'
         ORDER BY sp.created_at DESC
         LIMIT 3",
        [$clientId]
    );

    // Recent diagnostic
    $recentDiagnostic = db()->fetchOne(
        "SELECT * FROM diagnostic_results WHERE client_id = ? AND completed_at IS NOT NULL ORDER BY completed_at DESC LIMIT 1",
        [$clientId]
    );

} catch (Exception $e) {
    $upcomingConsultations = [];
    $pastConsultationsCount = 0;
    $diagnosticsCount = 0;
    $savedPostsCount = 0;
    $recentSavedPosts = [];
    $recentDiagnostic = null;
}

include __DIR__ . '/includes/header.php';
?>

<!-- Welcome Message -->
<?php if (isset($_GET['welcome'])): ?>
<div class="alert alert-success" style="margin-bottom: 1.5rem;">
    <i class="fas fa-party-horn"></i>
    مرحباً بك في لوحة التحكم! نحن سعداء بانضمامك إلينا.
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1>مرحباً، <?= e($_SESSION['client_name']) ?></h1>
        <p>هذه نظرة عامة على حسابك ونشاطك</p>
    </div>
    <a href="<?= url('contact') ?>" class="btn btn-primary">
        <i class="fas fa-calendar-plus"></i>
        حجز استشارة
    </a>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-content">
            <h3><?= count($upcomingConsultations) ?></h3>
            <p>استشارات قادمة</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon success">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?= $pastConsultationsCount ?></h3>
            <p>استشارات مكتملة</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon info">
            <i class="fas fa-stethoscope"></i>
        </div>
        <div class="stat-content">
            <h3><?= $diagnosticsCount ?></h3>
            <p>نتائج تشخيص</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="fas fa-bookmark"></i>
        </div>
        <div class="stat-content">
            <h3><?= $savedPostsCount ?></h3>
            <p>مقالات محفوظة</p>
        </div>
    </div>
</div>

<!-- Main Content -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
    <!-- Upcoming Consultations -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-alt"></i> استشاراتي القادمة</h3>
            <a href="consultations.php" class="btn btn-sm btn-secondary">عرض الكل</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (!empty($upcomingConsultations)): ?>
                <?php foreach ($upcomingConsultations as $consultation): ?>
                <?php
                    $date = new DateTime($consultation['scheduled_at']);
                    $arabicMonths = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
                    $statusLabels = [
                        'pending' => ['label' => 'بانتظار التأكيد', 'class' => 'warning'],
                        'confirmed' => ['label' => 'مؤكدة', 'class' => 'success'],
                        'rescheduled' => ['label' => 'معاد جدولتها', 'class' => 'info'],
                    ];
                    $status = $statusLabels[$consultation['status']] ?? ['label' => $consultation['status'], 'class' => 'secondary'];
                ?>
                <div class="consultation-card" style="margin: 1rem; margin-bottom: 0;">
                    <div class="consultation-date">
                        <div class="day"><?= $date->format('d') ?></div>
                        <div class="month"><?= $arabicMonths[$date->format('n') - 1] ?></div>
                    </div>
                    <div class="consultation-info">
                        <h4><?= e($consultation['title']) ?></h4>
                        <div class="consultation-meta">
                            <span><i class="fas fa-clock"></i> <?= $date->format('h:i A') ?></span>
                            <span><i class="fas fa-hourglass-half"></i> <?= $consultation['duration'] ?> دقيقة</span>
                            <?php if ($consultation['service_name']): ?>
                            <span><i class="fas fa-tag"></i> <?= e($consultation['service_name']) ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="badge badge-<?= $status['class'] ?>"><?= $status['label'] ?></span>
                    </div>
                    <div class="consultation-actions">
                        <?php if ($consultation['meeting_link'] && $consultation['status'] === 'confirmed'): ?>
                        <a href="<?= e($consultation['meeting_link']) ?>" class="btn btn-primary btn-sm" target="_blank">
                            <i class="fas fa-video"></i> انضمام
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-plus"></i>
                <h3>لا توجد استشارات قادمة</h3>
                <p>احجز استشارة جديدة للحصول على المساعدة</p>
                <a href="<?= url('contact') ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> حجز استشارة
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Side Panel -->
    <div>
        <!-- Latest Diagnostic -->
        <?php if ($recentDiagnostic): ?>
        <?php
            $score = $recentDiagnostic['score'];
            $level = $score >= 70 ? 'high' : ($score >= 40 ? 'medium' : 'low');
            $levelLabel = $score >= 70 ? 'ممتاز' : ($score >= 40 ? 'متوسط' : 'يحتاج تحسين');
        ?>
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3><i class="fas fa-stethoscope"></i> آخر تشخيص</h3>
            </div>
            <div class="card-body">
                <div class="diagnostic-header">
                    <div class="diagnostic-score">
                        <div class="score-circle <?= $level ?>">
                            <?= $score ?>%
                        </div>
                        <div>
                            <div style="font-weight: 600;"><?= $levelLabel ?></div>
                            <div style="font-size: 0.8rem; color: var(--dash-text-muted);">
                                <?= formatDate($recentDiagnostic['completed_at']) ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="diagnostic-progress">
                    <div class="bar <?= $level ?>" style="width: <?= $score ?>%;"></div>
                </div>
                <a href="diagnostics.php" class="btn btn-secondary btn-sm btn-block">
                    عرض التفاصيل
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Saved Posts -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bookmark"></i> المحفوظات</h3>
                <a href="saved.php" class="btn btn-sm btn-secondary">عرض الكل</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (!empty($recentSavedPosts)): ?>
                    <?php foreach ($recentSavedPosts as $post): ?>
                    <div class="saved-post">
                        <?php if ($post['featured_image']): ?>
                        <div class="saved-post-image">
                            <img src="<?= e($post['featured_image']) ?>" alt="<?= e($post['title']) ?>">
                        </div>
                        <?php endif; ?>
                        <div class="saved-post-info">
                            <h4><a href="<?= url('blog/' . $post['slug']) ?>"><?= e($post['title']) ?></a></h4>
                            <div class="saved-post-meta">
                                <i class="fas fa-eye"></i> <?= formatNumber($post['views_count']) ?> مشاهدة
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                <div class="empty-state" style="padding: 2rem;">
                    <i class="fas fa-bookmark" style="font-size: 2rem;"></i>
                    <p style="margin: 0.5rem 0 0;">لا توجد مقالات محفوظة</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card" style="margin-top: 1.5rem;">
            <div class="card-header">
                <h3><i class="fas fa-bolt"></i> إجراءات سريعة</h3>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <a href="<?= url('diagnostic') ?>" class="btn btn-secondary btn-block" style="justify-content: flex-start;">
                        <i class="fas fa-stethoscope"></i> ابدأ تشخيص جديد
                    </a>
                    <a href="<?= url('services') ?>" class="btn btn-secondary btn-block" style="justify-content: flex-start;">
                        <i class="fas fa-concierge-bell"></i> تصفح الخدمات
                    </a>
                    <a href="<?= url('blog') ?>" class="btn btn-secondary btn-block" style="justify-content: flex-start;">
                        <i class="fas fa-newspaper"></i> اقرأ المدونة
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
