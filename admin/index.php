<?php
/**
 * Admin Dashboard
 * لوحة التحكم الرئيسية
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'لوحة التحكم';

// Get stats
try {
    $stats = [
        'leads_total' => db()->fetchOne("SELECT COUNT(*) as c FROM leads")['c'] ?? 0,
        'leads_new' => db()->fetchOne("SELECT COUNT(*) as c FROM leads WHERE status = 'new'")['c'] ?? 0,
        'leads_month' => db()->fetchOne("SELECT COUNT(*) as c FROM leads WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")['c'] ?? 0,
        'posts_total' => db()->fetchOne("SELECT COUNT(*) as c FROM blog_posts WHERE status = 'published'")['c'] ?? 0,
        'subscribers' => db()->fetchOne("SELECT COUNT(*) as c FROM newsletter_subscribers WHERE status = 'confirmed'")['c'] ?? 0,
        'diagnostics' => db()->fetchOne("SELECT COUNT(*) as c FROM diagnostic_results WHERE completed_at IS NOT NULL")['c'] ?? 0,
    ];

    // Recent leads
    $recentLeads = db()->fetchAll("SELECT * FROM leads ORDER BY created_at DESC LIMIT 5");

    // Recent posts
    $recentPosts = db()->fetchAll("SELECT * FROM blog_posts ORDER BY created_at DESC LIMIT 5");

    // Lead status distribution
    $leadsByStatus = db()->fetchAll("SELECT status, COUNT(*) as count FROM leads GROUP BY status");

} catch (Exception $e) {
    $stats = ['leads_total' => 0, 'leads_new' => 0, 'leads_month' => 0, 'posts_total' => 0, 'subscribers' => 0, 'diagnostics' => 0];
    $recentLeads = [];
    $recentPosts = [];
    $leadsByStatus = [];
}

include __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1>مرحباً، <?= e($_SESSION['admin_name']) ?></h1>
        <p>هذه نظرة عامة على نشاط موقعك</p>
    </div>
    <div class="quick-actions">
        <a href="post-edit.php" class="btn btn-primary"><i class="fas fa-plus"></i> مقال جديد</a>
        <a href="leads.php" class="btn btn-secondary"><i class="fas fa-users"></i> عرض العملاء</a>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="fas fa-user-tie"></i>
        </div>
        <div class="stat-content">
            <h3><?= formatNumber($stats['leads_total']) ?></h3>
            <p>إجمالي العملاء المحتملين</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon success">
            <i class="fas fa-user-plus"></i>
        </div>
        <div class="stat-content">
            <h3><?= formatNumber($stats['leads_new']) ?></h3>
            <p>عملاء جدد</p>
            <?php if ($stats['leads_new'] > 0): ?>
            <span class="stat-change up">يتطلب انتباهك</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="fas fa-calendar-alt"></i>
        </div>
        <div class="stat-content">
            <h3><?= formatNumber($stats['leads_month']) ?></h3>
            <p>عملاء هذا الشهر</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon info">
            <i class="fas fa-newspaper"></i>
        </div>
        <div class="stat-content">
            <h3><?= formatNumber($stats['posts_total']) ?></h3>
            <p>مقالات منشورة</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon danger">
            <i class="fas fa-envelope"></i>
        </div>
        <div class="stat-content">
            <h3><?= formatNumber($stats['subscribers']) ?></h3>
            <p>مشترك في النشرة</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="fas fa-stethoscope"></i>
        </div>
        <div class="stat-content">
            <h3><?= formatNumber($stats['diagnostics']) ?></h3>
            <p>تشخيص مكتمل</p>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
    <!-- Recent Leads -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-tie"></i> أحدث العملاء المحتملين</h3>
            <a href="leads.php" class="btn btn-sm btn-secondary">عرض الكل</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (!empty($recentLeads)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>الاسم</th>
                            <th>البريد</th>
                            <th>الخدمة</th>
                            <th>الحالة</th>
                            <th>التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentLeads as $lead): ?>
                        <tr>
                            <td>
                                <a href="lead-view.php?id=<?= $lead['id'] ?>" class="text-primary">
                                    <?= e($lead['full_name']) ?>
                                </a>
                            </td>
                            <td><?= e($lead['email']) ?></td>
                            <td><?= e($lead['service_interested'] ?: '-') ?></td>
                            <td>
                                <?php
                                $statusLabels = [
                                    'new' => ['label' => 'جديد', 'class' => 'primary'],
                                    'contacted' => ['label' => 'تم التواصل', 'class' => 'info'],
                                    'qualified' => ['label' => 'مؤهل', 'class' => 'success'],
                                    'proposal' => ['label' => 'عرض سعر', 'class' => 'warning'],
                                    'negotiation' => ['label' => 'تفاوض', 'class' => 'warning'],
                                    'won' => ['label' => 'مكتمل', 'class' => 'success'],
                                    'lost' => ['label' => 'خسارة', 'class' => 'danger'],
                                ];
                                $status = $statusLabels[$lead['status']] ?? ['label' => $lead['status'], 'class' => 'secondary'];
                                ?>
                                <span class="badge badge-<?= $status['class'] ?>"><?= $status['label'] ?></span>
                            </td>
                            <td><?= formatDate($lead['created_at'], 'short') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <h3>لا يوجد عملاء</h3>
                <p>لم يتم استلام أي طلبات بعد</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Side Panel -->
    <div>
        <!-- Lead Status Chart -->
        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="fas fa-chart-pie"></i> حالة العملاء</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($leadsByStatus)): ?>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <?php
                    $statusColors = [
                        'new' => '#2563eb',
                        'contacted' => '#3b82f6',
                        'qualified' => '#10b981',
                        'proposal' => '#f59e0b',
                        'negotiation' => '#f97316',
                        'won' => '#22c55e',
                        'lost' => '#ef4444',
                    ];
                    $total = array_sum(array_column($leadsByStatus, 'count'));
                    foreach ($leadsByStatus as $item):
                        $percent = $total > 0 ? round(($item['count'] / $total) * 100) : 0;
                        $color = $statusColors[$item['status']] ?? '#94a3b8';
                        $label = $statusLabels[$item['status']]['label'] ?? $item['status'];
                    ?>
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                            <span style="font-size: 0.875rem;"><?= $label ?></span>
                            <span style="font-size: 0.875rem; color: var(--admin-text-muted);"><?= $item['count'] ?> (<?= $percent ?>%)</span>
                        </div>
                        <div style="height: 8px; background: var(--admin-border); border-radius: 4px; overflow: hidden;">
                            <div style="height: 100%; width: <?= $percent ?>%; background: <?= $color ?>;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted text-center">لا توجد بيانات</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bolt"></i> روابط سريعة</h3>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <a href="post-edit.php" class="btn btn-secondary w-100" style="justify-content: flex-start;">
                        <i class="fas fa-plus"></i> إضافة مقال جديد
                    </a>
                    <a href="services.php" class="btn btn-secondary w-100" style="justify-content: flex-start;">
                        <i class="fas fa-concierge-bell"></i> إدارة الخدمات
                    </a>
                    <a href="settings.php" class="btn btn-secondary w-100" style="justify-content: flex-start;">
                        <i class="fas fa-cog"></i> الإعدادات
                    </a>
                    <a href="<?= url('') ?>" target="_blank" class="btn btn-secondary w-100" style="justify-content: flex-start;">
                        <i class="fas fa-external-link-alt"></i> عرض الموقع
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
