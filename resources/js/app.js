const THEME_KEY = 'ks-theme';

function getStoredTheme() {
    try {
        return localStorage.getItem(THEME_KEY);
    } catch (_) {
        return null;
    }
}

function applyTheme(theme, { persist = true } = {}) {
    document.documentElement.setAttribute('data-theme', theme);

    if (!persist) {
        return;
    }

    try {
        localStorage.setItem(THEME_KEY, theme);
    } catch (_) {
        // Ignore storage failures.
    }
}

function initialTheme() {
    const savedTheme = getStoredTheme();

    if (savedTheme) {
        return savedTheme;
    }

    return window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches
        ? 'light'
        : 'dark';
}

function wireThemeToggle() {
    applyTheme(initialTheme(), { persist: false });

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            applyTheme(current === 'dark' ? 'light' : 'dark');
        });
    });

    if (!window.matchMedia) {
        return;
    }

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (event) => {
        if (!getStoredTheme()) {
            applyTheme(event.matches ? 'dark' : 'light', { persist: false });
        }
    });
}

function wireWorkspaceSwitcher() {
    const switcher = document.getElementById('workspace-switcher');
    const form = document.getElementById('workspace-switcher-form');

    if (!switcher || !form) {
        return;
    }

    switcher.addEventListener('change', (event) => {
        form.action = event.target.value;
        form.submit();
    });
}

function wireShellDrawer() {
    const body = document.body;
    const overlay = document.querySelector('.shell-overlay');
    const openButtons = document.querySelectorAll('[data-shell-toggle]');
    const closeButtons = document.querySelectorAll('[data-shell-close]');

    const closeDrawer = () => {
        body.classList.remove('shell-drawer-open');

        if (overlay) {
            overlay.hidden = true;
        }

        openButtons.forEach((button) => button.setAttribute('aria-expanded', 'false'));
    };

    const openDrawer = () => {
        body.classList.add('shell-drawer-open');

        if (overlay) {
            overlay.hidden = false;
        }

        openButtons.forEach((button) => button.setAttribute('aria-expanded', 'true'));
    };

    openButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (body.classList.contains('shell-drawer-open')) {
                closeDrawer();
            } else {
                openDrawer();
            }
        });
    });

    closeButtons.forEach((button) => {
        button.addEventListener('click', closeDrawer);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeDrawer();
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 1100) {
            closeDrawer();
        }
    });
}

function wireRevealAnimations() {
    const revealElements = document.querySelectorAll('.reveal, .reveal-left, .reveal-right');

    if (!revealElements.length) {
        return;
    }

    if (!('IntersectionObserver' in window)) {
        revealElements.forEach((element) => element.classList.add('visible'));

        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            entry.target.classList.add('visible');
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.01, rootMargin: '0px 0px -20px 0px' });

    revealElements.forEach((element) => observer.observe(element));
}

function animateCounter(element) {
    const target = Number.parseFloat(element.dataset.target ?? '0');
    const suffix = element.dataset.suffix ?? '';
    const isInt = Number.isInteger(target);
    const duration = 2000;
    const step = 16;
    const increment = target / (duration / step);
    let current = 0;

    const timer = window.setInterval(() => {
        current = Math.min(current + increment, target);
        element.textContent = `${isInt ? Math.floor(current) : current.toFixed(1)}${suffix}`;

        if (current >= target) {
            window.clearInterval(timer);
        }
    }, step);
}

function wireCounters() {
    const counters = document.querySelectorAll('.counter');

    if (!counters.length) {
        return;
    }

    if (!('IntersectionObserver' in window)) {
        counters.forEach((counter) => animateCounter(counter));

        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting || entry.target.dataset.counted === 'true') {
                return;
            }

            entry.target.dataset.counted = 'true';
            animateCounter(entry.target);
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.4 });

    counters.forEach((counter) => observer.observe(counter));
}

function wireStickyNav() {
    const nav = document.getElementById('main-nav');

    if (!nav) {
        return;
    }

    let isScrolled = false;

    window.addEventListener('scroll', () => {
        const nextState = window.scrollY > 40;

        if (nextState === isScrolled) {
            return;
        }

        nav.classList.toggle('scrolled', nextState);
        isScrolled = nextState;
    }, { passive: true });
}

function wireMarketingNav() {
    const nav = document.getElementById('main-nav');
    const toggle = document.querySelector('[data-nav-toggle]');
    const links = document.getElementById('nav-links');

    if (!nav || !toggle || !links) {
        return;
    }

    toggle.addEventListener('click', () => {
        const expanded = links.classList.toggle('open');
        toggle.setAttribute('aria-expanded', String(expanded));
        toggle.setAttribute('aria-label', expanded ? 'إغلاق القائمة' : 'فتح القائمة');
    });

    document.addEventListener('click', (event) => {
        if (nav.contains(event.target)) {
            return;
        }

        links.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'فتح القائمة');
    });
}

function wireDynamicLists() {
    document.querySelectorAll('[data-dynamic-list-add]').forEach((button) => {
        button.addEventListener('click', () => {
            const listId = button.dataset.dynamicListAdd;
            const templateId = button.dataset.templateId;

            if (!listId || !templateId) {
                return;
            }

            const container = document.getElementById(listId);
            const template = document.getElementById(templateId);

            if (!(container instanceof HTMLElement) || !(template instanceof HTMLTemplateElement)) {
                return;
            }

            const nextIndex = Number.parseInt(container.dataset.nextIndex ?? String(container.children.length), 10);
            const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex)).trim();

            container.insertAdjacentHTML('beforeend', html);
            container.dataset.nextIndex = String(nextIndex + 1);
        });
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-dynamic-remove]');

        if (!button) {
            return;
        }

        button.closest('.admin-dynamic-row')?.remove();
    });
}

function wireToolModeSwitcher() {
    document.querySelectorAll('[data-tool-mode-root]').forEach((root) => {
        const switcher = root.querySelector('[data-tool-mode-switcher]');
        const form = root.closest('form');

        if (!switcher || !form) {
            return;
        }

        const syncPanels = () => {
            const selectedMode = switcher.value;

            form.querySelectorAll('[data-tool-mode-panel]').forEach((panel) => {
                panel.hidden = panel.dataset.toolModePanel !== selectedMode;
            });

            root.querySelectorAll('[data-tool-mode-button]').forEach((button) => {
                button.classList.toggle('is-active', button.dataset.toolModeButton === selectedMode);
            });
        };

        if (switcher.tagName === 'SELECT') {
            switcher.addEventListener('change', syncPanels);
        }

        root.querySelectorAll('[data-tool-mode-button]').forEach((button) => {
            button.addEventListener('click', () => {
                switcher.value = button.dataset.toolModeButton ?? switcher.value;
                syncPanels();
            });
        });

        syncPanels();
    });
}

function buildDiagnosisPreviewState(root) {
    const value = (key) => {
        const element = root.querySelector(`[data-diagnosis-input="${key}"]`);
        return element ? element.value.trim() : '';
    };

    const scores = ['clarity_score', 'offer_score', 'conversion_score']
        .map((key) => Number.parseInt(value(key), 10))
        .filter((score) => !Number.isNaN(score));

    const score = scores.length
        ? Math.round((scores.reduce((sum, current) => sum + current, 0) / (scores.length * 10)) * 100)
        : 0;

    const headline = value('main_goal') || value('biggest_gap') || 'تشخيص أولي لحالة المشروع';
    const focus = value('main_bottleneck') || value('biggest_gap') || 'تحديد نقطة التعثر الرئيسية بشكل أوضح';
    const outcome = value('needed_outcome') || value('priority_week') || 'تحديد أول خطوة عملية بعد التشخيص';

    return {
        score,
        headline,
        text: `أهم نقطة تحتاج تركيزاً الآن هي ${focus}.`,
        bullets: [
            outcome,
            value('priority_month') || value('critical_assumption') || 'حدد أولوية الشهر القادم حتى لا يبقى التشخيص نظرياً.',
            value('brief') || 'إذا كانت هناك تفاصيل مؤثرة جداً، أضفها قبل الحفظ.',
        ],
    };
}

function wireDiagnosisPreview() {
    document.querySelectorAll('[data-diagnosis-preview-root]').forEach((root) => {
        const scoreNode = root.querySelector('[data-diagnosis-score]');
        const headlineNode = root.querySelector('[data-diagnosis-headline]');
        const textNode = root.querySelector('[data-diagnosis-text]');
        const bulletsNode = root.querySelector('[data-diagnosis-bullets]');

        if (!scoreNode || !headlineNode || !textNode || !bulletsNode) {
            return;
        }

        const syncPreview = () => {
            const preview = buildDiagnosisPreviewState(root);

            scoreNode.textContent = `${preview.score}%`;
            headlineNode.textContent = preview.headline;
            textNode.textContent = preview.text;
            bulletsNode.innerHTML = preview.bullets
                .filter((bullet) => bullet && bullet.trim() !== '')
                .map((bullet) => `<li>${bullet}</li>`)
                .join('');
        };

        root.querySelectorAll('[data-diagnosis-input]').forEach((input) => {
            input.addEventListener('input', syncPreview);
            input.addEventListener('change', syncPreview);
        });

        root.querySelectorAll('[data-tool-mode-button], [data-tool-mode-switcher]').forEach((control) => {
            control.addEventListener('click', syncPreview);
            control.addEventListener('change', syncPreview);
        });

        syncPreview();
    });
}

function escapeHtml(value) {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function isVisibleToolInput(input) {
    const panel = input.closest('[data-tool-mode-panel]');

    if (!panel) {
        return true;
    }

    return !panel.hidden;
}

function getToolInputValue(input) {
    if (input.tagName === 'SELECT') {
        const option = input.options[input.selectedIndex];
        return option && option.value !== '' ? option.text.trim() : '';
    }

    return input.value.trim();
}

function buildGenericToolPreviewState(root) {
    const inputs = Array.from(root.querySelectorAll('[data-tool-preview-input]'))
        .filter(isVisibleToolInput)
        .map((input) => ({
            label: input.dataset.toolPreviewLabel ?? 'مدخل',
            value: getToolInputValue(input),
        }));
    const filledInputs = inputs.filter((item) => item.value !== '');
    const score = inputs.length ? Math.round((filledInputs.length / inputs.length) * 100) : 0;
    const toolResult = root.dataset.toolPreviewResult ?? 'النتيجة الحالية';
    const emptyIntro = root.dataset.toolPreviewIntro ?? 'ابدأ بإكمال الحقول الأساسية لتظهر الخلاصة هنا.';
    const firstFilled = filledInputs[0];
    const headline = firstFilled
        ? `${toolResult}: ${firstFilled.value}`
        : `${toolResult} أولية`;
    const text = firstFilled
        ? `التركيز الحالي واضح حول ${firstFilled.label}. أكمل بقية الحقول حتى تتحول النتيجة إلى مخرج جاهز للاستخدام.`
        : emptyIntro;
    const bullets = filledInputs.slice(0, 3).map((item) => `${item.label}: ${item.value}`);

    if (!bullets.length) {
        bullets.push('املأ الحقول الرئيسية لتظهر هنا الخلاصة العملية والاتجاه الأقرب.');
    }

    return {
        score,
        headline,
        text,
        bullets,
    };
}

function wireGenericToolPreview() {
    document.querySelectorAll('[data-tool-preview-root]').forEach((root) => {
        const scoreNode = root.querySelector('[data-tool-preview-score]');
        const headlineNode = root.querySelector('[data-tool-preview-headline]');
        const textNode = root.querySelector('[data-tool-preview-text]');
        const bulletsNode = root.querySelector('[data-tool-preview-bullets]');

        if (!scoreNode || !headlineNode || !textNode || !bulletsNode) {
            return;
        }

        const syncPreview = () => {
            const preview = buildGenericToolPreviewState(root);

            scoreNode.textContent = `${preview.score}%`;
            headlineNode.textContent = preview.headline;
            textNode.textContent = preview.text;
            bulletsNode.innerHTML = preview.bullets
                .map((bullet) => `<li>${escapeHtml(bullet)}</li>`)
                .join('');
        };

        root.querySelectorAll('[data-tool-preview-input]').forEach((input) => {
            input.addEventListener('input', syncPreview);
            input.addEventListener('change', syncPreview);
        });

        root.querySelectorAll('[data-tool-mode-button], [data-tool-mode-switcher]').forEach((control) => {
            control.addEventListener('click', syncPreview);
            control.addEventListener('change', syncPreview);
        });

        syncPreview();
    });
}

function wireToolLibraryFilters() {
    const root = document.querySelector('[data-tool-library]');

    if (!root) {
        return;
    }

    const cards = Array.from(document.querySelectorAll('[data-tool-card]'));
    const searchInput = root.querySelector('[data-tool-search]');
    const filterButtons = Array.from(root.querySelectorAll('[data-tool-filter]'));
    let activeFilter = 'all';

    const sync = () => {
        const query = (searchInput?.value ?? '').trim().toLowerCase();

        cards.forEach((card) => {
            const state = card.dataset.toolState ?? 'all';
            const title = card.dataset.toolTitle ?? '';
            const body = card.dataset.toolBody ?? '';
            const matchesFilter = activeFilter === 'all' || state === activeFilter;
            const matchesSearch = query === '' || title.includes(query) || body.includes(query);
            const visible = matchesFilter && matchesSearch;
            const container = card.closest('article');

            if (container) {
                container.hidden = !visible;
            }
        });
    };

    filterButtons.forEach((button) => {
        button.addEventListener('click', () => {
            activeFilter = button.dataset.toolFilter ?? 'all';
            filterButtons.forEach((item) => item.classList.toggle('is-active', item === button));
            sync();
        });
    });

    searchInput?.addEventListener('input', sync);
    sync();
}

function setToolStepperIndex(stepper, nextIndex) {
    if (!stepper || stepper.dataset.toolStepperStatic === 'true') {
        return;
    }

    const panels = Array.from(stepper.querySelectorAll('[data-tool-step-panel]'));
    const nextButton = stepper.querySelector('[data-tool-step-next]');
    const prevButton = stepper.querySelector('[data-tool-step-prev]');
    const indicator = stepper.querySelector('[data-tool-step-indicator]');

    if (!panels.length || !nextButton || !prevButton) {
        return;
    }

    const index = Math.max(0, Math.min(nextIndex, panels.length - 1));
    stepper.dataset.currentStepIndex = String(index);

    panels.forEach((panel, panelIndex) => {
        panel.hidden = panelIndex !== index;
    });

    prevButton.disabled = index === 0;
    nextButton.textContent = index === panels.length - 1 ? 'تم' : 'التالي';

    if (indicator) {
        indicator.textContent = `${index + 1} / ${panels.length}`;
    }
}

function wireToolSteppers() {
    document.querySelectorAll('[data-tool-stepper]').forEach((stepper) => {
        if (stepper.dataset.toolStepperStatic === 'true') {
            return;
        }

        const panels = Array.from(stepper.querySelectorAll('[data-tool-step-panel]'));
        const nextButton = stepper.querySelector('[data-tool-step-next]');
        const prevButton = stepper.querySelector('[data-tool-step-prev]');

        if (!panels.length || !nextButton || !prevButton) {
            return;
        }

        nextButton.addEventListener('click', () => {
            const current = Number.parseInt(stepper.dataset.currentStepIndex ?? '0', 10) || 0;
            if (current < panels.length - 1) {
                setToolStepperIndex(stepper, current + 1);
            }
        });

        prevButton.addEventListener('click', () => {
            const current = Number.parseInt(stepper.dataset.currentStepIndex ?? '0', 10) || 0;
            if (current > 0) {
                setToolStepperIndex(stepper, current - 1);
            }
        });

        setToolStepperIndex(stepper, Number.parseInt(stepper.dataset.currentStepIndex ?? '0', 10) || 0);
    });
}

function wireToolWorkspaceShortcuts() {
    document.addEventListener('keydown', (event) => {
        if (!event.altKey) {
            return;
        }

        if (event.key.toLowerCase() === 's') {
            const submitButton = document.querySelector('[data-tool-workspace-form] button[type="submit"]');

            if (submitButton instanceof HTMLElement) {
                event.preventDefault();
                submitButton.click();
            }
        }
    });
}

function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

function fetchPost(url, body) {
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
        credentials: 'same-origin',
    });
}

function showToast(message, type = 'success') {
    const existing = document.querySelector('.app-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = `app-toast app-toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);

    requestAnimationFrame(() => toast.classList.add('app-toast-visible'));

    setTimeout(() => {
        toast.classList.remove('app-toast-visible');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

function collectFormInputs(form) {
    const data = {};
    const formData = new FormData(form);
    for (const [key, value] of formData.entries()) {
        if (key === '_token') continue;
        if (key.startsWith('inputs[')) {
            const inputKey = key.replace('inputs[', '').replace(']', '');
            if (!data.inputs) data.inputs = {};
            data.inputs[inputKey] = value;
        } else {
            data[key] = value;
        }
    }
    return data;
}

function dispatchToolFieldUpdate(field) {
    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.dispatchEvent(new Event('change', { bubbles: true }));
}

function resolveSelectSuggestionValue(field, value) {
    const normalizedValue = String(value ?? '').trim();

    if (normalizedValue === '') {
        return '';
    }

    const exactValueOption = Array.from(field.options).find((option) => option.value === normalizedValue);
    if (exactValueOption) {
        return exactValueOption.value;
    }

    const normalizedLower = normalizedValue.toLowerCase();
    const labelMatch = Array.from(field.options).find((option) => option.text.trim().toLowerCase() === normalizedLower);
    if (labelMatch) {
        return labelMatch.value;
    }

    const partialMatch = Array.from(field.options).find((option) => {
        const optionText = option.text.trim().toLowerCase();
        const optionValue = option.value.trim().toLowerCase();

        return optionText.includes(normalizedLower) || normalizedLower.includes(optionText)
            || optionValue.includes(normalizedLower) || normalizedLower.includes(optionValue);
    });

    return partialMatch ? partialMatch.value : null;
}

function setToolFieldValue(field, value) {
    if (!field) {
        return false;
    }

    const nextValue = String(value ?? '').trim();

    if (field.tagName === 'SELECT') {
        const resolvedValue = resolveSelectSuggestionValue(field, nextValue);
        if (resolvedValue === null) {
            return false;
        }

        field.value = resolvedValue;
        dispatchToolFieldUpdate(field);
        return true;
    }

    field.value = nextValue;
    dispatchToolFieldUpdate(field);

    return true;
}

async function submitToolFormAjax(form, options = {}) {
    const {
        triggerButton = null,
        pendingText = 'جارٍ الحفظ...',
        fallbackToSubmit = true,
        successMessage = null,
    } = options;
    const ajaxUrl = form.dataset.toolAjaxUrl;
    const statusEl = form.querySelector('[data-tool-status]');

    if (!ajaxUrl) {
        return { success: false, fallback: true };
    }

    const button = triggerButton;
    const originalText = button ? button.innerHTML : null;

    if (button) {
        button.disabled = true;
        button.innerHTML = pendingText;
    }

    if (statusEl) {
        statusEl.hidden = true;
        statusEl.textContent = '';
    }

    try {
        const formData = collectFormInputs(form);
        const response = await fetchPost(ajaxUrl, formData);

        if (!response.ok && response.status !== 422) {
            if (fallbackToSubmit) {
                form.submit();
            }

            return { success: false, fallback: fallbackToSubmit };
        }

        const result = await response.json();

        if (result.success) {
            showToast(successMessage || result.message || 'تم الحفظ بنجاح.');
            const workbench = form.closest('.tool-workbench');
            if (workbench) {
                renderToolResult(workbench, result.data);
            }

            if (statusEl) {
                statusEl.hidden = false;
                statusEl.textContent = 'تم الحفظ — يمكنك تعديل إجاباتك أعلاه وإعادة الحفظ في أي وقت لتحديث النتيجة.';
                statusEl.className = 'tool-form-status tool-form-status-success';
                setTimeout(() => {
                    statusEl.hidden = true;
                }, 6000);
            }
        } else {
            const errorMsg = result.error || result.message || 'حدث خطأ أثناء الحفظ.';
            showToast(errorMsg, 'error');

            if (statusEl) {
                statusEl.hidden = false;
                statusEl.textContent = errorMsg;
                statusEl.className = 'tool-form-status tool-form-status-error';
            }
        }

        return result;
    } catch (err) {
        if (fallbackToSubmit) {
            form.submit();
        }

        return { success: false, fallback: fallbackToSubmit, error: err };
    } finally {
        if (button) {
            button.disabled = false;
            button.innerHTML = originalText;
        }
    }
}

function clearToolFormFields(form) {
    form.querySelectorAll('input[name^="inputs["], textarea[name^="inputs["], select[name^="inputs["]').forEach((field) => {
        if (field.tagName === 'SELECT') {
            field.value = '';
        } else {
            field.value = '';
        }

        dispatchToolFieldUpdate(field);
    });

    const briefField = form.querySelector('[name="brief"]');
    if (briefField) {
        briefField.value = '';
        dispatchToolFieldUpdate(briefField);
    }
}

function parseJsonDataAttribute(value, fallback = []) {
    if (!value) {
        return fallback;
    }

    try {
        const parsed = JSON.parse(value);
        return Array.isArray(parsed) ? parsed : fallback;
    } catch (_) {
        return fallback;
    }
}

/** يمنع أسطر «الخطوة التالية» من أن تصبح فقرة ثانية تحت الحقل */
function truncateCoachLine(text, maxLen = 140) {
    const t = String(text || '').trim();
    if (t.length <= maxLen) {
        return t;
    }
    return `${t.slice(0, Math.max(0, maxLen - 1))}…`;
}

function ensureToolFieldSuggestionButton(wrap) {
    let button = wrap.querySelector('[data-field-suggestion-value]');

    if (button) {
        return button;
    }

    button = document.createElement('button');
    button.type = 'button';
    button.className = 'tool-field-suggestion';
    button.innerHTML = '<svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg><span></span>';
    const exampleButton = wrap.querySelector('[data-field-example]');
    const liveStatus = wrap.querySelector('[data-field-live-status]');

    if (exampleButton) {
        exampleButton.before(button);
    } else if (liveStatus) {
        liveStatus.after(button);
    } else {
        wrap.appendChild(button);
    }

    return button;
}

function renderToolUpstreamContext(items) {
    const root = document.querySelector('[data-upstream-context-root]');
    if (!root) {
        return;
    }

    const list = root.querySelector('[data-upstream-context-items]');
    const entries = Array.isArray(items)
        ? items.filter((item) => (item?.headline || item?.text))
        : [];

    root.hidden = entries.length === 0;

    if (!list) {
        return;
    }

    list.innerHTML = entries.map((item) => `
        <div class="tool-upstream-item">
            ${item.headline ? `<strong>${escapeHtml(item.headline)}</strong>` : ''}
            ${item.text ? `<p>${escapeHtml(item.text)}</p>` : ''}
        </div>
    `).join('');
}

function renderProjectBriefAssessment(assessment) {
    const root = document.querySelector('[data-project-brief-root]');
    if (!root) {
        return;
    }

    const scoreNode = root.querySelector('[data-project-brief-score]');
    const itemsNode = root.querySelector('[data-project-brief-items]');
    const executiveLines = Array.isArray(assessment?.reports?.executive_brief)
        ? assessment.reports.executive_brief.slice(0, 3)
        : [];
    const nextAction = Array.isArray(assessment?.next_actions)
        ? assessment.next_actions[0]
        : null;
    const hasContent = executiveLines.length > 0 || Boolean(nextAction);

    root.hidden = !hasContent;

    if (scoreNode) {
        scoreNode.textContent = `${assessment?.completeness_score || 0}% جاهزية`;
    }

    if (!itemsNode) {
        return;
    }

    const markup = [
        ...executiveLines.map((line) => `
            <div class="tool-upstream-item">
                <strong>${escapeHtml(line)}</strong>
            </div>
        `),
        nextAction ? `
            <div class="tool-upstream-item">
                <p>${escapeHtml(nextAction)}</p>
            </div>
        ` : '',
    ].filter(Boolean).join('');

    itemsNode.innerHTML = markup;
}

function renderToolBriefing(toolBriefing) {
    const root = document.querySelector('[data-tool-briefing-root]');
    if (!root) {
        return;
    }

    const hasBriefing = toolBriefing && typeof toolBriefing === 'object' && Object.keys(toolBriefing).length > 0;
    root.hidden = !hasBriefing;

    if (!hasBriefing) {
        return;
    }

    const scoreNode = root.querySelector('[data-tool-briefing-score]');
    const textNode = root.querySelector('[data-tool-briefing-text]');
    const signalsNode = root.querySelector('[data-tool-briefing-signals]');
    const missingNode = root.querySelector('[data-tool-briefing-missing]');
    const missingTextNode = root.querySelector('[data-tool-briefing-missing-text]');
    const nextActionNode = root.querySelector('[data-tool-briefing-next-action]');
    const headlineNode = root.querySelector('[data-tool-briefing-headline]');
    const reasonNode = root.querySelector('[data-tool-briefing-reason]');
    const actionsNode = root.querySelector('[data-tool-briefing-actions]');
    const actionLink = root.querySelector('[data-tool-briefing-action-link]');
    const signals = Array.isArray(toolBriefing.signals) ? toolBriefing.signals : [];
    const missingSignals = Array.isArray(toolBriefing.missing_signals) ? toolBriefing.missing_signals : [];
    const nextAction = toolBriefing.next_action || {};

    if (scoreNode) {
        scoreNode.textContent = `${toolBriefing.readiness_score || 0}%`;
    }

    if (textNode) {
        textNode.textContent = toolBriefing.summary?.text || '';
    }

    if (signalsNode) {
        signalsNode.hidden = signals.length === 0;
        signalsNode.innerHTML = signals.map((signal) => `
            <div class="app-list-item">
                <div>
                    <strong>${escapeHtml(signal.label || '')}</strong>
                    <small>${escapeHtml(signal.value || '')}</small>
                </div>
            </div>
        `).join('');
    }

    if (missingNode) {
        missingNode.hidden = missingSignals.length === 0;
    }

    if (missingTextNode) {
        missingTextNode.textContent = missingSignals.join('، ');
    }

    if (nextActionNode) {
        nextActionNode.hidden = !nextAction.reason;
    }

    if (headlineNode) {
        headlineNode.textContent = toolBriefing.summary?.headline || 'الخطوة التالية';
    }

    if (reasonNode) {
        reasonNode.textContent = nextAction.reason || '';
    }

    if (actionsNode) {
        actionsNode.hidden = !(nextAction.cta_url && nextAction.cta_label);
    }

    if (actionLink) {
        actionLink.href = nextAction.cta_url || '#';
        actionLink.textContent = nextAction.cta_label || '';
    }
}

function applyToolExperience(form, experience) {
    if (!experience || typeof experience !== 'object') {
        return;
    }

    const summary = experience.summary || {};
    const coach = document.querySelector('[data-tool-input-coach]');
    if (coach) {
        const title = coach.querySelector('[data-tool-coach-title]');
        const text = coach.querySelector('[data-tool-coach-text]');
        const nextLabel = coach.querySelector('[data-tool-coach-next-label-text]');
        const points = coach.querySelector('[data-tool-coach-points]');

        if (title && summary.title) {
            title.textContent = summary.title;
        }

        if (text && summary.intro) {
            text.textContent = summary.intro;
        }

        if (nextLabel && summary.focus_label) {
            nextLabel.textContent = summary.focus_label;
        }

        if (points && Array.isArray(summary.bullets)) {
            points.innerHTML = summary.bullets
                .filter((point) => point && point.trim() !== '')
                .map((point) => `<li>${escapeHtml(point)}</li>`)
                .join('');
        }
    }

    const modes = experience.modes || {};

    Object.values(modes).forEach((mode) => {
        Object.entries(mode.fields || {}).forEach(([key, meta]) => {
            const wrap = form.querySelector(`[data-field-wrap="${key}"]`);
            if (!wrap) {
                return;
            }

            wrap.dataset.fieldPriority = meta.priority || 'important';
            wrap.dataset.fieldContext = meta.context_hint || '';
            wrap.dataset.fieldEmptyPrompt = meta.empty_prompt || '';
            wrap.dataset.fieldWeakPrompt = meta.weak_prompt || '';
            wrap.dataset.fieldMinLength = String(meta.quality?.min_length || 0);
            wrap.dataset.fieldGenericTerms = JSON.stringify(meta.quality?.generic_terms || []);

            const priorityBadge = wrap.querySelector('.tool-field-priority-badge');
            if (priorityBadge) {
                priorityBadge.textContent = meta.priority_label || 'مهم جداً';
                priorityBadge.className = `tool-field-priority-badge is-${meta.priority || 'important'}`;
            }

            const input = wrap.querySelector('[data-field-key]');
            if (input && meta.smart_placeholder && input.tagName !== 'SELECT') {
                input.setAttribute('placeholder', meta.smart_placeholder);
            }

            const context = wrap.querySelector('.tool-field-context');
            if (context) {
                context.textContent = meta.context_hint || '';
                context.hidden = !meta.context_hint;
            }

            let suggestionButton = wrap.querySelector('[data-field-suggestion-value]');
            if (meta.suggested_value) {
                suggestionButton = suggestionButton || ensureToolFieldSuggestionButton(wrap);
                suggestionButton.dataset.fieldSuggestionValue = meta.suggested_value;
                suggestionButton.dataset.fieldSuggestionLabel = meta.suggestion_label || 'استخدم هذه الصياغة';
                suggestionButton.dataset.targetKey = key;
                const text = suggestionButton.querySelector('span');
                if (text) {
                    text.textContent = meta.suggestion_label || 'استخدم هذه الصياغة';
                } else {
                    suggestionButton.innerHTML = '<svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>'
                        + escapeHtml(meta.suggestion_label || 'استخدم هذه الصياغة');
                }
                suggestionButton.hidden = false;
            } else if (suggestionButton) {
                suggestionButton.hidden = true;
            }

            // تعبئة تلقائية: نملأ الحقول النصية الفارغة بالقيمة المقترحة كمسودّة قابلة للتعديل،
            // دون لمس ما كتبه المستخدم أو أعاد تحميله، ودون إطلاق أي حدث يستدعي الذكاء الاصطناعي.
            if (
                meta.suggested_value
                && input
                && input.tagName !== 'SELECT'
                && wrap.dataset.suggestedApplied !== '1'
                && getToolInputValue(input).trim() === ''
            ) {
                input.value = meta.suggested_value;
                wrap.dataset.suggestedApplied = '1';
                wrap.classList.add('is-suggested-draft');
                if (suggestionButton) {
                    suggestionButton.hidden = true;
                }

                const draftHint = document.createElement('span');
                draftHint.className = 'tool-field-draft-hint';
                draftHint.textContent = 'مسودة مقترحة من ملف مشروعك — عدّلها بحرية';
                input.insertAdjacentElement('afterend', draftHint);

                const clearDraftState = () => {
                    wrap.classList.remove('is-suggested-draft');
                    draftHint.remove();
                    input.removeEventListener('input', clearDraftState);
                };
                input.addEventListener('input', clearDraftState);
            }
        });
    });
}

function evaluateToolField(wrap) {
    const input = wrap.querySelector('[data-field-key]');
    if (!input || wrap.closest('[hidden]')) {
        return null;
    }

    const label = wrap.dataset.fieldLabel || input.dataset.fieldLabel || 'هذا الحقل';
    const priority = wrap.dataset.fieldPriority || 'important';
    const minLength = Number.parseInt(wrap.dataset.fieldMinLength || '0', 10) || 0;
    const genericTerms = parseJsonDataAttribute(wrap.dataset.fieldGenericTerms, []);
    const value = getToolInputValue(input);
    const normalized = value.trim().toLowerCase();

    let status = 'strong';
    let note = 'جيدة.';

    if (value.trim() === '') {
        status = 'empty';
        note = wrap.dataset.fieldEmptyPrompt || 'فاضي — عبّيه.';
    } else if (input.tagName !== 'SELECT') {
        const isGeneric = genericTerms.some((term) => {
            const normalizedTerm = String(term).trim().toLowerCase();
            return normalizedTerm !== '' && (normalized === normalizedTerm || normalized.includes(normalizedTerm));
        });

        if (normalized.length < minLength || isGeneric) {
            status = 'weak';
            note = wrap.dataset.fieldWeakPrompt || 'يحتاج تفصيلاً أوضح.';
        }
    }

    return {
        wrap,
        input,
        label,
        priority,
        status,
        note,
        context: wrap.dataset.fieldContext || '',
    };
}

function focusToolFieldRecommendation(recommendation) {
    if (!recommendation) {
        return;
    }

    const panel = recommendation.wrap.closest('[data-tool-step-panel]');
    const stepper = recommendation.wrap.closest('[data-tool-stepper]');

    if (panel && stepper) {
        const index = Number.parseInt(panel.dataset.toolStepPanel || '0', 10) || 0;
        setToolStepperIndex(stepper, index);
    }

    recommendation.input.focus();
    recommendation.input.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function updateToolInputCoach(form) {
    const coach = document.querySelector('[data-tool-input-coach]');
    if (!coach) {
        return;
    }

    const evaluations = Array.from(form.querySelectorAll('[data-field-wrap]'))
        .map((wrap) => evaluateToolField(wrap))
        .filter(Boolean);

    evaluations.forEach((evaluation) => {
        evaluation.wrap.classList.remove('is-empty', 'is-weak', 'is-strong');
        evaluation.wrap.classList.add(`is-${evaluation.status}`);

        const liveStatus = evaluation.wrap.querySelector(`[data-field-live-status="${evaluation.input.dataset.fieldKey}"]`);
        if (liveStatus) {
            liveStatus.textContent = evaluation.note;
            liveStatus.className = `tool-field-live-status is-${evaluation.status}`;
        }
    });

    const total = evaluations.length;
    const criticalEmpty = evaluations.filter((field) => field.priority === 'critical' && field.status === 'empty').length;
    const weakCount = evaluations.filter((field) => field.status === 'weak').length;
    const strengthScore = total
        ? Math.round((evaluations.reduce((sum, field) => sum + (field.status === 'strong' ? 1 : field.status === 'weak' ? 0.5 : 0), 0) / total) * 100)
        : 0;

    const recommendation = evaluations.find((field) => field.priority === 'critical' && field.status === 'empty')
        || evaluations.find((field) => field.priority === 'critical' && field.status === 'weak')
        || evaluations.find((field) => field.priority === 'important' && field.status === 'empty')
        || evaluations.find((field) => field.priority === 'important' && field.status === 'weak')
        || evaluations.find((field) => field.status === 'empty')
        || evaluations.find((field) => field.status === 'weak')
        || null;

    const criticalNode = coach.querySelector('[data-tool-coach-critical-count]');
    const weakNode = coach.querySelector('[data-tool-coach-weak-count]');
    const readyNode = coach.querySelector('[data-tool-coach-ready-score]');
    const nextLabel = coach.querySelector('[data-tool-coach-next-label-text]');
    const nextText = coach.querySelector('[data-tool-coach-next-text]');
    const focusButton = coach.querySelector('[data-tool-focus-next]');

    if (criticalNode) {
        criticalNode.textContent = String(criticalEmpty);
    }

    if (weakNode) {
        weakNode.textContent = String(weakCount);
    }

    if (readyNode) {
        readyNode.textContent = `${strengthScore}%`;
    }

    if (recommendation) {
        form.dataset.toolCoachFocusKey = recommendation.input.dataset.fieldKey || '';
        if (nextLabel) {
            nextLabel.textContent = recommendation.label;
        }
        if (nextText) {
            nextText.textContent = truncateCoachLine(recommendation.note, 140);
        }
        if (focusButton) {
            focusButton.disabled = false;
        }
    } else {
        form.dataset.toolCoachFocusKey = '';
        if (nextLabel) {
            nextLabel.textContent = 'جاهز للحفظ';
        }
        if (nextText) {
            nextText.textContent = 'يمكنك حفظ النتيجة أو طلب التحليل.';
        }
        if (focusButton) {
            focusButton.disabled = true;
        }
    }

    return recommendation;
}

function renderAgencyVerdictCard(resultBody, verdict, textNode) {
    if (!resultBody) return;

    const existing = resultBody.querySelector('.agency-verdict-card');
    if (!verdict || typeof verdict !== 'object') {
        if (existing) existing.remove();
        return;
    }

    const demands = Array.isArray(verdict.demands) ? verdict.demands.filter(Boolean).slice(0, 3) : [];
    const questions = Array.isArray(verdict.questions) ? verdict.questions.filter(Boolean).slice(0, 3) : [];
    const score = Number.parseInt(verdict.score, 10) || 0;
    const riskLevel = verdict.risk_level || 'غير محدد';
    const decision = verdict.decision || 'راجع القياس قبل القرار.';
    const firstDemand = demands[0] || 'اطلب أرقاماً قابلة للقياس.';
    const meetingBrief = typeof verdict.meeting_brief === 'string' ? verdict.meeting_brief.trim() : '';

    const cardHtml = `
        <section class="agency-verdict-card" aria-label="حكم تشغيل الوكالة">
            <div class="agency-verdict-head">
                <span>حكم تشغيل الوكالة</span>
                <strong>${score}/100</strong>
            </div>
            <p class="agency-verdict-decision">${escapeHtml(decision)}</p>
            <div class="agency-verdict-meta">
                <div>
                    <span>مستوى المخاطرة</span>
                    <strong>${escapeHtml(riskLevel)}</strong>
                </div>
                <div>
                    <span>أول طلب</span>
                    <strong>${escapeHtml(firstDemand)}</strong>
                </div>
            </div>
            ${demands.length > 0 ? `
                <div class="agency-verdict-list">
                    <strong>مطالب من الوكالة</strong>
                    <ul>${demands.map(demand => `<li>${escapeHtml(demand)}</li>`).join('')}</ul>
                </div>
            ` : ''}
            ${questions.length > 0 ? `
                <div class="agency-verdict-list">
                    <strong>أسئلة الاجتماع القادم</strong>
                    <ul>${questions.map(question => `<li>${escapeHtml(question)}</li>`).join('')}</ul>
                </div>
            ` : ''}
            ${meetingBrief ? `
                <div class="agency-verdict-list">
                    <strong>رسالة الاجتماع مع الوكالة</strong>
                    <p>${escapeHtml(meetingBrief).replace(/\n/g, '<br>')}</p>
                </div>
            ` : ''}
        </section>
    `;

    if (existing) {
        existing.outerHTML = cardHtml;
    } else if (textNode) {
        textNode.insertAdjacentHTML('afterend', cardHtml);
    } else {
        resultBody.insertAdjacentHTML('afterbegin', cardHtml);
    }
}

function renderToolNextActions(container, actions) {
    const card = container.querySelector('[data-tool-next-actions-card]');
    if (!card) return;

    const list = card.querySelector('[data-tool-next-actions-list]');
    const nextActions = Array.isArray(actions) ? actions.filter(Boolean).slice(0, 4) : [];

    card.hidden = nextActions.length === 0;
    if (list) {
        list.innerHTML = nextActions.map(action => `<li>${escapeHtml(action)}</li>`).join('');
    }
}

function renderToolResult(container, data) {
    const summary = data.summary || {};
    const score = data.completeness_score || 0;
    const headline = summary.headline || '';
    const text = summary.text || '';
    const bullets = summary.bullets || [];
    const isAiGenerated = data.ai_generated || false;
    const agencyVerdict = summary.agency_verdict || data.output?.agency_verdict || null;

    const scoreNode = container.querySelector('[data-tool-preview-score], [data-diagnosis-score]');
    const headlineNode = container.querySelector('[data-tool-preview-headline], [data-diagnosis-headline]');
    const textNode = container.querySelector('[data-tool-preview-text], [data-diagnosis-text]');
    const bulletsNode = container.querySelector('[data-tool-preview-bullets], [data-diagnosis-bullets]');
    const resultBody = container.querySelector('[data-tool-result-body]');

    if (scoreNode) scoreNode.textContent = `${score}%`;
    if (headlineNode) headlineNode.textContent = headline;
    if (textNode) textNode.textContent = text;
    renderAgencyVerdictCard(resultBody, agencyVerdict, textNode);
    renderToolNextActions(container, data.next_actions);
    if (bulletsNode) {
        bulletsNode.innerHTML = bullets
            .filter(b => b && b.trim() !== '')
            .map(b => `<li>${escapeHtml(b)}</li>`)
            .join('');
    }

    if (resultBody) {
        let badge = resultBody.querySelector('.tool-ai-badge');
        if (isAiGenerated && headline) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'tool-ai-badge tool-ai-badge-sm';
                badge.innerHTML = '<svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg> تحليل ذكي';
                resultBody.prepend(badge);
            }
        } else if (badge) {
            badge.remove();
        }

        let recsLabel = resultBody.querySelector('.tool-result-recs-label');
        if (isAiGenerated && bullets.length > 0) {
            if (!recsLabel) {
                recsLabel = document.createElement('div');
                recsLabel.className = 'tool-result-recs-label';
                recsLabel.innerHTML = '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg> توصيات عملية';
                if (bulletsNode) bulletsNode.before(recsLabel);
            }
            if (bulletsNode) bulletsNode.classList.add('tool-bullet-recs');
        } else {
            if (recsLabel) recsLabel.remove();
            if (bulletsNode) bulletsNode.classList.remove('tool-bullet-recs');
        }
    }

    const noteContainer = container.querySelector('.tool-preview-note, .diagnosis-saved-note');
    if (noteContainer) {
        noteContainer.innerHTML = `<small>آخر حفظ: ${data.created_at || 'الآن'} · ${score}%</small>`;
    } else {
        const previewPanel = container.querySelector('.tool-preview-panel, .tool-latest-result')?.closest('.card');
        if (previewPanel) {
            const noteDiv = document.createElement('div');
            noteDiv.className = 'tool-preview-note';
            noteDiv.innerHTML = `<small>آخر حفظ: ${data.created_at || 'الآن'} · ${score}%</small>`;
            previewPanel.appendChild(noteDiv);
        }
    }

    updateProgressRing(score);
}

function populateFormFields(form, inputs) {
    if (!inputs || typeof inputs !== 'object') return;

    Object.entries(inputs).forEach(([key, value]) => {
        if (key === 'brief') {
            const briefField = form.querySelector('[name="brief"]');
            if (briefField) {
                setToolFieldValue(briefField, value || '');
            }
            return;
        }

        const field = form.querySelector(`[name="inputs[${key}]"]`);
        if (field) {
            setToolFieldValue(field, value || '');
        }
    });
}

function wireToolAjaxSubmission() {
    document.querySelectorAll('[data-tool-ajax-form]').forEach((form) => {
        const submitBtn = form.querySelector('[data-tool-submit]');

        if (!submitBtn) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            await submitToolFormAjax(form, {
                triggerButton: submitBtn,
                pendingText: 'جارٍ الحفظ...',
            });
        });
    });
}

function wireToolProjectSwitch() {
    document.querySelectorAll('[data-tool-ajax-form]').forEach((form) => {
        const loadUrl = form.dataset.toolLoadUrl;
        const projectSelect = form.querySelector('[name="project_id"]');
        if (!loadUrl || !projectSelect) return;

        projectSelect.addEventListener('change', async () => {
            const projectId = projectSelect.value;
            if (!projectId) return;

            try {
                const response = await fetch(`${loadUrl}?project_id=${projectId}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                const result = await response.json();

                if (result.experience) {
                    applyToolExperience(form, result.experience);
                }

                renderToolUpstreamContext(result.upstream_context);
                renderProjectBriefAssessment(result.project_brief_assessment);
                renderToolBriefing(result.tool_briefing);

                if (result.success && result.data) {
                    populateFormFields(form, result.data.inputs);
                    const workbench = form.closest('.tool-workbench');
                    if (workbench) renderToolResult(workbench, result.data);
                    showToast('تم تحميل البيانات المحفوظة لهذا المشروع.');
                } else if (result.success && !result.data) {
                    clearToolFormFields(form);

                    const workbench = form.closest('.tool-workbench');
                    if (workbench) {
                        renderToolResult(workbench, {
                            completeness_score: 0,
                            summary: { headline: 'لا توجد بيانات محفوظة', text: 'ابدأ بملء الحقول لهذا المشروع.', bullets: [] },
                        });
                    }
                }
            } catch (err) {
                // Silent fail on load
            }
        });
    });
}

function renderStructuredAnalysis(data) {
    const panel = document.querySelector('[data-tool-analysis-panel]');
    if (!panel) return;

    panel.hidden = false;

    const score = data.score || 0;
    const ring = panel.querySelector('[data-analysis-ring]');
    if (ring) {
        const circ = 2 * Math.PI * 20;
        const offset = circ - (circ * score / 100);
        ring.style.strokeDashoffset = offset;
        ring.style.stroke = score >= 70 ? 'var(--success, #10b981)' : score >= 40 ? 'var(--warning, #f59e0b)' : 'var(--danger, #ef4444)';
    }
    const scoreNum = panel.querySelector('[data-analysis-score]');
    if (scoreNum) scoreNum.textContent = score;

    const verdict = panel.querySelector('[data-analysis-verdict]');
    if (verdict) {
        const p = verdict.querySelector('p');
        if (p) p.textContent = data.verdict || '';
    }

    const dimensionsEl = panel.querySelector('[data-analysis-dimensions]');
    if (dimensionsEl) {
        const dimensions = Array.isArray(data.dimensions) ? data.dimensions : [];
        dimensionsEl.hidden = dimensions.length === 0;
        dimensionsEl.innerHTML = dimensions.map((dimension) => {
            const dimScore = Number.parseInt(dimension.score, 10) || 0;
            const dimClass = dimScore >= 75 ? 'is-strong' : dimScore >= 45 ? 'is-mid' : 'is-weak';

            return `
                <div class="tool-analysis-dimension ${dimClass}">
                    <strong>${escapeHtml(dimension.label || '')}</strong>
                    <span>${dimScore}%</span>
                    <p>${escapeHtml(dimension.note || '')}</p>
                </div>
            `;
        }).join('');
    }

    const strengthsEl = panel.querySelector('[data-analysis-strengths]');
    if (strengthsEl) {
        const strengths = Array.isArray(data.strengths) ? data.strengths : [];
        strengthsEl.hidden = strengths.length === 0;
        strengthsEl.querySelector('ul').innerHTML = strengths.map(s => `<li>${escapeHtml(s)}</li>`).join('');
    }

    const gapsEl = panel.querySelector('[data-analysis-gaps]');
    if (gapsEl) {
        const gaps = Array.isArray(data.gaps) ? data.gaps : [];
        gapsEl.hidden = gaps.length === 0;
        gapsEl.querySelector('ul').innerHTML = gaps.map(g => `<li>${escapeHtml(g)}</li>`).join('');
    }

    const recsEl = panel.querySelector('[data-analysis-recs]');
    if (recsEl) {
        const recommendations = Array.isArray(data.recommendations) ? data.recommendations : [];
        recsEl.hidden = recommendations.length === 0;
        recsEl.querySelector('ol').innerHTML = recommendations.map(r => `<li>${escapeHtml(r)}</li>`).join('');
    }

    const strategicEl = panel.querySelector('[data-analysis-strategic]');
    if (strategicEl) {
        strategicEl.hidden = !data.strategic_note;
        if (data.strategic_note) {
            strategicEl.querySelector('p').textContent = data.strategic_note;
        }
    }

    document.querySelectorAll('[data-field-quality-badge]').forEach((badge) => {
        badge.hidden = true;
        badge.textContent = '';
        badge.classList.remove('is-strong', 'is-mid', 'is-weak', 'is-empty');
    });

    if (data.field_notes && typeof data.field_notes === 'object') {
        document.querySelectorAll('.tool-field-note').forEach(n => n.remove());

        Object.entries(data.field_notes).forEach(([key, note]) => {
            if (!note) return;
            const wrap = document.querySelector(`[data-field-wrap="${key}"]`);
            if (!wrap) return;

            const noteEl = document.createElement('div');
            noteEl.className = 'tool-field-note';
            noteEl.innerHTML = `<svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01"/></svg> ${escapeHtml(note)}`;
            wrap.appendChild(noteEl);
        });
    }

    if (data.field_scores && typeof data.field_scores === 'object') {
        Object.entries(data.field_scores).forEach(([key, meta]) => {
            const badge = document.querySelector(`[data-field-quality-badge="${key}"]`);
            if (!badge || !meta) return;

            const scoreValue = Number.parseInt(meta.score, 10) || 0;
            const status = meta.status || (scoreValue >= 75 ? 'strong' : scoreValue >= 45 ? 'mid' : 'weak');
            badge.hidden = false;
            badge.textContent = `${scoreValue}%`;
            badge.classList.add(`is-${status}`);
        });
    }
}

function runToolAnalysis(button, options = {}) {
    const form = document.querySelector('[data-tool-ajax-form]');
    if (!form) return;

    const analyzeUrl = form.dataset.analyzeUrl;
    const toolCode = form.dataset.toolCode;
    const toolName = form.dataset.toolName;
    if (!analyzeUrl) return;

    const filledInputs = Array.from(form.querySelectorAll('input[name^="inputs["], textarea[name^="inputs["], select[name^="inputs["]'))
        .filter(f => f.value.trim() !== '' && !f.closest('[hidden]'));

    if (filledInputs.length < 1) {
        if (button) showToast('ابدأ بكتابة إجابة واحدة على الأقل حتى يبدأ تقييم الجودة.', 'error');
        return;
    }

    const panel = document.querySelector('[data-tool-analysis-panel]');
    if (!panel) return;

    const originalHtml = button ? button.innerHTML : null;
    if (button) {
        button.disabled = true;
        button.innerHTML = '<span class="btn-spinner"></span> يقيّم الجودة...';
    }

    panel.hidden = false;
    const header = panel.querySelector('[data-tool-analysis-header]');
    if (header) {
        const verdict = header.querySelector('[data-analysis-verdict] p');
        if (verdict) verdict.textContent = 'جارٍ التحليل...';
    }

    const formData = collectFormInputs(form);
    fetchPost(analyzeUrl, {
        tool_code: toolCode,
        tool_name: toolName,
        inputs: { ...formData.inputs, brief: formData.brief },
        mode: formData.mode,
        project_id: formData.project_id,
        enrich: options.silent ? false : true,
    })
    .then(r => r.json())
    .then(result => {
        if (result.analysis && typeof result.analysis === 'object') {
            renderStructuredAnalysis(result.analysis);
            if (!options.silent) {
                showToast(`تقييم الجودة جاهز: ${result.analysis.score || 0}/100`);
            }
        } else if (result.analysis && typeof result.analysis === 'string') {
            renderStructuredAnalysis({ score: 50, verdict: result.analysis, strengths: [], gaps: [], recommendations: [], strategic_note: '', field_notes: {} });
        } else {
            const verdict = panel.querySelector('[data-analysis-verdict] p');
            if (verdict) verdict.textContent = result.error || 'تعذر التحليل حالياً.';
        }
    })
    .catch(() => {
        const verdict = panel.querySelector('[data-analysis-verdict] p');
        if (verdict) verdict.textContent = 'حدث خطأ في الاتصال.';
    })
    .finally(() => {
        if (button) {
            button.disabled = false;
            button.innerHTML = originalHtml;
        }
    });
}

function wireToolAiAnalysis() {
    document.querySelectorAll('[data-tool-analyze]').forEach((button) => {
        button.addEventListener('click', () => runToolAnalysis(button));
    });

    const form = document.querySelector('[data-tool-ajax-form]');
    if (!form) return;

    let autoTimer = null;
    let lastAnalyzedHash = '';

    const getInputsHash = () => {
        const vals = Array.from(form.querySelectorAll('[data-field-key]'))
            .filter(el => !el.closest('[hidden]'))
            .map(el => el.value.trim())
            .filter(v => v !== '');
        return vals.join('|');
    };

    const maybeAutoAnalyze = () => {
        const filled = Array.from(form.querySelectorAll('[data-field-key]'))
            .filter(el => !el.closest('[hidden]') && el.value.trim() !== '');

        if (filled.length < 1) return;

        const hash = getInputsHash();
        if (hash === lastAnalyzedHash) return;

        if (autoTimer) clearTimeout(autoTimer);
        autoTimer = setTimeout(() => {
            lastAnalyzedHash = hash;
            runToolAnalysis(null, { silent: true });
        }, 900);
    };

    form.querySelectorAll('[data-field-key]').forEach((input) => {
        input.addEventListener('input', maybeAutoAnalyze);
        input.addEventListener('change', maybeAutoAnalyze);
    });

    form.querySelectorAll('[data-tool-mode-button], [name="project_id"]').forEach((control) => {
        control.addEventListener('change', maybeAutoAnalyze);
        control.addEventListener('click', maybeAutoAnalyze);
    });

    const hint = document.querySelector('[data-tool-auto-hint]');
    if (hint) hint.hidden = false;
}

function wireToolAiSuggestions() {
    document.querySelectorAll('[data-tool-suggest]').forEach((button) => {
        const originalHtml = button.innerHTML;

        button.addEventListener('click', async () => {
            const form = document.querySelector('[data-tool-ajax-form]');
            if (!form) return;

            const suggestUrl = form.dataset.suggestUrl;
            const toolCode = form.dataset.toolCode;
            const toolName = form.dataset.toolName;
            if (!suggestUrl) return;

            button.disabled = true;
            button.innerHTML = '<span class="btn-spinner"></span> يحلل السياق...';

            try {
                const formData = collectFormInputs(form);
                const response = await fetchPost(suggestUrl, {
                    tool_code: toolCode,
                    tool_name: toolName,
                    inputs: formData.inputs || {},
                    project_id: formData.project_id,
                    mode: formData.mode || '',
                });

                const result = await response.json();
                if (!response.ok) {
                    showToast(result.error || 'تعذر توليد الاقتراحات.', 'error');
                    return;
                }

                if (result.suggestions && typeof result.suggestions === 'object') {
                    let applied = 0;
                    Object.entries(result.suggestions).forEach(([key, value]) => {
                        const field = form.querySelector(`[name="inputs[${key}]"]`);
                        if (!field || !value) return;

                        const newVal = String(value).trim();
                        const currentValue = (field.tagName === 'SELECT' ? field.value : field.value).trim();

                        // يطبّق على الفارغ (تعبئة) والممتلئ (استبدال بصياغة أقوى)، ويتجاهل المطابق.
                        if (newVal === '' || newVal === currentValue) return;

                        if (setToolFieldValue(field, newVal)) {
                            field.classList.add('ai-suggested');
                            applied++;
                            setTimeout(() => field.classList.remove('ai-suggested'), 3000);
                        }
                    });

                    if (applied > 0) {
                        const saveResult = await submitToolFormAjax(form, {
                            triggerButton: button,
                            pendingText: '<span class="btn-spinner"></span> يحفظ الاقتراحات...',
                            fallbackToSubmit: false,
                            successMessage: `تم تطبيق ${applied} اقتراحات وحفظها مباشرة في قاعدة البيانات.`,
                        });

                        if (!saveResult?.success) {
                            showToast('تم تطبيق الاقتراحات في الحقول لكن تعذر حفظها تلقائياً.', 'error');
                        }
                    } else {
                        const keys = result.suggestions ? Object.keys(result.suggestions) : [];
                        if (keys.length > 0) {
                            showToast('تعذر مطابقة الاقتراحات مع الحقول. جرّب الكتابة قليلاً في حقل مرتبط ثم أعد المحاولة.', 'info');
                        } else {
                            showToast('لا توجد حقول فارغة لاقتراح قيم لها، أو لم يُرجع النظام اقتراحات صالحة.', 'info');
                        }
                    }
                }

                if (result.insight) {
                    const insightEl = document.querySelector('[data-tool-ai-insight]');
                    const insightText = document.querySelector('[data-tool-ai-insight-text]');
                    if (insightEl && insightText) {
                        insightText.textContent = result.insight;
                        insightEl.hidden = false;
                    }
                }
            } catch (err) {
                showToast('تعذر الاتصال بالمستشار الذكي.', 'error');
            } finally {
                button.disabled = false;
                button.innerHTML = originalHtml;
            }
        });
    });
}

function wireToolAfterSaveHighlight() {
    const nextSteps = document.querySelector('[data-tool-next-steps]');
    if (!nextSteps) return;

    const observer = new MutationObserver(() => {
        const scoreNode = document.querySelector('[data-tool-preview-score]');
        if (!scoreNode) return;

        const score = parseInt(scoreNode.textContent, 10);
        if (score >= 40) {
            nextSteps.classList.add('tool-next-steps-visible');
        }
    });

    const scoreNode = document.querySelector('[data-tool-preview-score]');
    if (scoreNode) {
        observer.observe(scoreNode, { childList: true, characterData: true, subtree: true });

        const initialScore = parseInt(scoreNode.textContent, 10);
        if (initialScore >= 40) {
            nextSteps.classList.add('tool-next-steps-visible');
        }
    }
}

function updateProgressRing(score) {
    const circle = document.querySelector('[data-tool-ring-circle]');
    if (!circle) return;

    const circumference = parseFloat(circle.dataset.circumference);
    const offset = circumference - (circumference * score / 100);
    circle.style.strokeDashoffset = offset;

    if (score >= 80) {
        circle.setAttribute('data-score-high', '');
    } else {
        circle.removeAttribute('data-score-high');
    }

    const label = document.querySelector('.tool-progress-ring-label strong');
    if (label) {
        label.classList.remove('score-low', 'score-mid', 'score-high');
        if (score >= 80) label.classList.add('score-high');
        else if (score >= 40) label.classList.add('score-mid');
        else label.classList.add('score-low');
    }
}

function wireFieldCompletionTracking() {
    const form = document.querySelector('[data-tool-ajax-form]');
    if (!form) return;

    let previousScore = -1;

    const syncFieldState = () => {
        form.querySelectorAll('[data-field-wrap]').forEach((wrap) => {
            const key = wrap.dataset.fieldWrap;
            const input = wrap.querySelector('[data-field-key]');
            if (!input) return;

            const val = input.tagName === 'SELECT'
                ? (input.value !== '' ? input.options[input.selectedIndex]?.text.trim() : '')
                : input.value.trim();
            const filled = val !== '';

            wrap.classList.toggle('is-filled', filled);

            const dot = document.querySelector(`[data-field-dot="${key}"]`);
            if (dot) dot.classList.toggle('is-filled', filled);
        });

        const visibleInputs = Array.from(form.querySelectorAll('[data-field-key]'))
            .filter(el => !el.closest('[hidden]'));
        const filledCount = visibleInputs.filter(el => {
            return el.tagName === 'SELECT' ? el.value !== '' : el.value.trim() !== '';
        }).length;
        const score = visibleInputs.length ? Math.round((filledCount / visibleInputs.length) * 100) : 0;

        const scoreNode = document.querySelector('[data-tool-preview-score], [data-diagnosis-score]');
        if (scoreNode) scoreNode.textContent = `${score}%`;

        updateProgressRing(score);

        if (score === 100 && previousScore < 100 && previousScore >= 0) {
            triggerCompletionCelebration();
        }
        previousScore = score;
    };

    form.querySelectorAll('[data-field-key]').forEach((input) => {
        input.addEventListener('input', syncFieldState);
        input.addEventListener('change', syncFieldState);
    });

    form.querySelectorAll('[data-tool-mode-button]').forEach((btn) => {
        btn.addEventListener('click', () => {
            previousScore = -1;
            setTimeout(syncFieldState, 50);
        });
    });

    syncFieldState();
}

function wireFieldFocusDots() {
    const form = document.querySelector('[data-tool-ajax-form]');
    if (!form) return;

    form.querySelectorAll('[data-field-key]').forEach((input) => {
        const key = input.dataset.fieldKey;
        input.addEventListener('focus', () => {
            document.querySelectorAll('.tool-field-dot.is-active').forEach(d => d.classList.remove('is-active'));
            const dot = document.querySelector(`[data-field-dot="${key}"]`);
            if (dot) dot.classList.add('is-active');
        });
        input.addEventListener('blur', () => {
            const dot = document.querySelector(`[data-field-dot="${key}"]`);
            if (dot) dot.classList.remove('is-active');
        });
    });
}

function wireToolFieldSuggestions() {
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-field-suggestion-value]');
        if (!button) {
            return;
        }

        const targetKey = button.dataset.targetKey;
        const suggestionValue = button.dataset.fieldSuggestionValue;
        if (!targetKey || !suggestionValue) {
            return;
        }

        const input = document.querySelector(`[data-field-key="${targetKey}"]`);
        if (!input) {
            return;
        }

        const applied = setToolFieldValue(input, suggestionValue);
        if (!applied) {
            showToast('تعذر تطبيق الاقتراح على هذا الحقل.', 'error');
            return;
        }

        input.focus();
        input.classList.add('ai-suggested');
        setTimeout(() => input.classList.remove('ai-suggested'), 2000);
    });
}

function wireExampleChips() {
    document.querySelectorAll('[data-field-example]').forEach((chip) => {
        chip.addEventListener('click', (e) => {
            e.preventDefault();
            const example = chip.dataset.fieldExample;
            const targetKey = chip.dataset.targetKey;
            const input = document.querySelector(`[data-field-key="${targetKey}"]`);
            if (!input || !example) return;

            if (input.value.trim() !== '') return;

            setToolFieldValue(input, example);
            input.focus();

            input.classList.add('ai-suggested');
            setTimeout(() => input.classList.remove('ai-suggested'), 2000);
        });
    });
}

function wireToolInputCoach() {
    const form = document.querySelector('[data-tool-ajax-form]');
    if (!form) {
        return;
    }

    let lastRecommendation = null;
    const syncCoach = () => {
        lastRecommendation = updateToolInputCoach(form) || null;
    };

    form.querySelectorAll('[data-field-key], [name="brief"]').forEach((input) => {
        input.addEventListener('input', syncCoach);
        input.addEventListener('change', syncCoach);
    });

    form.querySelectorAll('[data-tool-mode-button], [data-tool-mode-switcher], [name="project_id"]').forEach((control) => {
        control.addEventListener('click', () => setTimeout(syncCoach, 20));
        control.addEventListener('change', () => setTimeout(syncCoach, 20));
    });

    const focusButton = document.querySelector('[data-tool-focus-next]');
    if (focusButton) {
        focusButton.addEventListener('click', () => {
            const recommendation = lastRecommendation || updateToolInputCoach(form);
            focusToolFieldRecommendation(recommendation);
        });
    }

    syncCoach();
}

function triggerCompletionCelebration() {
    const panel = document.querySelector('.tool-preview-panel');
    if (!panel) return;

    panel.classList.add('is-complete');
    setTimeout(() => panel.classList.remove('is-complete'), 1200);

    const ring = document.querySelector('.tool-progress-ring-wrap');
    if (!ring) return;

    const container = document.createElement('div');
    container.className = 'tool-confetti-container';
    ring.appendChild(container);

    const colors = ['#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
    for (let i = 0; i < 20; i++) {
        const particle = document.createElement('div');
        particle.className = 'tool-confetti-particle';
        particle.style.left = `${10 + Math.random() * 80}%`;
        particle.style.background = colors[Math.floor(Math.random() * colors.length)];
        particle.style.animationDelay = `${Math.random() * 0.4}s`;
        particle.style.animationDuration = `${0.8 + Math.random() * 0.6}s`;
        container.appendChild(particle);
    }

    setTimeout(() => container.remove(), 2000);

    showToast('أحسنت! جميع الحقول مكتملة. يمكنك الحفظ الآن.');
}

function wireAutoSaveDraft() {
    const form = document.querySelector('[data-tool-ajax-form]');
    if (!form) return;

    const toolCode = form.dataset.toolCode || 'unknown';
    const projectSelect = form.querySelector('[name="project_id"]');
    const draftIndicator = document.querySelector('[data-tool-draft-indicator]');
    let saveTimer = null;

    const getDraftKey = () => {
        const projectId = projectSelect ? projectSelect.value : 'default';
        return `tool-draft-${toolCode}-${projectId}`;
    };

    const saveDraft = () => {
        try {
            const inputs = {};
            form.querySelectorAll('[data-field-key]').forEach((el) => {
                inputs[el.dataset.fieldKey] = el.value;
            });
            const brief = form.querySelector('[name="brief"]');
            if (brief) inputs.__brief = brief.value;

            const mode = form.querySelector('[data-tool-mode-switcher]');
            if (mode) inputs.__mode = mode.value;

            localStorage.setItem(getDraftKey(), JSON.stringify(inputs));

            if (draftIndicator) {
                draftIndicator.hidden = false;
                draftIndicator.classList.add('is-visible');
                setTimeout(() => {
                    draftIndicator.classList.remove('is-visible');
                    setTimeout(() => { draftIndicator.hidden = true; }, 500);
                }, 2000);
            }
        } catch (_) { /* storage full or disabled */ }
    };

    const loadDraft = () => {
        try {
            const raw = localStorage.getItem(getDraftKey());
            if (!raw) return;

            const inputs = JSON.parse(raw);
            let anyLoaded = false;

            form.querySelectorAll('[data-field-key]').forEach((el) => {
                const saved = inputs[el.dataset.fieldKey];
                if (saved && el.value.trim() === '') {
                    setToolFieldValue(el, saved);
                    anyLoaded = true;
                }
            });

            const brief = form.querySelector('[name="brief"]');
            if (brief && inputs.__brief && brief.value.trim() === '') {
                setToolFieldValue(brief, inputs.__brief);
            }

            if (anyLoaded) {
                showToast('تم استعادة المسودة المحفوظة تلقائياً.');
            }
        } catch (_) { /* corrupt data */ }
    };

    const clearDraft = () => {
        try { localStorage.removeItem(getDraftKey()); } catch (_) { /* ignore */ }
    };

    form.querySelectorAll('[data-field-key], [name="brief"]').forEach((el) => {
        el.addEventListener('input', () => {
            if (saveTimer) clearTimeout(saveTimer);
            saveTimer = setTimeout(saveDraft, 1500);
        });
    });

    form.addEventListener('submit', () => {
        clearDraft();
    });

    if (projectSelect) {
        projectSelect.addEventListener('change', () => {
            setTimeout(loadDraft, 200);
        });
    }

    const hasServerData = form.querySelectorAll('[data-field-key]');
    const serverFilled = Array.from(hasServerData).some(el => el.value.trim() !== '');
    if (!serverFilled) {
        loadDraft();
    }
}

function wireLivePreviewFlash() {
    const form = document.querySelector('[data-tool-ajax-form]');
    if (!form) return;

    const headlineNode = document.querySelector('[data-tool-preview-headline], [data-diagnosis-headline]');
    if (!headlineNode) return;

    const observer = new MutationObserver(() => {
        headlineNode.classList.remove('tool-preview-flash');
        void headlineNode.offsetWidth;
        headlineNode.classList.add('tool-preview-flash');
    });

    observer.observe(headlineNode, { childList: true, characterData: true, subtree: true });
}

function wireAiChatWidget() {
    const toggle = document.getElementById('ai-chat-toggle');
    const panel = document.getElementById('ai-chat-panel');
    const closeBtn = document.getElementById('ai-chat-close');
    const newBtn = document.getElementById('ai-chat-new');
    const historyBtn = document.getElementById('ai-chat-history-toggle');
    const historyPanel = document.getElementById('ai-chat-history');
    const historyList = document.getElementById('ai-chat-history-list');
    const title = document.getElementById('ai-chat-title');
    const input = document.getElementById('ai-chat-input');
    const sendBtn = document.getElementById('ai-chat-send');
    const messagesContainer = document.getElementById('ai-chat-messages');
    const loadOlderBtn = document.getElementById('ai-chat-load-older');
    const suggestionsContainer = document.getElementById('ai-chat-suggestions');
    const footer = panel?.querySelector('.ai-chat-footer');

    if (!toggle || !panel || !input || !sendBtn || !messagesContainer || !loadOlderBtn) return;

    let chatOpen = false;
    let initialized = false;
    let currentConversation = null;
    let messagePage = 1;
    let hasOlderMessages = false;
    const activePolls = new Set();
    const conversationsUrl = toggle.dataset.conversationsUrl || '/api/ai/conversations';
    const chatStreamUrl = toggle.dataset.chatStreamUrl || '/api/ai/chat/stream';

    const requestJson = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                ...(options.headers || {}),
            },
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(data.error || data.message || 'تعذر إكمال الطلب.');
        }

        return data;
    };

    const conversationUrl = (conversationId) => `${conversationsUrl}/${encodeURIComponent(conversationId)}`;

    const showConversationView = () => {
        if (historyPanel) historyPanel.hidden = true;
        messagesContainer.hidden = false;
        if (footer) footer.hidden = false;
    };

    const showHistoryView = async () => {
        if (!historyPanel || !historyList) return;
        historyPanel.hidden = false;
        messagesContainer.hidden = true;
        if (footer) footer.hidden = true;
        historyList.innerHTML = '<p class="ai-chat-history-empty">جارٍ تحميل المحادثات...</p>';

        try {
            const result = await requestJson(conversationsUrl);
            const conversations = Array.isArray(result.data) ? result.data : [];
            historyList.innerHTML = '';
            if (!conversations.length) {
                historyList.innerHTML = '<p class="ai-chat-history-empty">لا توجد محادثات سابقة.</p>';
                return;
            }
            conversations.forEach((conversation) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'ai-chat-history-item';
                button.innerHTML = `<strong>${escapeHtml(conversation.title || 'محادثة')}</strong><small>${escapeHtml(conversation.project?.name || 'محادثة عامة')}</small>`;
                button.addEventListener('click', () => loadConversation(conversation.public_id));
                historyList.appendChild(button);
            });
        } catch (error) {
            historyList.innerHTML = `<p class="ai-chat-history-empty">${escapeHtml(error.message)}</p>`;
        }
    };

    const toggleChat = async () => {
        chatOpen = !chatOpen;
        panel.hidden = !chatOpen;
        toggle.setAttribute('aria-expanded', String(chatOpen));
        if (chatOpen) {
            if (!initialized) {
                initialized = true;
                await initializeConversation();
            }
            input.focus();
        }
    };

    toggle.addEventListener('click', toggleChat);
    if (closeBtn) closeBtn.addEventListener('click', toggleChat);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && chatOpen) toggleChat();
    });

    const hideSuggestions = () => {
        if (suggestionsContainer) suggestionsContainer.hidden = true;
    };

    const appendMessage = (role, text, publicId = null, prepend = false) => {
        const div = document.createElement('div');
        div.className = `ai-chat-msg ai-chat-msg-${role}`;
        div.innerHTML = text.split('\n').filter(l => l.trim()).map(l => `<p>${escapeHtml(l)}</p>`).join('');
        if (publicId) div.dataset.messageId = publicId;
        if (prepend) {
            loadOlderBtn.insertAdjacentElement('afterend', div);
        } else {
            messagesContainer.appendChild(div);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        return div;
    };

    const renderStoredMessage = (message, prepend = false) => {
        const existing = messagesContainer.querySelector(`[data-message-id="${CSS.escape(message.public_id)}"]`);
        if (existing) existing.remove();
        if (message.status === 'failed') {
            return appendMessage('assistant', message.error_message || 'تعذر إكمال الرد.', message.public_id, prepend);
        }
        if (message.status !== 'completed') {
            const node = appendMessage('loading', 'يفكر...', message.public_id, prepend);
            pollMessage(message.public_id);
            return node;
        }

        return appendMessage(message.role, message.content || '', message.public_id, prepend);
    };

    const resetMessages = () => {
        messagesContainer.replaceChildren(loadOlderBtn);
        loadOlderBtn.hidden = true;
    };

    const renderWelcome = () => {
        resetMessages();
        appendMessage('assistant', 'أنا المستشار الذكي. اسألني عن مشروعك أو عن الخطوة العملية التالية.');
        if (suggestionsContainer) {
            suggestionsContainer.hidden = false;
            messagesContainer.appendChild(suggestionsContainer);
        }
    };

    async function createConversation() {
        const toolForm = document.querySelector('[data-tool-ajax-form]');
        const projectSelect = document.querySelector('[name="project_id"]');
        const result = await requestJson(conversationsUrl, {
            method: 'POST',
            body: JSON.stringify({
                tool_key: toolForm?.dataset.toolCode || 'general',
                project_id: projectSelect?.value || null,
            }),
        });
        currentConversation = result.data;
        title.textContent = currentConversation.title;
        messagePage = 1;
        hasOlderMessages = false;
        showConversationView();
        renderWelcome();

        return currentConversation;
    }

    async function initializeConversation() {
        try {
            const result = await requestJson(conversationsUrl);
            const conversations = Array.isArray(result.data) ? result.data : [];
            if (conversations.length) {
                await loadConversation(conversations[0].public_id);
            } else {
                await createConversation();
            }
        } catch (error) {
            showConversationView();
            resetMessages();
            appendMessage('assistant', error.message || 'تعذر تحميل المحادثات.');
        }
    }

    async function loadConversation(conversationId, page = 1, prepend = false) {
        const result = await requestJson(`${conversationUrl(conversationId)}?per_page=50&page=${page}`);
        currentConversation = result.data;
        title.textContent = currentConversation.title || 'المستشار الذكي';
        messagePage = result.messages?.meta?.current_page || page;
        hasOlderMessages = messagePage < (result.messages?.meta?.last_page || 1);
        showConversationView();

        const storedMessages = Array.isArray(result.messages?.data) ? result.messages.data : [];
        if (!prepend) resetMessages();
        const orderedMessages = prepend ? [...storedMessages].reverse() : storedMessages;
        orderedMessages.forEach((message) => renderStoredMessage(message, prepend));
        loadOlderBtn.hidden = !hasOlderMessages;
        if (!prepend) messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    async function pollMessage(messageId) {
        if (!currentConversation || activePolls.has(messageId)) return;
        activePolls.add(messageId);
        const conversationId = currentConversation.public_id;

        for (let attempt = 0; attempt < 80; attempt += 1) {
            await new Promise((resolve) => window.setTimeout(resolve, 2500));
            try {
                const result = await requestJson(`${conversationUrl(conversationId)}/messages/${encodeURIComponent(messageId)}`);
                if (result.data?.status === 'completed' || result.data?.status === 'failed') {
                    renderStoredMessage(result.data);
                    activePolls.delete(messageId);
                    return;
                }
            } catch (_) {
                // Keep the pending message visible through transient network failures.
            }
        }
        activePolls.delete(messageId);
        const node = messagesContainer.querySelector(`[data-message-id="${CSS.escape(messageId)}"]`);
        if (node) node.textContent = 'لا يزال الرد قيد المعالجة. سيظهر عند فتح المحادثة مرة أخرى.';
    }

    const sendMessage = async (overrideText) => {
        const text = (overrideText || input.value).trim();
        if (!text) return;

        input.value = '';
        hideSuggestions();
        if (!currentConversation) {
            try {
                await createConversation();
            } catch (error) {
                appendMessage('assistant', error.message);
                return;
            }
        }
        appendMessage('user', text);

        sendBtn.disabled = true;

        // بثّ الرد رمزاً برمز (SSE) — نظير الموبايل. فقاعة حيّة تُملأ تدريجياً.
        const liveNode = appendMessage('assistant', '…');
        let acc = '';
        try {
            const resp = await fetch(chatStreamUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'text/event-stream',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ messages: [{ role: 'user', content: text }] }),
            });
            if (!resp.ok || !resp.body) throw new Error('تعذّر الاتصال بالمساعد.');

            const reader = resp.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            let streaming = true;
            while (streaming) {
                const { done, value } = await reader.read();
                if (done) break;
                buffer += decoder.decode(value, { stream: true });
                let nl;
                while ((nl = buffer.indexOf('\n')) >= 0) {
                    const line = buffer.slice(0, nl).trim();
                    buffer = buffer.slice(nl + 1);
                    if (!line.startsWith('data:')) continue;
                    const payload = line.slice(5).trim();
                    if (payload === '[DONE]') { streaming = false; break; }
                    try {
                        const ev = JSON.parse(payload);
                        if (ev.error) { acc = ev.error; }
                        else if (ev.delta) { acc += ev.delta; }
                        liveNode.textContent = acc || '…';
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    } catch (_) { /* سطر SSE غير مكتمل — نتجاهله */ }
                }
            }
            if (!acc) liveNode.textContent = 'تعذّر توليد رد الآن.';
        } catch (err) {
            liveNode.textContent = err.message || 'حدث خطأ في الاتصال. حاول مرة أخرى.';
        } finally {
            sendBtn.disabled = false;
            input.focus();
        }
    };

    const researchBtn = document.getElementById('ai-chat-research');

    const appendResearch = (data) => {
        const div = document.createElement('div');
        div.className = 'ai-chat-msg ai-chat-msg-assistant';
        const findings = Array.isArray(data.findings) ? data.findings : [];
        const head = `<p><strong>بحث حيّ:</strong> ${escapeHtml(data.summary || '')}</p>`;
        const list = findings.slice(0, 5).map((f) => {
            const cat = f.category ? `<span class="ai-chat-source-cat">[${escapeHtml(f.category)}]</span>` : '';
            const snippet = f.snippet ? `<small>${escapeHtml(String(f.snippet).slice(0, 140))}</small>` : '';
            return `<li><a class="ai-chat-source" href="${escapeHtml(f.url)}" target="_blank" rel="noopener noreferrer">${cat}${escapeHtml(f.title || f.url)}<br>${snippet}</a></li>`;
        }).join('');
        div.innerHTML = head + (list ? `<ul class="ai-chat-sources">${list}</ul>` : '');
        messagesContainer.appendChild(div);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    };

    const runResearch = async () => {
        const query = input.value.trim();
        if (!query) {
            input.focus();
            return;
        }

        input.value = '';
        hideSuggestions();
        appendMessage('user', `ابحث حيّاً: ${query}`);

        if (researchBtn) researchBtn.disabled = true;
        sendBtn.disabled = true;
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'ai-chat-msg ai-chat-msg-loading';
        loadingDiv.innerHTML = '<span class="btn-spinner"></span> أبحث في الإنترنت...';
        messagesContainer.appendChild(loadingDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;

        try {
            const researchUrl = toggle.dataset.researchUrl || '/api/ai/research';
            const response = await fetchPost(researchUrl, { query });
            const result = await response.json();
            loadingDiv.remove();

            if (result.research) {
                appendResearch(result.research);
            } else {
                appendMessage('assistant', result.error || 'تعذّر البحث الحيّ الآن.');
            }
        } catch (err) {
            loadingDiv.remove();
            appendMessage('assistant', 'حدث خطأ في الاتصال أثناء البحث. حاول مرة أخرى.');
        } finally {
            if (researchBtn) researchBtn.disabled = false;
            sendBtn.disabled = false;
            input.focus();
        }
    };

    if (researchBtn) {
        researchBtn.addEventListener('click', () => runResearch());
    }

    if (historyBtn) historyBtn.addEventListener('click', showHistoryView);
    if (newBtn) newBtn.addEventListener('click', async () => {
        try {
            await createConversation();
            input.focus();
        } catch (error) {
            appendMessage('assistant', error.message);
        }
    });
    loadOlderBtn.addEventListener('click', async () => {
        if (!currentConversation || !hasOlderMessages) return;
        loadOlderBtn.disabled = true;
        try {
            await loadConversation(currentConversation.public_id, messagePage + 1, true);
        } finally {
            loadOlderBtn.disabled = false;
        }
    });

    sendBtn.addEventListener('click', () => sendMessage());
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    if (suggestionsContainer) {
        suggestionsContainer.querySelectorAll('[data-ai-suggestion]').forEach((btn) => {
            btn.addEventListener('click', () => {
                sendMessage(btn.dataset.aiSuggestion);
            });
        });
    }
}

function wireStudioGenerationCopy() {
    const output = document.querySelector('[data-studio-output]');
    if (!output) return;

    document.querySelectorAll('[data-copy-studio]').forEach((button) => {
        button.addEventListener('click', async () => {
            const text = output.innerText.trim();
            if (!text) return;

            const originalText = button.textContent;

            try {
                await navigator.clipboard.writeText(text);
                button.textContent = 'تم النسخ!';
                window.setTimeout(() => {
                    button.textContent = originalText;
                }, 2000);
            } catch (_) {
                button.textContent = 'تعذر النسخ';
                window.setTimeout(() => {
                    button.textContent = originalText;
                }, 2000);
            }
        });
    });
}

function wireAuditProgressPoll() {
    const banner = document.querySelector('.tool-audit-progress[data-audit-status-url]');
    const url = banner?.dataset.auditStatusUrl;
    if (!url) {
        return;
    }

    let stopped = false;
    const poll = async () => {
        if (stopped) {
            return;
        }
        try {
            const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (res.ok) {
                const data = await res.json();
                // التدقيق غير المتزامن اكتمل (أو فشل) — نعيد تحميل الصفحة لإظهار النتائج وإخفاء البانر.
                if (data && data.in_progress === false) {
                    stopped = true;
                    window.location.reload();
                    return;
                }
            }
        } catch (error) {
            // نتجاهل أخطاء الشبكة العابرة ونعيد المحاولة.
        }
        if (!stopped) {
            setTimeout(poll, 7000);
        }
    };

    setTimeout(poll, 7000);
}

document.addEventListener('DOMContentLoaded', () => {
    wireThemeToggle();
    wireWorkspaceSwitcher();
    wireShellDrawer();
    wireRevealAnimations();
    wireCounters();
    wireStickyNav();
    wireMarketingNav();
    wireDynamicLists();
    wireToolModeSwitcher();
    wireToolSteppers();
    wireDiagnosisPreview();
    wireGenericToolPreview();
    wireToolLibraryFilters();
    wireToolWorkspaceShortcuts();
    wireToolAjaxSubmission();
    wireToolProjectSwitch();
    wireToolAiAnalysis();
    wireToolAiSuggestions();
    wireAuditProgressPoll();
    wireToolAfterSaveHighlight();
    wireAiChatWidget();
    wireFieldCompletionTracking();
    wireFieldFocusDots();
    wireToolFieldSuggestions();
    wireExampleChips();
    wireToolInputCoach();
    wireAutoSaveDraft();
    wireLivePreviewFlash();
    wireStudioGenerationCopy();
});
