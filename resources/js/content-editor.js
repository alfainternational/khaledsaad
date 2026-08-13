import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import TextAlign from '@tiptap/extension-text-align';
import Highlight from '@tiptap/extension-highlight';
import { TableKit } from '@tiptap/extension-table';
import TaskList from '@tiptap/extension-task-list';
import TaskItem from '@tiptap/extension-task-item';
import Youtube from '@tiptap/extension-youtube';
import Placeholder from '@tiptap/extension-placeholder';
import CharacterCount from '@tiptap/extension-character-count';
import { t } from './i18n';

const labels = {
    paragraph: t('نص عادي'),
    bold: t('عريض'),
    italic: t('مائل'),
    underline: t('تحته خط'),
    strike: t('شطب'),
    h2: t('عنوان 2'),
    h3: t('عنوان 3'),
    bulletList: t('نقاط'),
    orderedList: t('ترقيم'),
    taskList: t('مهام'),
    blockquote: t('اقتباس'),
    code: t('كود'),
    codeBlock: t('كتلة كود'),
    highlight: t('تمييز'),
    alignRight: t('يمين'),
    alignCenter: t('وسط'),
    alignJustify: t('ضبط'),
    link: t('رابط'),
    image: t('صورة'),
    youtube: 'YouTube',
    table: t('جدول'),
    horizontalRule: t('فاصل'),
    undo: t('تراجع'),
    redo: t('إعادة'),
    fullscreen: t('ملء الشاشة'),
    clear: t('مسح التنسيق'),
};

const toolbarGroups = [
    { label: t('تنسيق النص'), actions: ['bold', 'italic', 'underline', 'strike', 'highlight', 'clear'] },
    { label: t('بنية المحتوى'), actions: ['paragraph', 'h2', 'h3', 'bulletList', 'orderedList', 'taskList', 'blockquote', 'code', 'codeBlock'] },
    { label: t('إدراج عناصر'), actions: ['link', 'image', 'youtube', 'table', 'horizontalRule'] },
    { label: t('محاذاة النص'), actions: ['alignRight', 'alignCenter', 'alignJustify'] },
    { label: t('التحكم والعرض'), actions: ['undo', 'redo', 'fullscreen'] },
];

const svg = (body) => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${body}</svg>`;
const icons = {
    paragraph: svg('<path d="M6 4h12M12 4v16M8 20h8"/>'),
    bold: svg('<path d="M8 4h5a4 4 0 0 1 0 8H8zM8 12h6a4 4 0 0 1 0 8H8z"/>'),
    italic: svg('<path d="M10 4h7M7 20h7M14 4 10 20"/>'),
    underline: svg('<path d="M7 4v7a5 5 0 0 0 10 0V4M5 21h14"/>'),
    strike: svg('<path d="M17 6.5C16 4.8 14.3 4 12 4c-3 0-5 1.5-5 3.7 0 1 .4 1.8 1.2 2.3M5 12h14M9 14c.5 1.4 1.8 2 3.8 2 2.7 0 4.2-1.2 4.2-3"/>'),
    highlight: svg('<path d="m9 11 4 4 7-7-4-4zM4 20h8M6 17l3-6"/>'),
    clear: svg('<path d="m4 15 8-8 5 5-8 8H6zM14 18h6M11 8l5 5"/>'),
    h2: svg('<path d="M4 5v14M12 5v14M4 12h8M16 10a3 3 0 0 1 6 0c0 4-6 4-6 9h6"/>'),
    h3: svg('<path d="M3 5v14M11 5v14M3 12h8M16 7c1-2 5-2 5 1 0 2-2 2-3 2 1 0 4 0 4 3.5 0 3.5-5 4.5-7 1.5"/>'),
    bulletList: svg('<circle cx="5" cy="6" r="1"/><circle cx="5" cy="12" r="1"/><circle cx="5" cy="18" r="1"/><path d="M9 6h11M9 12h11M9 18h11"/>'),
    orderedList: svg('<path d="M4 5h1v3M3.5 8H6M3.5 12c.5-1 2.5-1 2.5.5 0 1.5-2.5 1.5-2.5 3H6M3.5 18c.5-1 2.5-1 2.5.2 0 1-1 1.3-1.8 1.3.8 0 2 .2 2 1.3 0 1.3-2 1.6-3 .5M9 6h11M9 13h11M9 20h11"/>'),
    taskList: svg('<rect x="3" y="4" width="4" height="4" rx="1"/><path d="m4 6 1 1 2-3M10 6h11M3 12h4v4H3zM10 14h11M3 20h4M10 20h11"/>'),
    blockquote: svg('<path d="M5 11h5v7H3v-5c0-4 2-7 6-8M16 11h5v7h-7v-5c0-4 2-7 6-8"/>'),
    code: svg('<path d="m8 9-4 3 4 3M16 9l4 3-4 3M14 5l-4 14"/>'),
    codeBlock: svg('<rect x="3" y="4" width="18" height="16" rx="2"/><path d="m8 9-3 3 3 3M12 16h5"/>'),
    link: svg('<path d="M10 13a5 5 0 0 0 7.5.5l2-2a5 5 0 0 0-7-7l-1.2 1.2M14 11a5 5 0 0 0-7.5-.5l-2 2a5 5 0 0 0 7 7l1.2-1.2"/>'),
    image: svg('<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m5 18 4-4 3 3 3-4 4 5"/>'),
    youtube: svg('<rect x="2" y="5" width="20" height="14" rx="4"/><path d="m10 9 5 3-5 3z"/>'),
    table: svg('<rect x="3" y="4" width="18" height="16" rx="1"/><path d="M3 9h18M9 4v16M15 4v16"/>'),
    horizontalRule: svg('<path d="M4 12h16"/>'),
    alignRight: svg('<path d="M4 6h16M8 10h12M4 14h16M10 18h10"/>'),
    alignCenter: svg('<path d="M4 6h16M7 10h10M4 14h16M8 18h8"/>'),
    alignJustify: svg('<path d="M4 6h16M4 10h16M4 14h16M4 18h16"/>'),
    undo: svg('<path d="m9 7-5 5 5 5M5 12h8a6 6 0 0 1 6 6"/>'),
    redo: svg('<path d="m15 7 5 5-5 5M19 12h-8a6 6 0 0 0-6 6"/>'),
    fullscreen: svg('<path d="M8 3H3v5M16 3h5v5M8 21H3v-5M16 21h5v-5"/>'),
};

function initializeContentEditors() {
    document.querySelectorAll('[data-content-editor]').forEach((shell) => initializeEditor(shell));
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeContentEditors, { once: true });
} else {
    initializeContentEditors();
}

function initializeEditor(shell) {
    const form = shell.closest('form');
    const area = shell.querySelector('[data-editor-area]');
    const toolbar = shell.querySelector('[data-editor-toolbar]');
    const htmlInput = form?.querySelector('[data-editor-html]');
    const jsonInput = form?.querySelector('[data-editor-json]');
    const count = shell.querySelector('[data-editor-count]');
    const initialJson = parseJson(jsonInput?.value);

    if (!form || !area || !toolbar || !htmlInput || !jsonInput) return;

    const editor = new Editor({
        element: area,
        content: initialJson || htmlInput.value || '<p></p>',
        textDirection: 'rtl',
        extensions: [
            StarterKit.configure({
                link: {
                    openOnClick: false,
                    autolink: true,
                    HTMLAttributes: { rel: 'noopener noreferrer' },
                },
            }),
            Image.configure({ allowBase64: false }),
            TextAlign.configure({
                types: ['heading', 'paragraph'],
                defaultAlignment: 'right',
            }),
            Highlight,
            TableKit.configure({ table: { resizable: true } }),
            TaskList,
            TaskItem.configure({ nested: true }),
            Youtube.configure({ nocookie: true, controls: true }),
            Placeholder.configure({ placeholder: t('ابدأ كتابة المحتوى هنا…') }),
            CharacterCount.configure({ limit: 100000 }),
        ],
        editorProps: {
            attributes: {
                class: 'content-editor-document',
                dir: 'rtl',
                lang: 'ar',
            },
        },
        onUpdate: ({ editor: instance }) => sync(instance),
        onSelectionUpdate: ({ editor: instance }) => updateToolbar(instance),
    });

    const actions = {
        paragraph: () => editor.chain().focus().setParagraph().run(),
        bold: () => editor.chain().focus().toggleBold().run(),
        italic: () => editor.chain().focus().toggleItalic().run(),
        underline: () => editor.chain().focus().toggleUnderline().run(),
        strike: () => editor.chain().focus().toggleStrike().run(),
        h2: () => editor.chain().focus().toggleHeading({ level: 2 }).run(),
        h3: () => editor.chain().focus().toggleHeading({ level: 3 }).run(),
        bulletList: () => editor.chain().focus().toggleBulletList().run(),
        orderedList: () => editor.chain().focus().toggleOrderedList().run(),
        taskList: () => editor.chain().focus().toggleTaskList().run(),
        blockquote: () => editor.chain().focus().toggleBlockquote().run(),
        code: () => editor.chain().focus().toggleCode().run(),
        codeBlock: () => editor.chain().focus().toggleCodeBlock().run(),
        highlight: () => editor.chain().focus().toggleHighlight().run(),
        alignRight: () => editor.chain().focus().setTextAlign('right').run(),
        alignCenter: () => editor.chain().focus().setTextAlign('center').run(),
        alignJustify: () => editor.chain().focus().setTextAlign('justify').run(),
        horizontalRule: () => editor.chain().focus().setHorizontalRule().run(),
        undo: () => editor.chain().focus().undo().run(),
        redo: () => editor.chain().focus().redo().run(),
        table: () => editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(),
        link: () => setLink(editor),
        image: () => selectImage(editor, shell.dataset.uploadUrl, Number(shell.dataset.mediaMaxBytes) || 256 * 1024 * 1024),
        youtube: () => addYoutube(editor),
        fullscreen: () => {
            shell.classList.toggle('is-fullscreen');
            updateToolbar(editor);
        },
        clear: () => editor.chain().focus().unsetAllMarks().clearNodes().run(),
    };

    toolbarGroups.forEach((group) => {
        const groupElement = document.createElement('div');
        groupElement.className = 'content-editor-toolbar__group';
        groupElement.setAttribute('role', 'group');
        groupElement.setAttribute('aria-label', group.label);

        group.actions.forEach((name) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'content-editor-tool';
            button.dataset.editorAction = name;
            button.title = labels[name];
            button.setAttribute('aria-label', labels[name]);
            button.innerHTML = `<span class="content-editor-tool__icon">${icons[name]}</span><span class="content-editor-tool__label">${labels[name]}</span>`;
            button.addEventListener('click', actions[name]);
            groupElement.appendChild(button);
        });

        toolbar.appendChild(groupElement);
    });

    shell.querySelector('[data-editor-preview]')?.addEventListener('click', () => preview(editor));
    form.addEventListener('submit', () => sync(editor));
    sync(editor);

    function sync(instance) {
        htmlInput.value = instance.getHTML();
        jsonInput.value = JSON.stringify(instance.getJSON());

        if (count) {
            count.textContent = t(':words كلمة · :chars حرف', { words: instance.storage.characterCount.words(), chars: instance.storage.characterCount.characters() });
        }

        updateToolbar(instance);
    }

    function updateToolbar(instance) {
        const active = {
            paragraph: instance.isActive('paragraph'),
            bold: instance.isActive('bold'),
            italic: instance.isActive('italic'),
            underline: instance.isActive('underline'),
            strike: instance.isActive('strike'),
            h2: instance.isActive('heading', { level: 2 }),
            h3: instance.isActive('heading', { level: 3 }),
            bulletList: instance.isActive('bulletList'),
            orderedList: instance.isActive('orderedList'),
            taskList: instance.isActive('taskList'),
            blockquote: instance.isActive('blockquote'),
            code: instance.isActive('code'),
            codeBlock: instance.isActive('codeBlock'),
            highlight: instance.isActive('highlight'),
            alignRight: instance.isActive({ textAlign: 'right' }),
            alignCenter: instance.isActive({ textAlign: 'center' }),
            alignJustify: instance.isActive({ textAlign: 'justify' }),
            link: instance.isActive('link'),
            fullscreen: shell.classList.contains('is-fullscreen'),
        };

        toolbar.querySelectorAll('[data-editor-action]').forEach((button) => {
            button.classList.toggle('is-active', Boolean(active[button.dataset.editorAction]));
            button.setAttribute('aria-pressed', Boolean(active[button.dataset.editorAction]).toString());
        });
    }
}

function setLink(editor) {
    const previous = editor.getAttributes('link').href || '';
    const url = window.prompt(t('رابط الوجهة:'), previous);

    if (url === null) return;
    if (url.trim() === '') {
        editor.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }

    editor.chain().focus().extendMarkRange('link').setLink({ href: url.trim(), target: '_blank' }).run();
}

function selectImage(editor, uploadUrl, maxBytes) {
    if (!uploadUrl) return;

    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/jpeg,image/png,image/webp,image/gif';
    input.hidden = true;

    input.addEventListener('change', async () => {
        const file = input.files?.[0];
        if (!file) return;

        if (file.size > maxBytes) {
            window.alert(t('حجم الصورة :size، والحد الأقصى :max.', { size: formatMediaBytes(file.size), max: formatMediaBytes(maxBytes) }));
            input.remove();
            return;
        }

        const alt = window.prompt(t('وصف الصورة البديل:'), '') || '';
        const body = new FormData();
        body.append('file', file);
        body.append('alt_text', alt);

        try {
            const response = await fetch(uploadUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body,
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(payload.errors?.file?.[0] || t('تعذر رفع الصورة.'));
            editor.chain().focus().setImage({ src: payload.data.url, alt }).run();
        } catch (error) {
            window.alert(error.message || t('تعذر رفع الصورة. تحقق من نوع الملف أو حجمه وحاول مجددًا.'));
        } finally {
            input.remove();
        }
    });

    document.body.appendChild(input);
    input.click();
}

function formatMediaBytes(bytes) {
    if (bytes < 1024) return t(':count بايت', { count: bytes });
    if (bytes < 1024 * 1024) return t(':count كيلوبايت', { count: (bytes / 1024).toLocaleString('ar', { maximumFractionDigits: 1 }) });

    return t(':count ميجابايت', { count: (bytes / 1024 / 1024).toLocaleString('ar', { maximumFractionDigits: 1 }) });
}

function addYoutube(editor) {
    const url = window.prompt(t('رابط فيديو YouTube:'));
    if (!url) return;

    editor.commands.setYoutubeVideo({ src: url, width: 800, height: 450 });
}

function preview(editor) {
    const dialog = document.createElement('dialog');
    dialog.className = 'content-editor-preview';
    dialog.innerHTML = t('<form method="dialog"><button class="btn btn--ghost">إغلاق</button></form>');
    const article = document.createElement('article');
    article.className = 'prose content-prose';
    article.dir = 'rtl';
    article.innerHTML = editor.getHTML();
    dialog.appendChild(article);
    dialog.addEventListener('close', () => dialog.remove());
    document.body.appendChild(dialog);
    dialog.showModal();
}

function parseJson(value) {
    if (!value) return null;

    try {
        return JSON.parse(value);
    } catch {
        return null;
    }
}
