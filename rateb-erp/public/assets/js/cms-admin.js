(function () {
    function loadScript(src, cb) {
        var s = document.createElement('script');
        s.src = src;
        s.onload = cb || null;
        document.head.appendChild(s);
    }

    function initWysiwyg() {
        var areas = document.querySelectorAll('.rateb-cms-wysiwyg');
        if (!areas.length || typeof window.tinymce === 'undefined') return;
        window.tinymce.init({
            selector: '.rateb-cms-wysiwyg',
            height: 320,
            menubar: false,
            plugins: 'lists link image code table',
            toolbar: 'undo redo | bold italic | bullist numlist | link image | code',
            branding: false
        });
    }

    function initPageBuilder() {
        var sections = document.getElementById('cmsSectionsSort');
        if (!sections || typeof window.Sortable === 'undefined') return;
        window.Sortable.create(sections, {
            handle: '.cms-pb-handle',
            animation: 150,
            draggable: '.cms-pb-section'
        });
        sections.querySelectorAll('.cms-pb-blocks').forEach(function (el) {
            window.Sortable.create(el, {
                handle: '.cms-pb-handle',
                animation: 150,
                draggable: '.cms-pb-block',
                group: 'blocks'
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var needsEditor = document.querySelector('.rateb-cms-wysiwyg');
        var needsSort = document.getElementById('cmsSectionsSort');
        if (needsEditor) {
            loadScript('https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js', initWysiwyg);
        }
        if (needsSort) {
            loadScript('https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js', initPageBuilder);
        }
    });
})();
