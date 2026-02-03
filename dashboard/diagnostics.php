<?php
/**
 * My Diagnostic Results
 * نتائج التشخيص
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'نتائج التشخيص';

// Get diagnostic results
$diagnostics = db()->fetchAll(
    "SELECT * FROM diagnostic_results
     WHERE client_id = ? AND completed_at IS NOT NULL
     ORDER BY completed_at DESC",
    [$_SESSION['client_id']]
);

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>نتائج التشخيص</h1>
        <p>استعرض جميع نتائج تشخيصاتك السابقة</p>
    </div>
    <a href="<?= url('diagnostic') ?>" class="btn btn-primary">
        <i class="fas fa-stethoscope"></i>
        بدء تشخيص جديد
    </a>
</div>

<?php if (!empty($diagnostics)): ?>
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem;">
    <?php foreach ($diagnostics as $diagnostic): ?>
    <?php
        $score = $diagnostic['score'];
        $level = $score >= 70 ? 'high' : ($score >= 40 ? 'medium' : 'low');
        $levelLabel = $score >= 70 ? 'ممتاز' : ($score >= 40 ? 'متوسط' : 'يحتاج تحسين');
        $levelColor = $score >= 70 ? 'var(--dash-success)' : ($score >= 40 ? 'var(--dash-warning)' : 'var(--dash-danger)');
        $recommendations = json_decode($diagnostic['recommendations'], true) ?? [];
    ?>
    <div class="diagnostic-card">
        <div class="diagnostic-header">
            <div class="diagnostic-score">
                <div class="score-circle <?= $level ?>">
                    <?= $score ?>%
                </div>
                <div>
                    <div style="font-weight: 600; font-size: 1.1rem;"><?= $levelLabel ?></div>
                    <div style="font-size: 0.8rem; color: var(--dash-text-muted);">
                        <?php if ($diagnostic['category']): ?>
                        <?= e($diagnostic['category']) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div style="text-align: left; font-size: 0.8rem; color: var(--dash-text-muted);">
                <i class="fas fa-calendar"></i>
                <?= formatDate($diagnostic['completed_at']) ?>
            </div>
        </div>

        <div class="diagnostic-progress">
            <div class="bar <?= $level ?>" style="width: <?= $score ?>%;"></div>
        </div>

        <?php if (!empty($recommendations)): ?>
        <div style="margin-bottom: 1rem;">
            <div style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.5rem;">
                <i class="fas fa-lightbulb" style="color: var(--dash-warning);"></i>
                التوصيات:
            </div>
            <ul style="margin: 0; padding-right: 1.25rem; font-size: 0.875rem; color: var(--dash-text-muted);">
                <?php foreach (array_slice($recommendations, 0, 3) as $rec): ?>
                <li style="margin-bottom: 0.25rem;"><?= e($rec) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div style="display: flex; gap: 0.5rem;">
            <button class="btn btn-secondary btn-sm btn-block" onclick="viewDetails(<?= $diagnostic['id'] ?>)">
                <i class="fas fa-eye"></i> عرض التفاصيل
            </button>
            <a href="<?= url('diagnostic') ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-redo"></i> إعادة
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body">
        <div class="empty-state">
            <i class="fas fa-stethoscope"></i>
            <h3>لا توجد نتائج تشخيص</h3>
            <p>لم تقم بإجراء أي تشخيص بعد. ابدأ الآن لمعرفة وضع عملك</p>
            <a href="<?= url('diagnostic') ?>" class="btn btn-primary">
                <i class="fas fa-stethoscope"></i>
                بدء التشخيص المجاني
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Details Modal -->
<div class="modal-overlay" id="detailsModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3>تفاصيل التشخيص</h3>
            <button class="modal-close" onclick="closeModal('detailsModal')">&times;</button>
        </div>
        <div class="modal-body" id="detailsContent">
            <div style="text-align: center; padding: 2rem;">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
            </div>
        </div>
    </div>
</div>

<style>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}
.modal-overlay.active { display: flex; }
.modal-content {
    background: var(--dash-card-bg);
    border-radius: var(--dash-radius);
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--dash-border);
    position: sticky;
    top: 0;
    background: var(--dash-card-bg);
}
.modal-header h3 { margin: 0; }
.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--dash-text-muted);
}
.modal-body { padding: 1.5rem; }
</style>

<script>
function viewDetails(id) {
    document.getElementById('detailsModal').classList.add('active');

    // Fetch details via AJAX
    fetch('api/diagnostic-details.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const d = data.data;
                const answers = d.answers || {};
                const recommendations = d.recommendations || [];

                let html = `
                    <div style="text-align: center; margin-bottom: 1.5rem;">
                        <div class="score-circle ${d.level}" style="width: 80px; height: 80px; font-size: 1.5rem; margin: 0 auto;">
                            ${d.score}%
                        </div>
                        <div style="font-weight: 600; font-size: 1.25rem; margin-top: 0.5rem;">${d.levelLabel}</div>
                        <div style="color: var(--dash-text-muted); font-size: 0.875rem;">${d.date}</div>
                    </div>
                `;

                if (recommendations.length > 0) {
                    html += `
                        <div style="margin-bottom: 1.5rem;">
                            <h4 style="margin-bottom: 0.75rem;"><i class="fas fa-lightbulb" style="color: var(--dash-warning);"></i> التوصيات</h4>
                            <ul style="margin: 0; padding-right: 1.25rem;">
                                ${recommendations.map(r => `<li style="margin-bottom: 0.5rem;">${r}</li>`).join('')}
                            </ul>
                        </div>
                    `;
                }

                if (Object.keys(answers).length > 0) {
                    html += `
                        <div>
                            <h4 style="margin-bottom: 0.75rem;"><i class="fas fa-clipboard-list" style="color: var(--dash-primary);"></i> إجاباتك</h4>
                            <div style="background: var(--dash-bg); border-radius: 8px; padding: 1rem;">
                    `;
                    for (const [question, answer] of Object.entries(answers)) {
                        html += `
                            <div style="margin-bottom: 0.75rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--dash-border);">
                                <div style="font-weight: 500; font-size: 0.875rem;">${question}</div>
                                <div style="color: var(--dash-text-muted); font-size: 0.875rem;">${answer}</div>
                            </div>
                        `;
                    }
                    html += `</div></div>`;
                }

                document.getElementById('detailsContent').innerHTML = html;
            } else {
                document.getElementById('detailsContent').innerHTML = '<p class="text-center text-danger">حدث خطأ في تحميل البيانات</p>';
            }
        })
        .catch(() => {
            document.getElementById('detailsContent').innerHTML = '<p class="text-center text-danger">حدث خطأ في الاتصال</p>';
        });
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
    }
});

document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', e => {
        if (e.target === modal) modal.classList.remove('active');
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
