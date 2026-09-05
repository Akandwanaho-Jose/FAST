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
        let insertableImages = [];
        try { insertableImages = JSON.parse(textarea.dataset.insertImages || '[]'); } catch (parseError) { insertableImages = []; }
        if (!Array.isArray(insertableImages)) insertableImages = [];

        const insertImage = (image) => {
            if (!image || !image.url) return;

            const figure = document.createElement('figure');
            figure.className = 'editorial-inline-figure';
            const imageElement = document.createElement('img');
            imageElement.src = image.url;
            imageElement.alt = image.alt || '';
            figure.append(imageElement);
            if (image.alt) {
                const figcaption = document.createElement('figcaption');
                figcaption.textContent = image.alt;
                figure.append(figcaption);
            }
            const spacer = document.createElement('p');
            spacer.innerHTML = '<br>';

            editor.focus();
            const selection = window.getSelection();
            let range;
            if (selection && selection.rangeCount > 0 && editor.contains(selection.anchorNode)) {
                range = selection.getRangeAt(0);
            } else {
                range = document.createRange();
                range.selectNodeContents(editor);
                range.collapse(false);
            }
            range.deleteContents();
            range.insertNode(figure);
            range.setStartAfter(figure);
            range.collapse(true);
            range.insertNode(spacer);
            range.selectNodeContents(spacer);
            range.collapse(true);
            selection.removeAllRanges();
            selection.addRange(range);

            sync();
        };

        let imagePicker = null;
        const buildImagePicker = () => {
            imagePicker = document.createElement('select');
            imagePicker.setAttribute('aria-label', 'Insert photo');
            const placeholderOption = document.createElement('option');
            placeholderOption.value = '';
            placeholderOption.textContent = 'Insert photo…';
            imagePicker.append(placeholderOption);
            insertableImages.forEach((image, index) => {
                const option = document.createElement('option');
                option.value = String(index);
                option.textContent = image && image.alt ? image.alt : ('Photo ' + (index + 1));
                imagePicker.append(option);
            });
            imagePicker.addEventListener('change', () => {
                const index = Number(imagePicker.value);
                imagePicker.value = '';
                insertImage(insertableImages[index]);
            });
            toolbar.append(imagePicker);
        };
        if (insertableImages.length > 0) buildImagePicker();

        const addInsertableImage = (image) => {
            insertableImages.push(image);
            if (!imagePicker) {
                buildImagePicker();
                return;
            }
            const option = document.createElement('option');
            option.value = String(insertableImages.length - 1);
            option.textContent = image.alt || ('Photo ' + insertableImages.length);
            imagePicker.append(option);
        };

        // Lets an editor upload a photo straight from their computer and have
        // it inserted immediately, instead of having to save a gallery photo
        // first (a separate form submit) and only then pick it from the
        // dropdown above. Only rendered once the article exists, since a
        // gallery photo must belong to a saved article id.
        const uploadUrl = textarea.dataset.galleryUploadUrl;
        if (uploadUrl) {
            const csrfToken = () => {
                const field = textarea.form ? textarea.form.querySelector('input[name="_token"]') : null;
                return field ? field.value : '';
            };
            const addGalleryRow = (image, id) => {
                const tableBody = document.getElementById('gallery-table-body');
                if (!tableBody) return;
                const emptyMessage = document.getElementById('gallery-empty');
                const tableWrap = document.getElementById('gallery-table-wrap');
                if (emptyMessage) emptyMessage.hidden = true;
                if (tableWrap) tableWrap.hidden = false;

                const row = document.createElement('tr');
                const photoCell = document.createElement('td');
                const thumbnail = document.createElement('img');
                thumbnail.src = image.url; thumbnail.alt = ''; thumbnail.width = 96;
                thumbnail.style.height = '64px'; thumbnail.style.objectFit = 'cover'; thumbnail.style.borderRadius = '4px';
                photoCell.append(thumbnail);
                const descriptionCell = document.createElement('td');
                descriptionCell.textContent = image.alt || '';
                const actionsCell = document.createElement('td');
                const removeForm = document.createElement('form');
                removeForm.method = 'post';
                removeForm.action = uploadUrl + '/' + id + '/remove';
                const tokenInput = document.createElement('input');
                tokenInput.type = 'hidden'; tokenInput.name = '_token'; tokenInput.value = csrfToken();
                const removeButton = document.createElement('button');
                removeButton.className = 'link-button'; removeButton.textContent = 'Remove';
                removeForm.append(tokenInput, removeButton);
                actionsCell.append(removeForm);
                row.append(photoCell, descriptionCell, actionsCell);
                tableBody.append(row);
            };

            const uploadButton = document.createElement('button');
            uploadButton.type = 'button';
            uploadButton.textContent = 'Upload photo';
            uploadButton.title = 'Upload a photo from your computer and insert it here';
            uploadButton.setAttribute('aria-label', 'Upload a photo from your computer and insert it here');
            const fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.accept = 'image/jpeg,image/png,image/webp,image/gif';
            fileInput.hidden = true;
            fileInput.addEventListener('change', () => {
                const file = fileInput.files && fileInput.files[0];
                fileInput.value = '';
                if (!file) return;
                const altText = window.prompt('Describe this photo for visitors using assistive technology:');
                if (altText === null || altText.trim() === '') return;

                const formData = new FormData();
                formData.append('gallery_photo', file);
                formData.append('gallery_alt_text', altText.trim());
                formData.append('ajax', '1');
                formData.append('_token', csrfToken());

                const originalLabel = uploadButton.textContent;
                uploadButton.disabled = true;
                uploadButton.textContent = 'Uploading…';
                fetch(uploadUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
                    .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
                    .then(({ ok, data }) => {
                        if (!ok) {
                            window.alert(data && Array.isArray(data.errors) && data.errors.length > 0
                                ? data.errors.join(' ')
                                : 'The photo could not be uploaded.');
                            return;
                        }
                        const image = { url: data.url, alt: data.alt };
                        insertImage(image);
                        addInsertableImage(image);
                        addGalleryRow(image, data.id);
                    })
                    .catch(() => {
                        window.alert('The photo could not be uploaded. Check your connection and try again.');
                    })
                    .finally(() => {
                        uploadButton.disabled = false;
                        uploadButton.textContent = originalLabel;
                    });
            });
            uploadButton.addEventListener('click', () => fileInput.click());
            toolbar.append(uploadButton, fileInput);
        }
        const count = document.createElement('small'); count.className = 'rich-editor-count';
        const sync = () => { const empty = editor.textContent.trim() === ''; textarea.value = empty ? '' : editor.innerHTML; count.textContent = editor.textContent.trim().length + ' characters'; };
        editor.addEventListener('input', sync);
        editor.addEventListener('paste', (event) => { event.preventDefault(); document.execCommand('insertText', false, event.clipboardData.getData('text/plain')); });
        wrapper.append(toolbar, editor, count); textarea.dataset.wasRequired = textarea.required ? 'true' : 'false'; textarea.hidden = true; textarea.removeAttribute('required'); textarea.insertAdjacentElement('afterend', wrapper); sync();
        textarea.form?.addEventListener('submit', (event) => { sync(); if (textarea.dataset.wasRequired === 'true' && textarea.value === '') { event.preventDefault(); editor.focus(); } });
    });

    // Lets any "reuse an existing image" <select> upload a brand new photo
    // in place, instead of requiring a trip to the Media library first (the
    // select just needs data-media-upload="<upload endpoint URL>").
    document.querySelectorAll('select[data-media-upload]').forEach((select) => {
        const uploadUrl = select.dataset.mediaUpload;
        if (!uploadUrl) return;
        const form = select.closest('form');
        const csrfToken = () => {
            const field = form ? form.querySelector('input[name="_token"]') : null;
            return field ? field.value : '';
        };

        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/jpeg,image/png,image/webp,image/gif';
        fileInput.hidden = true;

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'media-upload-trigger';
        button.textContent = 'Upload new photo…';

        fileInput.addEventListener('change', () => {
            const file = fileInput.files && fileInput.files[0];
            fileInput.value = '';
            if (!file) return;
            const altText = window.prompt('Describe this photo for visitors using assistive technology:');
            if (altText === null || altText.trim() === '') return;

            const formData = new FormData();
            formData.append('image', file);
            formData.append('alt_text', altText.trim());
            formData.append('ajax', '1');
            formData.append('_token', csrfToken());

            const originalLabel = button.textContent;
            button.disabled = true;
            button.textContent = 'Uploading…';
            fetch(uploadUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
                .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
                .then(({ ok, data }) => {
                    if (!ok) {
                        window.alert(data && Array.isArray(data.errors) && data.errors.length > 0
                            ? data.errors.join(' ')
                            : 'The photo could not be uploaded.');
                        return;
                    }
                    const option = document.createElement('option');
                    option.value = String(data.id);
                    option.textContent = data.name || data.alt || ('Photo ' + data.id);
                    option.selected = true;
                    select.append(option);
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                })
                .catch(() => {
                    window.alert('The photo could not be uploaded. Check your connection and try again.');
                })
                .finally(() => {
                    button.disabled = false;
                    button.textContent = originalLabel;
                });
        });
        button.addEventListener('click', () => fileInput.click());
        select.insertAdjacentElement('afterend', fileInput);
        fileInput.insertAdjacentElement('afterend', button);
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
