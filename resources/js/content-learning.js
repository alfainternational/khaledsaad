import { t } from './i18n';

function initLearningArticle() {
    const article = document.querySelector('[data-learning-article]');

    if (!article) {
        return;
    }

    const progress = document.querySelector('[data-reading-progress] span');
    const percentLabels = document.querySelectorAll('[data-learning-percent]');
    const feedback = document.querySelector('[data-learning-feedback]');
    const storageKey = article.dataset.progressKey;
    let saved = null;
    try {
        saved = storageKey ? JSON.parse(localStorage.getItem(storageKey) || 'null') : null;
    } catch {
        if (storageKey) localStorage.removeItem(storageKey);
    }

    const savedPercent = Number.isFinite(Number(saved?.percent))
        ? Math.min(100, Math.max(0, Number(saved.percent)))
        : 0;
    let currentPercent = 0;
    let maxPercent = savedPercent;
    let activeSectionId = saved?.section || window.location.hash.slice(1) || '';

    const announce = (message) => {
        if (!feedback) return;
        feedback.textContent = message;
        window.setTimeout(() => {
            feedback.textContent = '';
        }, 2200);
    };

    const renderProgress = (percent) => {
        if (progress) progress.style.transform = `scaleX(${percent / 100})`;
        percentLabels.forEach((label) => {
            label.textContent = `${percent}%`;
        });
    };

    const updateProgress = () => {
        const rect = article.getBoundingClientRect();
        const start = window.scrollY + rect.top;
        const readable = Math.max(article.offsetHeight - window.innerHeight, 1);
        currentPercent = Math.round(Math.min(100, Math.max(0, ((window.scrollY - start + 120) / readable) * 100)));
        maxPercent = Math.max(maxPercent, currentPercent);
        renderProgress(maxPercent);
    };

    const saveProgress = (announceSave = true) => {
        if (!storageKey) return;
        try {
            localStorage.setItem(storageKey, JSON.stringify({
                percent: maxPercent,
                section: activeSectionId || null,
                updated_at: new Date().toISOString(),
            }));
            if (announceSave) announce(t('حُفظ تقدّمك عند :percent%', { percent: maxPercent }));
        } catch {
            if (announceSave) announce(t('تعذر حفظ التقدم في هذا المتصفح'));
        }
    };

    document.querySelector('[data-learning-save]')?.addEventListener('click', () => saveProgress());
    document.querySelector('[data-learning-print]')?.addEventListener('click', () => window.print());
    document.querySelector('[data-learning-copy]')?.addEventListener('click', async () => {
        const sectionUrl = new URL(window.location.href);
        sectionUrl.hash = activeSectionId ? `#${activeSectionId}` : '';
        try {
            await navigator.clipboard.writeText(sectionUrl.toString());
            announce(t('نُسخ رابط موضع القراءة'));
        } catch {
            window.prompt(t('انسخ رابط موضع القراءة:'), sectionUrl.toString());
        }
    });
    renderProgress(maxPercent);
    if (savedPercent > 0) announce(t('تقدّمك المحفوظ: :percent%', { percent: savedPercent }));

    let ticking = false;
    window.addEventListener('scroll', () => {
        if (ticking) return;
        ticking = true;
        window.requestAnimationFrame(() => {
            updateProgress();
            ticking = false;
        });
    }, { passive: true });
    window.addEventListener('pagehide', () => saveProgress(false));
    updateProgress();

    const outlineLinks = [...document.querySelectorAll('[data-outline-link]')];
    const headings = outlineLinks
        .map((link) => document.getElementById(link.hash.slice(1)))
        .filter(Boolean);

    if ('IntersectionObserver' in window && headings.length) {
        const observer = new IntersectionObserver((entries) => {
            const visible = entries.find((entry) => entry.isIntersecting);
            if (!visible) return;
            activeSectionId = visible.target.id;
            outlineLinks.forEach((link) => link.classList.toggle('is-active', link.hash === `#${visible.target.id}`));
        }, { rootMargin: '-18% 0px -70%', threshold: 0 });
        headings.forEach((heading) => observer.observe(heading));
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLearningArticle);
} else {
    initLearningArticle();
}
