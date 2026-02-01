<?php
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'لوحة التحكم';

// إحصائيات
try {
    $stats = [
        'leads' => db()->fetchOne("SELECT COUNT(*) as count FROM leads")['count'] ?? 0,
        'leads_new' => db()->fetchOne("SELECT COUNT(*) as count FROM leads WHERE status = 'new'")['count'] ?? 0,
        'posts' => db()->fetchOne("SELECT COUNT(*) as count FROM blog_posts WHERE status = 'published'")['count'] ?? 0,
        'subscribers' => db()->fetchOne("SELECT COUNT(*) as count FROM newsletter_subscribers WHERE status = 'confirmed'")['count'] ?? 0,
    ];
    $recentLeads = db()->fetchAll("SELECT * FROM leads ORDER BY created_at DESC LIMIT 5");
} catch (Exception $e) {
    $stats = ['leads' => 0, 'leads_new' => 0, 'posts' => 0, 'subscribers' => 0];
    $recentLeads = [];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= SITE_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <style>
        .admin-layout { display: flex; min-height: 100vh; }
        .admin-sidebar { width: 260px; background: var(--bg-secondary); border-left: 1px solid var(--border-color); padding: var(--space-6); position: fixed; height: 100vh; overflow-y: auto; }
        .admin-content { flex: 1; margin-right: 260px; padding: var(--space-6); }
        .admin-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-6); padding-bottom: var(--space-4); border-bottom: 1px solid var(--border-color); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-8); }
        .stat-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: var(--space-5); }
        .stat-card .icon { width: 50px; height: 50px; border-radius: var(--radius); display: flex; align-items: center; justify-content: center; margin-bottom: var(--space-3); }
        .stat-card h3 { font-size: var(--font-size-2xl); margin-bottom: var(--space-1); }
        .stat-card p { color: var(--text-muted); font-size: var(--font-size-sm); margin: 0; }
        .nav-menu { list-style: none; margin-top: var(--space-6); }
        .nav-menu li { margin-bottom: var(--space-2); }
        .nav-menu a { display: flex; align-items: center; gap: var(--space-3); padding: var(--space-3) var(--space-4); border-radius: var(--radius); color: var(--text-secondary); transition: all var(--transition); }
        .nav-menu a:hover, .nav-menu a.active { background: var(--primary); color: white; }
        .nav-menu i { width: 20px; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: var(--space-3) var(--space-4); text-align: right; border-bottom: 1px solid var(--border-color); }
        .data-table th { background: var(--bg-tertiary); font-weight: 600; }
        .status-badge { padding: var(--space-1) var(--space-3); border-radius: var(--radius-full); font-size: var(--font-size-xs); }
        .status-new { background: rgba(37, 99, 235, 0.1); color: var(--primary); }
        .sidebar-logo { margin-bottom: var(--space-6); }
        @media (max-width: 992px) { .admin-sidebar { display: none; } .admin-content { margin-right: 0; } }
    </style>
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="sidebar-logo">
                <span class="logo-text">خالد سعد</span>
                <span class="logo-tagline" style="display: block; font-size: var(--font-size-xs); color: var(--primary);">لوحة التحكم</span>
            </div>
            <ul class="nav-menu">
                <li><a href="index.php" class="active"><i class="fas fa-home"></i> الرئيسية</a></li>
                <li><a href="leads.php"><i class="fas fa-users"></i> العملاء المحتملين</a></li>
                <li><a href="blog.php"><i class="fas fa-newspaper"></i> المدونة</a></li>
                <li><a href="subscribers.php"><i class="fas fa-envelope"></i> المشتركين</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> الإعدادات</a></li>
                <li style="margin-top: var(--space-8);"><a href="<?= url('') ?>" target="_blank"><i class="fas fa-external-link-alt"></i> عرض الموقع</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a></li>
            </ul>
        </aside>

        <main class="admin-content">
            <header class="admin-header">
                <div>
                    <h1 style="font-size: var(--font-size-2xl); margin-bottom: var(--space-1);">مرحباً، <?= e($_SESSION['admin_name']) ?></h1>
                    <p style="color: var(--text-muted); margin: 0;"><?= formatDate(date('Y-m-d'), 'full') ?></p>
                </div>
                <a href="logout.php" class="btn btn-secondary btn-sm"><i class="fas fa-sign-out-alt"></i></a>
            </header>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="icon" style="background: rgba(37, 99, 235, 0.1);"><i class="fas fa-users" style="color: var(--primary); font-size: 1.25rem;"></i></div>
                    <h3><?= formatNumber($stats['leads']) ?></h3>
                    <p>إجمالي العملاء المحتملين</p>
                </div>
                <div class="stat-card">
                    <div class="icon" style="background: rgba(16, 185, 129, 0.1);"><i class="fas fa-user-plus" style="color: var(--success); font-size: 1.25rem;"></i></div>
                    <h3><?= formatNumber($stats['leads_new']) ?></h3>
                    <p>عملاء جدد</p>
                </div>
                <div class="stat-card">
                    <div class="icon" style="background: rgba(245, 158, 11, 0.1);"><i class="fas fa-newspaper" style="color: var(--accent); font-size: 1.25rem;"></i></div>
                    <h3><?= formatNumber($stats['posts']) ?></h3>
                    <p>مقالات منشورة</p>
                </div>
                <div class="stat-card">
                    <div class="icon" style="background: rgba(139, 92, 246, 0.1);"><i class="fas fa-envelope" style="color: #8b5cf6; font-size: 1.25rem;"></i></div>
                    <h3><?= formatNumber($stats['subscribers']) ?></h3>
                    <p>مشترك في النشرة</p>
                </div>
            </div>

            <div class="card" style="padding: var(--space-6);">
                <h2 style="font-size: var(--font-size-lg); margin-bottom: var(--space-4);">أحدث العملاء المحتملين</h2>
                <?php if (!empty($recentLeads)): ?>
                <table class="data-table">
                    <thead>
                        <tr><th>الاسم</th><th>البريد</th><th>الخدمة</th><th>الحالة</th><th>التاريخ</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentLeads as $lead): ?>
                        <tr>
                            <td><?= e($lead['full_name']) ?></td>
                            <td><?= e($lead['email']) ?></td>
                            <td><?= e($lead['service_interested'] ?: '-') ?></td>
                            <td><span class="status-badge status-<?= $lead['status'] ?>"><?= e($lead['status']) ?></span></td>
                            <td><?= formatDate($lead['created_at'], 'short') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="color: var(--text-muted); text-align: center; padding: var(--space-8);">لا توجد بيانات</p>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
