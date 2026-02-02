<?php
/**
 * Users Management
 * إدارة المستخدمين
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'المستخدمين';

// Only super admin can access
if (($_SESSION['admin_role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'جلسة غير صالحة';
    } else {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);

        if ($action === 'create' || $action === 'update') {
            $fullName = clean($_POST['full_name'] ?? '');
            $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
            $role = in_array($_POST['role'] ?? '', ['admin', 'editor']) ? $_POST['role'] : 'editor';
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $password = $_POST['password'] ?? '';

            if (!$fullName || !$email) {
                $error = 'جميع الحقول مطلوبة';
            } else {
                $data = [
                    'full_name' => $fullName,
                    'email' => $email,
                    'role' => $role,
                    'is_active' => $isActive,
                ];

                if ($password) {
                    $data['password'] = Security::hashPassword($password);
                }

                try {
                    if ($id) {
                        // Check if email exists for another user
                        $exists = db()->fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $id]);
                        if ($exists) {
                            $error = 'البريد الإلكتروني مستخدم من قبل';
                        } else {
                            db()->update('users', $data, 'id = ?', ['id' => $id]);
                            Security::logActivity('user_updated', 'users', $id);
                            $success = 'تم تحديث المستخدم بنجاح';
                        }
                    } else {
                        if (!$password) {
                            $error = 'كلمة المرور مطلوبة للمستخدمين الجدد';
                        } else {
                            $exists = db()->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
                            if ($exists) {
                                $error = 'البريد الإلكتروني مستخدم من قبل';
                            } else {
                                $newId = db()->insert('users', $data);
                                Security::logActivity('user_created', 'users', $newId);
                                $success = 'تم إنشاء المستخدم بنجاح';
                            }
                        }
                    }
                } catch (Exception $e) {
                    $error = 'حدث خطأ';
                }
            }
        }

        if ($action === 'delete' && $id) {
            if ($id == $_SESSION['admin_id']) {
                $error = 'لا يمكنك حذف حسابك';
            } else {
                db()->delete('users', 'id = ?', ['id' => $id]);
                Security::logActivity('user_deleted', 'users', $id);
                $success = 'تم حذف المستخدم بنجاح';
            }
        }
    }
}

$users = db()->fetchAll("SELECT * FROM users ORDER BY created_at DESC");
$editUser = null;
if (isset($_GET['edit'])) {
    $editUser = db()->fetchOne("SELECT * FROM users WHERE id = ?", [(int)$_GET['edit']]);
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>المستخدمين</h1>
        <p>إدارة مستخدمي لوحة التحكم</p>
    </div>
</div>

<?php if (isset($success)): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 400px; gap: 1.5rem;">
    <!-- Users List -->
    <div class="card">
        <div class="card-header">
            <h3>قائمة المستخدمين</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>المستخدم</th>
                            <th>البريد</th>
                            <th>الدور</th>
                            <th>الحالة</th>
                            <th>آخر دخول</th>
                            <th style="width: 120px;">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 36px; height: 36px; background: var(--admin-primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                                        <?= mb_substr($user['full_name'], 0, 1) ?>
                                    </div>
                                    <span style="font-weight: 500;"><?= e($user['full_name']) ?></span>
                                </div>
                            </td>
                            <td><?= e($user['email']) ?></td>
                            <td>
                                <span class="badge badge-<?= $user['role'] === 'admin' ? 'primary' : 'secondary' ?>">
                                    <?= $user['role'] === 'admin' ? 'مدير' : 'محرر' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= $user['is_active'] ? 'success' : 'danger' ?>">
                                    <?= $user['is_active'] ? 'مفعّل' : 'معطّل' ?>
                                </span>
                            </td>
                            <td><?= $user['last_login'] ? formatDate($user['last_login'], 'short') : '-' ?></td>
                            <td>
                                <div class="actions">
                                    <a href="?edit=<?= $user['id'] ?>" class="btn btn-sm btn-icon btn-secondary" title="تعديل">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($user['id'] != $_SESSION['admin_id']): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد؟');">
                                        <?= Security::csrfField() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-icon btn-danger" title="حذف">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add/Edit Form -->
    <div class="card">
        <div class="card-header">
            <h3><?= $editUser ? 'تعديل المستخدم' : 'مستخدم جديد' ?></h3>
            <?php if ($editUser): ?>
            <a href="users.php" class="btn btn-sm btn-secondary">إلغاء</a>
            <?php endif; ?>
        </div>
        <form method="POST">
            <?= Security::csrfField() ?>
            <input type="hidden" name="action" value="<?= $editUser ? 'update' : 'create' ?>">
            <input type="hidden" name="id" value="<?= $editUser['id'] ?? '' ?>">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">الاسم الكامل <span class="required">*</span></label>
                    <input type="text" name="full_name" class="form-control" value="<?= e($editUser['full_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">البريد الإلكتروني <span class="required">*</span></label>
                    <input type="email" name="email" class="form-control" value="<?= e($editUser['email'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">كلمة المرور <?= $editUser ? '' : '<span class="required">*</span>' ?></label>
                    <input type="password" name="password" class="form-control" <?= $editUser ? '' : 'required' ?>>
                    <?php if ($editUser): ?>
                    <span class="form-hint">اتركه فارغاً للإبقاء على كلمة المرور الحالية</span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="form-label">الدور</label>
                    <select name="role" class="form-control">
                        <option value="editor" <?= ($editUser['role'] ?? '') === 'editor' ? 'selected' : '' ?>>محرر</option>
                        <option value="admin" <?= ($editUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>مدير</option>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-check">
                        <input type="checkbox" name="is_active" value="1" <?= ($editUser['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <span>حساب مفعّل</span>
                    </label>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-save"></i> <?= $editUser ? 'حفظ التغييرات' : 'إنشاء المستخدم' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
