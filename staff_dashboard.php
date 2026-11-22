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
    <button class="nav-btn logout" onclick="openLogoutModal()">
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
        <div style="position:relative; width:38px; height:38px; background:#ebdcd0; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer;">
          <i data-lucide="bell" style="width:18px; height:18px;"></i>
          <span style="position:absolute; top:-2px; right:-2px; background:#c0392b; color:#fff; font-size:0.6rem; width:15px; height:15px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700;">3</span>
        </div>
        <div style="width:38px; height:38px; background:var(--wood); color:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700;">A</div>
      </div>
    </header>

    <!-- ==========================================
         1. VIEW: DASHBOARD
    =========================================== -->
    <section id="view-dashboard" class="view-section active">
      <div>
        <span style="font-size:0.75rem; font-weight:800; color:#9a6845; letter-spacing:1px; text-transform:uppercase;">Staff Portal</span>
        <div class="title">Welcome back, Aina!</div>
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
          <div class="text-4xl font-black my-2">55</div>
          <p class="text-xs text-white/70">Tracked in the pantry</p>
        </div>
        <div onclick="filterIngredientsByStat('low')" class="kpi-card bg-cozy-orangeCard text-white p-5 rounded-3xl cursor-pointer">
          <h3 class="text-xs font-extrabold text-white/85 uppercase">LOW STOCK</h3>
          <div class="text-4xl font-black my-2">9</div>
          <p class="text-xs text-white/85">Below par level</p>
        </div>
        <div onclick="filterIngredientsByStat('expiring')" class="kpi-card bg-white p-5 rounded-3xl cursor-pointer">
          <h3 class="text-xs font-extrabold text-cozy-textMuted uppercase">EXPIRING SOON</h3>
          <div class="text-4xl font-black text-cozy-darkCard my-2">3</div>
          <p class="text-xs text-cozy-textMuted">Use within 5 days</p>
        </div>
        <div onclick="filterIngredientsByStat('expired')" class="kpi-card bg-white p-5 rounded-3xl cursor-pointer">
          <h3 class="text-xs font-extrabold text-cozy-textMuted uppercase">EXPIRED</h3>
          <div class="text-4xl font-black text-cozy-darkCard my-2">4</div>
          <p class="text-xs text-cozy-textMuted">Remove from the line</p>
        </div>
      </div>

      <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <button id="tab-pantry" onclick="switchIngredientTab('pantry')" class="pill active">PANTRY STOCK</button>
          <button id="tab-recipes" onclick="switchIngredientTab('recipes')" class="pill">PRODUCT RECIPES · 4</button>
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

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeLogoutModal();
    });
    document.addEventListener('click', (e) => {
      if (e.target && e.target.id === 'logoutConfirmModal') closeLogoutModal();
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
    // DATA & LOGIK 1: INVENTORY
    // ==========================================
    const inventoryData = [
      { id: 1, name: "Strawberry Cloud", category: "Cake", stock: 8, img: "https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=150&auto=format&fit=crop&q=80" },
      { id: 2, name: "Morning Croissant", category: "Savory", stock: 23, img: "https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=150&auto=format&fit=crop&q=80" },
      { id: 3, name: "Pistachio Stack", category: "Sweets", stock: 14, img: "https://images.unsplash.com/photo-1509440159596-0249088772ff?w=150&auto=format&fit=crop&q=80" },
      { id: 4, name: "Country Rolls", category: "Bread", stock: 4, img: "https://images.unsplash.com/photo-1586444248902-2f64eddc13df?w=150&auto=format&fit=crop&q=80" },
      { id: 5, name: "Sourdough Loaf", category: "Bread", stock: 12, img: "https://images.unsplash.com/photo-1589367920969-ab8e050bbc04?w=150&auto=format&fit=crop&q=80" },
      { id: 6, name: "Chocolate Layer Cake", category: "Cake", stock: 6, img: "https://images.unsplash.com/photo-1578985545062-69928b1d9587?w=150&auto=format&fit=crop&q=80" },
      { id: 7, name: "French Macarons", category: "Sweets", stock: 20, img: "https://images.unsplash.com/photo-1569864358642-9d1684040f43?w=150&auto=format&fit=crop&q=80" },
      { id: 8, name: "Berry Tart", category: "Tart", stock: 9, img: "https://images.unsplash.com/photo-1519869325930-281384150729?w=150&auto=format&fit=crop&q=80" },
      { id: 9, name: "Cheesecake Slice", category: "Cake", stock: 15, img: "https://images.unsplash.com/photo-1533134242443-d4fd215305ad?w=150&auto=format&fit=crop&q=80" },
      { id: 10, name: "Baguette", category: "Bread", stock: 18, img: "https://images.unsplash.com/photo-1597079910443-60c43fc4f729?w=150&auto=format&fit=crop&q=80" },
      { id: 11, name: "Rainbow Donut Box", category: "Sweets", stock: 7, img: "https://images.unsplash.com/photo-1527515637462-cff94eecc1ac?w=150&auto=format&fit=crop&q=80" },
      { id: 12, name: "Glazed Donut", category: "Sweets", stock: 24, img: "https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=150&auto=format&fit=crop&q=80" },
      { id: 13, name: "Fudge Brownies", category: "Sweets", stock: 16, img: "https://images.unsplash.com/photo-1606313564200-e75d5e30476c?w=150&auto=format&fit=crop&q=80" },
      { id: 14, name: "Apple Pie", category: "Tart", stock: 5, img: "https://images.unsplash.com/photo-1621743478914-cc8a86d7e7b5?w=150&auto=format&fit=crop&q=80" },
      { id: 15, name: "Fresh Scones", category: "Savory", stock: 14, img: "https://images.unsplash.com/photo-1589119908995-c6837fa14848?w=150&auto=format&fit=crop&q=80" },
      { id: 16, name: "Choc Chip Cookies", category: "Sweets", stock: 22, img: "https://images.unsplash.com/photo-1499636136210-6f4ee915583e?w=150&auto=format&fit=crop&q=80" },
      { id: 17, name: "Chocolate Cupcake", category: "Cake", stock: 19, img: "https://images.unsplash.com/photo-1576618148400-f54bed99fcfd?w=150&auto=format&fit=crop&q=80" },
      { id: 18, name: "Rustic Boule", category: "Bread", stock: 8, img: "https://images.unsplash.com/photo-1549931319-a545dcf3bc73?w=150&auto=format&fit=crop&q=80" },
      { id: 19, name: "Salted Caramel Cookies", category: "Cookies", stock: 18, img: "https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=150&auto=format&fit=crop&q=80" },
      { id: 20, name: "Dark Chocolate Fudge Cookies", category: "Cookies", stock: 16, img: "https://images.unsplash.com/photo-1618923850107-d1a234d7a73a?w=150&auto=format&fit=crop&q=80" },
      { id: 21, name: "Blueberry Bliss Cookies", category: "Cookies", stock: 14, img: "https://images.unsplash.com/photo-1590080875515-8a3a8dc5735e?w=150&auto=format&fit=crop&q=80" },
      { id: 22, name: "Red Velvet with White Chocolate Cookies", category: "Cookies", stock: 15, img: "https://images.unsplash.com/photo-1619149651177-b09092806f1a?w=150&auto=format&fit=crop&q=80" },
      { id: 23, name: "Matcha Oatmeal Chocolate Chip", category: "Cookies", stock: 13, img: "https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=150&auto=format&fit=crop&q=80" }
    ];

    function renderInventory() {
      const q = document.getElementById('inv-search').value.toLowerCase().trim();
      const cat = document.getElementById('inv-category').value;
      const filtered = inventoryData.filter(i => (i.name.toLowerCase().includes(q) || i.category.toLowerCase().includes(q)) && (cat === 'ALL' || i.category === cat));
      document.getElementById('inv-count-badge').textContent = `${filtered.length} items`;

      document.getElementById('inventory-list').innerHTML = filtered.map(item => {
        const isLow = item.stock <= 5;
        return `
          <div class="flex items-center justify-between p-3.5 rounded-2xl border border-gray-100 bg-white hover:border-gray-300 transition">
            <div class="flex items-center space-x-4">
              <img src="${item.img}" alt="${item.name}" class="w-14 h-14 rounded-2xl object-cover bg-cozy-bg">
              <div>
                <h3 class="font-bold text-base">${item.name}</h3>
                <p class="text-xs text-cozy-textMuted">${item.category}</p>
              </div>
            </div>
            <div class="flex items-center space-x-4">
              <span class="px-3 py-1 rounded-full text-[11px] font-extrabold ${isLow ? 'bg-[#FCEBEA] text-[#D9534F]' : 'bg-[#EAF4E8] text-[#4A7C43]'}">
                ${isLow ? 'LOW STOCK' : 'IN STOCK'}
              </span>
              <div class="flex items-center space-x-3">
                <button onclick="updateStock(${item.id}, -1)" class="w-8 h-8 rounded-full border border-gray-200 flex items-center justify-center hover:bg-gray-100 font-bold">-</button>
                <span class="w-6 text-center font-bold">${item.stock}</span>
                <button onclick="updateStock(${item.id}, 1)" class="w-8 h-8 rounded-full bg-cozy-wood text-white flex items-center justify-center hover:opacity-90 font-bold">+</button>
              </div>
            </div>
          </div>
        `;
      }).join('');
    }

    function updateStock(id, delta) {
      const item = inventoryData.find(p => p.id === id);
      if (item && item.stock + delta >= 0) {
        item.stock += delta;
        renderInventory();
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
        if (document.getElementById('sched-pickup-tab')?.classList.contains('active')) renderPickupSchedule();
      } catch (error) {
        listEl.innerHTML = `<div style="background:#fff;padding:24px;border-radius:14px;color:#a33;">Unable to load database orders: ${error.message}</div>`;
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
      document.getElementById('dash-completed-orders').textContent = counts.COMPLETED;
      document.getElementById('order-filters').innerHTML = Object.keys(counts).map(k =>
        `<button class="pill ${currentOrderFilter === k ? 'active' : ''}" onclick="setOrderFilter('${k}')">${k} · ${counts[k]}</button>`
      ).join('');
      const filtered = currentOrderFilter === 'ALL' ? ordersData : ordersData.filter(o => o.status === currentOrderFilter);
      const listEl = document.getElementById('orders-list');
      if (!filtered.length) {
        listEl.innerHTML = '<div style="background:#fff;padding:24px;border-radius:14px;text-align:center;color:#8c7a6b;">No orders for this status.</div>';
      } else {
        listEl.innerHTML = filtered.map(o => `
          <div class="order-card ${o.id === selectedOrderId ? 'selected' : ''}" onclick="selectOrder('${o.id}')">
            <div style="display:flex;gap:12px;align-items:center;">
              <div style="width:40px;height:40px;background:#f7ebe1;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#9a6845;"><i data-lucide="receipt" style="width:18px;height:18px;"></i></div>
              <div><h4 style="font-size:0.95rem;font-weight:700;">#${o.id} <span style="font-size:0.75rem;color:#8c7a6b;font-weight:500;">${o.time}</span></h4>
              <div style="font-size:0.85rem;color:#555;">${o.customer}</div><div style="font-size:0.78rem;color:#8c7a6b;">${o.summary}</div></div>
            </div><div style="text-align:right;"><div style="font-weight:700;">${o.total}</div><span class="badge badge-${o.status}">${o.status}</span></div>
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
          <div style="display:flex;justify-content:space-between;align-items:flex-start;"><div><span style="font-size:0.68rem;font-weight:800;color:#8c7a6b;text-transform:uppercase;">Order Details</span><div class="title" style="font-size:2.4rem;">#${ord.id}</div></div><span class="badge badge-${ord.status}">${ord.status}</span></div>
          <div class="info-grid"><div class="info-box"><span>Customer</span><b>${ord.customer}</b></div><div class="info-box"><span>Placed At</span><b>${ord.time}</b></div><div class="info-box"><span>Channel</span><b>${ord.channel}</b></div><div class="info-box"><span>Payment</span><b>${ord.payment}</b></div></div>
          <div style="border-top:1px dashed var(--border);padding-top:12px;font-size:0.9rem;"><div style="font-size:0.72rem;font-weight:800;color:#9a6845;text-transform:uppercase;margin-bottom:8px;">Items</div>
          ${ord.items.map(i => `<div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span>${i.name}</span><span>${i.price}</span></div>`).join('')}
          <div style="display:flex;justify-content:space-between;font-weight:700;font-size:1.05rem;margin-top:10px;border-top:1px solid var(--border);padding-top:10px;"><span>Total</span><span>${ord.total}</span></div></div>
          ${ord.note ? `<div style="background:#fff8d6;border-radius:14px;padding:12px 16px;margin:14px 0;"><div style="font-size:0.72rem;font-weight:800;color:#9a6845;text-transform:uppercase;margin-bottom:4px;">Kitchen Note</div><div style="font-size:0.88rem;color:#554433;">${ord.note}</div></div>` : ''}
          <div class="progress-tracker">${[1,2,3,4].map(s => `<div class="progress-step ${s <= stepCount ? 'fill' : ''}"></div>`).join('')}</div>
          <div style="display:flex;justify-content:space-between;font-size:0.65rem;font-weight:700;color:#8c7a6b;text-transform:uppercase;margin-bottom:15px;"><span>New</span><span>Preparing</span><span>Ready</span><span>Completed</span></div>
          ${ord.status === 'COMPLETED' ? '<button class="btn-disabled" disabled>Order Completed</button>' : `<button class="btn-action" onclick="advanceOrderStatus('${ord.id}')">${nextLabel}</button>`}`;
      }
      lucide.createIcons();
    }

    // ==========================================
    // DATA & LOGIK 3: INGREDIENTS & RECIPES
    // ==========================================
    const pantryItems = [
      { name: "Unsalted Butter", category: "Dairy", quantity: "12 kg", status: "expiring", statusText: "Expiring in 3 days" },
      { name: "Organic Bread Flour", category: "Dry Goods", quantity: "150 kg", status: "ok", statusText: "In Stock" },
      { name: "Granulated Sugar", category: "Dry Goods", quantity: "4 kg", status: "low", statusText: "Low Stock (Below 10kg)" },
      { name: "Heavy Cream 35%", category: "Dairy", quantity: "2 L", status: "expired", statusText: "Expired yesterday" },
      { name: "Dark Chocolate 70%", category: "Chocolate", quantity: "8 kg", status: "low", statusText: "Low Stock" },
      { name: "Fresh Strawberries", category: "Produce", quantity: "1.5 kg", status: "expiring", statusText: "Expiring in 2 days" },
      { name: "Whole Milk", category: "Dairy", quantity: "0 L", status: "expired", statusText: "Expired" },
      { name: "Active Dry Yeast", category: "Baking Supplies", quantity: "250 g", status: "low", statusText: "Low Stock" },
      { name: "Vanilla Extract", category: "Flavors", quantity: "300 ml", status: "ok", statusText: "In Stock" }
    ];

    const recipeItems = [
      { name: "Strawberry Cloud Cake", category: "Cake", ingredientsCount: 6, prepTime: "45 mins" },
      { name: "Artisan Sourdough Loaf", category: "Bread", ingredientsCount: 4, prepTime: "24 hrs" },
      { name: "French Butter Croissant", category: "Savory", ingredientsCount: 5, prepTime: "12 hrs" },
      { name: "Dark Chocolate Fudge Brownie", category: "Sweets", ingredientsCount: 7, prepTime: "30 mins" }
    ];

    let ingTab = 'pantry';
    let ingFilter = 'all';

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
        container.innerHTML = filtered.map(item => {
          let badgeBg = "bg-green-100 text-green-800";
          if (item.status === 'low') badgeBg = "bg-amber-100 text-amber-800";
          if (item.status === 'expiring') badgeBg = "bg-orange-100 text-orange-800";
          if (item.status === 'expired') badgeBg = "bg-red-100 text-red-800";
          return `
            <div class="bg-white p-4 rounded-2xl flex items-center justify-between shadow-sm">
              <div>
                <h4 class="font-bold text-base">${item.name}</h4>
                <span class="text-xs text-cozy-textMuted">${item.category}</span>
              </div>
              <div class="flex items-center space-x-4">
                <span class="px-3 py-1 rounded-full text-xs font-bold ${badgeBg}">${item.statusText}</span>
                <span class="font-black text-cozy-darkCard text-lg w-20 text-right">${item.quantity}</span>
              </div>
            </div>
          `;
        }).join('');
      } else {
        const filtered = recipeItems.filter(i => i.name.toLowerCase().includes(q));
        container.innerHTML = filtered.map(item => `
          <div class="bg-white p-4 rounded-2xl flex items-center justify-between shadow-sm">
            <div>
              <h4 class="font-bold text-base">${item.name}</h4>
              <span class="text-xs text-cozy-textMuted">${item.category} · Prep: ${item.prepTime}</span>
            </div>
            <span class="font-extrabold text-xs bg-cozy-bg px-3 py-1.5 rounded-full">${item.ingredientsCount} Ingredients Required</span>
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
      renderInventory();
      loadDashboardOverview();
      loadDatabaseOrders();
      renderIngredients();
      renderPickupSchedule();
      lucide.createIcons();
    });
  </script>
</body>
</html>