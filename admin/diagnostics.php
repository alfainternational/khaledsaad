<?php
/**
 * Diagnostic Results
 * نتائج التشخيص
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'نتائج التشخيص';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$total = db()->fetchOne("SELECT COUNT(*) as c FROM diagnostic_results")['c'] ?? 0;
$totalPages = ceil($total / $perPage);

$results = db()->fetchAll("SELECT * FROM diagnostic_results ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>نتائج التشخيص</h1>
        <p>عرض نتائج أداة التشخيص التسويقي</p>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (!empty($results)): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>البريد</th>
                        <th>الدرجة</th>
                        <th>المستوى</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th style="width: 80px;">عرض</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $result):
                        $answers = json_decode($result['answers'], true) ?? [];
                        $score = $result['score'] ?? 0;
                        $level = $score >= 80 ? 'ممتاز' : ($score >= 60 ? 'جيد' : ($score >= 40 ? 'متوسط' : 'يحتاج تحسين'));
                        $levelClass = $score >= 80 ? 'success' : ($score >= 60 ? 'info' : ($score >= 40 ? 'warning' : 'danger'));
                    ?>
                    <tr>
                        <td><?= e($result['email'] ?? '-') ?></td>
                        <td><strong><?= $score ?>%</strong></td>
                        <td><span class="badge badge-<?= $levelClass ?>"><?= $level ?></span></td>
                        <td>
                            <span class="badge badge-<?= $result['completed_at'] ? 'success' : 'secondary' ?>">
                                <?= $result['completed_at'] ? 'مكتمل' : 'غير مكتمل' ?>
                            </span>
                        </td>
                        <td><?= formatDate($result['created_at'], 'short') ?></td>
                        <td>
                            <button class="btn btn-sm btn-icon btn-secondary" onclick="viewDetails(<?= htmlspecialchars(json_encode($result)) ?>)">
                                <i class="fas fa-eye"></i>
                            </button>
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
                <a href="?page=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-stethoscope"></i>
            <h3>لا توجد نتائج</h3>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Details Modal -->
<div class="modal-overlay" id="detailsModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3>تفاصيل التشخيص</h3>
            <button class="modal-close" onclick="closeModal('detailsModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="modalContent">
            <!-- Content loaded via JS -->
        </div>
    </div>
</div>

<script>
function viewDetails(result) {
    let answers = {};
    try { answers = JSON.parse(result.answers || '{}'); } catch(e) {}

    let html = `
        <div style="margin-bottom: 1rem;">
            <strong>البريد:</strong> ${result.email || '-'}<br>
            <strong>الدرجة:</strong> ${result.score || 0}%<br>
            <strong>التاريخ:</strong> ${result.created_at}
        </div>
        <div style="background: var(--admin-bg); padding: 1rem; border-radius: 8px;">
            <strong>الإجابات:</strong>
            <pre style="font-size: 0.8rem; margin-top: 0.5rem; white-space: pre-wrap;">${JSON.stringify(answers, null, 2)}</pre>
        </div>
    `;

    document.getElementById('modalContent').innerHTML = html;
    openModal('detailsModal');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
