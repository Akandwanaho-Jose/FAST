(function () {
    'use strict';

    const excluded = new Set([
        'summary', 'meta_description', 'revision_note', 'comment',
        'citation_text', 'contribution', 'caption', 'title'
    ]);

    const escapeHtml = (value) => value
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');

    document.querySelectorAll('textarea').forEach((textarea) => {
        const rows = Number(textarea.getAttribute('rows') || 0);
        if (rows < 5 || excluded.has(textarea.name) || textarea.dataset.plainText === 'true') return;

        const wrapper = document.createElement('div');
        wrapper.className = 'rich-editor';
        const toolbar = document.createElement('div');
        toolbar.className = 'rich-editor-toolbar';
        toolbar.setAttribute('role', 'toolbar');
        toolbar.setAttribute('aria-label', 'Text formatting');
        toolbar.addEventListener('mousedown', (event) => { if (event.target.closest('button')) event.preventDefault(); });
        const editor = document.createElement('div');
        editor.className = 'rich-editor-surface';
        editor.contentEditable = 'true';
        editor.setAttribute('role', 'textbox');
        editor.setAttribute('aria-multiline', 'true');
        editor.setAttribute('aria-label', textarea.previousElementSibling?.textContent?.trim() || 'Content editor');
        if (textarea.required) editor.setAttribute('aria-required', 'true');
        editor.innerHTML = /<\/?[a-z][^>]*>/i.test(textarea.value)
            ? textarea.value
            : escapeHtml(textarea.value).replace(/\r?\n/g, '<br>');

        const commands = [
            ['undo', '↶', 'Undo'], ['redo', '↷', 'Redo'],
            ['bold', 'B', 'Bold'], ['italic', 'I', 'Italic'], ['underline', 'U', 'Underline'],
            ['insertUnorderedList', '• List', 'Bulleted list'],
            ['insertOrderedList', '1. List', 'Numbered list'],
            ['formatBlock', '“ Quote', 'Block quote', 'blockquote'],
            ['removeFormat', 'Clear', 'Clear formatting']
        ];
        const format = document.createElement('select');
        format.setAttribute('aria-label', 'Paragraph style');
        [['p','Paragraph'],['h2','Heading 2'],['h3','Heading 3'],['h4','Heading 4']].forEach(([value,label]) => {
            const option = document.createElement('option'); option.value = value; option.textContent = label; format.append(option);
        });
        format.addEventListener('change', () => { editor.focus(); document.execCommand('formatBlock', false, format.value); });
        toolbar.append(format);
        commands.forEach(([command, label, title, value]) => {
            const button = document.createElement('button'); button.type = 'button'; button.textContent = label; button.title = title; button.setAttribute('aria-label', title);
            if (command === 'bold') button.innerHTML = '<strong>B</strong>';
            if (command === 'italic') button.innerHTML = '<em>I</em>';
            if (command === 'underline') button.innerHTML = '<u>U</u>';
            button.addEventListener('click', () => { editor.focus(); document.execCommand(command, false, value || null); }); toolbar.append(button);
        });
        const link = document.createElement('button'); link.type = 'button'; link.textContent = 'Link'; link.setAttribute('aria-label', 'Insert link');
        link.addEventListener('click', () => { const url = window.prompt('Enter a complete web address or a path beginning with /'); if (url) { editor.focus(); document.execCommand('createLink', false, url); } });
        toolbar.append(link);
        const count = document.createElement('small'); count.className = 'rich-editor-count';
        const sync = () => { const empty = editor.textContent.trim() === ''; textarea.value = empty ? '' : editor.innerHTML; count.textContent = editor.textContent.trim().length + ' characters'; };
        editor.addEventListener('input', sync);
        editor.addEventListener('paste', (event) => { event.preventDefault(); document.execCommand('insertText', false, event.clipboardData.getData('text/plain')); });
        wrapper.append(toolbar, editor, count); textarea.dataset.wasRequired = textarea.required ? 'true' : 'false'; textarea.hidden = true; textarea.removeAttribute('required'); textarea.insertAdjacentElement('afterend', wrapper); sync();
        textarea.form?.addEventListener('submit', (event) => { sync(); if (textarea.dataset.wasRequired === 'true' && textarea.value === '') { event.preventDefault(); editor.focus(); } });
    });

    document.querySelectorAll('[data-repeatable]').forEach((section) => {
        const list = section.querySelector('[data-repeatable-list]');
        const template = section.querySelector('[data-repeatable-template]');
        const add = section.querySelector('[data-repeatable-add]');
        if (!list || !template || !add) return;

        const bindRemove = (row) => {
            row.querySelector('[data-repeatable-remove]')?.addEventListener('click', () => {
                const rows = list.querySelectorAll('[data-repeatable-row]');
                if (rows.length === 1) {
                    row.querySelectorAll('input').forEach((input) => { input.value = ''; });
                    row.querySelectorAll('select').forEach((select) => { select.selectedIndex = 0; });
                    return;
                }
                row.remove();
            });
        };

        list.querySelectorAll('[data-repeatable-row]').forEach(bindRemove);
        add.addEventListener('click', () => {
            const index = Number(list.dataset.nextIndex || list.children.length);
            const wrapper = document.createElement('div');
            wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index)).trim();
            const row = wrapper.firstElementChild;
            if (!row) return;
            list.append(row);
            list.dataset.nextIndex = String(index + 1);
            bindRemove(row);
            row.querySelector('input,select')?.focus();
        });
    });
}());
