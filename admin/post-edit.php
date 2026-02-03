<?php
/**
 * Post Edit/Create
 * تعديل/إنشاء مقال - واجهة احترافية متكاملة
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

$id = (int)($_GET['id'] ?? 0);
$post = null;

if ($id) {
    $post = db()->fetchOne("SELECT * FROM blog_posts WHERE id = ?", [$id]);
    if (!$post) {
        header('Location: posts.php');
        exit;
    }
    $pageTitle = 'تعديل المقال: ' . mb_substr($post['title'], 0, 30) . (mb_strlen($post['title']) > 30 ? '...' : '');
} else {
    $pageTitle = 'مقال جديد';
    // تهيئة مصفوفة فارغة لتجنب تحذيرات PHP (Warnings on null)
    $post = [
        'title' => '', 'slug' => '', 'content' => '', 'excerpt' => '', 
        'meta_title' => '', 'meta_description' => '', 'featured_image' => '', 
        'category_id' => 0, 'status' => 'draft', 'created_at' => date('Y-m-d H:i:s')
    ];
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'جلسة غير صالحة';
    } else {
        $title = clean($_POST['title'] ?? '');
        $slug = clean($_POST['slug'] ?? '') ?: generateSlug($title);
        $content = $_POST['content'] ?? ''; 
        $excerpt = clean($_POST['excerpt'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
        $status = in_array($_POST['status'] ?? '', ['draft', 'published']) ? $_POST['status'] : 'draft';
        $featuredImage = clean($_POST['featured_image'] ?? '');
        $metaTitle = clean($_POST['meta_title'] ?? '');
        $metaDescription = clean($_POST['meta_description'] ?? '');

        // معالجة رفع الصورة الفعلية
        if (isset($_FILES['featured_image_file']) && $_FILES['featured_image_file']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $filename = $_FILES['featured_image_file']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $newName = 'post_' . time() . '_' . uniqid() . '.' . $ext;
                $uploadDir = SITE_ROOT . '/uploads/posts/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                
                if (move_uploaded_file($_FILES['featured_image_file']['tmp_name'], $uploadDir . $newName)) {
                    $featuredImage = url('uploads/posts/' . $newName);
                }
            } else {
                $error = 'نوع ملف الصورة غير مدعوم';
            }
        }

        if (!$title && !isset($error)) {
            $error = 'العنوان مطلوب';
        } 
        
        if (!isset($error)) {
            $data = [
                'title' => $title,
                'slug' => $slug,
                'content' => $content,
                'excerpt' => $excerpt,
                'category_id' => $categoryId,
                'status' => $status,
                'featured_image' => $featuredImage,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'author_id' => $_SESSION['admin_id'] ?? 1,
            ];

            try {
                if ($id) {
                    db()->update('blog_posts', $data, 'id = ?', ['id' => $id]);
                    Security::logActivity('post_updated', 'blog_posts', $id);
                    $success = 'تم تحديث المقال بنجاح';
                    $post = array_merge($post, $data);
                } else {
                    if ($status === 'published') {
                        $data['published_at'] = date('Y-m-d H:i:s');
                    }
                    $newId = db()->insert('blog_posts', $data);
                    Security::logActivity('post_created', 'blog_posts', $newId);
                    header('Location: post-edit.php?id=' . $newId . '&created=1');
                    exit;
                }
            } catch (Exception $e) {
                $error = 'حدث خطأ أثناء الحفظ: ' . $e->getMessage();
            }
        }
    }
}

$categories = db()->fetchAll("SELECT * FROM blog_categories ORDER BY name");

// إضافة سكربت TinyMCE مع كافة الأدوات المطلوبة بشكل صريح
$pageScripts = '
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js" referrerpolicy="origin"></script>
<script>
  // تهيئة المحرر بخصائص متقدمة جداً
  tinymce.init({
    selector: "#content_editor",
    directionality: "rtl",
    language: "ar",
    height: 700,
    menubar: true, // تفعيل القائمة العلوية لمزيد من الخيارات
    plugins: [
      "advlist", "autolink", "lists", "link", "image", "charmap", "preview",
      "anchor", "searchreplace", "visualblocks", "code", "fullscreen",
      "insertdatetime", "media", "table", "help", "wordcount", "emoticons", 
      "directionality", "visualchars", "template", "codesample"
    ],
    toolbar: "undo redo | blocks fontfamily fontsize | " +
      "bold italic underline strikethrough | forecolor backcolor | " +
      "alignleft aligncenter alignright alignjustify | ltr rtl | " +
      "bullist numlist outdent indent | link image media codesample | " +
      "table emoticons | removeformat | code fullscreen preview",
    
    // إعدادات الخطوط والأحجام المتاحة
    font_size_formats: "8pt 10pt 12pt 14pt 16pt 18pt 24pt 36pt 48pt",
    font_family_formats: "Tajawal=Tajawal, sans-serif; Cairo=Cairo, sans-serif; Arial=arial,helvetica,sans-serif; Tahoma=tahoma,arial,helvetica,sans-serif; Times New Roman=times new roman,times; Verdana=verdana,geneva;",
    
    // تخصيص شكل العناوين والقوائم
    style_formats: [
        { title: "العناوين الرئيسية", items: [
            { title: "عنوان 1", format: "h1" },
            { title: "عنوان 2", format: "h2" },
            { title: "عنوان 3", format: "h3" }
        ]},
        { title: "تنسيقات إضافية", items: [
            { title: "اقتباس", format: "blockquote" },
            { title: "كود برمجى", format: "code" },
            { title: "نص مميز", inline: "span", classes: "highlight-text" }
        ]}
    ],

    content_style: "@import url(\'https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&family=Tajawal:wght@400;700&display=swap\'); body { font-family: Tajawal, sans-serif; font-size:16px }",
    branding: false,
    promotion: false,
    image_title: true,
    automatic_uploads: true,
    images_upload_url: "upload.php",
    file_picker_types: "image",
    
    // التأكد من عمل الملقات عند الخطأ
    setup: function (editor) {
        editor.on("init", function () {
            console.log("TinyMCE initialized successfully");
        });
    }
  });

  document.addEventListener("DOMContentLoaded", function() {
      const titleInput = document.getElementById("postTitle");
      const slugInput = document.getElementById("postSlug");
      
      if(titleInput && slugInput) {
          titleInput.addEventListener("blur", function() {
              if (!slugInput.value) {
                  const slug = titleInput.value
                      .toLowerCase()
                      .replace(/[^\u0600-\u06FFa-z0-9]+/g, "-")
                      .replace(/^-+|-+$/g, "");
                  slugInput.value = slug;
              }
          });
      }
      
      const fileInput = document.getElementById("upload_featured_image");
      const urlInput = document.getElementById("featured_image_input");
      const imgPreview = document.getElementById("image_preview_img");
      const imgPreviewContainer = document.getElementById("image_preview_container");
      
      if(fileInput && imgPreview) {
          fileInput.addEventListener("change", function() {
              if (this.files && this.files[0]) {
                  const reader = new FileReader();
                  reader.onload = function(e) {
                      imgPreview.src = e.target.result;
                      imgPreviewContainer.style.display = "block";
                  }
                  reader.readAsDataURL(this.files[0]);
              }
          });
      }
      
      if(urlInput && imgPreview) {
          urlInput.addEventListener("input", function() {
              if (urlInput.value) {
                  imgPreview.src = urlInput.value;
                  imgPreviewContainer.style.display = "block";
              }
          });
      }
  });
</script>
<style>
    .tox-tinymce { border-radius: 12px !important; border: 1px solid var(--admin-border) !important; box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1); margin-top: 10px; }
    .required { color: #e11d48; }
    .seo-preview { background: #f9fafb; padding: 1.5rem; border-radius: 12px; border: 1px dashed var(--admin-border); margin-top: 1.5rem; }
    .seo-preview .title { color: #1a0dab; font-size: 1.25rem; margin-bottom: 4px; display: block; }
    .seo-preview .url { color: #006621; font-size: 0.95rem; margin-bottom: 4px; display: block; }
    .seo-preview .desc { color: #545454; font-size: 0.9rem; line-height: 1.5; }
    .file-upload-wrapper { position: relative; overflow: hidden; display: inline-block; width: 100%; transition: all 0.3s; }
    .file-upload-wrapper:hover { transform: translateY(-2px); }
    .file-upload-wrapper input[type=file] { font-size: 100px; position: absolute; left: 0; top: 0; opacity: 0; cursor: pointer; }
    .highlight-text { background-color: #fff3cd; padding: 2px 4px; border-radius: 3px; }
</style>
';

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= $pageTitle ?></h1>
        <p><?= $id ? 'إدارة وتحرير محتوى المقال' : 'إنشاء ونشر مقال جديد في المدونة' ?></p>
    </div>
    <div class="quick-actions">
        <a href="posts.php" class="btn btn-secondary"><i class="fas fa-arrow-right"></i> قمة المقالات</a>
        <?php if ($id && $post['status'] === 'published'): ?>
        <a href="<?= url('pages/blog-post.php?slug=' . $post['slug']) ?>" target="_blank" class="btn btn-info">
            <i class="fas fa-external-link-alt"></i> مشاهدة المقال
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['created'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <strong>🎉 مبروك!</strong> تم إنشاء المقال بنجاح. يمكنك المتابعة في التنسيق.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (isset($success)): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <?= Security::csrfField() ?>

    <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 1.5rem;">
        <!-- Main Content -->
        <div>
            <div class="card mb-4" style="border: none; box-shadow: var(--admin-shadow);">
                <div class="card-body">
                    <div class="form-group mb-4">
                        <label class="form-label" style="font-weight: 700; color: #374151;">عنوان المقال الماسي <span class="required">*</span></label>
                        <input type="text" name="title" class="form-control form-control-lg" value="<?= e($post['title'] ?? '') ?>" required placeholder="اكتب عنواناً جذاباً يخطف الأنظار..." id="postTitle" style="font-size: 1.5rem; height: auto; border-radius: 10px;">
                    </div>

                    <div class="form-group mb-4">
                        <label class="form-label">الرابط الدائم (Slug)</label>
                        <div class="input-group shadow-sm" style="border-radius: 8px; overflow: hidden;">
                            <span class="input-group-text" dir="ltr" style="background: #f9fafb; font-size: 0.8rem;"><?= SITE_URL ?>/blog/</span>
                            <input type="text" name="slug" id="postSlug" class="form-control" value="<?= e($post['slug'] ?? '') ?>" placeholder="example-url" dir="ltr">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 700; color: #374151;">محرر المحتوى المتقدم</label>
                        <textarea name="content" id="content_editor" rows="20" style="width: 100%; border-radius: 8px; padding: 15px; border: 1px solid var(--admin-border);"><?= e($post['content'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group mt-4 mb-0">
                        <label class="form-label">خلاصة المقال (Excerpt)</label>
                        <textarea name="excerpt" class="form-control" rows="3" placeholder="نبذة سريعة تشجع القراء على الضغط للمتابعة..." maxlength="300"><?= e($post['excerpt'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="background: #f9fafb;">
                    <h3><i class="fas fa-rocket" style="color: #FF6B35;"></i> تهيئة المحتوى لمحركات البحث (SEO)</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">عنوان الـ Meta (يظهر في جوجل)</label>
                        <input type="text" name="meta_title" class="form-control" value="<?= e($post['meta_title'] ?? '') ?>" placeholder="اتركه فارغاً لاستخدام العنوان الرئيسي">
                    </div>
                    <div class="form-group">
                        <label class="form-label">وصف الـ Meta (يظهر تحت الرابط في جوجل)</label>
                        <textarea name="meta_description" class="form-control" rows="2" placeholder="وصف مغري يدفع المستخدمين للضغط على مقالك..."><?= e($post['meta_description'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="seo-preview">
                        <small style="color: #6b7280; font-weight: bold; margin-bottom: 8px; display: block;">معاينة جوجل الحية:</small>
                        <span class="title"><?= e(($post['meta_title'] ?? '') ?: (($post['title'] ?? '') ?: 'عنوان المقال الاحترافي')) ?></span>
                        <span class="url"><?= SITE_URL ?>/blog/<?= e(($post['slug'] ?? '') ?: 'post-link') ?></span>
                        <span class="desc"><?= e(($post['meta_description'] ?? '') ?: (($post['excerpt'] ?? '') ?: 'وصف المقال سيظهر هنا بشكل جذاب ومنسق لمساعدة القراء في العثور عليك في محركات البحث...')) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div>
            <!-- Publish Box -->
            <div class="card mb-4" style="border-top: 4px solid var(--admin-primary);">
                <div class="card-header">
                    <h3><i class="fas fa-save"></i> النشر</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">حالة المقال</label>
                        <select name="status" class="form-control">
                            <option value="draft" <?= ($post['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>📁 مسودة (غير منشور)</option>
                            <option value="published" <?= ($post['status'] ?? '') === 'published' ? 'selected' : '' ?>>🌐 منشور (متاح للجميع)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" style="font-size: 0.85rem;">
                        <span class="d-block text-muted mb-1"><i class="far fa-calendar-alt"></i> أنشئ في: <?= formatDate($post['created_at']) ?></span>
                        <?php if (isset($post['updated_at'])): ?>
                        <span class="d-block text-muted"><i class="fas fa-history"></i> آخر تعديل: <?= formatDate($post['updated_at']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <button type="submit" class="btn btn-primary w-100 btn-lg shadow-sm" style="font-weight: 700;">
                        <i class="fas fa-save"></i> <?= $id ? 'حفظ التغييرات' : 'نشر المقال الماسي' ?>
                    </button>
                </div>
            </div>

            <!-- Category -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> التصنيف</h3>
                </div>
                <div class="card-body">
                    <select name="category_id" class="form-control">
                        <option value="">-- بدون تصنيف --</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($post['category_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Featured Image Upload -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-images"></i> الصورة البارزة</h3>
                </div>
                <div class="card-body text-center">
                    <div id="image_preview_container" class="mb-3" style="<?= empty($post['featured_image']) ? 'display: none;' : '' ?>">
                        <img id="image_preview_img" src="<?= e($post['featured_image'] ?? '') ?>" alt="Preview" style="max-width: 100%; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                    </div>
                    
                    <div class="mb-3">
                        <div class="file-upload-wrapper">
                            <button type="button" class="btn btn-secondary w-100"><i class="fas fa-upload"></i> ارفع من جهازك</button>
                            <input type="file" name="featured_image_file" id="upload_featured_image" accept="image/*">
                        </div>
                    </div>
                    
                    <div class="text-muted mb-2">أو ضع رابطاً مباشراً:</div>
                    <input type="text" name="featured_image" id="featured_image_input" class="form-control form-control-sm" value="<?= e($post['featured_image'] ?? '') ?>" placeholder="http://..." dir="ltr">
                </div>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/includes/footer.php'; ?>


