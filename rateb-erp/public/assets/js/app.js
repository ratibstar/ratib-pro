(function () {
    'use strict';

    function initBulkTables() {
        document.querySelectorAll('[data-rateb-bulk-table="1"]').forEach(function (table) {
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
                    form.addEventListener('submit', function (e) {
                        var ids = selectedIds();
                        if (ids.length === 0) {
                            e.preventDefault();
                            return;
                        }
                        var msg = form.getAttribute('data-confirm-delete');
                        if (msg && !window.confirm(msg)) {
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

    function initSidebarNavGroups() {
        document.querySelectorAll('[data-nav-group-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var group = btn.closest('[data-nav-group]');
                if (!group) {
                    return;
                }
                var open = group.classList.toggle('is-open');
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
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

        document.querySelectorAll('form[data-confirm-delete]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                var msg = form.getAttribute('data-confirm-delete') || 'Delete?';
                if (!window.confirm(msg)) {
                    e.preventDefault();
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
        initPermissionMatrix();
        initSidebarNavGroups();
    });
})();
