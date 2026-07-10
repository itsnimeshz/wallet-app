<?php
require __DIR__ . '/config/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/functions.php';
requireLogin();

$pageTitle = 'Records';
$activeNav = 'records';
$categories = loadCategories();
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Records</h1>
        <p>Every income, expense, and transfer. All records are fully editable.</p>
    </div>
    <label class="trash-toggle">
        <input type="checkbox" id="trashToggle"> Show Trash
    </label>
</div>

<div class="panel">
    <div class="filter-bar">
        <div class="field">
            <label for="f_type">Type</label>
            <select id="f_type">
                <option value="">All</option>
                <option value="income">Income</option>
                <option value="expense">Expense</option>
                <option value="transfer">Transfer</option>
            </select>
        </div>
        <div class="field">
            <label for="f_account">Account</label>
            <select id="f_account">
                <option value="">All</option>
                <option value="cash">Cash</option>
                <option value="bank">Bank</option>
            </select>
        </div>
        <div class="field">
            <label for="f_category">Category</label>
            <select id="f_category"><option value="">All</option></select>
        </div>
        <div class="field">
            <label for="f_from">From date</label>
            <input type="date" id="f_from">
        </div>
        <div class="field">
            <label for="f_to">To date</label>
            <input type="date" id="f_to">
        </div>
        <div class="field" style="flex:1; min-width:180px;">
            <label for="f_q">Search</label>
            <input type="text" id="f_q" placeholder="Description or category">
        </div>
        <div class="field">
            <button class="btn btn-secondary" id="clearFilters" type="button">Clear</button>
        </div>
    </div>

    <div class="table-wrap">
        <table class="ledger">
            <thead>
                <tr>
                    <th>Date</th><th>Type</th><th>Category</th><th>Account</th>
                    <th>Description</th><th style="text-align:right;">Amount</th><th>Actions</th>
                </tr>
            </thead>
            <tbody id="recordsBody"><tr><td colspan="7" class="empty-state">Loading…</td></tr></tbody>
        </table>
    </div>
    <div class="pagination" id="pagination"></div>
</div>

<!-- Edit modal -->
<div class="modal-overlay" id="editOverlay">
    <div class="modal">
        <h2>Edit record</h2>
        <div id="editErrors"></div>
        <form id="editForm">
            <input type="hidden" id="edit_id">
            <div class="type-toggle" id="editTypeToggle">
                <button type="button" data-type="income">Income</button>
                <button type="button" data-type="expense">Expense</button>
                <button type="button" data-type="transfer">Transfer</button>
            </div>
            <div class="form-grid" style="margin-top:14px;">
                <div class="field" id="edit_accountField">
                    <label for="edit_account">Account</label>
                    <select id="edit_account"><option value="cash">Cash</option><option value="bank">Bank</option></select>
                </div>
                <div class="field" id="edit_fromField" style="display:none;">
                    <label for="edit_from_account">From</label>
                    <select id="edit_from_account"><option value="cash">Cash</option><option value="bank">Bank</option></select>
                </div>
                <div class="field" id="edit_toField" style="display:none;">
                    <label for="edit_to_account">To</label>
                    <select id="edit_to_account"><option value="bank">Bank</option><option value="cash">Cash</option></select>
                </div>
                <div class="field" id="edit_categoryField">
                    <label for="edit_category">Category</label>
                    <select id="edit_category"></select>
                </div>
                <div class="field">
                    <label for="edit_amount">Amount</label>
                    <input type="number" step="0.01" min="0.01" id="edit_amount" required>
                </div>
                <div class="field">
                    <label for="edit_record_date">Date</label>
                    <input type="date" id="edit_record_date" required>
                </div>
            </div>
            <div class="field" style="margin-top:14px;">
                <label for="edit_description">Description</label>
                <textarea id="edit_description" rows="2"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="editCancel">Cancel</button>
                <button type="submit" class="btn btn-primary">Save changes</button>
            </div>
        </form>
    </div>
</div>

<script>
const CATEGORIES = <?= json_encode($categories) ?>;
const ACCOUNTS = <?= json_encode(ACCOUNTS) ?>;
let currentPage = 1;
let isTrash = false;

function allCategoryOptions() {
    return [...new Set([...(CATEGORIES.income||[]), ...(CATEGORIES.expense||[])])];
}
allCategoryOptions().forEach(c => {
    const opt = document.createElement('option'); opt.value = c; opt.textContent = c;
    document.getElementById('f_category').appendChild(opt);
});

function typeTag(t) { return `<span class="tag ${t}">${t}</span>`; }

function buildQuery() {
    const p = new URLSearchParams();
    p.set('trash', isTrash ? '1' : '0');
    p.set('page', currentPage);
    const type = document.getElementById('f_type').value;
    const account = document.getElementById('f_account').value;
    const category = document.getElementById('f_category').value;
    const from = document.getElementById('f_from').value;
    const to = document.getElementById('f_to').value;
    const q = document.getElementById('f_q').value;
    if (type) p.set('type', type);
    if (account) p.set('account', account);
    if (category) p.set('category', category);
    if (from) p.set('from', from);
    if (to) p.set('to', to);
    if (q) p.set('q', q);
    return p.toString();
}

async function loadRecords() {
    const data = await apiFetch('api/get_records.php?' + buildQuery());
    const tbody = document.getElementById('recordsBody');
    tbody.innerHTML = '';
    if (!data.records || !data.records.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="empty-state">${isTrash ? 'Trash is empty.' : 'No records match your filters.'}</td></tr>`;
        renderPagination(0, 1);
        return;
    }
    data.records.forEach(r => {
        const tr = document.createElement('tr');
        let accountLabel, amountClass, sign;
        if (r.type === 'transfer') {
            accountLabel = `${ACCOUNTS[r.from_account]} → ${ACCOUNTS[r.to_account]}`;
            amountClass = 'transfer'; sign = '';
        } else {
            accountLabel = ACCOUNTS[r.account] || '';
            amountClass = r.type; sign = r.type === 'expense' ? '-' : '+';
        }
        let actions;
        if (isTrash) {
            actions = `
                <button class="btn btn-secondary btn-small" onclick="restoreRecord(${r.id})">Restore</button>
                <button class="btn btn-danger btn-small" onclick="permanentDelete(${r.id})">Delete forever</button>`;
        } else {
            actions = `
                <button class="btn btn-secondary btn-small" onclick='openEdit(${JSON.stringify(r)})'>Edit</button>
                <button class="btn btn-danger btn-small" onclick="softDelete(${r.id})">Delete</button>`;
        }
        tr.innerHTML = `
            <td>${r.record_date}</td>
            <td>${typeTag(r.type)}</td>
            <td>${r.category || ''}</td>
            <td>${accountLabel}</td>
            <td class="muted">${(r.description||'').replace(/</g,'&lt;')}</td>
            <td class="amount ${amountClass}">${sign}${fmtMoney(r.amount)}</td>
            <td class="row-actions">${actions}</td>
        `;
        tbody.appendChild(tr);
    });
    renderPagination(data.total_pages, data.page);
}

function renderPagination(totalPages, page) {
    const el = document.getElementById('pagination');
    el.innerHTML = '';
    if (totalPages <= 1) return;
    const mk = (label, p, disabled, active) => {
        const b = document.createElement('button');
        b.textContent = label; b.disabled = !!disabled;
        if (active) b.classList.add('active');
        b.addEventListener('click', () => { currentPage = p; loadRecords(); });
        return b;
    };
    el.appendChild(mk('‹ Prev', page - 1, page <= 1));
    for (let i = 1; i <= totalPages; i++) el.appendChild(mk(i, i, false, i === page));
    el.appendChild(mk('Next ›', page + 1, page >= totalPages));
}

['f_type','f_account','f_category','f_from','f_to'].forEach(id => {
    document.getElementById(id).addEventListener('change', () => { currentPage = 1; loadRecords(); });
});
let searchTimer;
document.getElementById('f_q').addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { currentPage = 1; loadRecords(); }, 350);
});
document.getElementById('clearFilters').addEventListener('click', () => {
    ['f_type','f_account','f_category','f_from','f_to','f_q'].forEach(id => document.getElementById(id).value = '');
    currentPage = 1; loadRecords();
});
document.getElementById('trashToggle').addEventListener('change', (e) => {
    isTrash = e.target.checked; currentPage = 1; loadRecords();
});

async function softDelete(id) {
    if (!confirm('Move this record to Trash?')) return;
    const res = await postJSON('api/delete_record.php', { id, mode: 'soft' });
    if (res.success) { showToast('Moved to Trash.'); refreshBalances(); loadRecords(); }
    else showToast(res.error || 'Could not delete.', true);
}
async function restoreRecord(id) {
    const res = await postJSON('api/restore_record.php', { id });
    if (res.success) { showToast('Record restored.'); refreshBalances(); loadRecords(); }
    else showToast(res.error || 'Could not restore.', true);
}
async function permanentDelete(id) {
    if (!confirm('Permanently delete this record? This cannot be undone.')) return;
    const res = await postJSON('api/delete_record.php', { id, mode: 'permanent' });
    if (res.success) { showToast('Record permanently deleted.'); refreshBalances(); loadRecords(); }
    else showToast(res.error || 'Could not delete.', true);
}

// ---- Edit modal ----
function populateEditCategories(type) {
    const sel = document.getElementById('edit_category');
    sel.innerHTML = '';
    (CATEGORIES[type] || []).forEach(c => {
        const opt = document.createElement('option'); opt.value = c; opt.textContent = c;
        sel.appendChild(opt);
    });
}
document.querySelectorAll('#editTypeToggle button').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('#editTypeToggle button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const type = btn.dataset.type;
        const isTransfer = type === 'transfer';
        document.getElementById('edit_accountField').style.display = isTransfer ? 'none' : '';
        document.getElementById('edit_categoryField').style.display = isTransfer ? 'none' : '';
        document.getElementById('edit_fromField').style.display = isTransfer ? '' : 'none';
        document.getElementById('edit_toField').style.display = isTransfer ? '' : 'none';
        if (!isTransfer) populateEditCategories(type);
    });
});

function openEdit(r) {
    document.getElementById('edit_id').value = r.id;
    document.querySelectorAll('#editTypeToggle button').forEach(b => {
        b.classList.toggle('active', b.dataset.type === r.type);
    });
    const isTransfer = r.type === 'transfer';
    document.getElementById('edit_accountField').style.display = isTransfer ? 'none' : '';
    document.getElementById('edit_categoryField').style.display = isTransfer ? 'none' : '';
    document.getElementById('edit_fromField').style.display = isTransfer ? '' : 'none';
    document.getElementById('edit_toField').style.display = isTransfer ? '' : 'none';

    if (!isTransfer) {
        populateEditCategories(r.type);
        document.getElementById('edit_account').value = r.account || 'cash';
        document.getElementById('edit_category').value = r.category || '';
    } else {
        document.getElementById('edit_from_account').value = r.from_account || 'cash';
        document.getElementById('edit_to_account').value = r.to_account || 'bank';
    }
    document.getElementById('edit_amount').value = r.amount;
    document.getElementById('edit_record_date').value = r.record_date;
    document.getElementById('edit_description').value = r.description || '';
    document.getElementById('editErrors').innerHTML = '';
    document.getElementById('editOverlay').classList.add('open');
}
document.getElementById('editCancel').addEventListener('click', () => {
    document.getElementById('editOverlay').classList.remove('open');
});
document.getElementById('editForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const type = document.querySelector('#editTypeToggle button.active').dataset.type;
    const payload = {
        id: document.getElementById('edit_id').value,
        type,
        account: document.getElementById('edit_account').value,
        from_account: document.getElementById('edit_from_account').value,
        to_account: document.getElementById('edit_to_account').value,
        category: document.getElementById('edit_category').value,
        amount: document.getElementById('edit_amount').value,
        record_date: document.getElementById('edit_record_date').value,
        description: document.getElementById('edit_description').value,
    };
    const res = await postJSON('api/update_record.php', payload);
    if (res.success) {
        showToast('Record updated.');
        document.getElementById('editOverlay').classList.remove('open');
        refreshBalances();
        loadRecords();
    } else {
        const msgs = res.errors || [res.error || 'Something went wrong.'];
        document.getElementById('editErrors').innerHTML = '<ul class="alert-list">' + msgs.map(m => `<li>${m}</li>`).join('') + '</ul>';
    }
});

loadRecords();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
