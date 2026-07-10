<?php
require __DIR__ . '/config/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/functions.php';
requireLogin();

$pageTitle = 'Yearly Report';
$activeNav = 'yearly';
$year = (int)($_GET['year'] ?? date('Y'));
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Yearly Report</h1>
        <p>Month-by-month totals and category breakdown for the year.</p>
    </div>
    <div class="filter-bar" style="margin:0;">
        <div class="field">
            <label for="r_year">Year</label>
            <select id="r_year">
                <?php for ($y = date('Y') + 1; $y >= date('Y') - 5; $y--): ?>
                <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
    </div>
</div>

<div class="panel">
    <div class="stat-grid">
        <div class="stat-box"><div class="label">Total Income</div><div class="value" id="s_income">--</div></div>
        <div class="stat-box"><div class="label">Total Expense</div><div class="value" id="s_expense">--</div></div>
        <div class="stat-box"><div class="label">Net for Year</div><div class="value" id="s_net">--</div></div>
        <div class="stat-box"><div class="label">Total Transferred</div><div class="value" id="s_transfer">--</div></div>
    </div>
</div>

<div class="panel">
    <h2>Month by month</h2>
    <div class="table-wrap">
        <table class="ledger">
            <thead><tr><th>Month</th><th style="text-align:right;">Income</th><th style="text-align:right;">Expense</th><th style="text-align:right;">Net</th></tr></thead>
            <tbody id="monthsBody"></tbody>
        </table>
    </div>
</div>

<div class="panel-row">
    <div class="panel">
        <h2>Income by category (year)</h2>
        <div id="incomeBars"><p class="muted">No income recorded this year.</p></div>
    </div>
    <div class="panel">
        <h2>Expense by category (year)</h2>
        <div id="expenseBars"><p class="muted">No expenses recorded this year.</p></div>
    </div>
</div>

<script>
function updateUrl(year) {
    const url = new URL(window.location);
    url.searchParams.set('year', year);
    window.history.replaceState({}, '', url);
}

function renderBars(container, rows, total, cls) {
    if (!rows.length) { container.innerHTML = '<p class="muted">Nothing recorded this year.</p>'; return; }
    container.innerHTML = rows.map(r => {
        const pct = total > 0 ? (r.total / total * 100) : 0;
        return `<div class="bar-row">
            <div class="bar-label">${r.category || 'Uncategorized'}</div>
            <div class="bar-track"><div class="bar-fill ${cls}" style="width:${pct.toFixed(1)}%"></div></div>
            <div class="bar-value">${fmtMoney(r.total)}</div>
        </div>`;
    }).join('');
}

async function loadYearly() {
    const year = document.getElementById('r_year').value;
    updateUrl(year);
    const data = await apiFetch(`api/get_report.php?scope=yearly&year=${year}`);
    if (!data.success) { showToast('Could not load report.', true); return; }

    document.getElementById('s_income').textContent = fmtMoney(data.totals.income);
    document.getElementById('s_expense').textContent = fmtMoney(data.totals.expense);
    const netEl = document.getElementById('s_net');
    netEl.textContent = fmtMoney(data.totals.net);
    netEl.style.color = data.totals.net < 0 ? 'var(--rust-600)' : 'var(--sage-700)';
    document.getElementById('s_transfer').textContent = fmtMoney(data.totals.transfer);

    const tbody = document.getElementById('monthsBody');
    tbody.innerHTML = '';
    data.months.forEach(m => {
        const netClass = m.net < 0 ? 'expense' : 'income';
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${m.month_name}</td>
            <td class="amount income">${fmtMoney(m.income)}</td>
            <td class="amount expense">${fmtMoney(m.expense)}</td>
            <td class="amount ${netClass}">${fmtMoney(m.net)}</td>`;
        tbody.appendChild(tr);
    });

    renderBars(document.getElementById('incomeBars'), data.income_by_category, data.totals.income, 'income');
    renderBars(document.getElementById('expenseBars'), data.expense_by_category, data.totals.expense, 'expense');
}

document.getElementById('r_year').addEventListener('change', loadYearly);
loadYearly();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
