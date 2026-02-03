<?php
/**
 * Diagnostic Results Management (v2.5)
 * إدارة نتائج ومؤشرات التشخيص الاستراتيجي المتكاملة
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'لوحة تحليلات التشخيص الاستراتيجي 2.5';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$total = db()->fetchOne("SELECT COUNT(*) as c FROM diagnostic_results")['c'] ?? 0;
$totalPages = ceil($total / $perPage);

$results = db()->fetchAll("SELECT * FROM diagnostic_results ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$avgScore = db()->fetchOne("SELECT AVG(overall_score) as avg FROM diagnostic_results WHERE overall_score > 0")['avg'] ?? 0;

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>لوحة تحليلات التشخيص الاستراتيجي <span class="badge badge-primary">2.5</span></h1>
        <p>إدارة طلبات التشخيص، بيانات التواصل، وتحليلات فجوات نضج الأعمال</p>
    </div>
    <div class="header-stats" style="display: flex; gap: 20px;">
        <div class="stat-card" style="background: white; padding: 15px 25px; border-radius: 12px; box-shadow: var(--shadow-sm); border-right: 4px solid var(--primary);">
            <small style="color: var(--text-muted); display: block; font-weight: 600;">متوسط نضج العملاء</small>
            <strong style="font-size: 1.5rem; color: var(--primary);"><?= round($avgScore, 1) ?>%</strong>
        </div>
        <div class="stat-card" style="background: white; padding: 15px 25px; border-radius: 12px; box-shadow: var(--shadow-sm); border-right: 4px solid #059669;">
            <small style="color: var(--text-muted); display: block; font-weight: 600;">إجمالي التقارير المصدرة</small>
            <strong style="font-size: 1.5rem;"><?= $total ?></strong>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (!empty($results)): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>العميل والجهة</th>
                        <th>المصدر</th>
                        <th>درجة النضج</th>
                        <th>جودة العميل</th>
                        <th>الحالة</th>
                        <th style="width: 140px;">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $result):
                        $score = (int)$result['overall_score'];
                        $levelClass = $score >= 80 ? 'success' : ($score >= 60 ? 'info' : ($score >= 40 ? 'warning' : 'danger'));
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight: 700;"><?= e($result['full_name'] ?: 'زائر مجهول') ?></div>
                            <div class="text-sm text-primary"><?= e($result['company_name'] ?: '-') ?></div>
                            <div class="text-xs text-muted mt-1"><?= e($result['email']) ?></div>
                        </td>
                        <td>
                            <div class="badge-source" style="background: #E0E7FF; color: #4338CA; padding: 4px 10px; border-radius: 5px; font-size: 0.75rem; display: inline-block;">
                                <?= translate($result['lead_source'] ?? 'direct') ?>
                            </div>
                        </td>
                        <td>
                            <div class="lead-score-badge" style="background: <?= $result['lead_quality_score'] > 60 ? '#D1FAE5; color: #065F46;' : '#F3F4F6; color: #374151;' ?> padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; display: inline-block;">
                                <?= $result['lead_quality_score'] ?>%
                            </div>
                        </td>
                        <td>
                            <?php if($result['status'] === 'sent'): ?>
                                <span class="badge badge-success"><i class="fas fa-check-double mr-1"></i> أُرسل</span>
                            <?php else: ?>
                                <span class="badge badge-warning"><i class="fas fa-clock mr-1"></i> معلق</span>
                                <div class="text-xs text-muted mt-1" style="font-size: 0.7rem;">
                                    <?= date('m/d H:i', strtotime($result['scheduled_send_at'])) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-icon btn-primary" title="عرض التحليل" onclick="viewLeadInsight(<?= htmlspecialchars(json_encode($result)) ?>)">
                                    <i class="fas fa-chart-line"></i>
                                </button>
                                <a href="consultant-report.php?id=<?= $result['id'] ?>" class="btn btn-sm btn-icon btn-warning" title="التقرير الاستشاري (خاص بك)">
                                    <i class="fas fa-user-tie"></i>
                                </a>
                                <a href="diagnostic-edit.php?id=<?= $result['id'] ?>" class="btn btn-sm btn-icon btn-info" title="تعديل ومعاينة للعميل">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if($result['status'] !== 'sent'): ?>
                                <button class="btn btn-sm btn-icon btn-success" title="إرسال الآن" onclick="sendNow(<?= $result['id'] ?>)">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                                <?php endif; ?>
                            </div>
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
            <i class="fas fa-user-md fa-3x mb-4 opacity-20"></i>
            <h3>لا توجد نتائج تشخيصية حتى الآن</h3>
            <p>عندما يقوم أحد العملاء بإكمال التشخيص، ستظهر بياناته وتحليله الاستراتيجي هنا.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Detailed Insight Modal -->
<div class="modal-overlay" id="insightModal">
    <div class="modal" style="max-width: 950px;">
        <div class="modal-header">
            <h3>ملف الرؤية الاستشارية للعميل</h3>
            <button class="modal-close" onclick="closeModal('insightModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="insightContent" style="padding: 30px;">
            <!-- Content loaded via JS -->
        </div>
    </div>
</div>

<script>
let insightRadar = null;

function viewLeadInsight(lead) {
    let pillars = {};
    try { pillars = JSON.parse(lead.pillars_data || '{}'); } catch(e) {}
    
    let roadmap = { p1: [], p2: [], p3: [] };
    try { roadmap = JSON.parse(lead.roadmap_data || '{}'); } catch(e) {}

    let html = `
        <div class="grid" style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 40px;">
            <div class="sidebar-info">
                <div class="p-4 rounded-xl bg-primary text-white mb-6">
                    <h5 class="mb-1">${lead.full_name || 'العميل'}</h5>
                    <div class="opacity-80 text-sm mb-3">${lead.company_name || 'بدون جهة'}</div>
                    <div style="font-size: 2rem; font-weight: 900;">${lead.overall_score}%</div>
                    <small>درجة نضج الأعمال (SBMI)</small>
                </div>
                
                <h6 class="mb-3 font-bold border-bottom pb-1">الرادار الاستراتيجي</h6>
                <canvas id="insightRadarChart" style="max-height: 300px;"></canvas>

                <div class="mt-6 p-4 rounded bg-light border">
                    <h6 class="text-xs font-bold mb-3 border-bottom pb-1">بيانات التواصل</h6>
                    <div class="mb-2" style="font-size: 0.85rem;"><i class="fas fa-envelope mr-1 opacity-50"></i> ${lead.email || '-'}</div>
                    <div class="mb-2" style="font-size: 0.85rem;"><i class="fas fa-phone mr-1 opacity-50"></i> 
                        <a href="https://wa.me/${(lead.phone || '').replace(/[^0-9]/g, '')}" target="_blank" class="text-success font-bold">${lead.phone || 'بدون هاتف'}</a>
                    </div>
                    <div style="font-size: 0.85rem;"><i class="fas fa-bullseye mr-1 opacity-50"></i> المصدر: <strong>${translate(lead.lead_source || 'direct')}</strong></div>
                </div>
            </div>
            
            <div class="main-analysis">
                <div class="row mb-6" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="border p-3 rounded bg-light">
                        <small class="text-muted d-block">القطاع</small>
                        <strong>${translate(lead.industry)}</strong>
                    </div>
                    <div class="border p-3 rounded bg-light">
                        <small class="text-muted d-block">حجم الفريق</small>
                        <strong>${translate(lead.company_size)}</strong>
                    </div>
                </div>

                <h5 class="mb-4"><i class="fas fa-rocket text-primary"></i> خارطة الطريق الاستراتيجية المقترحة:</h5>
                <div class="roadmap-preview mb-6" style="max-height: 380px; overflow-y: auto; padding-right: 10px;">
                    ${['p1', 'p2', 'p3'].map(phase => `
                        <div class="mb-4">
                            <strong class="text-xs uppercase opacity-60">${phase === 'p1' ? 'المرحلة 1: التدفق' : (phase === 'p2' ? 'المرحلة 2: الأنظمة' : 'المرحلة 3: التوسع')}</strong>
                            ${(roadmap[phase] || []).map(item => `
                                <div class="p-3 mt-2 border-right border-primary bg-white rounded shadow-sm" style="border-right-width: 4px !important;">
                                    <div class="font-bold text-sm mb-1">${item.title}</div>
                                    <div class="text-sm">${item.advice}</div>
                                </div>
                            `).join('')}
                        </div>
                    `).join('')}
                </div>

                ${(lead.insights_data && JSON.parse(lead.insights_data).length > 0) ? `
                <div class="p-4 rounded-xl bg-warning-ultra-light border-warning">
                    <h5 class="mb-3 text-warning-dark font-bold"><i class="fas fa-lightbulb mr-2"></i> الاستنتاجات والفرص الاستراتيجية المكتشفة</h5>
                    ${JSON.parse(lead.insights_data).map(ins => `
                        <div class="mb-3 last:mb-0">
                            <div class="font-bold text-sm text-warning-dark">${ins.insight}</div>
                            <div class="text-xs mt-1 text-muted"><strong>الإجراء المقترح:</strong> ${ins.action}</div>
                        </div>
                    `).join('')}
                </div>
                ` : ''}
            </div>
        </div>
    `;

    document.getElementById('insightContent').innerHTML = html;
    openModal('insightModal');

    // Chart init
    setTimeout(() => {
        const ctx = document.getElementById('insightRadarChart').getContext('2d');
        if(insightRadar) insightRadar.destroy();
        insightRadar = new Chart(ctx, {
            type: 'radar',
            data: {
                labels: ['الاستراتيجية', 'التسويق', 'التقنية', 'العمليات'],
                datasets: [{
                    label: 'أداء المنشأة',
                    data: [pillars.strategy || 0, pillars.marketing || 0, pillars.tech || 0, pillars.operations || 0],
                    backgroundColor: 'rgba(37, 99, 235, 0.2)',
                    borderColor: 'rgb(37, 99, 235)',
                    pointBackgroundColor: 'rgb(37, 99, 235)'
                }]
            },
            options: {
                scales: { r: { suggestedMin: 0, suggestedMax: 100, ticks: { display: false } } },
                plugins: { legend: { display: false } }
            }
        });
    }, 150);
}

async function sendNow(id) {
    if(!confirm('هل تريد تجاوز الجدولة وإرسال التقرير لهذا العميل الآن؟')) return;
    
    try {
        const formData = new FormData();
        formData.append('id', id);
        
        const res = await fetch('<?= url('admin/api/send_report_now.php') ?>', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        
        if(result.success) {
            alert(result.message);
            location.reload();
        } else {
            alert('خطأ: ' + result.message);
        }
    } catch(e) {
        alert('حدث خطأ في الاتصال');
    }
}

function translate(str) {
    const dict = {
        'ecommerce': 'تجارة إلكترونية',
        'services': 'خدمات',
        'retail': 'تجزئة',
        'tech': 'تقنية',
        'fmcg': 'سلع استهلاكية',
        'other': 'أخرى',
        'google': 'جوجل',
        'social': 'سوشيال ميديا',
        'referral': 'توصية',
        'ads': 'إعلانات ممولة',
        'solo': 'فردي',
        'small': 'صغيرة',
        'medium': 'متوسطة',
        'large': 'كبيرة'
    };
    return dict[str] || str || '-';
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
