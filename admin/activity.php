<?php
/**
 * Activity Logs
 * سجل النشاطات
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'سجل النشاطات';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$total = db()->fetchOne("SELECT COUNT(*) as c FROM activity_logs")['c'] ?? 0;
$totalPages = ceil($total / $perPage);

$logs = db()->fetchAll("
    SELECT a.*, u.full_name as user_name
    FROM activity_logs a
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC
    LIMIT $perPage OFFSET $offset
");

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>سجل النشاطات</h1>
        <p>متابعة جميع الأنشطة في لوحة التحكم</p>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (!empty($logs)): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>المستخدم</th>
                        <th>النشاط</th>
                        <th>الجدول</th>
                        <th>ID</th>
                        <th>IP</th>
                        <th>التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= e($log['user_name'] ?? 'نظام') ?></td>
                        <td><code><?= e($log['action']) ?></code></td>
                        <td><?= e($log['table_name'] ?? '-') ?></td>
                        <td><?= $log['record_id'] ?: '-' ?></td>
                        <td><code style="font-size: 0.75rem;"><?= e($log['ip_address']) ?></code></td>
                        <td><?= formatDate($log['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?page=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-history"></i>
            <h3>لا توجد نشاطات</h3>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
