<?php
require __DIR__ . '/config/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/functions.php';
requireLogin();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$categories = loadCategories();
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <p>Today is <?= date('l, j F Y') ?>. Add a transaction or review recent activity below.</p>
    </div>
</div>

<div class="panel-row">
    <div class="panel">
        <h2>Add a record</h2>
        <div id="formErrors"></div>
        <form id="quickAddForm">
            <div class="type-toggle" id="typeToggle">
                <button type="button" data-type="income" class="active">Income</button>
                <button type="button" data-type="expense">Expense</button>
                <button type="button" data-type="transfer">Transfer</button>
            </div>
            <input type="hidden" name="type" id="typeField" value="income">

            <div class="form-grid" style="margin-top:14px;">
                <div class="field" id="accountField">
                    <label for="account">Account</label>
                    <select name="account" id="account">
                        <option value="cash">Cash</option>
                        <option value="bank">Bank</option>
                    </select>
                </div>
                <div class="field" id="fromField" style="display:none;">
                    <label for="from_account">From</label>
                    <select name="from_account" id="from_account">
                        <option value="cash">Cash</option>
                        <option value="bank">Bank</option>
                    </select>
                </div>
                <div class="field" id="toField" style="display:none;">
                    <label for="to_account">To</label>
                    <select name="to_account" id="to_account">
                        <option value="bank">Bank</option>
                        <option value="cash">Cash</option>
                    </select>
                </div>
                <div class="field" id="categoryField">
                    <label for="category">Category</label>
                    <select name="category" id="category"></select>
                </div>
                <div class="field">
                    <label for="amount">Amount</label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="amount" placeholder="0.00" required>
                </div>
                <div class="field">
                    <label for="record_date">Date</label>
                    <input type="date" name="record_date" id="record_date" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="field" style="margin-top:14px;">
                <label for="description">Description (optional)</label>
                <textarea name="description" id="description" rows="2" placeholder="e.g. Grocery run at NTUC"></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:14px;">Save record</button>
        </form>
    </div>

    <div class="panel">
        <h2>Recent activity</h2>
        <div class="table-wrap">
            <table class="ledger" id="recentTable">
                <thead>
                    <tr><th>Date</th><th>Type</th><th>Category</th><th>Account</th><th style="text-align:right;">Amount</th></tr>
                </thead>
                <tbody><tr><td colspan="5" class="empty-state">Loading…</td></tr></tbody>
            </table>
        </div>
        <div style="margin-top:12px;">
            <a href="records.php" class="btn btn-secondary btn-small">View all records →</a>
        </div>
    </div>
</div>

<script>
const CATEGORIES = <?= json_encode($categories) ?>;
const ACCOUNTS = <?= json_encode(ACCOUNTS) ?>;

function populateCategories(type) {
    const sel = document.getElementById('category');
    sel.innerHTML = '';
    const list = CATEGORIES[type] || [];
    list.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c; opt.textContent = c;
        sel.appendChild(opt);
    });
}

document.querySelectorAll('#typeToggle button').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('#typeToggle button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const type = btn.dataset.type;
        document.getElementById('typeField').value = type;

        const isTransfer = type === 'transfer';
        document.getElementById('accountField').style.display = isTransfer ? 'none' : '';
        document.getElementById('categoryField').style.display = isTransfer ? 'none' : '';
        document.getElementById('fromField').style.display = isTransfer ? '' : 'none';
        document.getElementById('toField').style.display = isTransfer ? '' : 'none';

        if (!isTransfer) populateCategories(type);
    });
});
populateCategories('income');

document.getElementById('quickAddForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const payload = {
        type: document.getElementById('typeField').value,
        account: document.getElementById('account').value,
        from_account: document.getElementById('from_account').value,
        to_account: document.getElementById('to_account').value,
        category: document.getElementById('category').value,
        amount: document.getElementById('amount').value,
        record_date: document.getElementById('record_date').value,
        description: document.getElementById('description').value,
    };
    const errBox = document.getElementById('formErrors');
    errBox.innerHTML = '';

    const res = await postJSON('api/add_record.php', payload);
    if (res.success) {
        showToast('Record saved.');
        form.reset();
        document.getElementById('record_date').value = new Date().toISOString().slice(0,10);
        populateCategories(document.getElementById('typeField').value);
        refreshBalances();
        loadRecent();
    } else {
        const msgs = res.errors || [res.error || 'Something went wrong.'];
        errBox.innerHTML = '<ul class="alert-list">' + msgs.map(m => `<li>${m}</li>`).join('') + '</ul>';
    }
});

function typeTag(t) {
    return `<span class="tag ${t}">${t}</span>`;
}

async function loadRecent() {
    const data = await apiFetch('api/get_records.php?page=1');
    const tbody = document.querySelector('#recentTable tbody');
    tbody.innerHTML = '';
    const rows = (data.records || []).slice(0, 10);
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="empty-state">No records yet. Add your first one above.</td></tr>';
        return;
    }
    rows.forEach(r => {
        const tr = document.createElement('tr');
        let accountLabel, amountClass, sign;
        if (r.type === 'transfer') {
            accountLabel = `${ACCOUNTS[r.from_account]} → ${ACCOUNTS[r.to_account]}`;
            amountClass = 'transfer'; sign = '';
        } else {
            accountLabel = ACCOUNTS[r.account] || '';
            amountClass = r.type; sign = r.type === 'expense' ? '-' : '+';
        }
        tr.innerHTML = `
            <td>${r.record_date}</td>
            <td>${typeTag(r.type)}</td>
            <td>${r.category || ''}</td>
            <td>${accountLabel}</td>
            <td class="amount ${amountClass}">${sign}${fmtMoney(r.amount)}</td>
        `;
        tbody.appendChild(tr);
    });
}
loadRecent();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
