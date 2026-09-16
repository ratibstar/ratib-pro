(function () {
    'use strict';

    function bulkRowChecks(table) {
        return table ? table.querySelectorAll('[data-rateb-row-check]') : [];
    }

    function syncBulkBar(table) {
        if (!table) {
            return;
        }
        var card = table.closest('.rateb-card');
        var bar = card ? card.querySelector('[data-rateb-bulk-bar]') : null;
        var countEl = bar ? bar.querySelector('[data-rateb-bulk-count]') : null;
        var selectAll = table.querySelector('[data-rateb-select-all]');
        var rows = bulkRowChecks(table);
        var ids = [];
        rows.forEach(function (cb) {
            if (cb.checked) {
                ids.push(cb.value);
            }
        });
        if (bar && countEl) {
            if (ids.length > 0) {
                bar.classList.remove('d-none');
                countEl.textContent = ids.length + ' ' + (countEl.getAttribute('data-label') || 'selected');
            } else {
                bar.classList.add('d-none');
            }
        }
        if (selectAll) {
            selectAll.indeterminate = ids.length > 0 && ids.length < rows.length;
            selectAll.checked = rows.length > 0 && ids.length === rows.length;
        }
    }

    function bindBulkForms(table) {
        var card = table.closest('.rateb-card');
        var bar = card ? card.querySelector('[data-rateb-bulk-bar]') : null;
        if (!bar) {
            return;
        }
        bar.querySelectorAll('[data-rateb-bulk-form]').forEach(function (form) {
            if (form.querySelector('[data-rateb-bulk-delete-btn]')) {
                return;
            }
            if (form.getAttribute('data-rateb-ids-bound') === '1') {
                return;
            }
            form.setAttribute('data-rateb-ids-bound', '1');
            form.addEventListener('submit', function (e) {
                var ids = [];
                bulkRowChecks(table).forEach(function (cb) {
                    if (cb.checked) {
                        ids.push(cb.value);
                    }
                });
                if (ids.length === 0) {
                    e.preventDefault();
                    return;
                }
                form.querySelectorAll('input[name="ids[]"]').forEach(function (el) {
                    el.remove();
                });
                ids.forEach(function (id) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = id;
                    form.appendChild(input);
                });
            });
        });
    }

    function initBulkTables() {
        if (document.documentElement.getAttribute('data-rateb-bulk-delegate') !== '1') {
            document.documentElement.setAttribute('data-rateb-bulk-delegate', '1');
            document.addEventListener('change', function (e) {
                var t = e.target;
                if (!t || !t.closest) {
                    return;
                }
                if (t.matches('[data-rateb-select-all]')) {
                    var table = t.closest('table');
                    var on = !!t.checked;
                    bulkRowChecks(table).forEach(function (cb) {
                        cb.checked = on;
                    });
                    syncBulkBar(table);
                    return;
                }
                if (t.matches('[data-rateb-row-check]')) {
                    syncBulkBar(t.closest('table'));
                }
            }, true);
        }
        document.querySelectorAll('[data-rateb-bulk-table="1"]').forEach(function (table) {
            bindBulkForms(table);
            syncBulkBar(table);
        });
    }

    function initPermissionMatrix() {
        /* Soft-nav safe: one document-level listener — toggle col/module survive DOM swaps. */
        if (window.__ratebMatrixClickBound) {
            return;
        }
        window.__ratebMatrixClickBound = true;
        document.addEventListener('click', function (e) {
            var t = e.target && e.target.closest
                ? e.target.closest('[data-matrix-select-all], [data-matrix-select-none], [data-matrix-col], [data-matrix-module]')
                : null;
            if (!t) {
                return;
            }
            e.preventDefault();
            try { e.stopPropagation(); } catch (eStop) { /* ignore */ }
            var scope = t.closest('[data-role-lock-form]') || t.closest('form') || document;
            if (t.hasAttribute('data-matrix-select-all')) {
                scope.querySelectorAll('.rateb-matrix-check').forEach(function (cb) {
                    cb.checked = true;
                });
                return;
            }
            if (t.hasAttribute('data-matrix-select-none')) {
                scope.querySelectorAll('.rateb-matrix-check').forEach(function (cb) {
                    cb.checked = false;
                });
                return;
            }
            if (t.hasAttribute('data-matrix-col')) {
                var roleId = t.getAttribute('data-matrix-col');
                var colChecks = scope.querySelectorAll('.rateb-matrix-check[data-role="' + roleId + '"]');
                if (!colChecks.length) {
                    return;
                }
                var colAllOn = Array.prototype.every.call(colChecks, function (cb) { return cb.checked; });
                colChecks.forEach(function (cb) {
                    cb.checked = !colAllOn;
                });
                return;
            }
            if (t.hasAttribute('data-matrix-module')) {
                var mod = t.getAttribute('data-matrix-module');
                var modChecks = scope.querySelectorAll('.rateb-matrix-check[data-module="' + mod + '"]');
                if (!modChecks.length) {
                    return;
                }
                var modAllOn = Array.prototype.every.call(modChecks, function (cb) { return cb.checked; });
                modChecks.forEach(function (cb) {
                    cb.checked = !modAllOn;
                });
            }
        }, true);
    }

    function hydrateNavLazy(group) {
        if (window.RatebSidebarNav && typeof window.RatebSidebarNav.hydrate === 'function') {
            window.RatebSidebarNav.hydrate(group);
            return;
        }
        if (!group) {
            return;
        }
        var body = group.querySelector('.rateb-nav-group-body, .rateb-nav-subgroup-body');
        if (!body) {
            return;
        }
        var tpl = null;
        var kids = body.children;
        for (var i = 0; i < kids.length; i++) {
            if (kids[i].tagName === 'TEMPLATE' && kids[i].getAttribute('data-rateb-nav-lazy') !== null) {
                tpl = kids[i];
                break;
            }
        }
        if (!tpl) {
            return;
        }
        try {
            body.appendChild(tpl.content.cloneNode(true));
            tpl.remove();
        } catch (eHydrate) { /* ignore */ }
        try {
            if (window.RatebNavInstant && typeof window.RatebNavInstant.bindPrefetch === 'function') {
                var bodyRef = body;
                var bind = function () {
                    window.RatebNavInstant.bindPrefetch(bodyRef);
                };
                if (typeof window.requestIdleCallback === 'function') {
                    window.requestIdleCallback(bind, { timeout: 1200 });
                } else {
                    setTimeout(bind, 0);
                }
            }
        } catch (eBind) { /* ignore */ }
    }

    function initSidebarNavGroups() {
        if (window.RatebSidebarNav && typeof window.RatebSidebarNav.ensure === 'function') {
            window.RatebSidebarNav.ensure();
            return;
        }
        var side = document.getElementById('rateb-sidebar');
        if (!side || side.getAttribute('data-rateb-nav-delegated') === '3') {
            return;
        }
        side.setAttribute('data-rateb-nav-delegated', '3');
        side.addEventListener('click', function (ev) {
            if (ev.target && ev.target.closest && ev.target.closest('a[href]')) {
                return;
            }
            if (ev.__ratebNavToggleHandled) {
                return;
            }
            var btn = ev.target && ev.target.closest ? ev.target.closest('[data-nav-group-toggle]') : null;
            if (!btn || !side.contains(btn)) {
                return;
            }
            var group = btn.closest('[data-nav-group]');
            if (!group) {
                return;
            }
            ev.__ratebNavToggleHandled = true;
            try { ev.stopImmediatePropagation(); } catch (eStop) { /* ignore */ }
            var willOpen = !group.classList.contains('is-open');
            if (willOpen) {
                var parent = group.parentElement;
                if (parent) {
                    var isSub = group.classList.contains('rateb-nav-subgroup');
                    var kids = parent.children;
                    for (var i = 0; i < kids.length; i++) {
                        var sib = kids[i];
                        if (sib === group || !sib.getAttribute || sib.getAttribute('data-nav-group') === null) {
                            continue;
                        }
                        if (isSub !== sib.classList.contains('rateb-nav-subgroup')) {
                            continue;
                        }
                        if (!sib.classList.contains('is-open')) {
                            continue;
                        }
                        sib.classList.remove('is-open');
                        var t = sib.querySelector(':scope > [data-nav-group-toggle]');
                        if (t) {
                            t.setAttribute('aria-expanded', 'false');
                        }
                    }
                }
                group.classList.add('is-open');
                btn.setAttribute('aria-expanded', 'true');
                hydrateNavLazy(group);
            } else {
                group.classList.remove('is-open');
                btn.setAttribute('aria-expanded', 'false');
            }
        }, true);
    }

    function initTableSearch() {
        var isAr = document.documentElement.lang === 'ar';
        var placeholder = isAr ? 'بحث في الجدول…' : 'Search table…';
        var noResults = isAr ? 'لا توجد نتائج مطابقة' : 'No matching rows';
        var resultsLabel = isAr ? 'نتيجة' : 'results';

        function isEmptyStateRow(tr) {
            if (tr.classList && tr.classList.contains('rateb-matrix-module-row')) {
                return false;
            }
            return tr.querySelectorAll('td').length === 1 && tr.querySelector('td[colspan]') !== null;
        }

        function rowSearchText(tr) {
            var hay = tr.getAttribute('data-search-haystack');
            if (hay) {
                return String(hay).toLowerCase();
            }
            return (tr.textContent || '').toLowerCase();
        }

        function filterFromInput(input) {
            if (!input) {
                return;
            }
            var wrapEl = input.closest('[data-rateb-table-search-wrap]');
            if (!wrapEl || wrapEl.getAttribute('data-rateb-server-search') === '1') {
                return;
            }
            var host = wrapEl.nextElementSibling;
            var table = host && host.querySelector ? host.querySelector('table.rateb-table') : null;
            if (!table && wrapEl.parentElement) {
                table = wrapEl.parentElement.querySelector('table.rateb-table');
            }
            if (!table) {
                return;
            }
            var clearBtn = wrapEl.querySelector('[data-rateb-table-search-clear]');
            var meta = wrapEl.querySelector('[data-rateb-search-meta]');
            var tbody = table.querySelector('tbody');
            if (!tbody) {
                return;
            }

            var q = String(input.value || '').trim().toLowerCase();
            var visible = 0;
            var dataRows = 0;
            var emptyRow = tbody.querySelector('tr[data-rateb-search-empty="1"]');
            if (emptyRow) {
                emptyRow.remove();
                emptyRow = null;
            }

            var isMatrix = table.classList.contains('rateb-matrix-table');
            if (isMatrix) {
                var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
                var moduleRow = null;
                var moduleHasVisible = false;
                var moduleText = '';

                function flushModule() {
                    if (!moduleRow) {
                        return;
                    }
                    var modMatch = q === '' || moduleText.indexOf(q) !== -1;
                    moduleRow.style.display = (q === '' || modMatch || moduleHasVisible) ? '' : 'none';
                }

                rows.forEach(function (tr) {
                    if (tr.getAttribute('data-rateb-search-empty') === '1') {
                        return;
                    }
                    if (tr.classList.contains('rateb-matrix-module-row')) {
                        flushModule();
                        moduleRow = tr;
                        moduleHasVisible = false;
                        moduleText = rowSearchText(tr);
                        return;
                    }
                    dataRows++;
                    var text = rowSearchText(tr);
                    var show = q === '' || text.indexOf(q) !== -1
                        || (moduleText !== '' && moduleText.indexOf(q) !== -1);
                    tr.style.display = show ? '' : 'none';
                    if (show) {
                        visible++;
                        moduleHasVisible = true;
                    }
                });
                flushModule();
            } else {
                tbody.querySelectorAll('tr').forEach(function (tr) {
                    if (tr.getAttribute('data-rateb-search-empty') === '1') {
                        return;
                    }
                    if (isEmptyStateRow(tr)) {
                        tr.style.display = q === '' ? '' : 'none';
                        return;
                    }
                    dataRows++;
                    var text = rowSearchText(tr);
                    var show = q === '' || text.indexOf(q) !== -1;
                    tr.style.display = show ? '' : 'none';
                    if (show) {
                        visible++;
                    }
                });
            }

            if (clearBtn) {
                clearBtn.classList.toggle('d-none', q === '');
            }
            if (meta) {
                if (q === '') {
                    meta.classList.add('d-none');
                } else {
                    meta.classList.remove('d-none');
                    meta.textContent = visible + ' ' + resultsLabel;
                }
            }
            if (q !== '' && visible === 0 && dataRows > 0) {
                var colCount = table.querySelectorAll('thead th').length || 1;
                emptyRow = document.createElement('tr');
                emptyRow.setAttribute('data-rateb-search-empty', '1');
                emptyRow.innerHTML = '<td colspan="' + colCount + '" class="text-center text-muted py-3">' + noResults + '</td>';
                tbody.appendChild(emptyRow);
            }
        }

        /* Soft-nav safe: filter while typing without re-binding each navigation. */
        if (!window.__ratebTableSearchDelegated) {
            window.__ratebTableSearchDelegated = true;
            document.addEventListener('input', function (e) {
                var input = e.target && e.target.closest
                    ? e.target.closest('[data-rateb-table-search-field], [data-rateb-table-search-wrap] input[type="search"]')
                    : null;
                if (!input) {
                    return;
                }
                filterFromInput(input);
            }, true);
            document.addEventListener('keyup', function (e) {
                var input = e.target && e.target.closest
                    ? e.target.closest('[data-rateb-table-search-field], [data-rateb-table-search-wrap] input[type="search"]')
                    : null;
                if (!input) {
                    return;
                }
                filterFromInput(input);
            }, true);
            document.addEventListener('click', function (e) {
                var clearBtn = e.target && e.target.closest
                    ? e.target.closest('[data-rateb-table-search-clear]')
                    : null;
                if (!clearBtn) {
                    return;
                }
                var wrap = clearBtn.closest('[data-rateb-table-search-wrap]');
                var input = wrap ? wrap.querySelector('[data-rateb-table-search-field], input[type="search"]') : null;
                if (!input) {
                    return;
                }
                e.preventDefault();
                input.value = '';
                filterFromInput(input);
                input.focus();
            }, true);
        }

        document.querySelectorAll('table.rateb-table').forEach(function (table) {
            if (table.getAttribute('data-rateb-search-skip') === '1') {
                return;
            }
            /* Matrix search is server-rendered — never late-inject (causes flicker on soft-nav). */
            if (table.classList.contains('rateb-matrix-table')) {
                return;
            }
            if (table.closest('[data-rateb-server-search]')) {
                return;
            }
            if (table.closest('[data-rateb-table-search-wrap]')) {
                return;
            }
            var responsive = table.closest('.table-responsive');
            var container = responsive || table.parentElement;
            if (!container || !container.parentElement) {
                return;
            }
            if (container.parentElement.querySelector('[data-rateb-table-search-wrap]')) {
                return;
            }

            var wrap = document.createElement('div');
            wrap.className = 'rateb-table-search';
            wrap.setAttribute('data-rateb-table-search-wrap', '1');
            wrap.innerHTML =
                '<div class="rateb-table-search-row">' +
                '<div class="input-group input-group-sm rateb-table-search-input">' +
                '<span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span>' +
                '<input type="search" class="form-control" data-rateb-table-search-field="1" placeholder="' + placeholder + '" autocomplete="off">' +
                '<button type="button" class="btn btn-outline-secondary d-none" data-rateb-table-search-clear="1" title="' + (isAr ? 'مسح' : 'Clear') + '">' +
                '<i class="fas fa-times" aria-hidden="true"></i></button></div>' +
                '<span class="rateb-table-search-meta small text-muted d-none" data-rateb-search-meta="1"></span></div>';
            container.parentElement.insertBefore(wrap, container);
        });
    }

    function bootAppUi() {
        if (document.documentElement.getAttribute('data-rateb-app-ui-booted') === '1') {
            return;
        }
        document.documentElement.setAttribute('data-rateb-app-ui-booted', '1');

        /* Mobile sidebar open/close is owned by the inline drawer in main.php. */

        document.querySelectorAll('.rateb-flash .btn-close').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var alert = btn.closest('.rateb-flash');
                if (alert) {
                    alert.remove();
                }
            });
        });

        document.querySelectorAll('[data-rateb-bulk-count]').forEach(function (el) {
            var label = el.getAttribute('data-label');
            if (!label && document.documentElement.lang === 'ar') {
                el.setAttribute('data-label', 'محدد');
            } else if (!label) {
                el.setAttribute('data-label', 'selected');
            }
        });

        initBulkTables();
        initTableSearch();
        initPermissionMatrix();
        initSidebarNavGroups();
        initCoaFullTree();
    }

    /* PERF-P3 late-load fix: app.js may inject after DOMContentLoaded — still boot UI. */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootAppUi);
    } else {
        bootAppUi();
    }

    // PERF-P1 — re-bind content widgets after content-swap (never re-bind sidebar).
    window.RatebApp = window.RatebApp || {};
    window.RatebApp.reinit = function () {
        initBulkTables();
        initTableSearch();
        initPermissionMatrix();
        initCoaFullTree();
    };
    document.addEventListener('rateb:nav:afterEnter', function () {
        /* Bind search + matrix toggles immediately so platform↔company soft-nav
         * does not leave a visible search box unbound (or reinject late). */
        initTableSearch();
        initPermissionMatrix();
        var run = function () {
            if (window.RatebApp && typeof window.RatebApp.reinit === 'function') {
                window.RatebApp.reinit();
            }
        };
        // Keep sidebar clicks free — defer heavy table/COA rebind off the paint path.
        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(run, { timeout: 1200 });
        } else {
            setTimeout(run, 0);
        }
    });

    function initCoaFullTree() {
        var wrap = document.querySelector('[data-rateb-coa-full-tree="1"]');
        if (!wrap || wrap.getAttribute('data-rateb-bound') === '1') {
            return;
        }
        wrap.setAttribute('data-rateb-bound', '1');
        var table = wrap.querySelector('.rateb-coa-tree');
        if (!table) {
            return;
        }

        function setChildrenVisible(parentId, visible) {
            table.querySelectorAll('[data-coa-child-of="' + parentId + '"]').forEach(function (row) {
                if (visible) {
                    row.classList.remove('rateb-coa-hidden');
                } else {
                    row.classList.add('rateb-coa-hidden');
                }
                var nodeId = row.getAttribute('data-coa-node');
                if (nodeId && !visible) {
                    setChildrenVisible(nodeId, false);
                    var btn = table.querySelector('[data-coa-toggle="' + nodeId + '"]');
                    if (btn) {
                        btn.setAttribute('aria-expanded', 'false');
                        var icon = btn.querySelector('i');
                        if (icon) {
                            icon.className = 'fas fa-chevron-left';
                        }
                    }
                }
            });
        }

        table.querySelectorAll('[data-coa-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var parentId = btn.getAttribute('data-coa-toggle');
                var expanded = btn.getAttribute('aria-expanded') !== 'false';
                setChildrenVisible(parentId, !expanded);
                btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                var icon = btn.querySelector('i');
                if (icon) {
                    icon.className = expanded ? 'fas fa-chevron-left' : 'fas fa-chevron-down';
                }
            });
        });

        var expandAll = wrap.querySelector('[data-coa-expand-all]');
        if (expandAll) {
            expandAll.addEventListener('click', function () {
                table.querySelectorAll('.rateb-coa-child').forEach(function (row) {
                    row.classList.remove('rateb-coa-hidden');
                });
                table.querySelectorAll('[data-coa-toggle]').forEach(function (btn) {
                    btn.setAttribute('aria-expanded', 'true');
                    var icon = btn.querySelector('i');
                    if (icon) {
                        icon.className = 'fas fa-chevron-down';
                    }
                });
            });
        }

        var collapseAll = wrap.querySelector('[data-coa-collapse-all]');
        if (collapseAll) {
            collapseAll.addEventListener('click', function () {
                table.querySelectorAll('.rateb-coa-child').forEach(function (row) {
                    row.classList.add('rateb-coa-hidden');
                });
                table.querySelectorAll('[data-coa-toggle]').forEach(function (btn) {
                    btn.setAttribute('aria-expanded', 'false');
                    var icon = btn.querySelector('i');
                    if (icon) {
                        icon.className = 'fas fa-chevron-left';
                    }
                });
            });
        }
    }

    document.querySelectorAll('.rateb-approvals-alert .btn-close[data-rateb-dismiss-approvals]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url = btn.getAttribute('data-rateb-dismiss-approvals');
            if (!url) {
                return;
            }
            if (navigator.sendBeacon) {
                navigator.sendBeacon(url);
            } else {
                fetch(url, { credentials: 'same-origin', keepalive: true }).catch(function () {});
            }
        });
    });
})();
