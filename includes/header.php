<?php
/**
 * رأس الصفحة
 */
if (!isset($pageTitle)) $pageTitle = SITE_NAME;
if (!isset($pageDescription)) $pageDescription = SITE_TAGLINE;

// تتبع الزيارة آلياً للذكاء الاصطناعي
logActivity('page_view', ['title' => $pageTitle]);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <?php if (isset($pageKeywords)): ?>
    <meta name="keywords" content="<?= e($pageKeywords) ?>">
    <?php endif; ?>

    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="ar_SA">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/utilities.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/modern.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
<!-- AI Activity Pulse -->
<script>
    let sessionStartTime = Date.now();
    function sendPulse() {
        fetch('<?= url('api/activity_pulse.php') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                duration: Math.round((Date.now() - sessionStartTime) / 1000),
                path: window.location.pathname 
            })
        });
    }
    setInterval(sendPulse, 30000); // إرسال نبض كل 30 ثانية
</script>
<script>
    window.SITE_URL = '<?= url() ?>';
</script>
</head>
<body class="<?= isset($_COOKIE['darkMode']) && $_COOKIE['darkMode'] === 'true' ? 'dark-mode' : '' ?>">
    <a href="#main-content" class="skip-link">تخطي إلى المحتوى</a>

    <?php if (getSetting('promo_active', '0') === '1'): ?>
    <div class="promo-banner" id="promoBanner">
        <div class="container">
            <p>
                <i class="fas fa-gift"></i>
                <span><?= e(getSetting('promo_message', 'عرض خاص: خصم 20% للشهر الأول!')) ?></span>
                <a href="<?= url('pages/contact.php') ?>" class="promo-link">احجز الآن</a>
            </p>
        </div>
        <button type="button" class="promo-close" onclick="closePromo()" aria-label="إغلاق">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php endif; ?>

    <header class="site-header" id="siteHeader">
        <div class="container">
            <nav class="main-nav" role="navigation" aria-label="القائمة الرئيسية">
                <a href="<?= url('') ?>" class="logo" aria-label="<?= SITE_NAME ?>">
                    <span class="logo-text">خالد سعد</span>
                </a>

                <div class="nav-menu" id="navMenu">
                    <ul class="nav-list">
                        <li><a href="<?= url('') ?>" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">الرئيسية</a></li>
                        <li><a href="<?= url('pages/about.php') ?>" class="nav-link">من أنا</a></li>
                        <li><a href="<?= url('pages/services.php') ?>" class="nav-link">الخدمات</a></li>
                        <li><a href="<?= url('pages/case-studies.php') ?>" class="nav-link">دراسات الحالة</a></li>
                        <li><a href="<?= url('pages/blog.php') ?>" class="nav-link">المدونة</a></li>
                    </ul>

                    <div class="header-actions">
                        <button type="button" class="theme-toggle" id="themeToggle" aria-label="تبديل الوضع">
                            <i class="fas fa-moon dark-icon"></i>
                            <i class="fas fa-sun light-icon"></i>
                        </button>
                        <a href="<?= url('pages/contact.php') ?>" class="btn btn-primary btn-sm">احجز استشارة</a>
                    </div>
                </div>

                <button type="button" class="menu-toggle" id="menuToggle" aria-expanded="false" aria-controls="navMenu" aria-label="القائمة">
                    <span class="hamburger"><span></span><span></span><span></span></span>
                </button>
            </nav>
        </div>
    </header>

    <main id="main-content">
