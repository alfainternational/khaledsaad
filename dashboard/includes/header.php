<?php
/**
 * Client Dashboard Header
 * رأس لوحة تحكم العميل
 */

if (!isset($_SESSION['client_id'])) {
    header('Location: login.php');
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Get client data
try {
    $client = db()->fetchOne("SELECT * FROM clients WHERE id = ?", [$_SESSION['client_id']]);
    if (!$client) {
        session_destroy();
        header('Location: login.php');
        exit;
    }
} catch (Exception $e) {
    $client = ['full_name' => $_SESSION['client_name'] ?? 'عميل', 'email' => ''];
}

// Get unread notifications count (placeholder)
$notificationsCount = 0;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?>لوحة التحكم - <?= SITE_NAME ?></title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Dashboard CSS -->
    <link rel="stylesheet" href="<?= asset('css/dashboard.css') ?>">

    <?php if (isset($pageStyles)): ?>
    <?= $pageStyles ?>
    <?php endif; ?>
</head>
<body class="dashboard-body<?= isset($_COOKIE['darkMode']) && $_COOKIE['darkMode'] === 'true' ? ' dark-mode' : '' ?>">
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="dashboard-sidebar" id="dashboardSidebar">
            <div class="sidebar-header">
                <a href="<?= url('') ?>" class="sidebar-logo">
                    <div style="width: 40px; height: 40px; background: var(--dash-primary); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.25rem;">خ</div>
                    <div>
                        <div class="sidebar-logo-text"><?= SITE_NAME ?></div>
                        <div class="sidebar-logo-sub">لوحة العميل</div>
                    </div>
                </a>
                <button class="sidebar-close" id="sidebarClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">الرئيسية</div>
                    <a href="index.php" class="nav-link <?= $currentPage === 'index' ? 'active' : '' ?>">
                        <i class="fas fa-home"></i>
                        <span>لوحة التحكم</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">خدماتي</div>
                    <a href="consultations.php" class="nav-link <?= $currentPage === 'consultations' ? 'active' : '' ?>">
                        <i class="fas fa-calendar-check"></i>
                        <span>استشاراتي</span>
                    </a>
                    <a href="diagnostics.php" class="nav-link <?= $currentPage === 'diagnostics' ? 'active' : '' ?>">
                        <i class="fas fa-stethoscope"></i>
                        <span>نتائج التشخيص</span>
                    </a>
                    <a href="saved.php" class="nav-link <?= $currentPage === 'saved' ? 'active' : '' ?>">
                        <i class="fas fa-bookmark"></i>
                        <span>المحفوظات</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">الحساب</div>
                    <a href="profile.php" class="nav-link <?= $currentPage === 'profile' ? 'active' : '' ?>">
                        <i class="fas fa-user-circle"></i>
                        <span>الملف الشخصي</span>
                    </a>
                    <a href="settings.php" class="nav-link <?= $currentPage === 'settings' ? 'active' : '' ?>">
                        <i class="fas fa-cog"></i>
                        <span>الإعدادات</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">روابط سريعة</div>
                    <a href="<?= url('services') ?>" class="nav-link" target="_blank">
                        <i class="fas fa-concierge-bell"></i>
                        <span>الخدمات</span>
                    </a>
                    <a href="<?= url('blog') ?>" class="nav-link" target="_blank">
                        <i class="fas fa-newspaper"></i>
                        <span>المدونة</span>
                    </a>
                    <a href="<?= url('contact') ?>" class="nav-link" target="_blank">
                        <i class="fas fa-envelope"></i>
                        <span>تواصل معنا</span>
                    </a>
                </div>
            </nav>

            <div class="sidebar-footer">
                <a href="profile.php" class="user-menu">
                    <div class="user-avatar">
                        <?php if (!empty($client['avatar'])): ?>
                        <img src="<?= e($client['avatar']) ?>" alt="<?= e($client['full_name']) ?>">
                        <?php else: ?>
                        <?= mb_substr($client['full_name'], 0, 1) ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?= e($client['full_name']) ?></div>
                        <div class="user-email"><?= e($client['email']) ?></div>
                    </div>
                    <i class="fas fa-chevron-left"></i>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="dashboard-main">
            <!-- Header -->
            <header class="dashboard-header">
                <div class="header-right">
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title"><?= isset($pageTitle) ? e($pageTitle) : 'لوحة التحكم' ?></h1>
                </div>

                <div class="header-left">
                    <button class="header-btn" id="themeToggle" title="تبديل الوضع">
                        <i class="fas fa-moon"></i>
                    </button>
                    <a href="<?= url('') ?>" class="btn-website" target="_blank">
                        <i class="fas fa-external-link-alt"></i>
                        <span>زيارة الموقع</span>
                    </a>
                    <a href="logout.php" class="header-btn" title="تسجيل الخروج">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </header>

            <!-- Content -->
            <main class="dashboard-content">
