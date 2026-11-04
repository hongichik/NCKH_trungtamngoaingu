{{-- Include once on any admin page that has <textarea data-rich-editor>. --}}
@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('vendor/ckeditor5/ckeditor5.css') }}">
        <style>
            .rich-editor { border: 1px solid var(--tblr-border-color); border-radius: var(--tblr-border-radius); background: #fff; }
            .rich-editor > .ck-toolbar { border: 0 !important; border-bottom: 1px solid var(--tblr-border-color) !important; border-radius: var(--tblr-border-radius) var(--tblr-border-radius) 0 0 !important; position: sticky; top: 0; z-index: 10; }
            .rich-editor > .ck-editor__editable { border: 0 !important; box-shadow: none !important; padding: 0 1rem; }
            .rich-editor .ck-content img { max-width: 100%; height: auto; }
        </style>
    @endpush
    @push('scripts')
        <script type="importmap">
            { "imports": { "ckeditor5": "{{ asset('vendor/ckeditor5/ckeditor5.js') }}", "ckeditor5/": "{{ asset('vendor/ckeditor5') }}/" } }
        </script>
        <script type="module" src="{{ asset('js/admin-editor.js') }}?v={{ filemtime(public_path('js/admin-editor.js')) }}"></script>
    @endpush
@endonce
