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

const labels = {
    bold: 'عريض',
    italic: 'مائل',
    underline: 'تحته خط',
    strike: 'شطب',
    h2: 'عنوان 2',
    h3: 'عنوان 3',
    bulletList: 'نقاط',
    orderedList: 'ترقيم',
    taskList: 'مهام',
    blockquote: 'اقتباس',
    code: 'كود',
    codeBlock: 'كتلة كود',
    highlight: 'تمييز',
    alignRight: 'يمين',
    alignCenter: 'وسط',
    alignJustify: 'ضبط',
    link: 'رابط',
    image: 'صورة',
    youtube: 'YouTube',
    table: 'جدول',
    horizontalRule: 'فاصل',
    undo: 'تراجع',
    redo: 'إعادة',
    fullscreen: 'ملء الشاشة',
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-content-editor]').forEach((shell) => initializeEditor(shell));
});

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
            Placeholder.configure({ placeholder: 'ابدأ كتابة المحتوى هنا…' }),
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
        image: () => selectImage(editor, shell.dataset.uploadUrl),
        youtube: () => addYoutube(editor),
        fullscreen: () => shell.classList.toggle('is-fullscreen'),
    };

    Object.keys(actions).forEach((name) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'content-editor-tool';
        button.dataset.editorAction = name;
        button.textContent = labels[name];
        button.addEventListener('click', actions[name]);
        toolbar.appendChild(button);
    });

    shell.querySelector('[data-editor-preview]')?.addEventListener('click', () => preview(editor));
    form.addEventListener('submit', () => sync(editor));
    sync(editor);

    function sync(instance) {
        htmlInput.value = instance.getHTML();
        jsonInput.value = JSON.stringify(instance.getJSON());

        if (count) {
            count.textContent = `${instance.storage.characterCount.words()} كلمة · ${instance.storage.characterCount.characters()} حرف`;
        }

        updateToolbar(instance);
    }

    function updateToolbar(instance) {
        const active = {
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
        };

        toolbar.querySelectorAll('[data-editor-action]').forEach((button) => {
            button.classList.toggle('is-active', Boolean(active[button.dataset.editorAction]));
        });
    }
}

function setLink(editor) {
    const previous = editor.getAttributes('link').href || '';
    const url = window.prompt('رابط الوجهة:', previous);

    if (url === null) return;
    if (url.trim() === '') {
        editor.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }

    editor.chain().focus().extendMarkRange('link').setLink({ href: url.trim(), target: '_blank' }).run();
}

function selectImage(editor, uploadUrl) {
    if (!uploadUrl) return;

    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/jpeg,image/png,image/webp,image/gif';
    input.hidden = true;

    input.addEventListener('change', async () => {
        const file = input.files?.[0];
        if (!file) return;

        const alt = window.prompt('وصف الصورة البديل:', '') || '';
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

            if (!response.ok) throw new Error('upload-failed');
            const payload = await response.json();
            editor.chain().focus().setImage({ src: payload.data.url, alt }).run();
        } catch {
            window.alert('تعذر رفع الصورة. تحقق من نوع الملف أو حجمه وحاول مجددًا.');
        } finally {
            input.remove();
        }
    });

    document.body.appendChild(input);
    input.click();
}

function addYoutube(editor) {
    const url = window.prompt('رابط فيديو YouTube:');
    if (!url) return;

    editor.commands.setYoutubeVideo({ src: url, width: 800, height: 450 });
}

function preview(editor) {
    const dialog = document.createElement('dialog');
    dialog.className = 'content-editor-preview';
    dialog.innerHTML = '<form method="dialog"><button class="btn btn--ghost">إغلاق</button></form>';
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
