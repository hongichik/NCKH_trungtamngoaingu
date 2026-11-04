/**
 * Rich-text editor for the admin (CKEditor 5, GPL build copied from the
 * truongthuchanh project). Turns every <textarea data-rich-editor> into an
 * editor and keeps the textarea in sync so normal form posts keep working.
 *
 * Images: drag & drop, paste, or the "Chèn ảnh" button upload to
 * data-upload-url; once inserted, click an image to resize (drag the corner
 * handles or pick 25/50/75/100%), align/wrap text, add a caption, alt text or link.
 */
import {
    DecoupledEditor, Essentials, Paragraph, Heading, Autoformat, PasteFromOffice,
    Bold, Italic, Underline, Strikethrough, Subscript, Superscript, RemoveFormat,
    FontSize, FontColor, FontBackgroundColor, Alignment,
    List, ListProperties, Indent, IndentBlock, BlockQuote, HorizontalLine, SpecialCharacters, SpecialCharactersEssentials,
    Link, AutoLink, LinkImage,
    Image, ImageInsert, ImageUpload, ImageInsertViaUrl, AutoImage, ImageToolbar, ImageCaption, ImageStyle,
    ImageResize, ImageTextAlternative,
    Table, TableToolbar, TableProperties, TableCellProperties, TableColumnResize, TableCaption,
    MediaEmbed, FindAndReplace, SourceEditing, ShowBlocks, GeneralHtmlSupport, HtmlComment,
} from 'ckeditor5';
import vi from 'ckeditor5/translations/vi.js';

function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

/** Sends each image to Laravel; the server answers { url } or { message }. */
function uploadAdapterPlugin(uploadUrl) {
    return function UploadAdapter(editor) {
        editor.plugins.get('FileRepository').createUploadAdapter = (loader) => {
            const controller = new AbortController();
            return {
                upload: () => loader.file.then((file) => {
                    const data = new FormData();
                    data.append('upload', file);
                    return fetch(uploadUrl, {
                        method: 'POST',
                        body: data,
                        signal: controller.signal,
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                    })
                        .then((response) => response.json().catch(() => ({})).then((body) => {
                            if (!response.ok || !body.url) {
                                const message = (body.errors && Object.values(body.errors)[0]?.[0]) || body.message || 'Tải ảnh lên thất bại.';
                                throw message;
                            }
                            return { default: body.url };
                        }));
                }),
                abort: () => controller.abort(),
            };
        };
    };
}

function buildConfig(element, textarea) {
    const uploadUrl = textarea.dataset.uploadUrl;
    const simple = textarea.dataset.richEditor === 'simple';

    const plugins = [
        Essentials, Paragraph, Heading, Autoformat, PasteFromOffice,
        Bold, Italic, Underline, Strikethrough, Subscript, Superscript, RemoveFormat,
        FontSize, FontColor, FontBackgroundColor, Alignment,
        List, ListProperties, Indent, IndentBlock, BlockQuote, HorizontalLine, SpecialCharacters, SpecialCharactersEssentials,
        Link, AutoLink, LinkImage,
        Image, ImageInsert, ImageInsertViaUrl, AutoImage, ImageToolbar, ImageCaption, ImageStyle, ImageResize, ImageTextAlternative,
        Table, TableToolbar, TableProperties, TableCellProperties, TableColumnResize, TableCaption,
        MediaEmbed, FindAndReplace, SourceEditing, ShowBlocks, GeneralHtmlSupport, HtmlComment,
    ];
    if (uploadUrl) {
        plugins.push(ImageUpload, uploadAdapterPlugin(uploadUrl));
    }

    const toolbar = simple
        ? ['undo', 'redo', '|', 'bold', 'italic', 'underline', 'link', '|', 'bulletedList', 'numberedList', '|', 'removeFormat']
        : [
            'undo', 'redo', '|',
            'heading', 'fontSize', 'fontColor', 'fontBackgroundColor', '|',
            'bold', 'italic', 'underline', 'strikethrough', 'removeFormat', '|',
            'link', 'insertImage', 'mediaEmbed', 'insertTable', 'blockQuote', 'horizontalLine', 'specialCharacters', '|',
            'alignment', 'bulletedList', 'numberedList', 'outdent', 'indent', '|',
            'findAndReplace', 'showBlocks', 'sourceEditing',
        ];

    return {
        root: {
            element,
            placeholder: textarea.getAttribute('placeholder') || 'Nhập nội dung, hoặc kéo thả / dán ảnh trực tiếp vào đây...',
        },
        licenseKey: 'GPL',
        language: 'vi',
        translations: [vi],
        plugins,
        toolbar: { items: toolbar, shouldNotGroupWhenFull: true },
        heading: {
            options: [
                { model: 'paragraph', title: 'Đoạn văn', class: 'ck-heading_paragraph' },
                { model: 'heading2', view: 'h2', title: 'Tiêu đề lớn', class: 'ck-heading_heading2' },
                { model: 'heading3', view: 'h3', title: 'Tiêu đề vừa', class: 'ck-heading_heading3' },
                { model: 'heading4', view: 'h4', title: 'Tiêu đề nhỏ', class: 'ck-heading_heading4' },
            ],
        },
        fontSize: { options: [12, 14, 'default', 18, 20, 24, 30], supportAllValues: true },
        image: {
            insert: { integrations: uploadUrl ? ['upload', 'url'] : ['url'] },
            resizeUnit: '%',
            resizeOptions: [
                { name: 'resizeImage:original', value: null, label: 'Gốc' },
                { name: 'resizeImage:25', value: '25', label: '25%' },
                { name: 'resizeImage:50', value: '50', label: '50%' },
                { name: 'resizeImage:75', value: '75', label: '75%' },
                { name: 'resizeImage:100', value: '100', label: '100%' },
            ],
            toolbar: [
                'imageStyle:inline', 'imageStyle:wrapText', 'imageStyle:breakText', '|',
                'resizeImage', '|',
                'toggleImageCaption', 'imageTextAlternative', 'linkImage',
            ],
        },
        table: {
            contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells', 'tableProperties', 'tableCellProperties', 'toggleTableCaption'],
        },
        // Store real <iframe> embeds so YouTube/Vimeo videos play on the public site.
        mediaEmbed: { previewsInData: true },
        link: {
            addTargetToExternalLinks: true,
            defaultProtocol: 'https://',
        },
        // Keep existing HTML (legacy posts, Quill content) intact, but never event handlers or scripts.
        htmlSupport: {
            allow: [{ name: /^.*$/, styles: true, attributes: true, classes: true }],
            disallow: [
                { name: /^(script|object|embed)$/ },
                { attributes: [{ key: /^on.*/i, value: true }] },
            ],
        },
    };
}

function init(textarea) {
    const holder = document.createElement('div');
    holder.className = 'rich-editor';
    const editable = document.createElement('div');
    editable.innerHTML = textarea.value;
    holder.appendChild(editable);
    textarea.after(holder);
    textarea.classList.add('d-none');
    textarea.removeAttribute('required');

    DecoupledEditor.create(buildConfig(editable, textarea))
        .then((editor) => {
            holder.insertBefore(editor.ui.view.toolbar.element, editable);
            editable.style.minHeight = (textarea.dataset.minHeight || (textarea.dataset.richEditor === 'simple' ? 120 : 420)) + 'px';

            const sync = () => { textarea.value = editor.getData(); };
            editor.model.document.on('change:data', sync);
            textarea.form?.addEventListener('submit', sync);

            // Warn before leaving with unsaved changes.
            let dirty = false;
            editor.model.document.on('change:data', () => { dirty = true; });
            textarea.form?.addEventListener('submit', () => { dirty = false; });
            window.addEventListener('beforeunload', (e) => {
                if (dirty) { e.preventDefault(); e.returnValue = ''; }
            });
        })
        .catch((error) => {
            console.error(error);
            // Fall back to the plain textarea so content can still be edited.
            holder.remove();
            textarea.classList.remove('d-none');
        });
}

document.querySelectorAll('textarea[data-rich-editor]').forEach(init);
