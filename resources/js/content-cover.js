import { t } from './i18n';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-content-cover]').forEach((shell) => initializeCoverUploader(shell));
});

function initializeCoverUploader(shell) {
    const fileInput = shell.querySelector('[data-cover-file]');
    const valueInput = shell.querySelector('[data-cover-input]');
    const preview = shell.querySelector('[data-cover-preview]');
    const empty = shell.querySelector('[data-cover-empty]');
    const remove = shell.querySelector('[data-cover-remove]');
    const status = shell.querySelector('[data-cover-status]');
    const maxBytes = Number(shell.dataset.mediaMaxBytes) || 256 * 1024 * 1024;

    if (!fileInput || !valueInput || !preview || !empty || !remove || !status) return;

    fileInput.addEventListener('change', async () => {
        const file = fileInput.files?.[0];
        if (!file) return;

        if (file.size > maxBytes) {
            status.classList.add('is-error');
            status.textContent = t('حجم الصورة :size، والحد الأقصى :max.', { size: formatBytes(file.size), max: formatBytes(maxBytes) });
            fileInput.value = '';
            return;
        }

        const dimensions = await readImageDimensions(file);
        const fileDetails = [formatBytes(file.size), dimensions ? t(':width × :height بكسل', { width: dimensions.width, height: dimensions.height }) : null]
            .filter(Boolean)
            .join(' · ');

        status.classList.remove('is-error');
        status.textContent = t('جارٍ رفع الصورة… :details', { details: fileDetails });
        fileInput.disabled = true;

        const body = new FormData();
        body.append('file', file);
        body.append('alt_text', shell.closest('form')?.querySelector('[name="title"]')?.value || t('الصورة الرئيسية'));

        try {
            const response = await fetch(shell.dataset.uploadUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body,
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(payload.errors?.file?.[0] || t('تعذر رفع الصورة.'));

            valueInput.value = payload.data.url;
            preview.src = payload.data.url;
            preview.hidden = false;
            empty.hidden = true;
            remove.hidden = false;
            status.textContent = t('تم رفع الصورة (:details). احفظ المحتوى لتثبيتها.', { details: fileDetails });
        } catch (error) {
            status.classList.add('is-error');
            status.textContent = error.message || t('تعذر رفع الصورة. استخدم JPG أو PNG أو WebP أو GIF.');
        } finally {
            fileInput.disabled = false;
            fileInput.value = '';
        }
    });

    remove.addEventListener('click', () => {
        valueInput.value = '';
        preview.removeAttribute('src');
        preview.hidden = true;
        empty.hidden = false;
        remove.hidden = true;
        status.classList.remove('is-error');
        status.textContent = t('ستُحذف الصورة الرئيسية عند حفظ المحتوى.');
    });
}

function readImageDimensions(file) {
    return new Promise((resolve) => {
        const image = new Image();
        const url = URL.createObjectURL(file);

        image.onload = () => {
            resolve({ width: image.naturalWidth, height: image.naturalHeight });
            URL.revokeObjectURL(url);
        };
        image.onerror = () => {
            resolve(null);
            URL.revokeObjectURL(url);
        };
        image.src = url;
    });
}

function formatBytes(bytes) {
    if (bytes < 1024) return t(':count بايت', { count: bytes });
    if (bytes < 1024 * 1024) return t(':count كيلوبايت', { count: formatNumber(bytes / 1024) });

    return t(':count ميجابايت', { count: formatNumber(bytes / 1024 / 1024) });
}

function formatNumber(value) {
    return value.toLocaleString('ar', { maximumFractionDigits: 1 });
}
