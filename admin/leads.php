<?php
/**
 * Leads Management
 * إدارة العملاء المحتملين
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'العملاء المحتملين';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        die(json_encode(['success' => false, 'message' => 'جلسة غير صالحة']));
    }

    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete' && $id) {
        try {
            db()->delete('leads', 'id = ?', ['id' => $id]);
            Security::logActivity('lead_deleted', 'leads', $id);
            if (isset($_POST['ajax'])) {
                die(json_encode(['success' => true]));
            }
            header('Location: leads.php?deleted=1');
            exit;
        } catch (Exception $e) {
            if (isset($_POST['ajax'])) {
                die(json_encode(['success' => false, 'message' => 'فشل الحذف']));
            }
        }
    }

    if ($action === 'update_status' && $id) {
        $status = clean($_POST['status'] ?? '');
        $validStatuses = ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'won', 'lost'];
        if (in_array($status, $validStatuses)) {
            db()->update('leads', ['status' => $status], 'id = ?', ['id' => $id]);
            Security::logActivity('lead_status_updated', 'leads', $id, ['status' => $status]);
            die(json_encode(['success' => true]));
        }
    }

    if ($action === 'bulk_delete') {
        $ids = array_filter(array_map('intval', $_POST['ids'] ?? []));
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            db()->query("DELETE FROM leads WHERE id IN ($placeholders)", $ids);
            Security::logActivity('leads_bulk_deleted', 'leads', 0, ['count' => count($ids)]);
            die(json_encode(['success' => true]));
        }
    }
}

// Filters
$status = clean($_GET['status'] ?? '');
$search = clean($_GET['search'] ?? '');
$service = clean($_GET['service'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// Build query
$where = [];
$params = [];

if ($status) {
    $where[] = 'status = ?';
    $params[] = $status;
}

if ($search) {
    $where[] = '(full_name LIKE ? OR email LIKE ? OR company LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($service) {
    $where[] = 'service_interested = ?';
    $params[] = $service;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$total = db()->fetchOne("SELECT COUNT(*) as c FROM leads $whereClause", $params)['c'] ?? 0;
$totalPages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

// Get leads
$leads = db()->fetchAll("SELECT * FROM leads $whereClause ORDER BY created_at DESC LIMIT $perPage OFFSET $offset", $params);

// Status labels
$statusLabels = [
    'new' => ['label' => 'جديد', 'class' => 'primary'],
    'contacted' => ['label' => 'تم التواصل', 'class' => 'info'],
    'qualified' => ['label' => 'مؤهل', 'class' => 'success'],
    'proposal' => ['label' => 'عرض سعر', 'class' => 'warning'],
    'negotiation' => ['label' => 'تفاوض', 'class' => 'warning'],
    'won' => ['label' => 'مكتمل', 'class' => 'success'],
    'lost' => ['label' => 'خسارة', 'class' => 'danger'],
];

include __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1>العملاء المحتملين</h1>
        <p>إدارة ومتابعة جميع طلبات العملاء</p>
    </div>
    <div class="quick-actions">
        <button class="btn btn-danger" id="bulkDeleteBtn" style="display: none;" onclick="bulkDelete()">
            <i class="fas fa-trash"></i> حذف المحدد (<span id="selectedCount">0</span>)
        </button>
        <a href="lead-export.php" class="btn btn-secondary"><i class="fas fa-download"></i> تصدير</a>
    </div>
</div>

<?php if (isset($_GET['deleted'])): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> تم حذف العميل بنجاح
</div>
<?php endif; ?>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="filters">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" class="form-control" placeholder="بحث بالاسم أو البريد..." value="<?= e($search) ?>">
            </div>
            <select name="status" class="form-control" onchange="this.form.submit()">
                <option value="">جميع الحالات</option>
                <?php foreach ($statusLabels as $key => $val): ?>
                <option value="<?= $key ?>" <?= $status === $key ? 'selected' : '' ?>><?= $val['label'] ?></option>
                <?php endforeach; ?>
            </select>
            <select name="service" class="form-control" onchange="this.form.submit()">
                <option value="">جميع الخدمات</option>
                <option value="consulting" <?= $service === 'consulting' ? 'selected' : '' ?>>الاستشارات</option>
                <option value="digital" <?= $service === 'digital' ? 'selected' : '' ?>>التحول الرقمي</option>
                <option value="branding" <?= $service === 'branding' ? 'selected' : '' ?>>الهوية التجارية</option>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> بحث</button>
            <?php if ($search || $status || $service): ?>
            <a href="leads.php" class="btn btn-secondary"><i class="fas fa-times"></i> مسح</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Leads Table -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (!empty($leads)): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" class="select-all" id="selectAll">
                        </th>
                        <th>الاسم</th>
                        <th>البريد الإلكتروني</th>
                        <th>الهاتف</th>
                        <th>الشركة</th>
                        <th>الخدمة</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th style="width: 120px;">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leads as $lead): ?>
                    <tr data-id="<?= $lead['id'] ?>">
                        <td>
                            <input type="checkbox" class="select-item" value="<?= $lead['id'] ?>">
                        </td>
                        <td>
                            <a href="lead-view.php?id=<?= $lead['id'] ?>" class="text-primary" style="font-weight: 500;">
                                <?= e($lead['full_name']) ?>
                            </a>
                        </td>
                        <td>
                            <a href="mailto:<?= e($lead['email']) ?>"><?= e($lead['email']) ?></a>
                        </td>
                        <td><?= e($lead['phone'] ?: '-') ?></td>
                        <td><?= e($lead['company'] ?: '-') ?></td>
                        <td><?= e($lead['service_interested'] ?: '-') ?></td>
                        <td>
                            <select class="form-control" style="width: auto; padding: 0.25rem 0.5rem; font-size: 0.8rem;" onchange="updateStatus(<?= $lead['id'] ?>, this.value)">
                                <?php foreach ($statusLabels as $key => $val): ?>
                                <option value="<?= $key ?>" <?= $lead['status'] === $key ? 'selected' : '' ?>><?= $val['label'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><?= formatDate($lead['created_at'], 'short') ?></td>
                        <td>
                            <div class="actions">
                                <a href="lead-view.php?id=<?= $lead['id'] ?>" class="btn btn-sm btn-icon btn-secondary" title="عرض">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من حذف هذا العميل؟');">
                                    <?= Security::csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $lead['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-icon btn-danger" title="حذف">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&status=<?= e($status) ?>&search=<?= e($search) ?>&service=<?= e($service) ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?page=<?= $i ?>&status=<?= e($status) ?>&search=<?= e($search) ?>&service=<?= e($service) ?>" class="<?= $i === $page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&status=<?= e($status) ?>&search=<?= e($search) ?>&service=<?= e($service) ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <h3>لا يوجد عملاء</h3>
            <p>لم يتم العثور على أي عملاء مطابقين للبحث</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Update status
function updateStatus(id, status) {
    fetch('leads.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=update_status&id=${id}&status=${status}&<?= CSRF_TOKEN_NAME ?>=<?= Security::generateCSRFToken() ?>`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showNotification('success', 'تم تحديث الحالة');
        }
    });
}

// Select all
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.select-item').forEach(cb => cb.checked = this.checked);
    updateBulkButton();
});

document.querySelectorAll('.select-item').forEach(cb => {
    cb.addEventListener('change', updateBulkButton);
});

function updateBulkButton() {
    const selected = document.querySelectorAll('.select-item:checked').length;
    document.getElementById('bulkDeleteBtn').style.display = selected > 0 ? 'inline-flex' : 'none';
    document.getElementById('selectedCount').textContent = selected;
}

function bulkDelete() {
    if (!confirm('هل أنت متأكد من حذف العملاء المحددين؟')) return;

    const ids = Array.from(document.querySelectorAll('.select-item:checked')).map(cb => cb.value);
    const formData = new FormData();
    formData.append('action', 'bulk_delete');
    formData.append('<?= CSRF_TOKEN_NAME ?>', '<?= Security::generateCSRFToken() ?>');
    ids.forEach(id => formData.append('ids[]', id));

    fetch('leads.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
