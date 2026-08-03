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

    if (!fileInput || !valueInput || !preview || !empty || !remove || !status) return;

    fileInput.addEventListener('change', async () => {
        const file = fileInput.files?.[0];
        if (!file) return;

        status.classList.remove('is-error');
        status.textContent = 'جارٍ رفع الصورة…';
        fileInput.disabled = true;

        const body = new FormData();
        body.append('file', file);
        body.append('alt_text', shell.closest('form')?.querySelector('[name="title"]')?.value || 'الصورة الرئيسية');

        try {
            const response = await fetch(shell.dataset.uploadUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body,
            });

            if (!response.ok) throw new Error('upload-failed');

            const payload = await response.json();
            valueInput.value = payload.data.url;
            preview.src = payload.data.url;
            preview.hidden = false;
            empty.hidden = true;
            remove.hidden = false;
            status.textContent = 'تم رفع الصورة. احفظ المحتوى لتثبيتها.';
        } catch {
            status.classList.add('is-error');
            status.textContent = 'تعذر رفع الصورة. استخدم JPG أو PNG أو WebP بحجم مناسب.';
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
        status.textContent = 'ستُحذف الصورة الرئيسية عند حفظ المحتوى.';
    });
}
