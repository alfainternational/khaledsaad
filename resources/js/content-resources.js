document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-content-resources]').forEach(initializeResources);
});

function initializeResources(shell) {
    const uploadUrl = shell.dataset.uploadUrl;
    const fileInput = shell.querySelector('[data-resource-files]');
    const list = shell.querySelector('[data-resource-list]');
    const empty = shell.querySelector('[data-resource-empty]');
    const status = shell.querySelector('[data-resource-status]');
    const hidden = shell.querySelector('[data-resources-json]');
    const titleInput = shell.querySelector('[data-resource-link-title]');
    const urlInput = shell.querySelector('[data-resource-link-url]');
    const addLink = shell.querySelector('[data-resource-add-link]');
    const maxBytes = Number(shell.dataset.mediaMaxBytes) || 256 * 1024 * 1024;
    let resources = parseInitial(shell.querySelector('[data-resources-initial]')?.textContent);

    if (!uploadUrl || !fileInput || !list || !empty || !status || !hidden) return;

    fileInput.addEventListener('change', async () => {
        const files = [...(fileInput.files || [])];
        fileInput.value = '';
        if (files.length === 0) return;

        status.classList.remove('is-error');

        for (const [index, file] of files.entries()) {
            if (file.size > maxBytes) {
                status.classList.add('is-error');
                status.textContent = `حجم ${file.name} هو ${formatBytes(file.size)}، والحد الأقصى ${formatBytes(maxBytes)}.`;
                return;
            }

            status.textContent = `جارٍ رفع ${file.name} · ${formatBytes(file.size)} (${index + 1} من ${files.length})…`;

            try {
                const uploaded = await uploadFile(uploadUrl, file);
                resources.push({
                    type: 'file',
                    title: uploaded.original_name || file.name,
                    media_id: uploaded.id,
                    url: uploaded.url,
                    original_name: uploaded.original_name || file.name,
                    size_bytes: uploaded.size_bytes || file.size,
                    mime_type: uploaded.mime_type || file.type,
                });
                render();
            } catch (error) {
                status.classList.add('is-error');
                status.textContent = error.message || `تعذر رفع ${file.name}.`;
                return;
            }
        }

        status.textContent = `تم رفع ${files.length} ${files.length === 1 ? 'ملف' : 'ملفات'} بنجاح.`;
    });

    addLink?.addEventListener('click', () => {
        const title = titleInput?.value.trim() || '';
        const url = urlInput?.value.trim() || '';

        if (!title || !isSafeHttpUrl(url)) {
            status.classList.add('is-error');
            status.textContent = 'اكتب اسمًا للرابط وعنوانًا صحيحًا يبدأ بـ http أو https.';
            return;
        }

        resources.push({ type: 'link', title, url });
        titleInput.value = '';
        urlInput.value = '';
        status.classList.remove('is-error');
        status.textContent = 'تمت إضافة الرابط.';
        render();
    });

    list.addEventListener('click', (event) => {
        const button = event.target.closest('[data-resource-action]');
        if (!button) return;

        const index = Number(button.dataset.resourceIndex);
        const action = button.dataset.resourceAction;
        if (!Number.isInteger(index) || !resources[index]) return;

        if (action === 'remove') resources.splice(index, 1);
        if (action === 'up' && index > 0) [resources[index - 1], resources[index]] = [resources[index], resources[index - 1]];
        if (action === 'down' && index < resources.length - 1) [resources[index], resources[index + 1]] = [resources[index + 1], resources[index]];
        render();
    });

    render();

    function render() {
        list.replaceChildren();

        resources.forEach((resource, index) => {
            const item = document.createElement('li');
            item.className = 'content-resources__item';

            const info = document.createElement('div');
            const name = document.createElement('strong');
            const meta = document.createElement('small');
            name.textContent = resource.title;
            meta.textContent = resource.type === 'file'
                ? `${resource.original_name || 'ملف مرفوع'}${resource.size_bytes ? ` · ${formatBytes(resource.size_bytes)}` : ''}`
                : resource.url;
            info.append(name, meta);

            const actions = document.createElement('div');
            actions.className = 'content-resources__actions';
            actions.append(
                actionButton('up', 'رفع', index, index === 0),
                actionButton('down', 'خفض', index, index === resources.length - 1),
                actionButton('remove', 'حذف', index, false),
            );
            item.append(info, actions);
            list.appendChild(item);
        });

        empty.hidden = resources.length > 0;
        hidden.value = JSON.stringify(resources.map((resource) => ({
            type: resource.type,
            title: resource.title,
            media_id: resource.type === 'file' ? resource.media_id : null,
            url: resource.type === 'link' ? resource.url : null,
        })));
    }
}

async function uploadFile(uploadUrl, file) {
    const body = new FormData();
    body.append('file', file);

    const response = await fetch(uploadUrl, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body,
    });
    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(payload.errors?.file?.[0] || 'تعذر رفع الملف. تحقق من نوعه وحجمه وحاول مجددًا.');
    }

    return payload.data;
}

function actionButton(action, label, index, disabled) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn--ghost btn--sm';
    button.dataset.resourceAction = action;
    button.dataset.resourceIndex = String(index);
    button.textContent = label;
    button.disabled = disabled;

    return button;
}

function parseInitial(value) {
    try {
        const parsed = JSON.parse(value || '[]');
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

function isSafeHttpUrl(value) {
    try {
        return ['http:', 'https:'].includes(new URL(value).protocol);
    } catch {
        return false;
    }
}

function formatBytes(bytes) {
    if (bytes < 1024) return `${bytes} بايت`;
    if (bytes < 1024 * 1024) return `${formatNumber(bytes / 1024)} كيلوبايت`;

    return `${formatNumber(bytes / 1024 / 1024)} ميجابايت`;
}

function formatNumber(value) {
    return value.toLocaleString('ar', { maximumFractionDigits: 1 });
}
