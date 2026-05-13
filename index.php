<?php
// Load storage (needed for both seeding and inlining categories)
require_once __DIR__ . '/api/ExpenseStorage.php';
 
$dataFile = __DIR__ . '/data/expenses.json';
$data = json_decode(file_get_contents($dataFile), true);
$storage = new ExpenseStorage($dataFile);
 
if (empty($data['expenses'])) {
    $demos = [
        ['amount'=>45.50, 'date'=>date('Y-m-d', strtotime('-1 day')),  'description'=>'Grocery run', 'category'=>'Food & Dining', 'notes'=>'Weekly shop'],
        ['amount'=>12.00, 'date'=>date('Y-m-d', strtotime('-2 days')), 'description'=>'Uber to work', 'category'=>'Transport', 'notes'=>''],
        ['amount'=>800.00,'date'=>date('Y-m-d', strtotime('-3 days')), 'description'=>'Monthly rent', 'category'=>'Housing', 'notes'=>'May rent'],
        ['amount'=>35.00, 'date'=>date('Y-m-d', strtotime('-4 days')), 'description'=>'Netflix & Spotify', 'category'=>'Entertainment', 'notes'=>''],
        ['amount'=>22.75, 'date'=>date('Y-m-d', strtotime('-5 days')), 'description'=>'Lunch with team', 'category'=>'Food & Dining', 'notes'=>''],
        ['amount'=>60.00, 'date'=>date('Y-m-d', strtotime('-8 days')), 'description'=>'Electricity bill', 'category'=>'Utilities', 'notes'=>''],
        ['amount'=>150.00,'date'=>date('Y-m-d', strtotime('-10 days')),'description'=>'New shoes', 'category'=>'Shopping', 'notes'=>''],
        ['amount'=>18.50, 'date'=>date('Y-m-d', strtotime('-12 days')),'description'=>'Bus pass top-up', 'category'=>'Transport', 'notes'=>''],
    ];
    foreach ($demos as $d) $storage->create($d);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Expense Tracker</title>
<link rel="stylesheet" href="styles.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Mono:wght@300;400;500&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>

<header class="site-header">
  <div class="logo">Trac<span>ker</span></div>
  <div class="header-date" id="headerDate"></div>
</header>

<div class="app">
  <!-- ── Sidebar ── -->
  <aside class="sidebar">
    <!-- Stats summary -->
    <div>
      <div style="font-family:var(--mono);font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:.75rem;">Overview</div>
      <div id="sidebarSummary">Loading…</div>
    </div>

    <!-- Category breakdown -->
    <div class="card">
      <div class="card-header">By Category</div>
      <div class="card-body" id="catBreakdown"><div class="empty" style="padding:1rem">…</div></div>
    </div>

    <!-- Month chart -->
    <div class="card">
      <div class="card-header">Last 6 Months</div>
      <div class="card-body" id="monthChart" style="padding:.75rem 1.25rem 1.25rem"></div>
    </div>

    <button class="btn btn-primary" style="width:100%;" onclick="openModal()">+ Add Expense</button>
  </aside>

  <!-- ── Main ── -->
  <main class="main">
    <div class="stats" id="statsRow"></div>

    <div class="card">
      <div class="card-header">
        <span>Expenses</span>
        <button class="btn btn-ghost btn-sm" onclick="openModal()">+ Add</button>
      </div>

      <div class="card-body" style="padding:0">
        <div class="filter-bar" style="padding:1rem 1rem 0;">
          <input type="search" id="searchBox" placeholder="Search description…" oninput="applyFilters()">
          <select id="catFilter" onchange="applyFilters()">
            <option value="">All categories</option>
          </select>
          <input type="month" id="monthFilter" onchange="applyFilters()">
          <button class="btn btn-ghost btn-sm" onclick="clearFilters()">Clear</button>
        </div>

        <div id="tableWrap">
          <table class="expense-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Description</th>
                <th>Category</th>
                <th style="text-align:right">Amount</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="expenseBody"></tbody>
          </table>
        </div>
        <div id="emptyState" class="empty" style="display:none">No expenses yet — add one!</div>
      </div>
    </div>
  </main>
</div>

<!-- ── Add / Edit Modal ── -->
<div class="modal-overlay" id="modalOverlay" onclick="closeOnOverlay(event)">
  <div class="modal">
    <div class="modal-head">
      <h2 id="modalTitle">Add Expense</h2>
      <button class="close-btn" onclick="closeModal()">×</button>
    </div>
    <div class="modal-body">
      <div class="form-grid">
        <div class="form-group">
          <label>Amount (₦)</label>
          <input type="number" id="fAmount" min="0.01" step="0.01" placeholder="0.00">
        </div>
        <div class="form-group">
          <label>Date</label>
          <input type="date" id="fDate">
        </div>
        <div class="form-group full">
          <label>Description</label>
          <input type="text" id="fDescription" placeholder="What was this for?">
        </div>
        <div class="form-group">
          <label>Category</label>
          <select id="fCategory"></select>
        </div>
        <div class="form-group">
          <label>Notes (optional)</label>
          <input type="text" id="fNotes" placeholder="Extra details…">
        </div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-ghost" onclick="closeModal()">Cancel</button>
      <button class="btn btn-primary" onclick="saveExpense()">Save Expense</button>
    </div>
  </div>
</div>

<div id="toast"></div>

<script>
const API = 'api/expenses.php';
let allExpenses = [];
// Categories inlined by PHP — available instantly, no fetch needed
let categories  = <?= json_encode($storage->categories()) ?>;
let editingId   = null;
 
// ── Init ─────────────────────────────────────────────────────────────────────
async function init() {
  document.getElementById('headerDate').textContent =
    new Date().toLocaleDateString('en-GB', {weekday:'short',day:'numeric',month:'long',year:'numeric'});
 
  populateCatSelects(); // categories already loaded — call immediately
  await refresh();
}
 
async function refresh() {
  const [expRes, sumRes] = await Promise.all([
    fetch(API), fetch(`${API}?summary=1`)
  ]);
  allExpenses = (await expRes.json()).data;
  const summary = (await sumRes.json()).data;
  renderTable(allExpenses);
  renderStats(summary);
  renderCatBreakdown(summary);
  renderMonthChart(summary);
}
 
// ── Render ────────────────────────────────────────────────────────────────────
function renderTable(rows) {
  const tbody = document.getElementById('expenseBody');
  const empty = document.getElementById('emptyState');
  if (!rows.length) {
    tbody.innerHTML = '';
    empty.style.display = 'block';
    return;
  }
  empty.style.display = 'none';
  tbody.innerHTML = rows.map(e => `
    <tr>
      <td class="date">${formatDate(e.date)}</td>
      <td>
        <div style="font-weight:500">${esc(e.description)}</div>
        ${e.notes ? `<div style="font-size:.78rem;color:var(--muted)">${esc(e.notes)}</div>` : ''}
      </td>
      <td><span class="badge">${esc(e.category)}</span></td>
      <td class="amount">₦${fmt(e.amount)}</td>
      <td style="text-align:right;white-space:nowrap">
        <button class="btn btn-ghost btn-sm" onclick="editExpense('${e.id}')">Edit</button>
        <button class="btn btn-danger btn-sm" onclick="deleteExpense('${e.id}','${esc(e.description)}')">Del</button>
      </td>
    </tr>
  `).join('');
}
 
function renderStats(s) {
  document.getElementById('statsRow').innerHTML = `
    <div class="stat"><div class="stat-label">Total spent</div><div class="stat-value accent">₦${fmt(s.total)}</div></div>
    <div class="stat"><div class="stat-label">This month</div><div class="stat-value accent2">₦${fmt(s.monthTotal)}</div></div>
    <div class="stat"><div class="stat-label">Transactions</div><div class="stat-value">${allExpenses.length}</div></div>
  `;
  document.getElementById('sidebarSummary').innerHTML = `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem">
      <div style="background:white;border:1px solid var(--rule);border-radius:var(--radius);padding:.75rem">
        <div style="font-family:var(--mono);font-size:.65rem;color:var(--muted);text-transform:uppercase">Total</div>
        <div style="font-family:var(--serif);font-size:1.1rem;color:var(--accent)">₦${fmt(s.total)}</div>
      </div>
      <div style="background:white;border:1px solid var(--rule);border-radius:var(--radius);padding:.75rem">
        <div style="font-family:var(--mono);font-size:.65rem;color:var(--muted);text-transform:uppercase">Month</div>
        <div style="font-family:var(--serif);font-size:1.1rem;color:var(--accent2)">₦${fmt(s.monthTotal)}</div>
      </div>
    </div>
  `;
}
 
function renderCatBreakdown(s) {
  const entries = Object.entries(s.byCat);
  if (!entries.length) { document.getElementById('catBreakdown').innerHTML = '<div class="empty" style="padding:1rem">No data</div>'; return; }
  const max = entries[0][1];
  document.getElementById('catBreakdown').innerHTML = `
    <div class="cat-list">
      ${entries.map(([cat, amt]) => `
        <div class="cat-row">
          <div class="cat-meta"><span class="cat-name">${esc(cat)}</span><span class="cat-amt">₦${fmt(amt)}</span></div>
          <div class="cat-bar-track"><div class="cat-bar-fill" style="width:${Math.round(amt/max*100)}%"></div></div>
        </div>
      `).join('')}
    </div>
  `;
}
 
function renderMonthChart(s) {
  const entries = Object.entries(s.byMonth).reverse();
  if (!entries.length) { document.getElementById('monthChart').innerHTML = ''; return; }
  const max = Math.max(...entries.map(([,v]) => v));
  document.getElementById('monthChart').innerHTML = `
    <div class="month-bars">
      ${entries.map(([m, amt]) => `
        <div class="month-col">
          <div class="month-bar" style="height:${Math.round(amt/max*80)}px" title="₦${fmt(amt)}"></div>
          <div class="month-label">${m.slice(5)}</div>
        </div>
      `).join('')}
    </div>
  `;
}
 
// ── Filters ───────────────────────────────────────────────────────────────────
function applyFilters() {
  const search = document.getElementById('searchBox').value.toLowerCase();
  const cat    = document.getElementById('catFilter').value;
  const month  = document.getElementById('monthFilter').value;
  let rows = allExpenses;
  if (search) rows = rows.filter(e => e.description.toLowerCase().includes(search));
  if (cat)    rows = rows.filter(e => e.category === cat);
  if (month)  rows = rows.filter(e => e.date.startsWith(month));
  renderTable(rows);
}
function clearFilters() {
  document.getElementById('searchBox').value = '';
  document.getElementById('catFilter').value = '';
  document.getElementById('monthFilter').value = '';
  renderTable(allExpenses);
}
 
// ── Modal ─────────────────────────────────────────────────────────────────────
function openModal(id = null) {
  editingId = id;
  populateCatSelects(); // re-populate in case DOM was wiped
  document.getElementById('modalTitle').textContent = id ? 'Edit Expense' : 'Add Expense';
  if (id) {
    const e = allExpenses.find(x => x.id === id);
    document.getElementById('fAmount').value      = e.amount;
    document.getElementById('fDate').value        = e.date;
    document.getElementById('fDescription').value = e.description;
    document.getElementById('fCategory').value    = e.category;
    document.getElementById('fNotes').value       = e.notes || '';
  } else {
    document.getElementById('fAmount').value      = '';
    document.getElementById('fDate').value        = new Date().toISOString().slice(0,10);
    document.getElementById('fDescription').value = '';
    document.getElementById('fCategory').value    = categories[0] || '';
    document.getElementById('fNotes').value       = '';
  }
  document.getElementById('modalOverlay').classList.add('open');
}
function closeModal() { document.getElementById('modalOverlay').classList.remove('open'); }
function closeOnOverlay(e) { if (e.target === document.getElementById('modalOverlay')) closeModal(); }
 
function editExpense(id) { openModal(id); }
 
async function saveExpense() {
  const body = {
    amount:      parseFloat(document.getElementById('fAmount').value),
    date:        document.getElementById('fDate').value,
    description: document.getElementById('fDescription').value,
    category:    document.getElementById('fCategory').value,
    notes:       document.getElementById('fNotes').value,
  };
  try {
    const method = editingId ? 'PUT' : 'POST';
    const url    = editingId ? `${API}?id=${editingId}` : API;
    const res    = await fetch(url, { method, headers:{'Content-Type':'application/json'}, body: JSON.stringify(body) });
    const json   = await res.json();
    if (!json.ok) throw new Error(json.error);
    closeModal();
    await refresh();
    toast(editingId ? 'Expense updated' : 'Expense added', 'ok');
  } catch(err) { toast(err.message, 'err'); }
}
 
async function deleteExpense(id, desc) {
  if (!confirm(`Delete "${desc}"?`)) return;
  try {
    const res  = await fetch(`${API}?id=${id}`, { method:'DELETE' });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error);
    await refresh();
    toast('Expense deleted', 'ok');
  } catch(err) { toast(err.message, 'err'); }
}
 
// ── Helpers ───────────────────────────────────────────────────────────────────
function populateCatSelects() {
  const opts = categories.map(c => `<option value="${c}">${c}</option>`).join('');
  document.getElementById('catFilter').innerHTML = '<option value="">All categories</option>' + opts;
  document.getElementById('fCategory').innerHTML = opts;
}
function fmt(n)  { return Number(n).toLocaleString('en-NG', {minimumFractionDigits:2,maximumFractionDigits:2}); }
function esc(s)  { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function formatDate(d) {
  const dt = new Date(d + 'T00:00:00');
  return dt.toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric'});
}
function toast(msg, type) {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.className = `show ${type}`;
  setTimeout(() => el.className = '', 2800);
}
 
init();
</script>

</body>
</html>
