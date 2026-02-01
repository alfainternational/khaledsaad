<?php
/**
 * أداة التشخيص التفاعلية
 * موقع خالد سعد للاستشارات
 */

require_once dirname(__DIR__) . '/includes/init.php';

// إعدادات SEO
$pageTitle = 'أداة التشخيص المجانية - ' . SITE_NAME;
$pageDescription = 'اكتشف مستوى جاهزيتك الرقمية والتسويقية مع أداة التشخيص المجانية. احصل على تقرير مخصص وتوصيات لتحسين أعمالك.';

include dirname(__DIR__) . '/includes/header.php';
?>

<!-- Page Hero -->
<section class="page-hero" style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: white; padding: var(--space-12) 0;">
    <div class="container">
        <div class="text-center" data-aos="fade-up">
            <span class="hero-badge" style="background: rgba(255,255,255,0.2); color: white;">
                <i class="fas fa-clipboard-check"></i>
                أداة مجانية
            </span>
            <h1 style="font-size: var(--font-size-4xl); margin-bottom: var(--space-4); color: white;">اكتشف مستوى جاهزيتك الرقمية</h1>
            <p style="font-size: var(--font-size-lg); color: rgba(255,255,255,0.9); max-width: 600px; margin: 0 auto;">
                أجب على بضعة أسئلة واحصل على تقرير مخصص مع توصيات لتحسين أداء أعمالك
            </p>
        </div>
    </div>
</section>

<!-- Diagnostic Tool -->
<section style="padding: var(--space-16) 0;">
    <div class="container" style="max-width: 800px;">
        <div class="card" style="padding: var(--space-8);" data-aos="fade-up">

            <!-- Progress -->
            <div class="diagnostic-progress" style="margin-bottom: var(--space-8);">
                <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-2);">
                    <span style="font-weight: 600;">سؤال <span id="currentQuestion">1</span> من <span id="totalQuestions">10</span></span>
                    <span id="progressPercent">0%</span>
                </div>
                <div style="height: 8px; background: var(--bg-tertiary); border-radius: var(--radius-full); overflow: hidden;">
                    <div id="questionProgress" style="height: 100%; width: 0%; background: var(--primary); transition: width var(--transition);"></div>
                </div>
            </div>

            <!-- Questions Container -->
            <div id="questionsContainer">
                <!-- Questions will be loaded here -->
            </div>

            <!-- Results Container (Hidden initially) -->
            <div id="resultsContainer" style="display: none;">
                <div class="text-center" style="margin-bottom: var(--space-8);">
                    <div id="scoreCircle" style="width: 150px; height: 150px; margin: 0 auto var(--space-6); position: relative;">
                        <svg viewBox="0 0 100 100" style="transform: rotate(-90deg);">
                            <circle cx="50" cy="50" r="45" fill="none" stroke="var(--bg-tertiary)" stroke-width="8"/>
                            <circle id="scoreArc" cx="50" cy="50" r="45" fill="none" stroke="var(--primary)" stroke-width="8" stroke-linecap="round" stroke-dasharray="283" stroke-dashoffset="283" style="transition: stroke-dashoffset 1s ease;"/>
                        </svg>
                        <div style="position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                            <span id="scoreValue" style="font-size: var(--font-size-3xl); font-weight: 800;">0</span>
                            <span style="font-size: var(--font-size-sm); color: var(--text-muted);">من 100</span>
                        </div>
                    </div>
                    <h2 id="scoreTitle" style="margin-bottom: var(--space-2);">مستوى جاهزيتك</h2>
                    <p id="scoreDescription" style="color: var(--text-secondary);"></p>
                </div>

                <!-- Category Scores -->
                <div id="categoryScores" style="margin-bottom: var(--space-8);"></div>

                <!-- Recommendations -->
                <div id="recommendations" style="margin-bottom: var(--space-8);">
                    <h3 style="margin-bottom: var(--space-4);">
                        <i class="fas fa-lightbulb" style="color: var(--accent); margin-left: var(--space-2);"></i>
                        توصياتنا لك
                    </h3>
                    <div id="recommendationsList"></div>
                </div>

                <!-- CTA -->
                <div style="text-align: center; padding: var(--space-6); background: var(--bg-tertiary); border-radius: var(--radius-lg);">
                    <h4 style="margin-bottom: var(--space-3);">هل تريد تحسين نتائجك؟</h4>
                    <p style="color: var(--text-secondary); margin-bottom: var(--space-4);">احجز استشارة مجانية مع خبرائنا لمناقشة خطة تحسين مخصصة</p>
                    <div style="display: flex; justify-content: center; gap: var(--space-4); flex-wrap: wrap;">
                        <a href="<?= url('pages/contact.php') ?>" class="btn btn-primary">
                            <i class="fas fa-calendar-check"></i>
                            احجز استشارة
                        </a>
                        <button type="button" onclick="restartQuiz()" class="btn btn-secondary">
                            <i class="fas fa-redo"></i>
                            إعادة التشخيص
                        </button>
                    </div>
                </div>

                <!-- Email Results -->
                <div style="margin-top: var(--space-6); padding: var(--space-6); border: 1px solid var(--border-color); border-radius: var(--radius-lg);">
                    <h4 style="margin-bottom: var(--space-4);">
                        <i class="fas fa-envelope" style="color: var(--primary); margin-left: var(--space-2);"></i>
                        احصل على التقرير الكامل
                    </h4>
                    <form id="resultsEmailForm" style="display: flex; gap: var(--space-3);">
                        <input type="email" name="email" placeholder="بريدك الإلكتروني" class="form-control" required>
                        <button type="submit" class="btn btn-primary">إرسال</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// Questions Data
const questions = [
    {
        id: 1,
        category: 'digital',
        question: 'ما مدى اعتمادك على الأدوات الرقمية في إدارة أعمالك؟',
        options: [
            { text: 'لا نستخدم أي أدوات رقمية', score: 0 },
            { text: 'نستخدم أدوات أساسية (بريد إلكتروني فقط)', score: 25 },
            { text: 'نستخدم بعض الأدوات (CRM، محاسبة)', score: 50 },
            { text: 'نستخدم أدوات متعددة ومتكاملة', score: 75 },
            { text: 'لدينا نظام رقمي شامل ومتقدم', score: 100 }
        ]
    },
    {
        id: 2,
        category: 'marketing',
        question: 'كيف تصف استراتيجيتك التسويقية الحالية؟',
        options: [
            { text: 'لا توجد استراتيجية واضحة', score: 0 },
            { text: 'نعتمد على التسويق التقليدي فقط', score: 25 },
            { text: 'لدينا تواجد أساسي على وسائل التواصل', score: 50 },
            { text: 'لدينا استراتيجية رقمية متكاملة', score: 75 },
            { text: 'نستخدم تحليلات متقدمة وأتمتة التسويق', score: 100 }
        ]
    },
    {
        id: 3,
        category: 'online_presence',
        question: 'ما هو مستوى تواجدك على الإنترنت؟',
        options: [
            { text: 'لا يوجد تواجد على الإنترنت', score: 0 },
            { text: 'لدينا صفحة على وسائل التواصل فقط', score: 25 },
            { text: 'لدينا موقع إلكتروني بسيط', score: 50 },
            { text: 'لدينا موقع متكامل ونشط على وسائل التواصل', score: 75 },
            { text: 'لدينا منظومة رقمية متكاملة (موقع، تطبيق، متجر)', score: 100 }
        ]
    },
    {
        id: 4,
        category: 'data',
        question: 'كيف تستخدم البيانات في اتخاذ القرارات؟',
        options: [
            { text: 'نعتمد على الحدس والخبرة فقط', score: 0 },
            { text: 'نراجع البيانات أحياناً', score: 25 },
            { text: 'نستخدم تقارير أساسية بشكل دوري', score: 50 },
            { text: 'لدينا لوحات تحكم وتحليلات منتظمة', score: 75 },
            { text: 'نستخدم الذكاء الاصطناعي والتحليلات المتقدمة', score: 100 }
        ]
    },
    {
        id: 5,
        category: 'customer',
        question: 'كيف تدير علاقاتك مع العملاء؟',
        options: [
            { text: 'لا يوجد نظام محدد', score: 0 },
            { text: 'نستخدم جداول Excel', score: 25 },
            { text: 'لدينا نظام CRM بسيط', score: 50 },
            { text: 'لدينا نظام CRM متكامل', score: 75 },
            { text: 'لدينا نظام CRM مع أتمتة وتخصيص', score: 100 }
        ]
    },
    {
        id: 6,
        category: 'branding',
        question: 'ما مدى وضوح وقوة هويتك التجارية؟',
        options: [
            { text: 'لا توجد هوية واضحة', score: 0 },
            { text: 'لدينا شعار فقط', score: 25 },
            { text: 'لدينا هوية بصرية أساسية', score: 50 },
            { text: 'لدينا هوية متكاملة ومتسقة', score: 75 },
            { text: 'علامتنا التجارية معروفة ومؤثرة', score: 100 }
        ]
    },
    {
        id: 7,
        category: 'team',
        question: 'ما مدى جاهزية فريقك للعمل الرقمي؟',
        options: [
            { text: 'لا توجد مهارات رقمية', score: 0 },
            { text: 'مهارات أساسية محدودة', score: 25 },
            { text: 'بعض أعضاء الفريق لديهم مهارات جيدة', score: 50 },
            { text: 'معظم الفريق مدرب على الأدوات الرقمية', score: 75 },
            { text: 'فريق متخصص ومحترف في المجال الرقمي', score: 100 }
        ]
    },
    {
        id: 8,
        category: 'competition',
        question: 'كيف ترى موقعك مقارنة بالمنافسين رقمياً؟',
        options: [
            { text: 'متأخرون كثيراً', score: 0 },
            { text: 'متأخرون قليلاً', score: 25 },
            { text: 'في نفس المستوى', score: 50 },
            { text: 'متقدمون قليلاً', score: 75 },
            { text: 'رواد في المجال', score: 100 }
        ]
    },
    {
        id: 9,
        category: 'budget',
        question: 'ما نسبة ميزانيتك المخصصة للتسويق الرقمي؟',
        options: [
            { text: 'لا توجد ميزانية', score: 0 },
            { text: 'أقل من 5%', score: 25 },
            { text: '5-15%', score: 50 },
            { text: '15-30%', score: 75 },
            { text: 'أكثر من 30%', score: 100 }
        ]
    },
    {
        id: 10,
        category: 'goals',
        question: 'ما مدى وضوح أهدافك الرقمية؟',
        options: [
            { text: 'لا توجد أهداف محددة', score: 0 },
            { text: 'أهداف عامة غير قابلة للقياس', score: 25 },
            { text: 'أهداف واضحة لكن بدون متابعة', score: 50 },
            { text: 'أهداف SMART مع متابعة دورية', score: 75 },
            { text: 'أهداف ذكية مع KPIs وتحسين مستمر', score: 100 }
        ]
    }
];

// Category labels
const categoryLabels = {
    digital: 'التحول الرقمي',
    marketing: 'التسويق',
    online_presence: 'التواجد الرقمي',
    data: 'تحليل البيانات',
    customer: 'إدارة العملاء',
    branding: 'الهوية التجارية',
    team: 'جاهزية الفريق',
    competition: 'المنافسة',
    budget: 'الميزانية',
    goals: 'الأهداف'
};

let currentQuestion = 0;
let answers = [];

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('totalQuestions').textContent = questions.length;
    renderQuestion();
});

function renderQuestion() {
    const q = questions[currentQuestion];
    const container = document.getElementById('questionsContainer');

    let optionsHtml = '';
    q.options.forEach((opt, index) => {
        optionsHtml += `
            <label class="option-card" style="display: block; padding: var(--space-4); border: 2px solid var(--border-color); border-radius: var(--radius-lg); margin-bottom: var(--space-3); cursor: pointer; transition: all var(--transition);">
                <input type="radio" name="question_${q.id}" value="${opt.score}" style="display: none;" onchange="selectOption(${opt.score})">
                <div style="display: flex; align-items: center; gap: var(--space-3);">
                    <div class="option-radio" style="width: 24px; height: 24px; border: 2px solid var(--border-color); border-radius: var(--radius-full); flex-shrink: 0; display: flex; align-items: center; justify-content: center; transition: all var(--transition);">
                        <div style="width: 12px; height: 12px; background: var(--primary); border-radius: var(--radius-full); transform: scale(0); transition: transform var(--transition);"></div>
                    </div>
                    <span>${opt.text}</span>
                </div>
            </label>
        `;
    });

    container.innerHTML = `
        <div class="question-slide" style="animation: fadeIn 0.3s ease;">
            <h2 style="margin-bottom: var(--space-2); font-size: var(--font-size-xl);">${q.question}</h2>
            <p style="color: var(--text-muted); margin-bottom: var(--space-6); font-size: var(--font-size-sm);">
                <i class="fas fa-tag" style="margin-left: var(--space-2);"></i>
                ${categoryLabels[q.category]}
            </p>
            <div class="options">${optionsHtml}</div>
        </div>
    `;

    // Update progress
    document.getElementById('currentQuestion').textContent = currentQuestion + 1;
    document.getElementById('progressPercent').textContent = Math.round((currentQuestion / questions.length) * 100) + '%';
    document.getElementById('questionProgress').style.width = ((currentQuestion / questions.length) * 100) + '%';
}

function selectOption(score) {
    // Visual feedback
    document.querySelectorAll('.option-card').forEach(card => {
        card.style.borderColor = 'var(--border-color)';
        card.style.background = 'transparent';
        card.querySelector('.option-radio').style.borderColor = 'var(--border-color)';
        card.querySelector('.option-radio div').style.transform = 'scale(0)';
    });

    const selected = event.target.closest('.option-card');
    selected.style.borderColor = 'var(--primary)';
    selected.style.background = 'rgba(37, 99, 235, 0.05)';
    selected.querySelector('.option-radio').style.borderColor = 'var(--primary)';
    selected.querySelector('.option-radio div').style.transform = 'scale(1)';

    // Store answer
    answers[currentQuestion] = {
        questionId: questions[currentQuestion].id,
        category: questions[currentQuestion].category,
        score: score
    };

    // Move to next question after delay
    setTimeout(() => {
        if (currentQuestion < questions.length - 1) {
            currentQuestion++;
            renderQuestion();
        } else {
            showResults();
        }
    }, 500);
}

function showResults() {
    // Calculate scores
    const categoryScores = {};
    let totalScore = 0;

    answers.forEach(answer => {
        if (!categoryScores[answer.category]) {
            categoryScores[answer.category] = { total: 0, count: 0 };
        }
        categoryScores[answer.category].total += answer.score;
        categoryScores[answer.category].count++;
        totalScore += answer.score;
    });

    const overallScore = Math.round(totalScore / questions.length);

    // Hide questions, show results
    document.getElementById('questionsContainer').style.display = 'none';
    document.querySelector('.diagnostic-progress').style.display = 'none';
    document.getElementById('resultsContainer').style.display = 'block';

    // Animate score
    setTimeout(() => {
        const offset = 283 - (283 * overallScore / 100);
        document.getElementById('scoreArc').style.strokeDashoffset = offset;
        animateValue('scoreValue', 0, overallScore, 1000);
    }, 100);

    // Set score title and description
    let title, description, color;
    if (overallScore >= 80) {
        title = 'ممتاز! أنت جاهز رقمياً';
        description = 'لديك أساس قوي للنمو الرقمي. نوصي بالتركيز على التحسين المستمر والابتكار.';
        color = 'var(--success)';
    } else if (overallScore >= 60) {
        title = 'جيد! لديك إمكانات واعدة';
        description = 'أنت على الطريق الصحيح. بعض التحسينات ستساعدك في تحقيق نتائج أفضل.';
        color = 'var(--primary)';
    } else if (overallScore >= 40) {
        title = 'متوسط - هناك فرص للتحسين';
        description = 'لديك أساسيات جيدة لكنك بحاجة لاستراتيجية شاملة للتحول الرقمي.';
        color = 'var(--accent)';
    } else {
        title = 'بحاجة لتطوير شامل';
        description = 'الخبر الجيد أن هناك فرص كبيرة للنمو. دعنا نساعدك في بناء حضورك الرقمي.';
        color = 'var(--danger)';
    }

    document.getElementById('scoreArc').style.stroke = color;
    document.getElementById('scoreTitle').textContent = title;
    document.getElementById('scoreDescription').textContent = description;

    // Render category scores
    let categoryHtml = '<div style="display: grid; gap: var(--space-4);">';
    for (const [cat, data] of Object.entries(categoryScores)) {
        const avg = Math.round(data.total / data.count);
        categoryHtml += `
            <div>
                <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-2);">
                    <span>${categoryLabels[cat]}</span>
                    <span style="font-weight: 600;">${avg}%</span>
                </div>
                <div style="height: 8px; background: var(--bg-tertiary); border-radius: var(--radius-full); overflow: hidden;">
                    <div style="height: 100%; width: ${avg}%; background: ${avg >= 70 ? 'var(--success)' : avg >= 40 ? 'var(--accent)' : 'var(--danger)'}; transition: width 1s ease;"></div>
                </div>
            </div>
        `;
    }
    categoryHtml += '</div>';
    document.getElementById('categoryScores').innerHTML = categoryHtml;

    // Render recommendations
    const weakCategories = Object.entries(categoryScores)
        .filter(([_, data]) => (data.total / data.count) < 50)
        .sort((a, b) => (a[1].total / a[1].count) - (b[1].total / b[1].count))
        .slice(0, 3);

    const recommendations = {
        digital: 'ابدأ بأتمتة العمليات الأساسية واستخدام أدوات إدارة المشاريع',
        marketing: 'طور استراتيجية تسويق رقمي متكاملة مع قنوات متعددة',
        online_presence: 'أنشئ موقعاً احترافياً وعزز تواجدك على وسائل التواصل',
        data: 'استثمر في أدوات التحليل وابدأ بتتبع مؤشرات الأداء الرئيسية',
        customer: 'اعتمد نظام CRM لإدارة علاقات العملاء بشكل أفضل',
        branding: 'اعمل على بناء هوية تجارية قوية ومتسقة',
        team: 'استثمر في تدريب فريقك على المهارات الرقمية',
        competition: 'حلل منافسيك وحدد فرص التميز',
        budget: 'زد استثمارك في التسويق الرقمي بشكل تدريجي',
        goals: 'حدد أهداف SMART وتابع تقدمك بانتظام'
    };

    let recsHtml = '';
    weakCategories.forEach(([cat], index) => {
        recsHtml += `
            <div style="display: flex; gap: var(--space-4); padding: var(--space-4); background: var(--bg-tertiary); border-radius: var(--radius-lg); margin-bottom: var(--space-3);">
                <div style="width: 32px; height: 32px; background: var(--primary); color: white; border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: 600;">${index + 1}</div>
                <div>
                    <strong style="display: block; margin-bottom: var(--space-1);">${categoryLabels[cat]}</strong>
                    <p style="margin: 0; color: var(--text-secondary); font-size: var(--font-size-sm);">${recommendations[cat]}</p>
                </div>
            </div>
        `;
    });
    document.getElementById('recommendationsList').innerHTML = recsHtml;

    // Save results
    saveResults(overallScore, categoryScores);
}

function animateValue(id, start, end, duration) {
    const element = document.getElementById(id);
    const range = end - start;
    const startTime = performance.now();

    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const value = Math.round(start + range * progress);
        element.textContent = value;
        if (progress < 1) requestAnimationFrame(update);
    }

    requestAnimationFrame(update);
}

function restartQuiz() {
    currentQuestion = 0;
    answers = [];
    document.getElementById('questionsContainer').style.display = 'block';
    document.querySelector('.diagnostic-progress').style.display = 'block';
    document.getElementById('resultsContainer').style.display = 'none';
    renderQuestion();
}

async function saveResults(score, categoryScores) {
    try {
        await fetch('<?= url('api/diagnostic.php') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                answers: answers,
                score: score,
                category_scores: categoryScores,
                session_id: '<?= session_id() ?>'
            })
        });
    } catch (e) {
        console.log('Could not save results');
    }
}

// Email form
document.getElementById('resultsEmailForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const email = this.querySelector('input').value;
    const btn = this.querySelector('button');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    try {
        // Save email with results
        showNotification('success', 'سيتم إرسال التقرير إلى بريدك الإلكتروني');
        this.innerHTML = '<p style="color: var(--success); margin: 0;"><i class="fas fa-check"></i> تم الإرسال!</p>';
    } catch (e) {
        showNotification('error', 'حدث خطأ. يرجى المحاولة مرة أخرى.');
        btn.disabled = false;
        btn.innerHTML = 'إرسال';
    }
});
</script>

<style>
.option-card:hover {
    border-color: var(--primary) !important;
    background: rgba(37, 99, 235, 0.02);
}
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
