<?php
require __DIR__ . '/config/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/functions.php';
requireLogin();

$pageTitle = 'Audit Log';
$activeNav = 'audit';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Audit Log</h1>
        <p>Every create, edit, delete, restore, and category change is recorded here.</p>
    </div>
</div>

<div class="panel">
    <div class="filter-bar">
        <div class="field">
            <label for="f_action">Action</label>
            <select id="f_action">
                <option value="">All</option>
                <option value="create">Create</option>
                <option value="update">Update</option>
                <option value="soft_delete">Soft delete</option>
                <option value="restore">Restore</option>
                <option value="permanent_delete">Permanent delete</option>
                <option value="categories_update">Category change</option>
            </select>
        </div>
        <div class="field">
            <label for="f_user">User</label>
            <select id="f_user">
                <option value="">All</option>
                <?php foreach (getUsers() as $uname => $u): ?>
                <option value="<?= htmlspecialchars($uname) ?>"><?= htmlspecialchars($u['display_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="table-wrap">
        <table class="ledger">
            <thead><tr><th>When</th><th>Action</th><th>Record</th><th>User</th><th>Details</th></tr></thead>
            <tbody id="auditBody"><tr><td colspan="5" class="empty-state">Loading…</td></tr></tbody>
        </table>
    </div>
    <div class="pagination" id="pagination"></div>
</div>

<script>
let currentPage = 1;
const ACTION_LABELS = {
    create: 'Created', update: 'Updated', soft_delete: 'Moved to Trash',
    restore: 'Restored', permanent_delete: 'Permanently deleted', categories_update: 'Categories changed'
};

function summarize(entry) {
    try {
        if (entry.action === 'update' && entry.old_data && entry.new_data) {
            const o = JSON.parse(entry.old_data), n = JSON.parse(entry.new_data);
            const diffs = [];
            ['type','account','from_account','to_account','category','amount','description','record_date'].forEach(f => {
                if (String(o[f]) !== String(n[f])) diffs.push(`${f}: "${o[f] ?? ''}" → "${n[f] ?? ''}"`);
            });
            return diffs.join('; ') || 'No field changes';
        }
        if (entry.action === 'create' && entry.new_data) {
            const n = JSON.parse(entry.new_data);
            return `${n.type} · ${n.category || (n.from_account+'→'+n.to_account)} · ${n.amount}`;
        }
        if ((entry.action === 'soft_delete' || entry.action === 'permanent_delete' || entry.action === 'restore') && (entry.old_data || entry.new_data)) {
            const d = JSON.parse(entry.old_data || entry.new_data);
            return `${d.type} · ${d.category || ''} · ${d.amount}`;
        }
        if (entry.action === 'categories_update') return 'Category list edited';
    } catch (e) { /* ignore parse issues */ }
    return '';
}

async function loadAudit() {
    const p = new URLSearchParams({ page: currentPage });
    const action = document.getElementById('f_action').value;
    const user = document.getElementById('f_user').value;
    if (action) p.set('action', action);
    if (user) p.set('user', user);

    const data = await apiFetch('api/get_audit_log.php?' + p.toString());
    const tbody = document.getElementById('auditBody');
    tbody.innerHTML = '';
    if (!data.entries.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="empty-state">No audit entries match.</td></tr>';
        renderPagination(0, 1);
        return;
    }
    data.entries.forEach(e => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${e.performed_at}</td>
            <td><span class="tag ${e.action.includes('delete') ? 'expense' : (e.action==='create'?'income':'transfer')}">${ACTION_LABELS[e.action] || e.action}</span></td>
            <td>#${e.record_id ?? '—'}</td>
            <td>${e.performed_by}</td>
            <td class="muted">${summarize(e)}</td>`;
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
        b.addEventListener('click', () => { currentPage = p; loadAudit(); });
        return b;
    };
    el.appendChild(mk('‹ Prev', page - 1, page <= 1));
    for (let i = 1; i <= totalPages; i++) el.appendChild(mk(i, i, false, i === page));
    el.appendChild(mk('Next ›', page + 1, page >= totalPages));
}

document.getElementById('f_action').addEventListener('change', () => { currentPage = 1; loadAudit(); });
document.getElementById('f_user').addEventListener('change', () => { currentPage = 1; loadAudit(); });
loadAudit();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
