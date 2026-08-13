document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy-template]');
    if (!button) return;

    const template = button.closest('.recommendation-template');
    const text = [...(template?.querySelectorAll('p') ?? [])]
        .map((node) => node.textContent?.trim() ?? '')
        .filter(Boolean)
        .join('\n');
    if (!text) return;

    try {
        await navigator.clipboard.writeText(text);
    } catch (_) {
        const area = document.createElement('textarea');
        area.value = text;
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        document.execCommand('copy');
        area.remove();
    }

    const original = button.textContent;
    button.textContent = 'تم النسخ';
    setTimeout(() => { button.textContent = original; }, 1600);
});
