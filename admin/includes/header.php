<?php
/**
 * Admin Header Template
 */
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'لوحة التحكم') ?> - <?= SITE_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body class="<?= isset($_COOKIE['darkMode']) && $_COOKIE['darkMode'] === 'true' ? 'dark-mode' : '' ?>">
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="admin-sidebar" id="adminSidebar">
            <div class="sidebar-header">
                <a href="index.php" class="sidebar-logo">
                    <span class="logo-icon">خ</span>
                    <div class="logo-text">
                        <span class="logo-name">خالد سعد</span>
                        <span class="logo-subtitle">لوحة التحكم</span>
                    </div>
                </a>
                <button class="sidebar-close" id="sidebarClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section">
                    <span class="nav-section-title">الرئيسية</span>
                    <ul class="nav-menu">
                        <li>
                            <a href="index.php" class="<?= $currentPage === 'index' ? 'active' : '' ?>">
                                <i class="fas fa-home"></i>
                                <span>لوحة التحكم</span>
                            </a>
                        </li>
                        <li>
                            <a href="analytics.php" class="<?= $currentPage === 'analytics' ? 'active' : '' ?>">
                                <i class="fas fa-chart-bar"></i>
                                <span>التحليلات</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="nav-section">
                    <span class="nav-section-title">إدارة المحتوى</span>
                    <ul class="nav-menu">
                        <li>
                            <a href="leads.php" class="<?= $currentPage === 'leads' || $currentPage === 'lead-view' ? 'active' : '' ?>">
                                <i class="fas fa-user-tie"></i>
                                <span>العملاء المحتملين</span>
                                <?php
                                $newLeads = db()->fetchOne("SELECT COUNT(*) as c FROM leads WHERE status = 'new'")['c'] ?? 0;
                                if ($newLeads > 0): ?>
                                <span class="nav-badge"><?= $newLeads ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li>
                            <a href="posts.php" class="<?= in_array($currentPage, ['posts', 'post-edit', 'post-new']) ? 'active' : '' ?>">
                                <i class="fas fa-newspaper"></i>
                                <span>المقالات</span>
                            </a>
                        </li>
                        <li>
                            <a href="categories.php" class="<?= $currentPage === 'categories' ? 'active' : '' ?>">
                                <i class="fas fa-folder"></i>
                                <span>التصنيفات</span>
                            </a>
                        </li>
                        <li>
                            <a href="services.php" class="<?= in_array($currentPage, ['services', 'service-edit']) ? 'active' : '' ?>">
                                <i class="fas fa-concierge-bell"></i>
                                <span>الخدمات</span>
                            </a>
                        </li>
                        <li>
                            <a href="pricing.php" class="<?= in_array($currentPage, ['pricing', 'pricing-edit']) ? 'active' : '' ?>">
                                <i class="fas fa-tags"></i>
                                <span>باقات الأسعار</span>
                            </a>
                        </li>
                        <li>
                            <a href="stories.php" class="<?= in_array($currentPage, ['stories', 'story-edit']) ? 'active' : '' ?>">
                                <i class="fas fa-trophy"></i>
                                <span>قصص النجاح</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="nav-section">
                    <span class="nav-section-title">التسويق</span>
                    <ul class="nav-menu">
                        <li>
                            <a href="subscribers.php" class="<?= $currentPage === 'subscribers' ? 'active' : '' ?>">
                                <i class="fas fa-envelope"></i>
                                <span>المشتركين</span>
                            </a>
                        </li>
                        <li>
                            <a href="diagnostics.php" class="<?= $currentPage === 'diagnostics' ? 'active' : '' ?>">
                                <i class="fas fa-stethoscope"></i>
                                <span>نتائج التشخيص</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="nav-section">
                    <span class="nav-section-title">الإعدادات</span>
                    <ul class="nav-menu">
                        <li>
                            <a href="settings.php" class="<?= $currentPage === 'settings' ? 'active' : '' ?>">
                                <i class="fas fa-cog"></i>
                                <span>الإعدادات العامة</span>
                            </a>
                        </li>
                        <li>
                            <a href="users.php" class="<?= in_array($currentPage, ['users', 'user-edit']) ? 'active' : '' ?>">
                                <i class="fas fa-users-cog"></i>
                                <span>المستخدمين</span>
                            </a>
                        </li>
                        <li>
                            <a href="activity.php" class="<?= $currentPage === 'activity' ? 'active' : '' ?>">
                                <i class="fas fa-history"></i>
                                <span>سجل النشاطات</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="nav-section">
                    <ul class="nav-menu">
                        <li>
                            <a href="<?= url('') ?>" target="_blank">
                                <i class="fas fa-external-link-alt"></i>
                                <span>عرض الموقع</span>
                            </a>
                        </li>
                        <li>
                            <a href="logout.php" class="text-danger">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>تسجيل الخروج</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="admin-main">
            <!-- Top Header -->
            <header class="admin-header">
                <div class="header-right">
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="breadcrumb">
                        <a href="index.php">الرئيسية</a>
                        <?php if (isset($pageTitle) && $pageTitle !== 'لوحة التحكم'): ?>
                        <i class="fas fa-chevron-left"></i>
                        <span><?= e($pageTitle) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="header-left">
                    <button class="header-btn" id="themeToggle" title="تبديل الوضع">
                        <i class="fas fa-moon dark-icon"></i>
                        <i class="fas fa-sun light-icon"></i>
                    </button>
                    <div class="user-dropdown">
                        <button class="user-btn">
                            <span class="user-avatar"><?= mb_substr($_SESSION['admin_name'], 0, 1) ?></span>
                            <span class="user-name"><?= e($_SESSION['admin_name']) ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="profile.php"><i class="fas fa-user"></i> الملف الشخصي</a>
                            <a href="settings.php"><i class="fas fa-cog"></i> الإعدادات</a>
                            <hr>
                            <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> خروج</a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="admin-content">
