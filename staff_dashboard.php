<?php
session_start();
if (
    empty($_SESSION['staff_logged_in']) ||
    ($_SESSION['role'] ?? '') !== 'staff' ||
    empty($_SESSION['staff_id'])
) {
    header('Location: staff.html');
    exit;
}
require_once __DIR__ . '/db.php';
$activeStaffCheck = $conn->prepare("SELECT status FROM staff WHERE staff_id = ? LIMIT 1");
$activeStaffId = (int)$_SESSION['staff_id'];
$activeStaffCheck->bind_param('i', $activeStaffId);
$activeStaffCheck->execute();
$activeStaffRow = $activeStaffCheck->get_result()->fetch_assoc();
if (!$activeStaffRow || strcasecmp(trim((string)$activeStaffRow['status']), 'Active') !== 0) {
    $_SESSION = [];
    session_destroy();
    header('Location: staff.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cozy Bake — Staff Portal</title>
  <link rel="stylesheet" href="cozy-theme.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            cozy: {
              bg: '#F5EDE3',
              wood: '#5C2C16',
              accent: '#EB9E55',
              border: '#EFE6DC',
              darkCard: '#5C2C16',
              orangeCard: '#EB9E55',
              textDark: '#2C1A11',
              textMuted: '#8C7A6B'
            }
          },
          fontFamily: {
            sans: ['DM Sans', 'sans-serif'],
            script: ['Pacifico', 'cursive']
          }
        }
      }
    }
  </script>
  <style>
    :root {
      --wood: #5C2C16;
      --bg: #F5EDE3;
      --accent: #EB9E55;
      --border: #EFE6DC;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'DM Sans', sans-serif; }
    body { display: flex; height: 100vh; background: var(--bg); color: #2C1A11; overflow: hidden; }

    /* Sidebar Auto-Expand Bila Hover & Saiz Simbol 24px */
    .sidebar {
      width: 75px;
      background: linear-gradient(180deg, #5A351D 0%, #432512 50%, #5A351D 100%);
      display: flex;
      flex-direction: column;
      align-items: stretch;
      padding: 20px 15px;
      gap: 14px;
      z-index: 30;
      flex-shrink: 0;
      transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      overflow: hidden;
      white-space: nowrap;
    }
    .sidebar:hover {
      width: 220px;
      box-shadow: 6px 0 24px rgba(0, 0, 0, 0.18);
    }
    .sidebar svg {
      width: 24px !important;
      height: 24px !important;
      stroke-width: 2;
      flex-shrink: 0;
    }
    .brand-btn {
      height: 44px;
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 0 10px;
      color: #fff;
      margin-bottom: 10px;
      background: none;
      border: none;
      cursor: pointer;
      text-align: left;
    }
    .nav-btn {
      width: 100%;
      height: 44px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 0 10px;
      color: rgba(255, 255, 255, 0.65);
      cursor: pointer;
      transition: background 0.2s, color 0.2s;
      border: none;
      background: transparent;
      text-align: left;
    }
    .nav-btn.active, .nav-btn:hover {
      background: rgba(255, 255, 255, 0.15);
      color: #fff;
    }
    .nav-label {
      font-size: 0.92rem;
      font-weight: 700;
      opacity: 0;
      transform: translateX(-8px);
      transition: opacity 0.2s ease, transform 0.25s ease;
      pointer-events: none;
    }
    .brand-label {
      font-family: 'DM Sans', sans-serif;
      font-size: 16px;
      font-weight: 600;
      color: #fff;
      opacity: 0;
      transform: translateX(-8px);
      transition: opacity 0.2s ease, transform 0.25s ease;
    }
    .sidebar:hover .nav-label,
    .sidebar:hover .brand-label {
      opacity: 1;
      transform: translateX(0);
    }
    .logout { margin-top: auto; }

    /* Layout Utama */
    .main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
    header { height: 76px; background: #FDFBF7; display: flex; justify-content: space-between; align-items: center; padding: 0 38px; border-bottom: 1px solid #EFE6DC; flex-shrink: 0; }
    #topbar-title { font-family: 'Oswald', sans-serif !important; font-size: 18px !important; letter-spacing: 0.6px; font-weight: 600 !important; }
    .view-section { display: none; flex: 1; padding: 32px 38px; overflow-y: auto; flex-direction: column; gap: 22px; }
    .view-section.active { display: flex; }

    /* Komponen & Tipografi */
    .title { font-family: 'Pacifico', cursive; font-size: 38px; font-weight: 400; color: #3B1D0E; line-height: 1.2; margin: 4px 0; }
    .filters { display: flex; gap: 10px; flex-wrap: wrap; }
    .pill { padding: 10px 20px; border-radius: 999px; background: #fff; border: 1px solid var(--border); font-weight: 600; font-size: 12.5px; letter-spacing: 0.5px; text-transform: uppercase; cursor: pointer; transition: 0.2s; }
    .pill.active { background: var(--wood); color: #fff; border-color: var(--wood); }

    /* Kad & Grid Orders */
    .orders-grid { display: grid; grid-template-columns: 1fr 400px; gap: 24px; align-items: start; }
    @media (max-width: 950px) { .orders-grid { grid-template-columns: 1fr; } }
    .order-card { background: #fff; border-radius: 14px; padding: 16px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; border: 2px solid transparent; margin-bottom: 10px; transition: 0.2s; }
    .order-card:hover { border-color: var(--border); }
    .order-card.selected { border-color: var(--accent); box-shadow: 0 4px 12px rgba(232,152,73,0.15); }

    /* Badges Status */
    .badge { padding: 4px 10px; border-radius: 10px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; display: inline-block; }
    .badge-NEW { background: #fde8d7; color: #b86527; }
    .badge-PREPARING { background: #fdf3cf; color: #967015; }
    .badge-READY { background: #dcf3e7; color: #2a7a52; }
    .badge-COMPLETED { background: #eae4e0; color: #736760; }
    .badge-birthday { background: #ffe0a8; color: #8a4b00; }
    .badge-wedding { background: #fbd6dc; color: #b02d47; }
    .badge-everyday, .badge-table { background: #f0e6dc; color: #76533e; }

    /* Progress Bar & Panel */
    .progress-tracker { display: flex; gap: 6px; margin: 15px 0 6px; }
    .progress-step { flex: 1; height: 6px; background-color: #e6ded7; border-radius: 4px; transition: 0.3s; }
    .progress-step.fill { background-color: var(--accent); }
    .panel { background: #fff; border-radius: 20px; padding: 24px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); position: sticky; top: 0; }
    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 15px 0; }
    .info-box { background: #fbf6f0; padding: 10px; border-radius: 10px; }
    .info-box span { display: block; font-size: 0.65rem; color: #8c7a6b; font-weight: 800; text-transform: uppercase; margin-bottom: 2px; }
    .btn-action { width: 100%; padding: 14px; background: #a87b5b; color: white; border: none; border-radius: 12px; font-weight: 800; text-transform: uppercase; cursor: pointer; margin-top: 15px; transition: 0.2s; }
    .btn-action:hover { background: #91694d; }
    .btn-disabled { width: 100%; padding: 14px; background: #ded6cf; color: #fff; border: none; border-radius: 12px; font-weight: 800; text-transform: uppercase; cursor: not-allowed; margin-top: 15px; }

    /* Schedule Baris */
    .schedule-row { display: grid; grid-template-columns: 70px 90px 100px 1.2fr 1.5fr 120px; align-items: center; background: #fff; padding: 14px 20px; border-radius: 16px; font-size: 0.9rem; cursor: pointer; margin-bottom: 10px; transition: 0.2s; }
    .schedule-row:hover { background: #fff8f1; }
    @media (max-width: 800px) { .schedule-row { grid-template-columns: 1fr 1fr; gap: 8px; } }
    .row-details { display: none; grid-column: 1 / -1; margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--border); color: #8c7a6b; font-size: 0.8rem; }
    .schedule-row.expanded .row-details { display: block; }

    /* Notification Popover */
    .staff-notif-wrap { position: relative; }
    .staff-notif-btn { position: relative; width: 38px; height: 38px; background: #ebdcd0; color: #5C2C16; border: none; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s; }
    .staff-notif-btn:hover { background: #e1cbb9; }
    .staff-notif-badge { position: absolute; top: -3px; right: -3px; background: #c0392b; color: #fff; font-size: 0.62rem; min-width: 17px; height: 17px; padding: 0 4px; border-radius: 999px; display: none; align-items: center; justify-content: center; font-weight: 700; border: 2px solid #fff; }
    .staff-notif-popover { display: none; position: absolute; top: 48px; right: 0; width: 340px; max-width: calc(100vw - 32px); background: #fff; border: 1px solid var(--border); border-radius: 18px; box-shadow: 0 16px 36px rgba(44, 26, 17, 0.16); z-index: 250; overflow: hidden; }
    .staff-notif-popover.open { display: block; }
    .staff-notif-head { padding: 14px 16px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; background: #FDFBF7; }
    .staff-notif-head h4 { font-size: 0.78rem; font-weight: 800; letter-spacing: 0.8px; text-transform: uppercase; color: var(--wood); }
    .staff-notif-list { max-height: 360px; overflow-y: auto; }
    .staff-notif-item { width: 100%; text-align: left; background: transparent; border: none; border-bottom: 1px solid #F5EDE3; padding: 12px 16px; display: flex; gap: 12px; align-items: flex-start; cursor: pointer; transition: background 0.15s; }
    .staff-notif-item:last-child { border-bottom: none; }
    .staff-notif-item:hover { background: #FDF8F2; }
    .staff-notif-icon { width: 32px; height: 32px; border-radius: 10px; background: #F5EDE3; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 15px; }
    .staff-notif-body h5 { font-size: 0.84rem; font-weight: 700; color: #2C1A11; margin-bottom: 2px; }
    .staff-notif-body p { font-size: 0.75rem; color: #8c7a6b; line-height: 1.35; }
  </style>
</head>
<body>

  <!-- SIDEBAR AUTO-EXPAND DENGAN NAMA SIMBOL -->
  <aside class="sidebar">
    <button onclick="switchPage('dashboard')" class="brand-btn" title="Cozy Bake">
      <i data-lucide="leaf"></i>
      <span class="brand-label">Cozy Bake</span>
    </button>
    <button id="nav-dashboard" class="nav-btn active" onclick="switchPage('dashboard')">
      <i data-lucide="layout-grid"></i>
      <span class="nav-label">Dashboard</span>
    </button>
    <button id="nav-inventory" class="nav-btn" onclick="switchPage('inventory')">
      <i data-lucide="box"></i>
      <span class="nav-label">Inventory</span>
    </button>
    <button id="nav-orders" class="nav-btn" onclick="switchPage('orders')">
      <i data-lucide="receipt"></i>
      <span class="nav-label">Orders</span>
    </button>
    <button id="nav-ingredients" class="nav-btn" onclick="switchPage('ingredients')">
      <i data-lucide="wheat"></i>
      <span class="nav-label">Ingredients</span>
    </button>
    <button id="nav-schedule" class="nav-btn" onclick="switchPage('schedule')">
      <i data-lucide="calendar"></i>
      <span class="nav-label">Schedule</span>
    </button>
    <button class="nav-btn" style="margin-top:auto;" onclick="openChangePasswordModal()">
      <i data-lucide="lock"></i>
      <span class="nav-label">Change Password</span>
    </button>
    <button class="nav-btn logout" style="margin-top:4px;" onclick="openLogoutModal()">
      <i data-lucide="log-out"></i>
      <span class="nav-label">Log Out</span>
    </button>
  </aside>

  <!-- KANDUNGAN UTAMA -->
  <div class="main">
    <!-- HEADER ATAS -->
    <header>
      <div>
        <h1 id="topbar-title" style="font-size:1.1rem; font-weight:800; text-transform:uppercase;">Dashboard</h1>
        <p style="font-size:0.8rem; color:#8c7a6b;">Cozy Bake management</p>
      </div>
      <div style="display:flex; align-items:center; gap:12px;">
        <span class="hidden sm:flex text-xs font-bold px-3 py-1.5 bg-amber-100 text-amber-900 rounded-full items-center gap-1.5">
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Shift: Morning (07:00 - 15:00)
        </span>
        <div class="staff-notif-wrap">
          <button type="button" id="staffNotifBtn" class="staff-notif-btn" onclick="toggleStaffNotifications(event)" aria-label="Notifications" aria-expanded="false">
            <i data-lucide="bell" style="width:18px; height:18px;"></i>
            <span id="staffNotifBadge" class="staff-notif-badge">0</span>
          </button>
          <div id="staffNotifPopover" class="staff-notif-popover" role="region" aria-label="Staff alerts">
            <div class="staff-notif-head">
              <h4>Kitchen &amp; Stock Alerts</h4>
              <span id="staffNotifSummary" style="font-size:0.72rem; color:#8c7a6b; font-weight:600;">Loading…</span>
            </div>
            <div id="staffNotifList" class="staff-notif-list">
              <div style="padding:18px 16px; font-size:0.82rem; color:#8c7a6b;">Checking for alerts…</div>
            </div>
          </div>
        </div>
        <div style="width:38px; height:38px; background:var(--wood); color:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700;"><?= htmlspecialchars(strtoupper(mb_substr(trim((string)($_SESSION['staff_name'] ?? 'S')), 0, 1)), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    </header>

    <!-- ==========================================
         1. VIEW: DASHBOARD
    =========================================== -->
    <section id="view-dashboard" class="view-section active">
      <div>
        <span style="font-size:0.75rem; font-weight:800; color:#9a6845; letter-spacing:1px; text-transform:uppercase;">Staff Portal</span>
        <div class="title">Welcome back, <?= htmlspecialchars(trim((string)($_SESSION['staff_name'] ?? 'Staff')), ENT_QUOTES, 'UTF-8') ?>!</div>
        <p style="font-size:0.88rem; color:#8c7a6b; margin-top:4px;">Here is today's bakery overview. Click any card below to jump to its section.</p>
      </div>

      <!-- Kad Statistik Interaktif -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div onclick="openOrdersWithFilter('NEW')" class="bg-[#EB9E55] text-[#2C1A11] p-6 rounded-[20px] shadow-sm hover:-translate-y-1 hover:shadow-md cursor-pointer transition flex items-center gap-4">
          <div class="p-3 bg-white/30 text-[#2C1A11] rounded-xl"><i data-lucide="shopping-bag"></i></div>
          <div>
            <p class="text-[11px] uppercase font-bold tracking-wider opacity-85">Active Orders</p>
            <h3 id="dash-active-orders" class="text-3xl font-bold mt-1">—</h3>
          </div>
        </div>

        <div onclick="openOrdersWithFilter('COMPLETED')" class="bg-white p-6 rounded-[20px] shadow-sm hover:-translate-y-1 hover:shadow-md cursor-pointer transition flex items-center gap-4">
          <div class="p-3 bg-[#DCECE3] text-[#286646] rounded-xl"><i data-lucide="check-circle-2"></i></div>
          <div>
            <p class="text-[11px] uppercase font-bold tracking-wider text-cozy-textMuted">Completed Today</p>
            <h3 id="dash-completed-orders" class="text-3xl font-bold mt-1">—</h3>
          </div>
        </div>

        <div onclick="switchPage('ingredients'); filterIngredientsByStat('low');" class="bg-[#5C2C16] text-white p-6 rounded-[20px] shadow-sm hover:-translate-y-1 hover:shadow-md cursor-pointer transition flex items-center gap-4">
          <div class="p-3 bg-white/15 text-white rounded-xl"><i data-lucide="alert-triangle"></i></div>
          <div>
            <p class="text-[11px] uppercase font-bold tracking-wider text-white/80">Low Stock Pantry</p>
            <h3 id="dash-low-stock" class="text-3xl font-bold mt-1">—</h3>
          </div>
        </div>

        <div onclick="switchPage('schedule')" class="bg-white p-6 rounded-[20px] shadow-sm hover:-translate-y-1 hover:shadow-md cursor-pointer transition flex items-center gap-4">
          <div class="p-3 bg-[#FDE5CE] text-[#9C5116] rounded-xl"><i data-lucide="flame"></i></div>
          <div>
            <p class="text-[11px] uppercase font-bold tracking-wider text-cozy-textMuted">Upcoming Pickups</p>
            <h3 id="dash-upcoming-pickups" class="text-3xl font-bold mt-1">—</h3>
          </div>
        </div>
      </div>

      <!-- Live pickup overview loaded from MySQL -->
      <div class="bg-white rounded-2xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-5">
          <h3 class="text-lg font-bold">Upcoming Pickup Orders</h3>
          <button onclick="switchPage('schedule')" class="text-xs text-[#9a6845] font-bold hover:underline">View Schedule →</button>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead><tr class="border-b border-gray-100 text-xs text-cozy-textMuted uppercase font-extrabold">
              <th class="pb-3 px-2">Order</th><th class="pb-3 px-2">Customer</th><th class="pb-3 px-2">Pickup Time</th><th class="pb-3 px-2">Status</th>
            </tr></thead>
            <tbody id="dash-upcoming-list" class="divide-y divide-gray-50">
              <tr><td colspan="4" class="py-4 px-2 text-cozy-textMuted">Loading live pickup orders...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ==========================================
         2. VIEW: INVENTORY (STOCK ROOM)
    =========================================== -->
    <section id="view-inventory" class="view-section">
      <div>
        <span style="font-size:0.75rem; font-weight:800; color:#9a6845; letter-spacing:1px; text-transform:uppercase;">Stock Management</span>
        <div class="title">Stock room</div>
        <p style="font-size:0.88rem; color:#8c7a6b; margin-top:4px;">Monitor product stock and update quantities as deliveries arrive.</p>
      </div>

      <div class="bg-white rounded-3xl p-6 shadow-sm flex-1 flex flex-col">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-gray-100">
          <div class="flex items-center space-x-3">
            <h2 class="text-lg font-extrabold text-cozy-wood uppercase">Product Inventory</h2>
            <span id="inv-count-badge" class="bg-cozy-bg text-cozy-wood text-xs font-bold px-2.5 py-1 rounded-full">23 items</span>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <input type="text" id="inv-search" oninput="renderInventory()" placeholder="Search bakery item..." class="px-4 py-2 bg-cozy-bg/60 border border-gray-200 rounded-xl text-sm focus:outline-none">
            <select id="inv-category" onchange="renderInventory()" class="bg-cozy-bg/60 border border-gray-200 rounded-xl text-sm font-semibold text-cozy-wood py-2 px-3">
              <option value="ALL">All Categories</option>
              <option value="Cake">Cake</option>
              <option value="Bread">Bread</option>
              <option value="Sweets">Sweets</option>
              <option value="Tart">Tart</option>
              <option value="Savory">Savory</option>
              <option value="Cookies">Cookies</option>
            </select>
          </div>
        </div>
        <div id="inventory-list" class="space-y-3"></div>
      </div>
    </section>

    <!-- ==========================================
         3. VIEW: ORDERS (ALL / NEW / PREPARING / READY / COMPLETED)
    =========================================== -->
    <section id="view-orders" class="view-section">
      <div>
        <span style="font-size:0.75rem; font-weight:800; color:#9a6845; letter-spacing:1px; text-transform:uppercase;">Order Management</span>
        <div class="title" id="order-page-heading">Live orders</div>
        <p style="font-size:0.88rem; color:#8c7a6b; margin-top:4px;">Pick an order to see everything the kitchen needs to know.</p>
      </div>

      <div class="filters" id="order-filters"></div>

      <div class="orders-grid">
        <div id="orders-list"></div>
        <div class="panel" id="order-detail-panel"></div>
      </div>
    </section>

    <!-- ==========================================
         4. VIEW: INGREDIENTS & RECIPES
    =========================================== -->
    <section id="view-ingredients" class="view-section">
      <div>
        <span style="font-size:0.75rem; font-weight:800; color:#9a6845; letter-spacing:1px; text-transform:uppercase;">Ingredients</span>
        <div class="title">Pantry & recipes</div>
        <p style="font-size:0.88rem; color:#8c7a6b; margin-top:4px;">Everything in the pantry, what is running low, what has expired, and what each product needs.</p>
      </div>

      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div onclick="filterIngredientsByStat('all')" class="kpi-card bg-cozy-darkCard text-white p-5 rounded-3xl cursor-pointer">
          <h3 class="text-xs font-extrabold text-white/70 uppercase">ALL INGREDIENTS</h3>
          <div id="ing-stat-total" class="text-4xl font-black my-2">—</div>
          <p class="text-xs text-white/70">Tracked in the pantry</p>
        </div>
        <div onclick="filterIngredientsByStat('low')" class="kpi-card bg-cozy-orangeCard text-white p-5 rounded-3xl cursor-pointer">
          <h3 class="text-xs font-extrabold text-white/85 uppercase">LOW STOCK</h3>
          <div id="ing-stat-low" class="text-4xl font-black my-2">—</div>
          <p class="text-xs text-white/85">Below par level</p>
        </div>
        <div onclick="filterIngredientsByStat('expiring')" class="kpi-card bg-white p-5 rounded-3xl cursor-pointer">
          <h3 class="text-xs font-extrabold text-cozy-textMuted uppercase">EXPIRING SOON</h3>
          <div id="ing-stat-expiring" class="text-4xl font-black text-cozy-darkCard my-2">—</div>
          <p class="text-xs text-cozy-textMuted">Use within 5 days</p>
        </div>
        <div onclick="filterIngredientsByStat('expired')" class="kpi-card bg-white p-5 rounded-3xl cursor-pointer">
          <h3 class="text-xs font-extrabold text-cozy-textMuted uppercase">EXPIRED</h3>
          <div id="ing-stat-expired" class="text-4xl font-black text-cozy-darkCard my-2">—</div>
          <p class="text-xs text-cozy-textMuted">Remove from the line</p>
        </div>
      </div>

      <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <button id="tab-pantry" onclick="switchIngredientTab('pantry')" class="pill active">PANTRY STOCK</button>
          <button id="tab-recipes" onclick="switchIngredientTab('recipes')" class="pill">PRODUCT RECIPES · 0</button>
        </div>
        <input type="text" id="ing-search" oninput="renderIngredients()" placeholder="Search ingredients..." class="px-5 py-2.5 bg-white rounded-full text-sm focus:outline-none">
      </div>

      <div id="ingredients-list-container" class="space-y-3"></div>
    </section>

    <!-- ==========================================
         5. VIEW: SCHEDULE (PICKUPS & TABLES)
    =========================================== -->
    <section id="view-schedule" class="view-section">
      <div>
        <span style="font-size:0.75rem; font-weight:800; color:#9a6845; letter-spacing:1px; text-transform:uppercase;">Schedule</span>
        <div class="title">Pickups & tables</div>
        <p style="font-size:0.88rem; color:#8c7a6b; margin-top:4px;">Plan the week ahead and keep an eye on every table in the room.</p>
      </div>

      <div class="filters">
        <button class="pill active" id="sched-pickup-tab" onclick="switchScheduleTab('pickup')">PICKUP · 8</button>
        <button class="pill" id="sched-dinein-tab" onclick="switchScheduleTab('dinein')">RESERVATIONS · LIVE</button>
      </div>

      <div id="schedule-list"></div>
    </section>
  </div>

  <!-- Floating Help Button -->
  <div onclick="alert('Cozy Bake Staff Portal — Hover over the left sidebar to see menu labels.')" style="position:fixed; bottom:24px; right:24px; width:36px; height:36px; background:#5C2C16; color:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; cursor:pointer; z-index:50; box-shadow:0 6px 16px rgba(92,44,22,0.25);">?</div>

  <!-- Log Out Confirmation Modal -->
  <div id="logoutConfirmModal" style="position:fixed; inset:0; background:rgba(32,18,10,0.55); display:none; align-items:center; justify-content:center; z-index:300; padding:16px;">
    <div style="width:min(400px,100%); background:#FDFBF7; border-radius:22px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,0.3);">
      <div style="background:#B58E6B; color:#FFF; padding:22px 26px; display:flex; justify-content:space-between; align-items:center;">
        <div>
          <small style="display:block; font-size:10px; letter-spacing:1.4px; text-transform:uppercase; opacity:0.9;">STAFF SESSION</small>
          <h3 style="font-family:'Instrument Serif',serif; font-size:28px; font-weight:400;">Log out of portal?</h3>
        </div>
        <button type="button" onclick="closeLogoutModal()" style="width:32px; height:32px; border-radius:50%; border:1px solid rgba(255,255,255,0.4); background:transparent; color:#FFF; cursor:pointer; font-size:16px;">✕</button>
      </div>
      <div style="padding:24px 26px;">
        <p style="font-size:14px; color:#8C7A6B; line-height:1.5;">You're on the first page of the Staff Portal. Would you like to log out?</p>
        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
          <button type="button" onclick="closeLogoutModal()" style="background:#EFE5D8; color:#2C1A11; border:none; padding:9px 18px; border-radius:999px; font-size:11px; font-weight:700; letter-spacing:0.8px; text-transform:uppercase; cursor:pointer;">STAY HERE</button>
          <a href="staff_logout.php" style="background:transparent; color:#A93226; border:1px solid #E6B0AA; padding:9px 16px; border-radius:999px; font-size:11px; font-weight:700; text-decoration:none; display:inline-flex; align-items:center;">YES, LOG OUT</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Change Password Modal -->
  <div id="changePasswordModal" style="position:fixed; inset:0; background:rgba(32,18,10,0.55); display:none; align-items:center; justify-content:center; z-index:300; padding:16px;">
    <div style="width:min(440px,100%); background:#FDFBF7; border-radius:22px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,0.3);">
      <div style="background:#B58E6B; color:#FFF; padding:22px 26px; display:flex; justify-content:space-between; align-items:center;">
        <div>
          <small style="display:block; font-size:10px; letter-spacing:1.4px; text-transform:uppercase; opacity:0.9;">STAFF SECURITY</small>
          <h3 style="font-family:'Instrument Serif',serif; font-size:28px; font-weight:400;">Change password</h3>
        </div>
        <button type="button" onclick="closeChangePasswordModal()" style="width:32px; height:32px; border-radius:50%; border:1px solid rgba(255,255,255,0.4); background:transparent; color:#FFF; cursor:pointer; font-size:16px;">✕</button>
      </div>
      <form id="changePasswordForm" style="padding:24px 26px; display:flex; flex-direction:column; gap:14px;">
        <div>
          <label for="currentPassword" style="display:block; font-size:10.5px; font-weight:700; letter-spacing:1.2px; text-transform:uppercase; margin-bottom:6px; color:#2C1A11;">CURRENT PASSWORD</label>
          <input id="currentPassword" type="password" autocomplete="current-password" placeholder="Enter current password" required style="width:100%; padding:12px 14px; border-radius:10px; border:1px solid #DFD3C6; background:#FFF; font-size:14px;">
        </div>
        <div>
          <label for="newPassword" style="display:block; font-size:10.5px; font-weight:700; letter-spacing:1.2px; text-transform:uppercase; margin-bottom:6px; color:#2C1A11;">NEW PASSWORD</label>
          <input id="newPassword" type="password" minlength="8" autocomplete="new-password" placeholder="At least 8 characters" required style="width:100%; padding:12px 14px; border-radius:10px; border:1px solid #DFD3C6; background:#FFF; font-size:14px;">
        </div>
        <div>
          <label for="confirmPassword" style="display:block; font-size:10.5px; font-weight:700; letter-spacing:1.2px; text-transform:uppercase; margin-bottom:6px; color:#2C1A11;">CONFIRM NEW PASSWORD</label>
          <input id="confirmPassword" type="password" minlength="8" autocomplete="new-password" placeholder="Re-enter new password" required style="width:100%; padding:12px 14px; border-radius:10px; border:1px solid #DFD3C6; background:#FFF; font-size:14px;">
        </div>
        <p id="changePasswordMsg" role="alert" style="font-size:12px; margin:2px 0;"></p>
        <button id="changePasswordBtn" type="submit" style="width:100%; background:#B58E6B; color:#FFF; border:none; padding:12px 20px; border-radius:999px; font-size:12px; font-weight:700; letter-spacing:0.8px; text-transform:uppercase; cursor:pointer;">UPDATE PASSWORD</button>
      </form>
    </div>
  </div>

  <script>
    // ==========================================
    // SPA ROUTER + BROWSER BACK BUTTON HISTORY
    // ==========================================
    const STAFF_TITLES = {
      dashboard: 'Dashboard',
      inventory: 'Inventory',
      orders: 'Orders',
      ingredients: 'Ingredients',
      schedule: 'Schedule'
    };
    let currentStaffPage = 'dashboard';

    function openLogoutModal() {
      const m = document.getElementById('logoutConfirmModal');
      if (m) m.style.display = 'flex';
    }
    function closeLogoutModal() {
      const m = document.getElementById('logoutConfirmModal');
      if (m) m.style.display = 'none';
    }
    function openChangePasswordModal() {
      const f = document.getElementById('changePasswordForm');
      const msg = document.getElementById('changePasswordMsg');
      const m = document.getElementById('changePasswordModal');
      if (f) f.reset();
      if (msg) msg.textContent = '';
      if (m) m.style.display = 'flex';
    }
    function closeChangePasswordModal() {
      const m = document.getElementById('changePasswordModal');
      if (m) m.style.display = 'none';
    }

    function closeStaffNotifications() {
      const pop = document.getElementById('staffNotifPopover');
      const btn = document.getElementById('staffNotifBtn');
      if (pop) pop.classList.remove('open');
      if (btn) btn.setAttribute('aria-expanded', 'false');
    }

    function toggleStaffNotifications(e) {
      if (e) e.stopPropagation();
      const pop = document.getElementById('staffNotifPopover');
      const btn = document.getElementById('staffNotifBtn');
      if (!pop) return;
      const isOpen = pop.classList.toggle('open');
      if (btn) btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    let staffNotifications = [];

    function renderStaffNotifications() {
      const alerts = [];

      ordersData
        .filter(o => o.status === 'NEW')
        .forEach(o => {
          alerts.push({
            icon: '🧾',
            title: `New Order #${o.id} — ${o.customer}`,
            details: `${o.summary || 'Order received'} · ${o.total}`,
            action: () => {
              closeStaffNotifications();
              switchPage('orders');
              currentOrderFilter = 'NEW';
              selectedOrderId = o.id;
              document.getElementById('order-page-heading').textContent = orderTitles.NEW;
              renderOrders();
            }
          });
        });

      inventoryData
        .filter(p => Number(p.stock) <= 5)
        .forEach(p => {
          alerts.push({
            icon: Number(p.stock) <= 0 ? '🚫' : '📦',
            title: Number(p.stock) <= 0 ? `Out of Stock: ${p.name}` : `Low Stock: ${p.name}`,
            details: `${p.stock} left in Stock Room (${p.category})`,
            action: () => {
              closeStaffNotifications();
              switchPage('inventory');
              const search = document.getElementById('inv-search');
              if (search) search.value = p.name;
              const cat = document.getElementById('inv-category');
              if (cat) cat.value = 'ALL';
              renderInventory();
            }
          });
        });

      pantryItems
        .filter(i => ['expired', 'expiring', 'low'].includes(i.status))
        .forEach(i => {
          const icon = i.status === 'expired' ? '⛔' : (i.status === 'expiring' ? '⏳' : '⚠️');
          alerts.push({
            icon,
            title: `Pantry (${i.statusText}): ${i.name}`,
            details: `${i.quantity} · ${i.category}`,
            action: () => {
              closeStaffNotifications();
              switchPage('ingredients');
              filterIngredientsByStat(i.status);
            }
          });
        });

      staffNotifications = alerts;
      const badge = document.getElementById('staffNotifBadge');
      const summary = document.getElementById('staffNotifSummary');
      const list = document.getElementById('staffNotifList');

      if (badge) {
        badge.textContent = alerts.length > 99 ? '99+' : String(alerts.length);
        badge.style.display = alerts.length ? 'flex' : 'none';
      }
      if (summary) {
        summary.textContent = alerts.length ? `${alerts.length} active` : 'All clear';
      }
      if (!list) return;

      if (!alerts.length) {
        list.innerHTML = '<div class="staff-notif-item" style="cursor:default;"><div class="staff-notif-icon">✓</div><div class="staff-notif-body"><h5>You’re all caught up</h5><p>No new orders, low-stock items, or pantry alerts right now.</p></div></div>';
        return;
      }

      list.innerHTML = alerts.map((a, idx) => `
        <button type="button" class="staff-notif-item" onclick="handleStaffNotification(${idx})">
          <div class="staff-notif-icon">${a.icon}</div>
          <div class="staff-notif-body">
            <h5>${escapeScheduleText(a.title)}</h5>
            <p>${escapeScheduleText(a.details)}</p>
          </div>
        </button>
      `).join('');
    }

    function handleStaffNotification(idx) {
      const item = staffNotifications[idx];
      if (item && typeof item.action === 'function') item.action();
    }

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeLogoutModal();
        closeChangePasswordModal();
        closeStaffNotifications();
      }
    });
    document.addEventListener('click', (e) => {
      if (e.target && e.target.id === 'logoutConfirmModal') closeLogoutModal();
      if (e.target && e.target.id === 'changePasswordModal') closeChangePasswordModal();
      const pop = document.getElementById('staffNotifPopover');
      if (pop && pop.classList.contains('open') && !e.target.closest('#staffNotifPopover') && !e.target.closest('#staffNotifBtn')) {
        closeStaffNotifications();
      }
    });

    document.getElementById('changePasswordForm').addEventListener('submit', async function (e) {
      e.preventDefault();
      const msg = document.getElementById('changePasswordMsg');
      const btn = document.getElementById('changePasswordBtn');
      const current_password = document.getElementById('currentPassword').value;
      const new_password = document.getElementById('newPassword').value;
      const confirm_password = document.getElementById('confirmPassword').value;
      if (new_password.length < 8) { msg.style.color = '#922B21'; msg.textContent = 'New password must be at least 8 characters.'; return; }
      if (new_password !== confirm_password) { msg.style.color = '#922B21'; msg.textContent = 'New password and confirmation do not match.'; return; }
      btn.disabled = true;
      msg.style.color = '#8C7A6B';
      msg.textContent = 'Updating password…';
      try {
        const res = await fetch('staff_change_password.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ current_password, new_password, confirm_password })
        });
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.message || 'Could not update password.');
        msg.style.color = '#286646';
        msg.textContent = data.message || 'Password updated successfully.';
        this.reset();
        setTimeout(closeChangePasswordModal, 1000);
      } catch (err) {
        msg.style.color = '#922B21';
        msg.textContent = err.message || 'Could not update password.';
      } finally {
        btn.disabled = false;
      }
    });

    function switchPage(pageId, pushHistory = true) {
      if (!STAFF_TITLES[pageId]) pageId = 'dashboard';
      document.querySelectorAll('.view-section').forEach(s => s.classList.remove('active'));
      document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));

      document.getElementById('view-' + pageId).classList.add('active');
      const activeBtn = document.getElementById('nav-' + pageId);
      if (activeBtn) activeBtn.classList.add('active');

      document.getElementById('topbar-title').textContent = STAFF_TITLES[pageId] || 'Dashboard';
      if (pushHistory && pageId !== currentStaffPage) {
        history.pushState({ page: pageId }, '', '#' + pageId);
      }
      currentStaffPage = pageId;
      lucide.createIcons();
    }

    window.addEventListener('popstate', (e) => {
      if (e.state && e.state.guard) {
        history.pushState({ page: currentStaffPage }, '', '#' + currentStaffPage);
        openLogoutModal();
        return;
      }
      const hashPage = location.hash.replace('#', '');
      const pageId = (e.state && e.state.page) || (STAFF_TITLES[hashPage] ? hashPage : 'dashboard');
      switchPage(pageId, false);
    });

    (function initStaffHistory() {
      const hashPage = location.hash.replace('#', '');
      const initialPage = STAFF_TITLES[hashPage] ? hashPage : 'dashboard';
      history.replaceState({ guard: true }, '', '#' + initialPage);
      history.pushState({ page: initialPage }, '', '#' + initialPage);
      if (initialPage !== 'dashboard') switchPage(initialPage, false);
    })();

    function openOrdersWithFilter(filterName) {
      switchPage('orders');
      setOrderFilter(filterName);
    }

    // ==========================================
    // DATA & LOGIK 1: INVENTORY (LIVE MYSQL)
    // ==========================================
    let inventoryData = [];

    function syncInventoryCategories() {
      const sel = document.getElementById('inv-category');
      if (!sel) return;
      const current = sel.value || 'ALL';
      const cats = [...new Set(inventoryData.map(i => i.category).filter(Boolean))].sort((a, b) => a.localeCompare(b));
      sel.innerHTML = '<option value="ALL">All Categories</option>' + cats.map(c => `<option value="${escapeScheduleText(c)}">${escapeScheduleText(c)}</option>`).join('');
      if (current === 'ALL' || cats.includes(current)) sel.value = current;
    }

    function renderInventory() {
      const q = document.getElementById('inv-search').value.toLowerCase().trim();
      const cat = document.getElementById('inv-category').value;
      const filtered = inventoryData.filter(i => (i.name.toLowerCase().includes(q) || i.category.toLowerCase().includes(q)) && (cat === 'ALL' || i.category === cat));
      document.getElementById('inv-count-badge').textContent = `${filtered.length} item${filtered.length === 1 ? '' : 's'}`;

      if (!filtered.length) {
        document.getElementById('inventory-list').innerHTML = '<div class="p-6 text-center text-sm text-cozy-textMuted">No inventory products found.</div>';
        return;
      }

      document.getElementById('inventory-list').innerHTML = filtered.map(item => {
        const isLow = item.stock <= 5;
        const imgHtml = item.img
          ? `<img src="${escapeScheduleText(item.img)}" alt="${escapeScheduleText(item.name)}" class="w-14 h-14 rounded-2xl object-cover bg-cozy-bg" onerror="this.style.display='none'">`
          : `<div class="w-14 h-14 rounded-2xl bg-cozy-bg flex items-center justify-center text-xl">🥐</div>`;
        return `
          <div class="flex items-center justify-between p-3.5 rounded-2xl border border-gray-100 bg-white hover:border-gray-300 transition">
            <div class="flex items-center space-x-4">
              ${imgHtml}
              <div>
                <h3 class="font-bold text-base">${escapeScheduleText(item.name)}</h3>
                <p class="text-xs text-cozy-textMuted">${escapeScheduleText(item.category)}</p>
              </div>
            </div>
            <div class="flex items-center space-x-4">
              <span class="px-3 py-1 rounded-full text-[11px] font-extrabold ${isLow ? 'bg-[#FCEBEA] text-[#D9534F]' : 'bg-[#EAF4E8] text-[#4A7C43]'}">
                ${item.stock <= 0 ? 'OUT OF STOCK' : (isLow ? 'LOW STOCK' : 'IN STOCK')}
              </span>
              <div class="flex items-center space-x-3">
                <button onclick="updateStock(${Number(item.id)}, -1)" class="w-8 h-8 rounded-full border border-gray-200 flex items-center justify-center hover:bg-gray-100 font-bold">-</button>
                <span class="w-8 text-center font-bold">${Number(item.stock)}</span>
                <button onclick="updateStock(${Number(item.id)}, 1)" class="w-8 h-8 rounded-full bg-cozy-wood text-white flex items-center justify-center hover:opacity-90 font-bold">+</button>
              </div>
            </div>
          </div>
        `;
      }).join('');
    }

    async function updateStock(id, delta) {
      const item = inventoryData.find(p => Number(p.id) === Number(id));
      if (!item || item.stock + delta < 0) return;
      try {
        const response = await fetch('staff_action.php', {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'update_stock', product_id: Number(id), delta: Number(delta) })
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Stock update failed.');
        item.stock += delta;
        renderInventory();
        renderStaffNotifications();
        loadDashboardOverview();
      } catch (error) {
        alert('Could not update stock: ' + error.message);
      }
    }

    // ==========================================
    // DATA & LOGIK 2: ORDERS
    // ==========================================
    // Orders are loaded from MySQL through staff_data.php (no demo orders).
    let ordersData = [];
    let currentOrderFilter = 'ALL';
    let selectedOrderId = null;

    const orderTitles = {
      ALL: 'Live orders', NEW: 'New orders', PREPARING: 'Preparing orders',
      READY: 'Ready orders', COMPLETED: 'Completed orders'
    };

    function money(value) {
      return 'RM ' + Number(value || 0).toFixed(2);
    }

    async function loadDatabaseOrders() {
      const listEl = document.getElementById('orders-list');
      listEl.innerHTML = '<div style="background:#fff;padding:24px;border-radius:14px;text-align:center;color:#8c7a6b;">Loading orders...</div>';
      try {
        const response = await fetch('staff_data.php', { credentials: 'same-origin', cache: 'no-store' });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Could not load orders.');
        inventoryData = (data.inventory || []).map(p => ({
          id: Number(p.product_id),
          name: p.product_name || 'Product',
          category: p.category || 'Uncategorized',
          stock: Number(p.product_quantity || 0),
          img: p.image_path || ''
        }));
        syncInventoryCategories();
        renderInventory();
        ordersData = (data.orders || []).map(row => {
          const status = String(row.status || 'NEW').toUpperCase();
          const validStatus = ['NEW','PREPARING','READY','COMPLETED'].includes(status) ? status : 'NEW';
          const items = (row.items || []).map(item => ({
            name: `${item.quantity} × ${item.product_name || 'Product'}`,
            price: money(Number(item.line_total || Number(item.quantity) * Number(item.unit_price)))
          }));
          return {
            id: String(row.order_id), time: row.order_time || 'Time not recorded',
            customer: row.customer_name || 'Customer', summary: row.summary || 'No items listed',
            total: money(row.total), status: validStatus,
            channel: row.order_type || 'Not specified',
            pickupDatetime: row.pickup_datetime || '', outlet: row.outlet || '',
            payment: row.payment_method || 'Not recorded',
            note: row.special_instructions || '', items
          };
        });
        if (!ordersData.some(o => o.id === selectedOrderId)) selectedOrderId = ordersData.length ? ordersData[0].id : null;
        renderOrders();
        renderStaffNotifications();
        if (document.getElementById('sched-pickup-tab')?.classList.contains('active')) renderPickupSchedule();
      } catch (error) {
        listEl.innerHTML = `<div style="background:#fff;padding:24px;border-radius:14px;color:#a33;">Unable to load database orders: ${escapeScheduleText(error.message)}</div>`;
      }
    }

    function setOrderFilter(filter) {
      currentOrderFilter = filter;
      document.getElementById('order-page-heading').textContent = orderTitles[filter];
      const list = filter === 'ALL' ? ordersData : ordersData.filter(o => o.status === filter);
      if (list.length && !list.some(o => o.id === selectedOrderId)) selectedOrderId = list[0].id;
      renderOrders();
    }

    function selectOrder(id) {
      selectedOrderId = id;
      renderOrders();
    }

    async function advanceOrderStatus(id) {
      const flow = ['NEW', 'PREPARING', 'READY', 'COMPLETED'];
      const ord = ordersData.find(o => o.id === String(id));
      if (!ord) return;
      const nextIdx = flow.indexOf(ord.status) + 1;
      if (nextIdx >= flow.length) return;
      const nextStatus = flow[nextIdx];
      try {
        const response = await fetch('staff_action.php', {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'update_order_status', order_id: Number(id), status: nextStatus })
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Status update failed.');
        await loadDatabaseOrders();
        await loadDashboardOverview();
      } catch (error) {
        alert('Could not update order status: ' + error.message);
      }
    }

    function renderOrders() {
      const counts = {
        ALL: ordersData.length,
        NEW: ordersData.filter(o => o.status === 'NEW').length,
        PREPARING: ordersData.filter(o => o.status === 'PREPARING').length,
        READY: ordersData.filter(o => o.status === 'READY').length,
        COMPLETED: ordersData.filter(o => o.status === 'COMPLETED').length
      };
      document.getElementById('dash-active-orders').textContent = counts.NEW + counts.PREPARING + counts.READY;
      document.getElementById('order-filters').innerHTML = Object.keys(counts).map(k =>
        `<button class="pill ${currentOrderFilter === k ? 'active' : ''}" onclick="setOrderFilter('${k}')">${k} · ${counts[k]}</button>`
      ).join('');
      const filtered = currentOrderFilter === 'ALL' ? ordersData : ordersData.filter(o => o.status === currentOrderFilter);
      const listEl = document.getElementById('orders-list');
      if (!filtered.length) {
        listEl.innerHTML = '<div style="background:#fff;padding:24px;border-radius:14px;text-align:center;color:#8c7a6b;">No orders for this status.</div>';
      } else {
        listEl.innerHTML = filtered.map(o => `
          <div class="order-card ${o.id === selectedOrderId ? 'selected' : ''}" onclick="selectOrder('${escapeScheduleText(o.id)}')">
            <div style="display:flex;gap:12px;align-items:center;">
              <div style="width:40px;height:40px;background:#f7ebe1;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#9a6845;"><i data-lucide="receipt" style="width:18px;height:18px;"></i></div>
              <div><h4 style="font-size:0.95rem;font-weight:700;">#${escapeScheduleText(o.id)} <span style="font-size:0.75rem;color:#8c7a6b;font-weight:500;">${escapeScheduleText(o.time)}</span></h4>
              <div style="font-size:0.85rem;color:#555;">${escapeScheduleText(o.customer)}</div><div style="font-size:0.78rem;color:#8c7a6b;">${escapeScheduleText(o.summary)}</div></div>
            </div><div style="text-align:right;"><div style="font-weight:700;">${escapeScheduleText(o.total)}</div><span class="badge badge-${escapeScheduleText(o.status)}">${escapeScheduleText(o.status)}</span></div>
          </div>`).join('');
      }
      const ord = ordersData.find(o => o.id === selectedOrderId);
      const panel = document.getElementById('order-detail-panel');
      if (!ord) {
        panel.innerHTML = '<p style="color:#8c7a6b;">No order selected.</p>';
      } else {
        const stepCount = { NEW: 1, PREPARING: 2, READY: 3, COMPLETED: 4 }[ord.status];
        const nextLabel = { NEW: 'Move to Preparing', PREPARING: 'Move to Ready', READY: 'Move to Completed', COMPLETED: 'Order Completed' }[ord.status];
        panel.innerHTML = `
          <div style="display:flex;justify-content:space-between;align-items:flex-start;"><div><span style="font-size:0.68rem;font-weight:800;color:#8c7a6b;text-transform:uppercase;">Order Details</span><div class="title" style="font-size:2.4rem;">#${escapeScheduleText(ord.id)}</div></div><span class="badge badge-${escapeScheduleText(ord.status)}">${escapeScheduleText(ord.status)}</span></div>
          <div class="info-grid"><div class="info-box"><span>Customer</span><b>${escapeScheduleText(ord.customer)}</b></div><div class="info-box"><span>Placed At</span><b>${escapeScheduleText(ord.time)}</b></div><div class="info-box"><span>Channel</span><b>${escapeScheduleText(ord.channel)}</b></div><div class="info-box"><span>Payment</span><b>${escapeScheduleText(ord.payment)}</b></div></div>
          <div style="border-top:1px dashed var(--border);padding-top:12px;font-size:0.9rem;"><div style="font-size:0.72rem;font-weight:800;color:#9a6845;text-transform:uppercase;margin-bottom:8px;">Items</div>
          ${ord.items.map(i => `<div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span>${escapeScheduleText(i.name)}</span><span>${escapeScheduleText(i.price)}</span></div>`).join('')}
          <div style="display:flex;justify-content:space-between;font-weight:700;font-size:1.05rem;margin-top:10px;border-top:1px solid var(--border);padding-top:10px;"><span>Total</span><span>${escapeScheduleText(ord.total)}</span></div></div>
          ${ord.note ? `<div style="background:#fff8d6;border-radius:14px;padding:12px 16px;margin:14px 0;"><div style="font-size:0.72rem;font-weight:800;color:#9a6845;text-transform:uppercase;margin-bottom:4px;">Kitchen Note</div><div style="font-size:0.88rem;color:#554433;">${escapeScheduleText(ord.note)}</div></div>` : ''}
          <div class="progress-tracker">${[1,2,3,4].map(s => `<div class="progress-step ${s <= stepCount ? 'fill' : ''}"></div>`).join('')}</div>
          <div style="display:flex;justify-content:space-between;font-size:0.65rem;font-weight:700;color:#8c7a6b;text-transform:uppercase;margin-bottom:15px;"><span>New</span><span>Preparing</span><span>Ready</span><span>Completed</span></div>
          ${ord.status === 'COMPLETED' ? '<button class="btn-disabled" disabled>Order Completed</button>' : `<button class="btn-action" onclick="advanceOrderStatus('${escapeScheduleText(ord.id)}')">${nextLabel}</button>`}`;
      }
      lucide.createIcons();
    }

    // ==========================================
    // DATA & LOGIK 3: INGREDIENTS & RECIPES (LIVE MYSQL)
    // ==========================================
    let pantryItems = [];
    let recipeItems = [];

    let ingTab = 'pantry';
    let ingFilter = 'all';

    async function loadIngredients() {
      try {
        const res = await fetch('staff_ingredients.php', { credentials: 'same-origin', cache: 'no-store' });
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.message || 'Could not load pantry data.');
        pantryItems = (data.ingredients || []).map(i => ({
          name: i.name || '',
          category: i.category || 'General',
          quantity: i.quantity_display || String(i.quantity || ''),
          status: i.status || 'ok',
          statusText: i.status_text || 'In Stock'
        }));
        recipeItems = (data.recipes || []).map(r => ({
          name: r.name || '',
          category: r.category || 'General',
          ingredientsCount: Number(r.ingredients_count || 0),
          prepTime: r.prep_time || '—'
        }));
        const st = data.stats || {};
        document.getElementById('ing-stat-total').textContent = st.total ?? pantryItems.length;
        document.getElementById('ing-stat-low').textContent = st.low ?? 0;
        document.getElementById('ing-stat-expiring').textContent = st.expiring ?? 0;
        document.getElementById('ing-stat-expired').textContent = st.expired ?? 0;
        document.getElementById('tab-recipes').textContent = `PRODUCT RECIPES · ${st.recipe_count ?? recipeItems.length}`;
        renderIngredients();
        renderStaffNotifications();
      } catch (error) {
        console.error('Staff ingredients:', error);
        document.getElementById('ingredients-list-container').innerHTML = `<div class="bg-white p-4 rounded-2xl text-red-700 text-sm">Could not load pantry data: ${escapeScheduleText(error.message)}</div>`;
      }
    }

    function switchIngredientTab(tab) {
      ingTab = tab;
      document.getElementById('tab-pantry').classList.toggle('active', tab === 'pantry');
      document.getElementById('tab-recipes').classList.toggle('active', tab === 'recipes');
      renderIngredients();
    }

    function filterIngredientsByStat(stat) {
      ingFilter = stat;
      switchIngredientTab('pantry');
    }

    function renderIngredients() {
      const container = document.getElementById('ingredients-list-container');
      const q = document.getElementById('ing-search').value.toLowerCase();

      if (ingTab === 'pantry') {
        const filtered = pantryItems.filter(i => (i.name.toLowerCase().includes(q) || i.category.toLowerCase().includes(q)) && (ingFilter === 'all' || i.status === ingFilter));
        if (!filtered.length) {
          container.innerHTML = '<div class="bg-white p-6 rounded-2xl text-center text-sm text-cozy-textMuted">No pantry ingredients match this filter.</div>';
          return;
        }
        container.innerHTML = filtered.map(item => {
          let badgeBg = "bg-green-100 text-green-800";
          if (item.status === 'low') badgeBg = "bg-amber-100 text-amber-800";
          if (item.status === 'expiring') badgeBg = "bg-orange-100 text-orange-800";
          if (item.status === 'expired') badgeBg = "bg-red-100 text-red-800";
          return `
            <div class="bg-white p-4 rounded-2xl flex items-center justify-between shadow-sm">
              <div>
                <h4 class="font-bold text-base">${escapeScheduleText(item.name)}</h4>
                <span class="text-xs text-cozy-textMuted">${escapeScheduleText(item.category)}</span>
              </div>
              <div class="flex items-center space-x-4">
                <span class="px-3 py-1 rounded-full text-xs font-bold ${badgeBg}">${escapeScheduleText(item.statusText)}</span>
                <span class="font-black text-cozy-darkCard text-lg w-24 text-right">${escapeScheduleText(item.quantity)}</span>
              </div>
            </div>
          `;
        }).join('');
      } else {
        const filtered = recipeItems.filter(i => i.name.toLowerCase().includes(q));
        if (!filtered.length) {
          container.innerHTML = '<div class="bg-white p-6 rounded-2xl text-center text-sm text-cozy-textMuted">No product recipes found.</div>';
          return;
        }
        container.innerHTML = filtered.map(item => `
          <div class="bg-white p-4 rounded-2xl flex items-center justify-between shadow-sm">
            <div>
              <h4 class="font-bold text-base">${escapeScheduleText(item.name)}</h4>
              <span class="text-xs text-cozy-textMuted">${escapeScheduleText(item.category)} · Prep: ${escapeScheduleText(item.prepTime)}</span>
            </div>
            <span class="font-extrabold text-xs bg-cozy-bg px-3 py-1.5 rounded-full">${Number(item.ingredientsCount)} Ingredients Required</span>
          </div>
        `).join('');
      }
    }

    // ==========================================
    // DATA & LOGIK 4: SCHEDULE
    // ==========================================
    let tableBookings = [];

    function escapeScheduleText(value) {
      return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
      }[char]));
    }

    function formatScheduleDateTime(value) {
      if (!value) return { day: '—', date: 'Date not set', time: 'Time not set' };
      const parsed = new Date(String(value).replace(' ', 'T'));
      if (Number.isNaN(parsed.getTime())) return { day: '—', date: String(value), time: '' };
      return {
        day: parsed.toLocaleDateString('en-MY', { weekday: 'short' }),
        date: parsed.toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }),
        time: parsed.toLocaleTimeString('en-MY', { hour: 'numeric', minute: '2-digit', hour12: true })
      };
    }

    function renderSchedule(items, mode) {
      const container = document.getElementById('schedule-list');
      if (!items.length) {
        container.innerHTML = `<div class="bg-white p-5 rounded-2xl text-sm text-cozy-textMuted">No ${mode === 'dinein' ? 'dining reservations' : 'pick-up orders'} have been recorded yet.</div>`;
        return;
      }
      container.innerHTML = items.map(item => `
        <div class="schedule-row" onclick="this.classList.toggle('expanded')">
          <b>${escapeScheduleText(item.day)}</b>
          <span style="color:#8c7a6b;">${escapeScheduleText(item.date)}</span>
          <b style="color:#9d431b;">${escapeScheduleText(item.time)}</b>
          <b>${escapeScheduleText(item.customer)}</b>
          <span style="color:#8c7a6b;">${escapeScheduleText(item.order)}</span>
          <span class="badge badge-${mode === 'dinein' ? 'table' : 'everyday'}" style="text-align:center;">${escapeScheduleText(mode === 'dinein' ? item.status : item.status)}</span>
          <div class="row-details">
            ${escapeScheduleText(item.details || '')}
            ${mode === 'dinein' ? `<div style="margin-top:10px;display:flex;align-items:center;gap:8px;" onclick="event.stopPropagation()"><label for="reservation-status-${item.reservation_id}">Reservation status:</label><select id="reservation-status-${item.reservation_id}" onchange="updateReservationStatus(${Number(item.reservation_id)}, this.value)" style="padding:7px 10px;border:1px solid #d8c6b7;border-radius:8px;background:white;"><option value="Pending" ${item.status === 'Pending' ? 'selected' : ''}>Pending</option><option value="Confirmed" ${item.status === 'Confirmed' ? 'selected' : ''}>Confirmed</option><option value="Cancelled" ${item.status === 'Cancelled' ? 'selected' : ''}>Cancelled</option><option value="Completed" ${item.status === 'Completed' ? 'selected' : ''}>Completed</option></select></div>` : ''}
          </div>
        </div>
      `).join('');
    }

    function renderPickupSchedule() {
      const pickupRows = ordersData
        .filter(order => String(order.channel || '').toLowerCase().includes('pickup'))
        .map(order => {
          const dt = formatScheduleDateTime(order.pickupDatetime);
          return {
            day: dt.day, date: dt.date, time: dt.time,
            customer: order.customer,
            order: `${order.summary} · ${order.outlet || 'Outlet not specified'}`,
            status: order.status,
            details: `Order #${order.id} · ${order.channel} · Status: ${order.status}${order.note ? ` · Note: ${order.note}` : ''}`
          };
        });
      document.getElementById('sched-pickup-tab').textContent = `PICKUP · ${pickupRows.length}`;
      renderSchedule(pickupRows, 'pickup');
    }

    async function loadStaffReservations() {
      const container = document.getElementById('schedule-list');
      container.innerHTML = '<div class="bg-white p-5 rounded-2xl text-sm text-cozy-textMuted">Loading reservations…</div>';
      try {
        const response = await fetch('staff_reservations.php', { credentials: 'same-origin', cache: 'no-store' });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Unable to load reservations.');
        tableBookings = (data.reservations || []).map(item => {
          const dt = formatScheduleDateTime(`${item.date} ${item.time || '00:00:00'}`);
          return {
            reservation_id: Number(item.reservation_id),
            day: dt.day, date: dt.date, time: dt.time,
            customer: item.customer_name || 'Customer',
            order: `${item.guests} guest(s) · ${item.table_name} · ${item.outlet}`,
            status: item.status || 'Pending',
            details: `Reservation ${item.reservation_code} · Phone: ${item.phone || 'Not provided'} · Status: ${item.status}${item.special_request ? ` · Note: ${item.special_request}` : ''}`
          };
        });
        document.getElementById('sched-dinein-tab').textContent = `DINE-IN · ${tableBookings.length}`;
        if (document.getElementById('sched-dinein-tab').classList.contains('active')) renderSchedule(tableBookings, 'dinein');
      } catch (error) {
        container.innerHTML = `<div class="bg-white p-5 rounded-2xl text-sm text-red-700">${escapeScheduleText(error.message)} Please refresh the page or contact the administrator.</div>`;
      }
    }

    async function updateReservationStatus(reservationId, status) {
      try {
        const response = await fetch('staff_reservations.php', {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ reservation_id: reservationId, status })
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Could not update reservation status.');
        await loadStaffReservations();
      } catch (error) {
        alert('Reservation status was not saved: ' + error.message);
        await loadStaffReservations();
      }
    }

    function switchScheduleTab(mode) {
      document.getElementById('sched-pickup-tab').classList.toggle('active', mode === 'pickup');
      document.getElementById('sched-dinein-tab').classList.toggle('active', mode === 'dinein');
      if (mode === 'pickup') {
        renderPickupSchedule();
        loadDatabaseOrders();
      } else {
        loadStaffReservations();
      }
    }

    async function loadDashboardOverview() {
      const body = document.getElementById('dash-upcoming-list');
      try {
        const response = await fetch('staff_dashboard_stats.php', { credentials: 'same-origin', cache: 'no-store' });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Dashboard data unavailable');
        document.getElementById('dash-active-orders').textContent = data.stats.active_orders;
        document.getElementById('dash-completed-orders').textContent = data.stats.completed_today;
        document.getElementById('dash-low-stock').textContent = data.stats.low_stock + ' Items';
        document.getElementById('dash-upcoming-pickups').textContent = data.stats.upcoming_pickups + ' Orders';
        if (!data.upcoming_pickups.length) {
          body.innerHTML = '<tr><td colspan="4" class="py-4 px-2 text-cozy-textMuted">No upcoming pickup orders.</td></tr>';
          return;
        }
        body.innerHTML = data.upcoming_pickups.map(order => {
          const tr = document.createElement('tr');
          const values = [order.order_label, order.customer_name, order.pickup_display, order.status];
          values.forEach(value => { const td = document.createElement('td'); td.className = 'py-3.5 px-2'; td.textContent = value || '—'; tr.appendChild(td); });
          return tr.outerHTML;
        }).join('');
      } catch (error) {
        body.innerHTML = '<tr><td colspan="4" class="py-4 px-2 text-red-700">Could not load dashboard data. Check staff_dashboard_stats.php.</td></tr>';
        console.error(error);
      }
    }

    // ==========================================
    // INITIALIZE SEMUA VIEW SEMASA MULA DIBUKA
    // ==========================================
    window.addEventListener('DOMContentLoaded', () => {
      loadDashboardOverview();
      loadDatabaseOrders();
      loadIngredients();
      renderPickupSchedule();
      lucide.createIcons();
    });
  </script>
</body>
</html>