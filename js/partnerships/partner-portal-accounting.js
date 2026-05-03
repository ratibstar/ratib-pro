/**
 * Partner portal — read-only account statement (same Ratib Pro GL as staff).
 */
(function () {
    /** @type {{ destroy?: () => void } | null} */
    let chartInst = null;

    function escapeHtml(s) {
        if (s == null) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatMoneyAmount(n) {
        const x = Number(n);
        if (Number.isNaN(x)) return '—';
        return `${x.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} SAR`;
    }

    function destroyChartInstanceOnly() {
        if (chartInst && typeof chartInst.destroy === 'function') {
            chartInst.destroy();
        }
        chartInst = null;
    }

    function destroyChart() {
        destroyChartInstanceOnly();
        const wrap = document.getElementById('ppAcctChartWrap');
        if (wrap) {
            wrap.classList.add('is-hidden');
            wrap.hidden = true;
        }
    }

    function renderChart(monthRows) {
        const wrap = document.getElementById('ppAcctChartWrap');
        const canvas = document.getElementById('ppAcctChart');
        if (!wrap || !canvas) return;
        if (typeof Chart === 'undefined') {
            wrap.classList.add('is-hidden');
            wrap.hidden = true;
            return;
        }
        const rows = Array.isArray(monthRows) ? monthRows : [];
        if (rows.length === 0) {
            destroyChart();
            return;
        }
        wrap.classList.remove('is-hidden');
        wrap.hidden = false;
        destroyChartInstanceOnly();
        const labels = rows.map((r) => (r && r.label) || (r && r.key) || '');
        const debits = rows.map((r) => (Number(r && r.debit) ? Number(r.debit) : 0));
        const credits = rows.map((r) => (Number(r && r.credit) ? Number(r.credit) : 0));
        chartInst = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Debit (SAR)',
                        data: debits,
                        backgroundColor: 'rgba(45, 212, 191, 0.65)',
                        borderColor: 'rgba(45, 212, 191, 1)',
                        borderWidth: 1,
                    },
                    {
                        label: 'Credit (SAR)',
                        data: credits,
                        backgroundColor: 'rgba(99, 102, 241, 0.55)',
                        borderColor: 'rgba(165, 180, 252, 1)',
                        borderWidth: 1,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: '#cbd5e1' } },
                    title: {
                        display: true,
                        text: 'Posted activity by month (English)',
                        color: '#e2e8f0',
                        font: { size: 14 },
                    },
                },
                scales: {
                    x: {
                        ticks: { color: '#94a3b8' },
                        grid: { color: 'rgba(148, 163, 184, 0.12)' },
                    },
                    y: {
                        ticks: { color: '#94a3b8' },
                        grid: { color: 'rgba(148, 163, 184, 0.12)' },
                    },
                },
            },
        });
    }

    function showError(msg) {
        const el = document.getElementById('ppAcctError');
        if (el) {
            el.textContent = msg;
            el.classList.remove('is-hidden');
            el.hidden = false;
        }
    }

    function clearError() {
        const el = document.getElementById('ppAcctError');
        if (el) {
            el.textContent = '';
            el.classList.add('is-hidden');
            el.hidden = true;
        }
    }

    async function loadStatement() {
        const sub = document.getElementById('ppAcctSub');
        const sum = document.getElementById('ppAcctLinkSummary');
        const filters = document.getElementById('ppAcctFilters');
        const balances = document.getElementById('ppAcctBalances');
        const wrap = document.getElementById('ppAcctTableWrap');
        const tbody = document.getElementById('ppAcctTbody');
        const hintEl = document.getElementById('ppAcctHint');

        clearError();
        if (hintEl) {
            hintEl.classList.add('is-hidden');
            hintEl.hidden = true;
        }

        const startEl = document.getElementById('ppAcctStart');
        const endEl = document.getElementById('ppAcctEnd');
        const qs = new URLSearchParams();
        if (startEl && startEl.value) qs.set('start_date', startEl.value);
        if (endEl && endEl.value) qs.set('end_date', endEl.value);

        try {
            const res = await fetch(`../api/partnerships/partner-portal-account-statement.php?${qs.toString()}`, {
                credentials: 'same-origin',
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || json.success === false) {
                throw new Error(json.message || `Request failed (${res.status})`);
            }

            if (!json.linked) {
                destroyChart();
                if (filters) filters.hidden = true;
                if (balances) {
                    balances.classList.add('is-hidden');
                    balances.hidden = true;
                }
                if (wrap) {
                    wrap.classList.add('is-hidden');
                    wrap.hidden = true;
                }
                if (sum) {
                    sum.textContent =
                        json.message ||
                        'Not linked yet. Ask your office to connect this agency to the chart of accounts.';
                }
                if (sub) sub.textContent = 'Ledger not connected';
                if (hintEl && json.message) {
                    hintEl.textContent = json.message;
                    hintEl.classList.remove('is-hidden');
                    hintEl.hidden = false;
                }
                return;
            }

            const code = json.account_code || '';
            const aname = json.account_name || '';
            if (sum) {
                sum.textContent =
                    code && aname
                        ? `Linked to chart account ${code} — ${aname}. Same ledger as Ratib Pro accounting.`
                        : 'Linked to your office chart of accounts.';
            }
            if (sub) {
                sub.textContent = code ? `Chart code ${code} · read-only` : 'Read-only statement';
            }

            if (filters) filters.hidden = false;
            if (balances) {
                balances.innerHTML = `
            <div><span>Opening</span><span class="agency-accounting-balance-val">${escapeHtml(formatMoneyAmount(json.opening_balance))}</span></div>
            <div><span>Closing</span><span class="agency-accounting-balance-val">${escapeHtml(formatMoneyAmount(json.closing_balance))}</span></div>
            <div><span>Period debits</span><span class="agency-accounting-balance-val">${escapeHtml(formatMoneyAmount(json.total_debit))}</span></div>
            <div><span>Period credits</span><span class="agency-accounting-balance-val">${escapeHtml(formatMoneyAmount(json.total_credit))}</span></div>
        `;
                balances.classList.remove('is-hidden');
                balances.hidden = false;
            }

            const lines = Array.isArray(json.lines) ? json.lines : [];
            if (tbody) {
                tbody.innerHTML = lines
                    .map((row) => {
                        const d = escapeHtml(String(row.date || ''));
                        const ref = escapeHtml(String(row.reference || ''));
                        const desc = escapeHtml(String(row.description || ''));
                        const dr = escapeHtml(formatMoneyAmount(row.debit));
                        const cr = escapeHtml(formatMoneyAmount(row.credit));
                        const bal = escapeHtml(formatMoneyAmount(row.balance));
                        return `<tr><td>${d}</td><td>${ref}</td><td>${desc}</td><td class="num">${dr}</td><td class="num">${cr}</td><td class="num">${bal}</td></tr>`;
                    })
                    .join('');
            }
            if (wrap) {
                wrap.classList.remove('is-hidden');
                wrap.hidden = false;
            }

            renderChart(json.chart_by_month);
        } catch (e) {
            destroyChart();
            showError(e && e.message ? e.message : 'Could not load statement.');
        }
    }

    function initDefaultDates() {
        const startEl = document.getElementById('ppAcctStart');
        const endEl = document.getElementById('ppAcctEnd');
        if (startEl && !startEl.value) {
            const now = new Date();
            const start = new Date(now.getFullYear(), now.getMonth() - 11, 1);
            startEl.value = start.toISOString().slice(0, 10);
        }
        if (endEl && !endEl.value) {
            const now = new Date();
            const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            endEl.value = end.toISOString().slice(0, 10);
        }
    }

    function init() {
        initDefaultDates();
        const btn = document.getElementById('ppAcctRefreshBtn');
        if (btn) btn.addEventListener('click', () => loadStatement());
        loadStatement();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
