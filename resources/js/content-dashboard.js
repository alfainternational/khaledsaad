document.addEventListener('DOMContentLoaded', () => {
    const dashboard = document.querySelector('[data-content-dashboard]');

    if (!dashboard) {
        return;
    }

    const viewButtons = dashboard.querySelectorAll('[data-content-view]');
    const panels = dashboard.querySelectorAll('[data-content-panel]');
    const items = dashboard.querySelectorAll('[data-content-item]');
    const search = dashboard.querySelector('[data-content-search]');
    const pillar = dashboard.querySelector('[data-content-pillar]');
    const stage = dashboard.querySelector('[data-content-stage]');
    const empty = dashboard.querySelector('[data-content-empty]');

    viewButtons.forEach((button) => {
        button.addEventListener('click', () => {
            viewButtons.forEach((candidate) => {
                const active = candidate === button;
                candidate.classList.toggle('is-active', active);
                candidate.classList.toggle('btn--ghost', !active);
                candidate.setAttribute('aria-pressed', String(active));
            });

            panels.forEach((panel) => {
                panel.hidden = panel.dataset.contentPanel !== button.dataset.contentView;
            });
        });
    });

    const normalize = (value) => String(value || '')
        .normalize('NFKD')
        .replace(/[\u064B-\u065F\u0670]/g, '')
        .replace(/[إأآ]/g, 'ا')
        .replace(/ى/g, 'ي')
        .toLowerCase();

    const applyFilters = () => {
        const query = normalize(search?.value);
        const selectedPillar = pillar?.value || '';
        const selectedStage = stage?.value || '';
        let visible = 0;

        items.forEach((item) => {
            const matches = (!query || normalize(item.dataset.search).includes(query))
                && (!selectedPillar || item.dataset.pillar === selectedPillar)
                && (!selectedStage || item.dataset.stage === selectedStage);

            item.hidden = !matches;
            if (matches && item.closest('[data-content-panel]:not([hidden]), .content-workspace')) {
                visible += 1;
            }
        });

        if (empty) {
            empty.hidden = visible > 0;
        }
    };

    search?.addEventListener('input', applyFilters);
    pillar?.addEventListener('change', applyFilters);
    stage?.addEventListener('change', applyFilters);

    dashboard.addEventListener('click', (event) => {
        const button = event.target.closest('[data-copy-content]');

        if (!button) {
            return;
        }

        const source = document.getElementById(button.dataset.copyContent);
        if (!source) {
            return;
        }

        const original = button.textContent;
        const done = () => {
            button.textContent = 'نُسخ ✓';
            setTimeout(() => { button.textContent = original; }, 1800);
        };

        if (navigator.clipboard?.writeText) {
            navigator.clipboard.writeText(source.textContent.trim()).then(done).catch(() => selectText(source));
        } else {
            selectText(source);
        }
    });

    const openHashTarget = () => {
        const target = document.querySelector(window.location.hash);
        if (target instanceof HTMLDetailsElement) {
            target.open = true;
        }
    };

    window.addEventListener('hashchange', openHashTarget);
    openHashTarget();
});

function selectText(node) {
    const range = document.createRange();
    range.selectNodeContents(node);
    const selection = window.getSelection();
    selection?.removeAllRanges();
    selection?.addRange(range);
}
