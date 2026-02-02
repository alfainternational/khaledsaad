<?php
/**
 * Newsletter Subscribers Management
 * إدارة المشتركين في النشرة
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'المشتركين';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);

        if ($action === 'delete' && $id) {
            db()->delete('newsletter_subscribers', 'id = ?', ['id' => $id]);
            $success = 'تم حذف المشترك';
        }

        if ($action === 'export') {
            $subscribers = db()->fetchAll("SELECT email, status, created_at FROM newsletter_subscribers ORDER BY created_at DESC");
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="subscribers_' . date('Y-m-d') . '.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Email', 'Status', 'Date']);
            foreach ($subscribers as $sub) {
                fputcsv($output, [$sub['email'], $sub['status'], $sub['created_at']]);
            }
            fclose($output);
            exit;
        }
    }
}

// Filters
$status = clean($_GET['status'] ?? '');
$search = clean($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;

$where = [];
$params = [];

if ($status) {
    $where[] = 'status = ?';
    $params[] = $status;
}

if ($search) {
    $where[] = 'email LIKE ?';
    $params[] = "%$search%";
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$total = db()->fetchOne("SELECT COUNT(*) as c FROM newsletter_subscribers $whereClause", $params)['c'] ?? 0;
$totalPages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$subscribers = db()->fetchAll("SELECT * FROM newsletter_subscribers $whereClause ORDER BY created_at DESC LIMIT $perPage OFFSET $offset", $params);

// Stats
$stats = [
    'total' => db()->fetchOne("SELECT COUNT(*) as c FROM newsletter_subscribers")['c'] ?? 0,
    'confirmed' => db()->fetchOne("SELECT COUNT(*) as c FROM newsletter_subscribers WHERE status = 'confirmed'")['c'] ?? 0,
    'pending' => db()->fetchOne("SELECT COUNT(*) as c FROM newsletter_subscribers WHERE status = 'pending'")['c'] ?? 0,
];

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>المشتركين في النشرة</h1>
        <p>إدارة قائمة المشتركين في النشرة البريدية</p>
    </div>
    <form method="POST" style="display: inline;">
        <?= Security::csrfField() ?>
        <input type="hidden" name="action" value="export">
        <button type="submit" class="btn btn-secondary"><i class="fas fa-download"></i> تصدير CSV</button>
    </form>
</div>

<?php if (isset($success)): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="margin-bottom: 1.5rem;">
    <div class="stat-card">
        <div class="stat-icon primary"><i class="fas fa-users"></i></div>
        <div class="stat-content">
            <h3><?= formatNumber($stats['total']) ?></h3>
            <p>إجمالي المشتركين</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
        <div class="stat-content">
            <h3><?= formatNumber($stats['confirmed']) ?></h3>
            <p>مؤكد</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning"><i class="fas fa-clock"></i></div>
        <div class="stat-content">
            <h3><?= formatNumber($stats['pending']) ?></h3>
            <p>قيد الانتظار</p>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="filters">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" class="form-control" placeholder="بحث بالبريد..." value="<?= e($search) ?>">
            </div>
            <select name="status" class="form-control" onchange="this.form.submit()">
                <option value="">جميع الحالات</option>
                <option value="confirmed" <?= $status === 'confirmed' ? 'selected' : '' ?>>مؤكد</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>قيد الانتظار</option>
                <option value="unsubscribed" <?= $status === 'unsubscribed' ? 'selected' : '' ?>>ملغي</option>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (!empty($subscribers)): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>البريد الإلكتروني</th>
                        <th>الحالة</th>
                        <th>تاريخ الاشتراك</th>
                        <th style="width: 80px;">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscribers as $sub): ?>
                    <tr>
                        <td><?= e($sub['email']) ?></td>
                        <td>
                            <?php
                            $statusLabels = [
                                'confirmed' => ['label' => 'مؤكد', 'class' => 'success'],
                                'pending' => ['label' => 'قيد الانتظار', 'class' => 'warning'],
                                'unsubscribed' => ['label' => 'ملغي', 'class' => 'danger'],
                            ];
                            $s = $statusLabels[$sub['status']] ?? ['label' => $sub['status'], 'class' => 'secondary'];
                            ?>
                            <span class="badge badge-<?= $s['class'] ?>"><?= $s['label'] ?></span>
                        </td>
                        <td><?= formatDate($sub['created_at'], 'short') ?></td>
                        <td>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('حذف هذا المشترك؟');">
                                <?= Security::csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-icon btn-danger" title="حذف">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <div class="pagination">
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?page=<?= $i ?>&status=<?= e($status) ?>&search=<?= e($search) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-envelope"></i>
            <h3>لا يوجد مشتركين</h3>
            <p>لم يتم العثور على أي مشتركين</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
