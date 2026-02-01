<?php
require_once dirname(__DIR__) . '/includes/init.php';

$code = intval($_GET['code'] ?? 404);
$errors = [
    400 => ['title' => 'طلب غير صالح', 'message' => 'الطلب الذي أرسلته غير صالح.'],
    401 => ['title' => 'غير مصرح', 'message' => 'يجب تسجيل الدخول للوصول لهذه الصفحة.'],
    403 => ['title' => 'محظور', 'message' => 'ليس لديك صلاحية للوصول لهذه الصفحة.'],
    404 => ['title' => 'الصفحة غير موجودة', 'message' => 'عذراً، الصفحة التي تبحث عنها غير موجودة.'],
    500 => ['title' => 'خطأ في الخادم', 'message' => 'حدث خطأ غير متوقع. يرجى المحاولة لاحقاً.']
];

$error = $errors[$code] ?? $errors[404];
$pageTitle = $error['title'] . ' - ' . SITE_NAME;

http_response_code($code);
include dirname(__DIR__) . '/includes/header.php';
?>

<section style="min-height: 60vh; display: flex; align-items: center; justify-content: center; padding: var(--space-16) 0;">
    <div class="container" style="text-align: center;">
        <div style="font-size: 8rem; font-weight: 800; color: var(--primary); opacity: 0.3; margin-bottom: var(--space-4);"><?= $code ?></div>
        <h1 style="font-size: var(--font-size-3xl); margin-bottom: var(--space-4);"><?= e($error['title']) ?></h1>
        <p style="font-size: var(--font-size-lg); color: var(--text-secondary); margin-bottom: var(--space-8); max-width: 500px; margin-left: auto; margin-right: auto;"><?= e($error['message']) ?></p>
        <div style="display: flex; gap: var(--space-4); justify-content: center; flex-wrap: wrap;">
            <a href="<?= url('') ?>" class="btn btn-primary"><i class="fas fa-home"></i> العودة للرئيسية</a>
            <a href="<?= url('pages/contact.php') ?>" class="btn btn-secondary"><i class="fas fa-envelope"></i> تواصل معنا</a>
        </div>
    </div>
</section>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
