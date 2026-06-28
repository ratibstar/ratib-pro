(function () {
    'use strict';

    var SKIP_TABLE_SEL = '.rateb-line-items-table, .rateb-po-print-table, .rateb-matrix-table, [data-journal-lines-table]';

    function isSkippedTable(table) {
        if (!table || table.matches(SKIP_TABLE_SEL)) {
            return true;
        }
        if (table.closest('.rateb-line-items-wrap, .rateb-table-no-scroll')) {
            return true;
        }
        return false;
    }

    function isSkippedWrap(wrap) {
        if (!wrap) {
            return true;
        }
        if (wrap.classList && (wrap.classList.contains('rateb-line-items-wrap') || wrap.classList.contains('rateb-table-no-scroll'))) {
            return true;
        }
        if (wrap.querySelector && wrap.querySelector(SKIP_TABLE_SEL)) {
            return true;
        }
        return false;
    }

    function findTable(wrap) {
        if (wrap && wrap.tagName === 'TABLE') {
            return wrap;
        }
        return wrap ? wrap.querySelector('table.rateb-table, table.table') : null;
    }

    function resolveHost(el) {
        if (el.tagName === 'TABLE') {
            return isSkippedTable(el) ? null : { wrap: el.parentElement, table: el, bare: true };
        }
        if (isSkippedWrap(el)) {
            return null;
        }
        var table = findTable(el);
        return table && !isSkippedTable(table) ? { wrap: el, table: table, bare: false } : null;
    }

    function cellText(cell) {
        if (!cell) {
            return '';
        }
        var clone = cell.cloneNode(true);
        clone.querySelectorAll('.rateb-actions, .rateb-actions-cell, input, button, form, .btn').forEach(function (el) {
            el.remove();
        });
        return (clone.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function tableToCsv(table) {
        var rows = [];
        table.querySelectorAll('tr').forEach(function (tr) {
            if (tr.offsetParent === null && tr.closest('thead') === null) {
                return;
            }
            var cells = [];
            tr.querySelectorAll('th, td').forEach(function (td) {
                if (td.classList.contains('rateb-actions-cell') || td.classList.contains('rateb-actions')) {
                    return;
                }
                var text = cellText(td).replace(/"/g, '""');
                cells.push('"' + text + '"');
            });
            if (cells.length) {
                rows.push(cells.join(','));
            }
        });
        return '\uFEFF' + rows.join('\n');
    }

    function downloadCsv(table, filename) {
        var blob = new Blob([tableToCsv(table)], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = (filename || 'export') + '.csv';
        link.click();
        URL.revokeObjectURL(link.href);
    }

    function printTable(table, title) {
        var w = window.open('', '_blank', 'noopener,noreferrer');
        if (!w) {
            return;
        }
        var dir = document.documentElement.getAttribute('dir') || 'rtl';
        var html = '<!DOCTYPE html><html dir="' + dir + '"><head><meta charset="UTF-8"><title>'
            + (title || 'Print') + '</title>'
            + '<style>body{font-family:Tajawal,Segoe UI,Tahoma,sans-serif;padding:16px;color:#1a3354}'
            + 'table{border-collapse:collapse;width:100%;font-size:12px}'
            + 'th,td{border:1px solid #ccc;padding:6px 8px;text-align:start}'
            + 'th{background:#e8f1fb}@media print{body{padding:0}}</style></head><body>';
        if (title) {
            html += '<h2 style="margin:0 0 12px;font-size:18px">' + title + '</h2>';
        }
        var clone = table.cloneNode(true);
        clone.querySelectorAll('.rateb-actions, .rateb-actions-cell, input, button, form').forEach(function (el) {
            el.remove();
        });
        html += clone.outerHTML + '</body></html>';
        w.document.open();
        w.document.write(html);
        w.document.close();
        w.focus();
        w.print();
    }

    function bindToolbar(toolbar, ctx) {
        var table = ctx.table || findTable(ctx.wrap);
        if (!table) {
            return;
        }
        var source = ctx.bare ? ctx.table : ctx.wrap;
        var title = toolbar.getAttribute('data-table-title')
            || (source && source.getAttribute('data-table-title'))
            || document.title;
        toolbar.querySelectorAll('[data-rateb-table-print]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                printTable(table, title);
            });
        });
        toolbar.querySelectorAll('[data-rateb-table-csv]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                downloadCsv(table, title.replace(/[^\w\-]+/g, '_').slice(0, 40) || 'table');
            });
        });
    }

    function appendServerExportLinks(toolbar, exportRoute) {
        if (!exportRoute || toolbar.querySelector('[data-rateb-server-export]')) {
            return;
        }
        var links = document.createElement('div');
        links.className = 'd-flex flex-wrap gap-2';
        links.setAttribute('data-rateb-server-export', '1');
        ['csv', 'excel', 'pdf'].forEach(function (fmt) {
            var a = document.createElement('a');
            a.className = 'btn btn-sm btn-outline-secondary';
            a.href = exportRoute + (exportRoute.indexOf('?') >= 0 ? '&' : '?') + 'format=' + fmt;
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.innerHTML = fmt === 'pdf' ? '<i class="fas fa-file-pdf"></i>' : (fmt === 'excel' ? '<i class="fas fa-file-excel"></i>' : '<i class="fas fa-file-csv"></i>');
            links.appendChild(a);
        });
        toolbar.firstElementChild.appendChild(links);
    }

    function ensureToolbar(ctx) {
        var host = ctx.bare ? ctx.table : ctx.wrap;
        if (host.dataset.ratebToolsInit === '1') {
            return;
        }
        host.dataset.ratebToolsInit = '1';
        if (!ctx.bare) {
            host.setAttribute('data-rateb-table-root', '1');
        }

        var prev = ctx.bare ? ctx.table.previousElementSibling : ctx.wrap.previousElementSibling;
        if (prev && prev.classList.contains('rateb-table-toolbar')) {
            bindToolbar(prev, ctx);
            return;
        }

        var toolbar = document.createElement('div');
        toolbar.className = 'rateb-table-toolbar d-flex flex-wrap align-items-center justify-content-between gap-2 px-2 py-2 border-bottom';
        toolbar.innerHTML = ''
            + '<div class="d-flex flex-wrap gap-2 align-items-center">'
            + '<button type="button" class="btn btn-sm btn-outline-secondary" data-rateb-table-print><i class="fas fa-print"></i> '
            + '</button>'
            + '<button type="button" class="btn btn-sm btn-outline-success" data-rateb-table-csv><i class="fas fa-file-csv"></i> CSV</button>'
            + '</div>';

        var exportRoute = (ctx.bare ? ctx.table : ctx.wrap).getAttribute('data-export-route') || '';
        appendServerExportLinks(toolbar, exportRoute);

        if (ctx.bare) {
            ctx.table.parentElement.insertBefore(toolbar, ctx.table);
        } else {
            ctx.wrap.parentElement.insertBefore(toolbar, ctx.wrap);
        }
        bindToolbar(toolbar, ctx);
    }

    function collectHosts() {
        var hosts = [];
        var seen = new Set();

        function add(el) {
            if (!el || seen.has(el)) {
                return;
            }
            seen.add(el);
            hosts.push(el);
        }

        document.querySelectorAll('.rateb-table-wrap, .table-responsive, .rateb-oversight-table-wrap, .rateb-card-body.table-responsive').forEach(add);
        document.querySelectorAll('.rateb-card-body > table.rateb-table, .rateb-card-body > table.table').forEach(add);
        document.querySelectorAll('.rateb-content > table.table, .rateb-main > table.table').forEach(add);

        return hosts;
    }

    function init() {
        document.querySelectorAll('.rateb-table-toolbar').forEach(function (toolbar) {
            var wrap = toolbar.nextElementSibling;
            if (!wrap) {
                return;
            }
            var ctx = resolveHost(wrap.tagName === 'TABLE' ? wrap : wrap);
            if (ctx) {
                bindToolbar(toolbar, ctx);
            }
        });

        collectHosts().forEach(function (el) {
            var ctx = resolveHost(el);
            if (!ctx) {
                return;
            }
            var prev = ctx.bare ? ctx.table.previousElementSibling : ctx.wrap.previousElementSibling;
            if (prev && prev.classList.contains('rateb-table-toolbar')) {
                bindToolbar(prev, ctx);
                return;
            }
            ensureToolbar(ctx);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
