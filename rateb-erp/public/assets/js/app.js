(function () {
    'use strict';

    function initBulkTables() {
        document.querySelectorAll('[data-rateb-bulk-table="1"]').forEach(function (table) {
            if (table.getAttribute('data-rateb-bound') === '1') {
                return;
            }
            table.setAttribute('data-rateb-bound', '1');
            var card = table.closest('.rateb-card');
            if (!card) {
                return;
            }
            var bar = card.querySelector('[data-rateb-bulk-bar]');
            var countEl = bar ? bar.querySelector('[data-rateb-bulk-count]') : null;
            var selectAll = table.querySelector('[data-rateb-select-all]');
            var rowChecks = table.querySelectorAll('[data-rateb-row-check]');

            function selectedIds() {
                var ids = [];
                rowChecks.forEach(function (cb) {
                    if (cb.checked) {
                        ids.push(cb.value);
                    }
                });
                return ids;
            }

            function updateBar() {
                var ids = selectedIds();
                if (!bar || !countEl) {
                    return;
                }
                if (ids.length > 0) {
                    bar.classList.remove('d-none');
                    countEl.textContent = ids.length + ' ' + (countEl.getAttribute('data-label') || 'selected');
                } else {
                    bar.classList.add('d-none');
                }
                if (selectAll) {
                    selectAll.indeterminate = ids.length > 0 && ids.length < rowChecks.length;
                    selectAll.checked = rowChecks.length > 0 && ids.length === rowChecks.length;
                }
            }

            rowChecks.forEach(function (cb) {
                cb.addEventListener('change', updateBar);
            });

            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    rowChecks.forEach(function (cb) {
                        cb.checked = selectAll.checked;
                    });
                    updateBar();
                });
            }

            if (bar) {
                bar.querySelectorAll('[data-rateb-bulk-form]').forEach(function (form) {
                    if (form.querySelector('[data-rateb-bulk-delete-btn]')) {
                        return;
                    }
                    form.addEventListener('submit', function (e) {
                        var ids = selectedIds();
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

            updateBar();
        });
    }

    function initPermissionMatrix() {
        document.querySelectorAll('[data-matrix-select-all]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var scope = btn.closest('form') || document;
                scope.querySelectorAll('.rateb-matrix-check').forEach(function (cb) {
                    cb.checked = true;
                });
            });
        });

        document.querySelectorAll('[data-matrix-select-none]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var scope = btn.closest('form') || document;
                scope.querySelectorAll('.rateb-matrix-check').forEach(function (cb) {
                    cb.checked = false;
                });
            });
        });

        document.querySelectorAll('[data-matrix-col]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var scope = btn.closest('form') || document;
                var roleId = btn.getAttribute('data-matrix-col');
                var checks = scope.querySelectorAll('.rateb-matrix-check[data-role="' + roleId + '"]');
                var allOn = Array.prototype.every.call(checks, function (cb) { return cb.checked; });
                checks.forEach(function (cb) {
                    cb.checked = !allOn;
                });
            });
        });

        document.querySelectorAll('[data-matrix-module]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var scope = btn.closest('form') || document;
                var mod = btn.getAttribute('data-matrix-module');
                var checks = scope.querySelectorAll('.rateb-matrix-check[data-module="' + mod + '"]');
                var allOn = Array.prototype.every.call(checks, function (cb) { return cb.checked; });
                checks.forEach(function (cb) {
                    cb.checked = !allOn;
                });
            });
        });
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
                window.RatebNavInstant.bindPrefetch(body);
            }
        } catch (eBind) { /* ignore */ }
    }

    function initSidebarNavGroups() {
        if (window.RatebSidebarNav && typeof window.RatebSidebarNav.ensure === 'function') {
            window.RatebSidebarNav.ensure();
            return;
        }
        var side = document.getElementById('rateb-sidebar');
        if (!side || side.getAttribute('data-rateb-nav-delegated') === '2') {
            return;
        }
        side.setAttribute('data-rateb-nav-delegated', '2');
        side.addEventListener('click', function (ev) {
            if (ev.__ratebNavToggleHandled) {
                return;
            }
            var btn = ev.target && ev.target.closest ? ev.target.closest('[data-nav-group-toggle]') : null;
            if (!btn || !side.contains(btn)) {
                return;
            }
            if (ev.target.closest && ev.target.closest('a.rateb-nav-link')) {
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
                hydrateNavLazy(group);
            }
            var open = group.classList.toggle('is-open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }, true);
    }

    function initTableSearch() {
        var isAr = document.documentElement.lang === 'ar';
        var placeholder = isAr ? 'بحث في الجدول…' : 'Search table…';
        var noResults = isAr ? 'لا توجد نتائج مطابقة' : 'No matching rows';
        var resultsLabel = isAr ? 'نتيجة' : 'results';

        function isEmptyStateRow(tr) {
            return tr.querySelectorAll('td').length === 1 && tr.querySelector('td[colspan]') !== null;
        }

        function attachSearch(wrapEl, table) {
            if (!wrapEl || !table || table.getAttribute('data-rateb-search-bound') === '1') {
                return;
            }
            table.setAttribute('data-rateb-search-bound', '1');

            var input = wrapEl.querySelector('[data-rateb-table-search-field], input[type="search"]');
            if (!input) {
                return;
            }

            var clearBtn = wrapEl.querySelector('[data-rateb-table-search-clear]');
            var meta = wrapEl.querySelector('[data-rateb-search-meta]');
            var tbody = table.querySelector('tbody');
            if (!tbody) {
                return;
            }

            var emptyRow = null;

            function filterRows() {
                var q = input.value.trim().toLowerCase();
                var visible = 0;
                var dataRows = 0;

                if (emptyRow) {
                    emptyRow.remove();
                    emptyRow = null;
                }

                tbody.querySelectorAll('tr').forEach(function (tr) {
                    if (tr.getAttribute('data-rateb-search-empty') === '1') {
                        return;
                    }
                    if (isEmptyStateRow(tr)) {
                        tr.style.display = q === '' ? '' : 'none';
                        return;
                    }
                    dataRows++;
                    var text = (tr.textContent || '').toLowerCase();
                    var show = q === '' || text.indexOf(q) !== -1;
                    tr.style.display = show ? '' : 'none';
                    if (show) {
                        visible++;
                    }
                });

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

            input.addEventListener('input', filterRows);
            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    input.value = '';
                    filterRows();
                    input.focus();
                });
            }
        }

        document.querySelectorAll('[data-rateb-table-search-wrap]:not([data-rateb-server-search="1"])').forEach(function (wrap) {
            var host = wrap.nextElementSibling;
            var table = host && host.querySelector ? host.querySelector('table.rateb-table') : null;
            if (!table && wrap.parentElement) {
                table = wrap.parentElement.querySelector('table.rateb-table');
            }
            attachSearch(wrap, table);
        });

        document.querySelectorAll('table.rateb-table').forEach(function (table) {
            if (table.getAttribute('data-rateb-search-skip') === '1') {
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
            attachSearch(wrap, table);
        });
    }

    function bootAppUi() {
        if (document.documentElement.getAttribute('data-rateb-app-ui-booted') === '1') {
            return;
        }
        document.documentElement.setAttribute('data-rateb-app-ui-booted', '1');

        var toggle = document.getElementById('rateb-sidebar-toggle');
        var sidebar = document.getElementById('rateb-sidebar');
        if (toggle && sidebar) {
            toggle.addEventListener('click', function () {
                sidebar.classList.toggle('open');
            });
        }

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
        if (window.RatebApp && typeof window.RatebApp.reinit === 'function') {
            window.RatebApp.reinit();
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
