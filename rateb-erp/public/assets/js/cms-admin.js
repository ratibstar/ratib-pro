(function () {
    function loadScript(src, cb) {
        var s = document.createElement('script');
        s.src = src;
        s.onload = cb || null;
        document.head.appendChild(s);
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="rateb-csrf"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function mediaJsonUrl() {
        return document.body.getAttribute('data-rateb-media-json') || '/admin/cms/media/json';
    }

    function tinymceUploadUrl() {
        return document.body.getAttribute('data-rateb-tinymce-upload') || '/admin/cms/media/tinymce-upload';
    }

    function openMediaPicker(callback) {
        fetch(mediaJsonUrl(), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.items || !data.items.length) {
                    window.alert('No images in media library.');
                    return;
                }
                var list = data.items.map(function (it, i) {
                    return (i + 1) + '. ' + it.name;
                }).join('\n');
                var pick = window.prompt('Enter image number:\n' + list, '1');
                var idx = parseInt(pick, 10) - 1;
                if (idx >= 0 && data.items[idx]) {
                    callback(data.items[idx].url, { alt: data.items[idx].name });
                }
            })
            .catch(function () {
                window.alert('Could not load media library.');
            });
    }

    function initWysiwyg() {
        var areas = document.querySelectorAll('.rateb-cms-wysiwyg');
        if (!areas.length || typeof window.tinymce === 'undefined') return;
        window.tinymce.init({
            selector: '.rateb-cms-wysiwyg',
            height: 320,
            menubar: false,
            plugins: 'lists link image code table',
            toolbar: 'undo redo | bold italic | bullist numlist | link image media_lib | code',
            branding: false,
            relative_urls: false,
            remove_script_host: false,
            images_upload_handler: function (blobInfo, progress) {
                return new Promise(function (resolve, reject) {
                    var fd = new FormData();
                    fd.append('file', blobInfo.blob(), blobInfo.filename());
                    fd.append('_csrf', csrfToken());
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', tinymceUploadUrl());
                    xhr.onload = function () {
                        if (xhr.status < 200 || xhr.status >= 300) {
                            reject('Upload failed');
                            return;
                        }
                        try {
                            var json = JSON.parse(xhr.responseText);
                            if (json.location) {
                                resolve(json.location);
                            } else {
                                reject(json.error || 'Upload failed');
                            }
                        } catch (e) {
                            reject('Invalid response');
                        }
                    };
                    xhr.onerror = function () { reject('Network error'); };
                    xhr.send(fd);
                });
            },
            setup: function (editor) {
                editor.ui.registry.addButton('media_lib', {
                    text: 'Media',
                    onAction: function () {
                        openMediaPicker(function (url, meta) {
                            editor.insertContent('<img src="' + url + '" alt="' + (meta.alt || '') + '">');
                        });
                    }
                });
            }
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
