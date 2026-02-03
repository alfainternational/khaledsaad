<?php
/**
 * My Consultations
 * استشاراتي
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'استشاراتي';

// Filters
$filter = $_GET['filter'] ?? 'all';

// Build query
$whereClause = "client_id = ?";
$params = [$_SESSION['client_id']];

if ($filter === 'upcoming') {
    $whereClause .= " AND scheduled_at >= NOW() AND status NOT IN ('cancelled', 'completed')";
} elseif ($filter === 'past') {
    $whereClause .= " AND (scheduled_at < NOW() OR status IN ('completed', 'cancelled'))";
} elseif ($filter === 'pending') {
    $whereClause .= " AND status = 'pending'";
}

// Get consultations
$consultations = db()->fetchAll(
    "SELECT c.*, s.name as service_name, s.icon as service_icon
     FROM consultations c
     LEFT JOIN services s ON c.service_id = s.id
     WHERE {$whereClause}
     ORDER BY c.scheduled_at DESC",
    $params
);

// Stats
$stats = [
    'total' => db()->fetchOne("SELECT COUNT(*) as c FROM consultations WHERE client_id = ?", [$_SESSION['client_id']])['c'] ?? 0,
    'upcoming' => db()->fetchOne("SELECT COUNT(*) as c FROM consultations WHERE client_id = ? AND scheduled_at >= NOW() AND status NOT IN ('cancelled', 'completed')", [$_SESSION['client_id']])['c'] ?? 0,
    'completed' => db()->fetchOne("SELECT COUNT(*) as c FROM consultations WHERE client_id = ? AND status = 'completed'", [$_SESSION['client_id']])['c'] ?? 0,
];

$statusLabels = [
    'pending' => ['label' => 'بانتظار التأكيد', 'class' => 'warning'],
    'confirmed' => ['label' => 'مؤكدة', 'class' => 'success'],
    'completed' => ['label' => 'مكتملة', 'class' => 'info'],
    'cancelled' => ['label' => 'ملغاة', 'class' => 'danger'],
    'rescheduled' => ['label' => 'معاد جدولتها', 'class' => 'secondary'],
];

$arabicMonths = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>استشاراتي</h1>
        <p>تتبع جميع استشاراتك ومواعيدك</p>
    </div>
    <a href="<?= url('contact') ?>" class="btn btn-primary">
        <i class="fas fa-calendar-plus"></i>
        حجز استشارة جديدة
    </a>
</div>

<!-- Stats -->
<div class="stats-grid" style="margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-content">
            <h3><?= $stats['total'] ?></h3>
            <p>إجمالي الاستشارات</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <h3><?= $stats['upcoming'] ?></h3>
            <p>استشارات قادمة</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?= $stats['completed'] ?></h3>
            <p>استشارات مكتملة</p>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-body" style="padding: 1rem;">
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <a href="?filter=all" class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                الكل
            </a>
            <a href="?filter=upcoming" class="btn <?= $filter === 'upcoming' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                القادمة
            </a>
            <a href="?filter=past" class="btn <?= $filter === 'past' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                السابقة
            </a>
            <a href="?filter=pending" class="btn <?= $filter === 'pending' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                بانتظار التأكيد
            </a>
        </div>
    </div>
</div>

<!-- Consultations List -->
<?php if (!empty($consultations)): ?>
    <?php foreach ($consultations as $consultation): ?>
    <?php
        $date = new DateTime($consultation['scheduled_at']);
        $status = $statusLabels[$consultation['status']] ?? ['label' => $consultation['status'], 'class' => 'secondary'];
        $isUpcoming = strtotime($consultation['scheduled_at']) > time() && !in_array($consultation['status'], ['cancelled', 'completed']);
    ?>
    <div class="consultation-card">
        <div class="consultation-date" style="<?= $isUpcoming ? '' : 'background: var(--dash-secondary);' ?>">
            <div class="day"><?= $date->format('d') ?></div>
            <div class="month"><?= $arabicMonths[$date->format('n') - 1] ?></div>
        </div>
        <div class="consultation-info">
            <h4><?= e($consultation['title']) ?></h4>
            <div class="consultation-meta">
                <span><i class="fas fa-clock"></i> <?= $date->format('h:i A') ?></span>
                <span><i class="fas fa-hourglass-half"></i> <?= $consultation['duration'] ?> دقيقة</span>
                <?php if ($consultation['service_name']): ?>
                <span><i class="fas fa-tag"></i> <?= e($consultation['service_name']) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($consultation['description']): ?>
            <p style="color: var(--dash-text-muted); font-size: 0.875rem; margin-top: 0.5rem;">
                <?= e(mb_substr($consultation['description'], 0, 150)) ?>...
            </p>
            <?php endif; ?>
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-top: 0.75rem;">
                <span class="badge badge-<?= $status['class'] ?>"><?= $status['label'] ?></span>
                <?php if ($consultation['paid']): ?>
                <span class="badge badge-success"><i class="fas fa-check"></i> مدفوعة</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="consultation-actions" style="display: flex; flex-direction: column; gap: 0.5rem;">
            <?php if ($isUpcoming && $consultation['meeting_link'] && $consultation['status'] === 'confirmed'): ?>
            <a href="<?= e($consultation['meeting_link']) ?>" class="btn btn-primary btn-sm" target="_blank">
                <i class="fas fa-video"></i> انضمام
            </a>
            <?php endif; ?>
            <a href="consultation-view.php?id=<?= $consultation['id'] ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-eye"></i> التفاصيل
            </a>
            <?php if ($consultation['status'] === 'completed' && !$consultation['rating']): ?>
            <button class="btn btn-warning btn-sm" onclick="rateConsultation(<?= $consultation['id'] ?>)">
                <i class="fas fa-star"></i> تقييم
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
<?php else: ?>
<div class="card">
    <div class="card-body">
        <div class="empty-state">
            <i class="fas fa-calendar-alt"></i>
            <h3>لا توجد استشارات</h3>
            <p>لم تقم بحجز أي استشارات بعد</p>
            <a href="<?= url('contact') ?>" class="btn btn-primary">
                <i class="fas fa-calendar-plus"></i>
                حجز استشارة جديدة
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Rating Modal -->
<div class="modal-overlay" id="ratingModal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3>تقييم الاستشارة</h3>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST" action="api/rate-consultation.php">
            <?= Security::csrfField() ?>
            <input type="hidden" name="consultation_id" id="ratingConsultationId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">التقييم</label>
                    <div style="display: flex; gap: 0.5rem; justify-content: center; font-size: 2rem;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <label style="cursor: pointer;">
                            <input type="radio" name="rating" value="<?= $i ?>" style="display: none;" required>
                            <i class="far fa-star star-rating" data-value="<?= $i ?>"></i>
                        </label>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">ملاحظاتك (اختياري)</label>
                    <textarea name="feedback" class="form-control" rows="3" placeholder="أخبرنا عن تجربتك..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('ratingModal')">إلغاء</button>
                <button type="submit" class="btn btn-primary">إرسال التقييم</button>
            </div>
        </form>
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
    max-width: 500px;
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--dash-border);
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
.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--dash-border);
}
.star-rating { color: #ddd; cursor: pointer; transition: color 0.2s; }
.star-rating:hover, .star-rating.active { color: #f59e0b; }
.star-rating.active { font-weight: 900; }
</style>

<script>
function rateConsultation(id) {
    document.getElementById('ratingConsultationId').value = id;
    document.getElementById('ratingModal').classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

// Star rating interaction
document.querySelectorAll('.star-rating').forEach(star => {
    star.addEventListener('click', function() {
        const value = this.dataset.value;
        document.querySelectorAll('.star-rating').forEach(s => {
            s.classList.toggle('active', s.dataset.value <= value);
            s.classList.toggle('fas', s.dataset.value <= value);
            s.classList.toggle('far', s.dataset.value > value);
        });
    });
});

// Close modal on escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
    }
});

// Close modal on outside click
document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', e => {
        if (e.target === modal) modal.classList.remove('active');
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
