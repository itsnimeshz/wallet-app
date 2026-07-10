<?php
require __DIR__ . '/config/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/functions.php';
requireLogin();

$pageTitle = 'Monthly Report';
$activeNav = 'monthly';
$year = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Monthly Report</h1>
        <p>Income vs expenses for the selected month.</p>
    </div>
    <div class="filter-bar" style="margin:0;">
        <div class="field">
            <label for="r_month">Month</label>
            <select id="r_month">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= monthName($m) ?></option>
                <?php endfor; ?>
            </select>
        </div>
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
    <div class="stat-grid" id="statGrid">
        <div class="stat-box"><div class="label">Income</div><div class="value" id="s_income">--</div></div>
        <div class="stat-box"><div class="label">Expense</div><div class="value" id="s_expense">--</div></div>
        <div class="stat-box"><div class="label">Net</div><div class="value" id="s_net">--</div></div>
        <div class="stat-box"><div class="label">Transferred</div><div class="value" id="s_transfer">--</div></div>
    </div>
</div>

<div class="panel-row">
    <div class="panel">
        <h2>Income by category</h2>
        <div id="incomeBars"><p class="muted">No income recorded this month.</p></div>
    </div>
    <div class="panel">
        <h2>Expense by category</h2>
        <div id="expenseBars"><p class="muted">No expenses recorded this month.</p></div>
    </div>
</div>

<div class="panel">
    <h2>Transactions this month</h2>
    <div class="table-wrap">
        <table class="ledger">
            <thead><tr><th>Date</th><th>Type</th><th>Category</th><th>Account</th><th>Description</th><th style="text-align:right;">Amount</th></tr></thead>
            <tbody id="monthRecords"><tr><td colspan="6" class="empty-state">Loading…</td></tr></tbody>
        </table>
    </div>
</div>

<script>
const ACCOUNTS = <?= json_encode(ACCOUNTS) ?>;

function updateUrl(year, month) {
    const url = new URL(window.location);
    url.searchParams.set('year', year);
    url.searchParams.set('month', month);
    window.history.replaceState({}, '', url);
}

function renderBars(container, rows, total, cls) {
    if (!rows.length) {
        container.innerHTML = '<p class="muted">Nothing recorded this month.</p>';
        return;
    }
    container.innerHTML = rows.map(r => {
        const pct = total > 0 ? (r.total / total * 100) : 0;
        return `<div class="bar-row">
            <div class="bar-label">${r.category || 'Uncategorized'}</div>
            <div class="bar-track"><div class="bar-fill ${cls}" style="width:${pct.toFixed(1)}%"></div></div>
            <div class="bar-value">${fmtMoney(r.total)}</div>
        </div>`;
    }).join('');
}

async function loadMonthly() {
    const year = document.getElementById('r_year').value;
    const month = document.getElementById('r_month').value;
    updateUrl(year, month);

    const data = await apiFetch(`api/get_report.php?scope=monthly&year=${year}&month=${month}`);
    if (!data.success) { showToast('Could not load report.', true); return; }

    document.getElementById('s_income').textContent = fmtMoney(data.totals.income);
    document.getElementById('s_expense').textContent = fmtMoney(data.totals.expense);
    const netEl = document.getElementById('s_net');
    netEl.textContent = fmtMoney(data.totals.net);
    netEl.style.color = data.totals.net < 0 ? 'var(--rust-600)' : 'var(--sage-700)';
    document.getElementById('s_transfer').textContent = fmtMoney(data.totals.transfer);

    renderBars(document.getElementById('incomeBars'), data.income_by_category, data.totals.income, 'income');
    renderBars(document.getElementById('expenseBars'), data.expense_by_category, data.totals.expense, 'expense');

    const tbody = document.getElementById('monthRecords');
    tbody.innerHTML = '';
    if (!data.records.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No transactions this month.</td></tr>';
        return;
    }
    data.records.forEach(r => {
        let accountLabel, amountClass, sign;
        if (r.type === 'transfer') {
            accountLabel = `${ACCOUNTS[r.from_account]} → ${ACCOUNTS[r.to_account]}`;
            amountClass = 'transfer'; sign = '';
        } else {
            accountLabel = ACCOUNTS[r.account] || '';
            amountClass = r.type; sign = r.type === 'expense' ? '-' : '+';
        }
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${r.record_date}</td><td><span class="tag ${r.type}">${r.type}</span></td>
            <td>${r.category||''}</td><td>${accountLabel}</td>
            <td class="muted">${(r.description||'').replace(/</g,'&lt;')}</td>
            <td class="amount ${amountClass}">${sign}${fmtMoney(r.amount)}</td>`;
        tbody.appendChild(tr);
    });
}

document.getElementById('r_month').addEventListener('change', loadMonthly);
document.getElementById('r_year').addEventListener('change', loadMonthly);
loadMonthly();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
