<?php
require __DIR__ . '/config/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/functions.php';
requireLogin();

$pageTitle = 'Categories';
$activeNav = 'settings';
$categories = loadCategories();
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Categories</h1>
        <p>Stored in <code>data/categories.json</code>. Used by the income/expense forms.</p>
    </div>
</div>

<div class="panel-row">
    <div class="panel">
        <h2>Income categories</h2>
        <div id="incomeList"></div>
        <div class="form-grid" style="grid-template-columns: 1fr auto; margin-top:10px;">
            <input type="text" id="newIncome" placeholder="New income category">
            <button class="btn btn-secondary" onclick="addCategory('income')">Add</button>
        </div>
    </div>
    <div class="panel">
        <h2>Expense categories</h2>
        <div id="expenseList"></div>
        <div class="form-grid" style="grid-template-columns: 1fr auto; margin-top:10px;">
            <input type="text" id="newExpense" placeholder="New expense category">
            <button class="btn btn-secondary" onclick="addCategory('expense')">Add</button>
        </div>
    </div>
</div>

<div class="panel">
    <button class="btn btn-primary" id="saveBtn">Save changes</button>
    <span class="muted" style="margin-left:10px;">Removing a category here does not change existing records that already used it.</span>
</div>

<script>
let categories = <?= json_encode($categories) ?>;

function renderLists() {
    ['income','expense'].forEach(type => {
        const el = document.getElementById(type + 'List');
        el.innerHTML = '';
        categories[type].forEach((c, idx) => {
            const row = document.createElement('div');
            row.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--line);';
            row.innerHTML = `<span>${c}</span>`;
            const btn = document.createElement('button');
            btn.className = 'btn btn-danger btn-small';
            btn.textContent = 'Remove';
            btn.onclick = () => { categories[type].splice(idx, 1); renderLists(); };
            row.appendChild(btn);
            el.appendChild(row);
        });
        if (!categories[type].length) el.innerHTML = '<p class="muted">No categories yet.</p>';
    });
}
renderLists();

function addCategory(type) {
    const input = document.getElementById(type === 'income' ? 'newIncome' : 'newExpense');
    const val = input.value.trim();
    if (!val) return;
    if (categories[type].includes(val)) { showToast('Category already exists.', true); return; }
    categories[type].push(val);
    input.value = '';
    renderLists();
}

document.getElementById('saveBtn').addEventListener('click', async () => {
    const res = await postJSON('api/save_categories.php', categories);
    if (res.success) {
        categories = res.categories;
        renderLists();
        showToast('Categories saved.');
    } else {
        showToast(res.error || 'Could not save categories.', true);
    }
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
