(function () {
    'use strict';

    var modalEl = document.getElementById('ratebEntityDocsModal');
    if (!modalEl) {
        return;
    }

    var bodyEl = modalEl.querySelector('[data-entity-docs-body]');
    var titleEl = modalEl.querySelector('[data-entity-docs-title]');
    var activeBtn = null;
    var activeRoutePrefix = '';
    var activeEntityId = 0;

    function csrfToken() {
        var meta = document.querySelector('meta[name="rateb-csrf"]');
        return meta ? meta.getAttribute('content') || '' : '';
    }

    function panelUrl(routePrefix, entityId) {
        return routePrefix.replace(/\/$/, '') + '/' + entityId + '/documents/panel';
    }

    function updateBadge(count) {
        if (!activeBtn) {
            return;
        }
        var badge = activeBtn.querySelector('.badge');
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary';
                activeBtn.appendChild(badge);
            }
            badge.textContent = String(count);
        } else if (badge) {
            badge.remove();
        }
        activeBtn.setAttribute('data-doc-count', String(count));
    }

    function showAlert(message, isError) {
        if (!message) {
            return;
        }
        var alert = document.createElement('div');
        alert.className = 'alert alert-' + (isError ? 'danger' : 'success') + ' alert-dismissible fade show';
        alert.setAttribute('role', 'alert');
        alert.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        bodyEl.insertBefore(alert, bodyEl.firstChild);
        window.setTimeout(function () {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 4000);
    }

    function bindPanel(root) {
        root.querySelectorAll('[data-entity-docs-upload]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var fd = new FormData(form);
                if (!fd.get('rateb_doc_modal')) {
                    fd.append('rateb_doc_modal', '1');
                }
                fetch(form.action, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: { 'X-Rateb-Doc-Modal': '1', Accept: 'application/json' },
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success) {
                            updateBadge(data.count || 0);
                            loadPanel(activeRoutePrefix, activeEntityId, titleEl.textContent);
                            showAlert(data.message || '', false);
                        } else {
                            showAlert(data.message || 'Error', true);
                        }
                    })
                    .catch(function () { showAlert('Error', true); });
            });
        });

        root.querySelectorAll('[data-entity-docs-delete]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var msg = form.getAttribute('data-confirm') || 'Confirm?';
                if (!window.confirm(msg)) {
                    return;
                }
                var fd = new FormData(form);
                if (!fd.get('rateb_doc_modal')) {
                    fd.append('rateb_doc_modal', '1');
                }
                fetch(form.action, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: { 'X-Rateb-Doc-Modal': '1', Accept: 'application/json' },
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success) {
                            updateBadge(data.count || 0);
                            loadPanel(activeRoutePrefix, activeEntityId, titleEl.textContent);
                            showAlert(data.message || '', false);
                        } else {
                            showAlert(data.message || 'Error', true);
                        }
                    })
                    .catch(function () { showAlert('Error', true); });
            });
        });

        root.querySelectorAll('.js-edit-doc').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var editModalEl = root.querySelector('.rateb-edit-doc-modal');
                if (!editModalEl) {
                    return;
                }
                var form = editModalEl.querySelector('.rateb-edit-doc-form');
                var titleInput = editModalEl.querySelector('.rateb-edit-doc-title');
                var fileLabel = editModalEl.querySelector('.rateb-edit-doc-current');
                var docId = btn.getAttribute('data-doc-id');
                titleInput.value = btn.getAttribute('data-doc-title') || '';
                fileLabel.textContent = btn.getAttribute('data-doc-file') || '';
                form.action = form.getAttribute('data-route-prefix') + docId;
                bootstrap.Modal.getOrCreateInstance(editModalEl).show();
            });
        });

        root.querySelectorAll('.rateb-edit-doc-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var fd = new FormData(form);
                if (!fd.get('rateb_doc_modal')) {
                    fd.append('rateb_doc_modal', '1');
                }
                fetch(form.action, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: { 'X-Rateb-Doc-Modal': '1', Accept: 'application/json' },
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        var editModal = form.closest('.rateb-edit-doc-modal');
                        if (editModal) {
                            var inst = bootstrap.Modal.getInstance(editModal);
                            if (inst) {
                                inst.hide();
                            }
                        }
                        if (data.success) {
                            updateBadge(data.count || 0);
                            loadPanel(activeRoutePrefix, activeEntityId, titleEl.textContent);
                            showAlert(data.message || '', false);
                        } else {
                            showAlert(data.message || 'Error', true);
                        }
                    })
                    .catch(function () { showAlert('Error', true); });
            });
        });
    }

    function loadPanel(routePrefix, entityId, label) {
        activeRoutePrefix = routePrefix;
        activeEntityId = entityId;
        titleEl.textContent = label || titleEl.textContent;
        bodyEl.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
        fetch(panelUrl(routePrefix, entityId), {
            credentials: 'same-origin',
            headers: { Accept: 'text/html' },
        })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('load failed');
                }
                return r.text();
            })
            .then(function (html) {
                bodyEl.innerHTML = html;
                bindPanel(bodyEl);
            })
            .catch(function () {
                bodyEl.innerHTML = '<p class="text-danger text-center py-3">Error</p>';
            });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-entity-docs-open');
        if (!btn) {
            return;
        }
        e.preventDefault();
        activeBtn = btn;
        var routePrefix = btn.getAttribute('data-route-prefix') || '';
        var entityId = parseInt(btn.getAttribute('data-entity-id') || '0', 10);
        var label = btn.getAttribute('data-record-label') || '';
        if (!routePrefix || entityId < 1) {
            return;
        }
        var docsTitle = btn.getAttribute('data-docs-title') || '';
        loadPanel(routePrefix, entityId, docsTitle ? docsTitle + ' — ' + label : label);
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
})();
