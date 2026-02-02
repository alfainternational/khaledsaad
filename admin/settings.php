<?php
/**
 * Site Settings
 * الإعدادات العامة
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'الإعدادات';

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'جلسة غير صالحة';
    } else {
        $settings = [
            // Site Info
            'site_name' => clean($_POST['site_name'] ?? ''),
            'site_tagline' => clean($_POST['site_tagline'] ?? ''),
            'site_email' => clean($_POST['site_email'] ?? ''),
            'site_phone' => clean($_POST['site_phone'] ?? ''),

            // Expert Info
            'expert_name' => clean($_POST['expert_name'] ?? ''),
            'expert_title' => clean($_POST['expert_title'] ?? ''),
            'expert_bio' => clean($_POST['expert_bio'] ?? ''),

            // Social Media
            'social_twitter' => clean($_POST['social_twitter'] ?? ''),
            'social_linkedin' => clean($_POST['social_linkedin'] ?? ''),
            'social_instagram' => clean($_POST['social_instagram'] ?? ''),
            'social_youtube' => clean($_POST['social_youtube'] ?? ''),
            'whatsapp_number' => clean($_POST['whatsapp_number'] ?? ''),

            // Promo Banner
            'promo_active' => isset($_POST['promo_active']) ? '1' : '0',
            'promo_message' => clean($_POST['promo_message'] ?? ''),

            // SEO
            'meta_title' => clean($_POST['meta_title'] ?? ''),
            'meta_description' => clean($_POST['meta_description'] ?? ''),
            'meta_keywords' => clean($_POST['meta_keywords'] ?? ''),

            // Scripts
            'google_analytics' => clean($_POST['google_analytics'] ?? ''),
            'facebook_pixel' => clean($_POST['facebook_pixel'] ?? ''),
            'custom_head_scripts' => $_POST['custom_head_scripts'] ?? '',
            'custom_footer_scripts' => $_POST['custom_footer_scripts'] ?? '',
        ];

        try {
            foreach ($settings as $key => $value) {
                $exists = db()->fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$key]);
                if ($exists) {
                    db()->update('settings', ['setting_value' => $value], 'setting_key = ?', ['setting_key' => $key]);
                } else {
                    db()->insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
                }
            }
            Security::logActivity('settings_updated', 'settings', 0);
            $success = 'تم حفظ الإعدادات بنجاح';
        } catch (Exception $e) {
            $error = 'حدث خطأ أثناء الحفظ';
        }
    }
}

// Get all settings
$settingsRaw = db()->fetchAll("SELECT setting_key, setting_value FROM settings");
$settings = [];
foreach ($settingsRaw as $s) {
    $settings[$s['setting_key']] = $s['setting_value'];
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>الإعدادات</h1>
        <p>إعدادات الموقع العامة</p>
    </div>
</div>

<?php if (isset($success)): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
<?php endif; ?>

<form method="POST">
    <?= Security::csrfField() ?>

    <!-- Tabs -->
    <div class="tabs">
        <button type="button" class="tab-btn active" data-tab="general">معلومات الموقع</button>
        <button type="button" class="tab-btn" data-tab="social">التواصل الاجتماعي</button>
        <button type="button" class="tab-btn" data-tab="seo">SEO</button>
        <button type="button" class="tab-btn" data-tab="scripts">الأكواد</button>
    </div>

    <div class="tabs-content">
        <!-- General Settings -->
        <div class="tab-content active" data-tab-content="general">
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-globe"></i> معلومات الموقع</h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">اسم الموقع</label>
                            <input type="text" name="site_name" class="form-control" value="<?= e($settings['site_name'] ?? SITE_NAME) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الشعار النصي</label>
                            <input type="text" name="site_tagline" class="form-control" value="<?= e($settings['site_tagline'] ?? SITE_TAGLINE) ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">البريد الإلكتروني</label>
                            <input type="email" name="site_email" class="form-control" value="<?= e($settings['site_email'] ?? SITE_EMAIL) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">رقم الهاتف</label>
                            <input type="text" name="site_phone" class="form-control" value="<?= e($settings['site_phone'] ?? '') ?>" dir="ltr">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-user-tie"></i> معلومات الخبير</h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">اسم الخبير</label>
                            <input type="text" name="expert_name" class="form-control" value="<?= e($settings['expert_name'] ?? EXPERT_NAME) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">المسمى الوظيفي</label>
                            <input type="text" name="expert_title" class="form-control" value="<?= e($settings['expert_title'] ?? EXPERT_TITLE) ?>">
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">نبذة مختصرة</label>
                        <textarea name="expert_bio" class="form-control" rows="3"><?= e($settings['expert_bio'] ?? EXPERT_BIO) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-bullhorn"></i> شريط الإعلان</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-check">
                            <input type="checkbox" name="promo_active" value="1" <?= ($settings['promo_active'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <span>تفعيل شريط الإعلان</span>
                        </label>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">نص الإعلان</label>
                        <input type="text" name="promo_message" class="form-control" value="<?= e($settings['promo_message'] ?? '') ?>" placeholder="عرض خاص: خصم 20% للشهر الأول!">
                    </div>
                </div>
            </div>
        </div>

        <!-- Social Media -->
        <div class="tab-content" data-tab-content="social">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-share-alt"></i> روابط التواصل الاجتماعي</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label"><i class="fab fa-whatsapp text-success"></i> واتساب</label>
                        <input type="text" name="whatsapp_number" class="form-control" value="<?= e($settings['whatsapp_number'] ?? '') ?>" placeholder="966500000000" dir="ltr">
                        <span class="form-hint">الرقم بدون + أو 00</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fab fa-twitter text-info"></i> تويتر / X</label>
                        <input type="url" name="social_twitter" class="form-control" value="<?= e($settings['social_twitter'] ?? '') ?>" placeholder="https://twitter.com/username" dir="ltr">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fab fa-linkedin text-primary"></i> لينكد إن</label>
                        <input type="url" name="social_linkedin" class="form-control" value="<?= e($settings['social_linkedin'] ?? '') ?>" placeholder="https://linkedin.com/in/username" dir="ltr">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fab fa-instagram text-danger"></i> انستقرام</label>
                        <input type="url" name="social_instagram" class="form-control" value="<?= e($settings['social_instagram'] ?? '') ?>" placeholder="https://instagram.com/username" dir="ltr">
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label"><i class="fab fa-youtube text-danger"></i> يوتيوب</label>
                        <input type="url" name="social_youtube" class="form-control" value="<?= e($settings['social_youtube'] ?? '') ?>" placeholder="https://youtube.com/@channel" dir="ltr">
                    </div>
                </div>
            </div>
        </div>

        <!-- SEO -->
        <div class="tab-content" data-tab-content="seo">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-search"></i> تحسين محركات البحث</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">عنوان الصفحة الرئيسية</label>
                        <input type="text" name="meta_title" class="form-control" value="<?= e($settings['meta_title'] ?? '') ?>" data-max-length="60">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الوصف</label>
                        <textarea name="meta_description" class="form-control" rows="3" data-max-length="160"><?= e($settings['meta_description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">الكلمات المفتاحية</label>
                        <input type="text" name="meta_keywords" class="form-control" value="<?= e($settings['meta_keywords'] ?? '') ?>" placeholder="كلمة1, كلمة2, كلمة3">
                    </div>
                </div>
            </div>
        </div>

        <!-- Scripts -->
        <div class="tab-content" data-tab-content="scripts">
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> التحليلات</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Google Analytics ID</label>
                        <input type="text" name="google_analytics" class="form-control" value="<?= e($settings['google_analytics'] ?? '') ?>" placeholder="G-XXXXXXXXXX" dir="ltr">
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Facebook Pixel ID</label>
                        <input type="text" name="facebook_pixel" class="form-control" value="<?= e($settings['facebook_pixel'] ?? '') ?>" placeholder="XXXXXXXXXXXXXXX" dir="ltr">
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-code"></i> أكواد مخصصة</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">أكواد قبل &lt;/head&gt;</label>
                        <textarea name="custom_head_scripts" class="form-control" rows="4" dir="ltr" style="font-family: monospace; font-size: 0.875rem;"><?= e($settings['custom_head_scripts'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">أكواد قبل &lt;/body&gt;</label>
                        <textarea name="custom_footer_scripts" class="form-control" rows="4" dir="ltr" style="font-family: monospace; font-size: 0.875rem;"><?= e($settings['custom_footer_scripts'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div style="margin-top: 1.5rem;">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="fas fa-save"></i> حفظ الإعدادات
        </button>
    </div>
</form>

<?php include __DIR__ . '/includes/footer.php'; ?>
