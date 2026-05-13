<?php
// Seed some demo data on first run
$dataFile = __DIR__ . '/data/expenses.json';
$data = json_decode(file_get_contents($dataFile), true);

if (empty($data['expenses'])) {
    require_once __DIR__ . '/api/ExpenseStorage.php';
    $storage = new ExpenseStorage($dataFile);
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
</body>
</html>
