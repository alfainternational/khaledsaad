<?php
/**
 * المساعد الشخصي لخالد سعد (AI Brain Command Center)
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';
require_once SITE_ROOT . '/includes/ai_brain.php';

if (!isset($_SESSION['admin_id'])) {
    redirect(url('admin/login.php'));
}

$brain = new AIBrain();
$pageTitle = 'المساعد الاستراتيجي المتقدم';
include __DIR__ . '/includes/header.php';

// معالجة الرسائل الجديدة عبر AJAX (سأضع الكود للتبسيط في نفس الصفحة حالياً)
?>

<div class="page-header d-flex justify-between items-center no-print">
    <div>
        <h1>المساعد الاستراتيجي (Strategic Brain v2.0)</h1>
        <p>تحليل دائم لسلوك العملاء وتقديم توصيات نمو مباشرة</p>
    </div>
    <div class="header-actions">
        <div id="aiStatus" class="mr-4 small"></div>
        <button onclick="refreshAnalysis()" class="btn btn-primary"><i class="fas fa-sync mr-2"></i> تحديث تحليل السلوك</button>
    </div>
</div>

<div class="grid" style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 30px; margin-top: 30px;">
    
    <!-- Chat Interface -->
    <div class="card" style="display: flex; flex-direction: column; height: 600px;">
        <div class="card-header"><h3><i class="fas fa-comments mr-2"></i> دردشة المساعد الشخصي</h3></div>
        <div id="chatWindow" class="card-body" style="flex:1; overflow-y: auto; background: #f8fafc; padding: 20px;">
            <div class="message assistant mb-4" style="background:#fff; padding:15px; border-radius:12px; border:1px solid #e2e8f0; width: 80%;">
                أهلاً بك يا أستاذ خالد. لقد قمت بتحليل آخر 500 عملية تفاعل في الموقع. هل تود معرفة التوصيات الحالية أم لديك سؤال محدد؟
            </div>
            <!-- الرسائل ستظهر هنا -->
        </div>
        <div class="card-footer" style="padding: 15px; background: white;">
            <form id="chatForm" class="d-flex gap-2">
                <input type="text" id="userInput" class="form-control" placeholder="اسأل المساعد عن أي شيء في العمل..." required>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i></button>
            </form>
        </div>
    </div>

    <!-- AI Insights & KB Evolution -->
    <div class="sidebar">
        <div class="card mb-6">
            <div class="card-header"><h3><i class="fas fa-lightbulb mr-2"></i> رؤى ذكية (سلوك المنصة)</h3></div>
            <div id="aiSummary" class="card-body" style="font-size:0.9rem; line-height:1.7;">
                <div class="text-center py-10 opacity-50"><i class="fas fa-spinner fa-spin fa-2x"></i><br>جاري تحليل السلوك الحالي...</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3><i class="fas fa-brain mr-2"></i> قاعدة المعرفة المتطورة</h3></div>
            <div class="card-body">
                <p class="small text-muted mb-4">اقتراحات الأنظمة التنفيذية لتغذية قاعدة المعرفة تلقائياً بناءً على تكرار الأنماط:</p>
                <div id="kbSuggestions">
                    <div class="text-center py-4 opacity-50 small">جاري استخراج الأنماط من البيانات الحقيقية...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const chatForm = document.getElementById('chatForm');
const chatWindow = document.getElementById('chatWindow');
const userInput = document.getElementById('userInput');
const aiSummary = document.getElementById('aiSummary');
const kbSuggestions = document.getElementById('kbSuggestions');

window.onload = () => { refreshAnalysis(); };

function refreshAnalysis() {
    aiSummary.innerHTML = '<div class="text-center py-10 opacity-50"><i class="fas fa-spinner fa-spin fa-2x"></i><br>جاري استدعاء فريق العمل للتحليل...</div>';
    kbSuggestions.innerHTML = '';
    
    fetch('<?= url('api/ai_brain_analyst.php?action=analyze') ?>')
    .then(r => r.json())
    .then(res => {
        const data = res.data;
        
        // عرض حالة الرصيد/النظام
        const statusEl = document.getElementById('aiStatus');
        if (data.is_fallback) {
            statusEl.innerHTML = `<span class="text-warning"><i class="fas fa-exclamation-triangle"></i> ${data.status_message || 'تحليل محلي'}</span>`;
        } else {
            statusEl.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> محرك التحليل الاستراتيجي متصل ويعمل بنجاح</span>';
        }
        
        // 1. عرض رأي الفريق
        let teamHtml = `
            <div class="team-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:20px;">
                <div class="p-3 rounded bg-blue-50" style="background:#eff6ff; border-right:3px solid #3b82f6;">
                    <small class="d-block font-bold" style="color:#1d4ed8;"><i class="fas fa-code mr-1"></i> رأي المبرمج</small>
                    <div class="text-xs mt-1 text-muted">${data.team_report.programmer_view}</div>
                </div>
                <div class="p-3 rounded bg-green-50" style="background:#f0fdf4; border-right:3px solid #22c55e;">
                    <small class="d-block font-bold" style="color:#15803d;"><i class="fas fa-bullhorn mr-1"></i> رأي المسوق</small>
                    <div class="text-xs mt-1 text-muted">${data.team_report.marketer_view}</div>
                </div>
                <div class="p-3 rounded bg-orange-50" style="background:#fff7ed; border-right:3px solid #f97316;">
                    <small class="d-block font-bold" style="color:#c2410c;"><i class="fas fa-pen-nib mr-1"></i> رأي الكاتب</small>
                    <div class="text-xs mt-1 text-muted">${data.team_report.content_view}</div>
                </div>
                <div class="p-3 rounded bg-purple-50" style="background:#faf5ff; border-right:3px solid #a855f7;">
                    <small class="d-block font-bold" style="color:#7e22ce;"><i class="fas fa-user-check mr-1"></i> رأي خبير UX</small>
                    <div class="text-xs mt-1 text-muted">${data.team_report.ux_view}</div>
                </div>
            </div>
            <hr class="my-4">
            <h4 class="mb-3">توصيات الفريق:</h4>
        `;
        
        if (data.recommendations) {
            data.recommendations.forEach(r => {
                teamHtml += `
                    <div class="mb-2 p-2 rounded" style="background:#f8fafc; font-size:0.85rem; border:1px solid #e2e8f0;">
                       <span class="badge" style="background:#64748b; color:white; font-size:0.6rem;">${r.role}</span>
                       <span class="ml-2">${r.action}</span>
                       <span class="float-left" style="color:${r.impact === 'عالي' ? '#ef4444' : '#f59e0b'}; font-weight:900;">!!</span>
                    </div>
                `;
            });
        }
        
        aiSummary.innerHTML = teamHtml;

        // 2. عرض مقترحات قاعدة المعرفة
        if (data.kb_proposals) {
            data.kb_proposals.forEach(p => {
                const div = document.createElement('div');
                div.className = 'p-3 border rounded mb-3 bg-light';
                div.innerHTML = `
                    <div class="small"><strong>القاعدة:</strong> ${p.rule}</div>
                    <div class="text-xs text-muted mt-1 italic"><strong>السبب:</strong> ${p.reason}</div>
                    <button class="btn btn-xs btn-outline-success mt-2" onclick="alert('سيتم دمج القواعد تلقائيا في الإصدار القادم!')">اعتماد</button>
                `;
                kbSuggestions.appendChild(div);
            });
        }
    })
    .catch(err => {
        aiSummary.innerHTML = '<div class="alert alert-danger">حدث خطأ أثناء تحليل البيانات. تأكد من وجود نشاط حقيقي في الموقع.</div>';
    });
}

chatForm.onsubmit = (e) => {
    e.preventDefault();
    const msg = userInput.value;
    if(!msg) return;
    addMessage(msg, 'user');
    userInput.value = '';

    fetch('<?= url('api/ai_brain_analyst.php?action=chat') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: msg })
    })
    .then(r => r.json())
    .then(data => {
        addMessage(data.response, 'assistant');
    });
};

function addMessage(msg, role) {
    const div = document.createElement('div');
    div.className = `message ${role} mb-4`;
    div.style = role === 'user' 
        ? 'background:#3b82f6; color:white; padding:15px; border-radius:12px; margin-right:auto; width: 85%;'
        : 'background:#fff; padding:15px; border-radius:12px; border:1px solid #e2e8f0; width: 85%;';
    div.innerHTML = msg;
    chatWindow.appendChild(div);
    chatWindow.scrollTop = chatWindow.scrollHeight;
}
</script>

<style>
.message.assistant { border-right: 4px solid var(--primary); }
.message.user { border-left: 4px solid #ef4444; }
.text-xs { font-size: 0.75rem; }
.font-bold { font-weight: 700; }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
