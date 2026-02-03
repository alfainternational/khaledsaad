<?php
/**
 * Analytics Dashboard
 * لوحة التحليلات
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'التحليلات';

// Date range
$range = clean($_GET['range'] ?? '30');
$startDate = date('Y-m-d', strtotime("-$range days"));

// Get stats
$stats = [
    'total_leads' => db()->fetchOne("SELECT COUNT(*) as c FROM leads WHERE created_at >= ?", [$startDate])['c'] ?? 0,
    'total_subscribers' => db()->fetchOne("SELECT COUNT(*) as c FROM newsletter_subscribers WHERE created_at >= ?", [$startDate])['c'] ?? 0,
    'total_diagnostics' => db()->fetchOne("SELECT COUNT(*) as c FROM diagnostic_results WHERE created_at >= ?", [$startDate])['c'] ?? 0,
    'total_posts' => db()->fetchOne("SELECT COUNT(*) as c FROM blog_posts WHERE status = 'published'")['c'] ?? 0,
];

// Leads by day
$leadsByDay = db()->fetchAll("
    SELECT DATE(created_at) as date, COUNT(*) as count
    FROM leads
    WHERE created_at >= ?
    GROUP BY DATE(created_at)
    ORDER BY date
", [$startDate]);

// Leads by service
$leadsByService = db()->fetchAll("
    SELECT service_interested, COUNT(*) as count
    FROM leads
    WHERE created_at >= ? AND service_interested IS NOT NULL AND service_interested != ''
    GROUP BY service_interested
    ORDER BY count DESC
", [$startDate]);

// Top blog posts
$topPosts = db()->fetchAll("
    SELECT title, views_count, slug
    FROM blog_posts
    WHERE status = 'published'
    ORDER BY views_count DESC
    LIMIT 5
");

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>التحليلات</h1>
        <p>نظرة عامة على أداء موقعك</p>
    </div>
    <div>
        <select class="form-control" onchange="location.href='?range=' + this.value" style="width: auto;">
            <option value="7" <?= $range == '7' ? 'selected' : '' ?>>آخر 7 أيام</option>
            <option value="30" <?= $range == '30' ? 'selected' : '' ?>>آخر 30 يوم</option>
            <option value="90" <?= $range == '90' ? 'selected' : '' ?>>آخر 90 يوم</option>
            <option value="365" <?= $range == '365' ? 'selected' : '' ?>>آخر سنة</option>
        </select>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary"><i class="fas fa-user-tie"></i></div>
        <div class="stat-content">
            <h3><?= formatNumber($stats['total_leads']) ?></h3>
            <p>عملاء محتملين</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success"><i class="fas fa-envelope"></i></div>
        <div class="stat-content">
            <h3><?= formatNumber($stats['total_subscribers']) ?></h3>
            <p>مشترك جديد</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning"><i class="fas fa-stethoscope"></i></div>
        <div class="stat-content">
            <h3><?= formatNumber($stats['total_diagnostics']) ?></h3>
            <p>تشخيص مكتمل</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon info"><i class="fas fa-newspaper"></i></div>
        <div class="stat-content">
            <h3><?= formatNumber($stats['total_posts']) ?></h3>
            <p>مقال منشور</p>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
    <!-- Leads Chart -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-line"></i> العملاء المحتملين</h3>
        </div>
        <div class="card-body">
            <?php if (!empty($leadsByDay)): ?>
            <div style="display: flex; align-items: flex-end; gap: 4px; height: 200px;">
                <?php
                $maxCount = max(array_column($leadsByDay, 'count'));
                foreach ($leadsByDay as $day):
                    $height = $maxCount > 0 ? ($day['count'] / $maxCount) * 100 : 0;
                ?>
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center;">
                    <div style="width: 100%; background: var(--admin-primary); border-radius: 4px 4px 0 0; height: <?= $height ?>%;" title="<?= $day['date'] ?>: <?= $day['count'] ?>"></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="display: flex; justify-content: space-between; margin-top: 0.5rem; font-size: 0.75rem; color: var(--admin-text-muted);">
                <span><?= $leadsByDay[0]['date'] ?? '' ?></span>
                <span><?= end($leadsByDay)['date'] ?? '' ?></span>
            </div>
            <?php else: ?>
            <p class="text-muted text-center">لا توجد بيانات</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Leads by Service -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie"></i> حسب الخدمة</h3>
        </div>
        <div class="card-body">
            <?php if (!empty($leadsByService)): ?>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <?php
                $total = array_sum(array_column($leadsByService, 'count'));
                $colors = ['#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];
                $i = 0;
                foreach ($leadsByService as $item):
                    $percent = $total > 0 ? round(($item['count'] / $total) * 100) : 0;
                    $color = $colors[$i % count($colors)];
                    $i++;
                ?>
                <div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                        <span style="font-size: 0.875rem;"><?= e($item['service_interested']) ?></span>
                        <span style="font-size: 0.875rem; color: var(--admin-text-muted);"><?= $item['count'] ?></span>
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
</div>

<!-- Top Posts -->
<div class="card mt-4">
    <div class="card-header">
        <h3><i class="fas fa-trophy"></i> أكثر المقالات مشاهدة</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (!empty($topPosts)): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>المقال</th>
                        <th>المشاهدات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topPosts as $i => $post): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><a href="<?= url('pages/blog-post.php?slug=' . $post['slug']) ?>" target="_blank"><?= e($post['title']) ?></a></td>
                        <td><?= formatNumber($post['views_count']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-newspaper"></i>
            <h3>لا توجد مقالات</h3>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
