(function () {
    'use strict';

    var modalEl = document.getElementById('ratebEntityDocsModal');
    if (!modalEl) {
        return;
    }

    var editModalEl = document.getElementById('ratebEntityEditDocModal');
    [modalEl, editModalEl].forEach(function (el) {
        if (el && el.parentElement && el.parentElement !== document.body) {
            document.body.appendChild(el);
        }
    });

    var bodyEl = modalEl.querySelector('[data-entity-docs-body]');
    var titleEl = modalEl.querySelector('[data-entity-docs-title]');
    var editForm = document.getElementById('ratebEntityEditDocForm');
    var editTitleInput = document.getElementById('ratebEntityEditDocTitle');
    var editFileLabel = document.getElementById('ratebEntityEditDocCurrent');
    var activeBtn = null;
    var activeRoutePrefix = '';
    var activeEntityId = 0;
    var activeDocsRoutePrefix = '';

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
        }, 5000);
    }

    function parseJsonResponse(response) {
        var ct = (response.headers.get('content-type') || '').toLowerCase();
        if (ct.indexOf('application/json') !== -1) {
            return response.json();
        }
        return response.text().then(function (text) {
            throw new Error(text ? text.substring(0, 120) : 'Invalid response');
        });
    }

    function postForm(form) {
        var fd = new FormData(form);
        if (!fd.get('rateb_doc_modal')) {
            fd.append('rateb_doc_modal', '1');
        }
        if (!fd.get('_csrf')) {
            var csrfMeta = document.querySelector('meta[name="rateb-csrf"]');
            if (csrfMeta) {
                fd.append('_csrf', csrfMeta.getAttribute('content') || '');
            }
        }
        return fetch(form.action, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Rateb-Doc-Modal': '1', Accept: 'application/json' },
        }).then(parseJsonResponse);
    }

    function handleActionResult(data) {
        if (data.success) {
            updateBadge(data.count || 0);
            loadPanel(activeRoutePrefix, activeEntityId, titleEl.textContent);
            showAlert(data.message || '', false);
        } else {
            showAlert(data.message || 'Error', true);
        }
    }

    function loadPanel(routePrefix, entityId, label) {
        activeRoutePrefix = routePrefix;
        activeEntityId = entityId;
        titleEl.textContent = label || titleEl.textContent;
        bodyEl.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
        fetch(panelUrl(routePrefix, entityId), {
            credentials: 'same-origin',
            headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('load failed');
                }
                return r.text();
            })
            .then(function (html) {
                bodyEl.innerHTML = html;
                var panel = bodyEl.querySelector('.rateb-entity-docs-panel');
                activeDocsRoutePrefix = panel
                    ? (panel.getAttribute('data-docs-route-prefix') || '')
                    : '';
            })
            .catch(function () {
                bodyEl.innerHTML = '<p class="text-danger text-center py-3">Error</p>';
            });
    }

    bodyEl.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || form.tagName !== 'FORM') {
            return;
        }
        if (form.matches('[data-entity-docs-upload], [data-entity-docs-delete]')) {
            e.preventDefault();
            if (form.hasAttribute('data-entity-docs-delete')) {
                var msg = form.getAttribute('data-confirm') || 'Confirm?';
                if (!window.confirm(msg)) {
                    return;
                }
            }
            postForm(form)
                .then(handleActionResult)
                .catch(function (err) {
                    showAlert(err && err.message ? err.message : 'Error', true);
                });
        }
    });

    bodyEl.addEventListener('click', function (e) {
        var editBtn = e.target.closest('.js-edit-doc');
        if (editBtn && editForm && editModalEl) {
            e.preventDefault();
            var docId = editBtn.getAttribute('data-doc-id');
            editTitleInput.value = editBtn.getAttribute('data-doc-title') || '';
            editFileLabel.textContent = editBtn.getAttribute('data-doc-file') || '';
            editForm.action = activeDocsRoutePrefix + docId;
            bootstrap.Modal.getOrCreateInstance(editModalEl).show();
            return;
        }

        var fileLink = e.target.closest('.rateb-doc-actions a[href]');
        if (fileLink) {
            e.preventDefault();
            window.open(fileLink.href, '_blank', 'noopener,noreferrer');
        }
    });

    if (editForm) {
        editForm.addEventListener('submit', function (e) {
            e.preventDefault();
            postForm(editForm)
                .then(function (data) {
                    var inst = bootstrap.Modal.getInstance(editModalEl);
                    if (inst) {
                        inst.hide();
                    }
                    handleActionResult(data);
                })
                .catch(function (err) {
                    showAlert(err && err.message ? err.message : 'Error', true);
                });
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
