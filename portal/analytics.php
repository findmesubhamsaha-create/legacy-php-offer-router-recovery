<?php
session_start();
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header('Location: index.php');
    exit;
}
require __DIR__ . '/library/Settings.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Analytics — Offer Router</title>
   <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
   <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.2/css/all.min.css" crossorigin="anonymous">
   <link rel="stylesheet" href="assets/css/dashboard_style.css">
   <link rel="stylesheet" href="assets/css/modern.css">
   <link rel="stylesheet" href="assets/css/design-system-v2.css">
   <style>
      /* ── analytics-specific tokens ── */
      :root {
         --an-card-radius: 12px;
         --an-card-shadow: 0 1px 3px rgba(0,0,0,.07), 0 4px 12px rgba(0,0,0,.05);
      }

      /* KPI cards */
      .an-kpi-grid {
         display: grid;
         grid-template-columns: repeat(4, 1fr);
         gap: 16px;
         margin-bottom: 24px;
      }
      .an-kpi-card {
         background: #fff;
         border-radius: var(--an-card-radius);
         box-shadow: var(--an-card-shadow);
         border: 1px solid #e5e7eb;
         padding: 20px 22px;
         display: flex;
         align-items: center;
         gap: 16px;
      }
      .an-kpi-icon {
         width: 44px;
         height: 44px;
         border-radius: 10px;
         display: flex;
         align-items: center;
         justify-content: center;
         font-size: 20px;
         flex-shrink: 0;
      }
      .an-kpi-icon.blue   { background: #eff6ff; color: #3b82f6; }
      .an-kpi-icon.green  { background: #f0fdf4; color: #22c55e; }
      .an-kpi-icon.purple { background: #faf5ff; color: #a855f7; }
      .an-kpi-icon.orange { background: #fff7ed; color: #f97316; }
      .an-kpi-label {
         font-size: 12px;
         font-weight: 500;
         color: #6b7280;
         text-transform: uppercase;
         letter-spacing: .04em;
         margin-bottom: 4px;
      }
      .an-kpi-value {
         font-size: 28px;
         font-weight: 700;
         color: #111827;
         line-height: 1;
      }

      /* Section cards */
      .an-section {
         background: #fff;
         border-radius: var(--an-card-radius);
         box-shadow: var(--an-card-shadow);
         border: 1px solid #e5e7eb;
         padding: 20px 22px;
         margin-bottom: 20px;
      }
      .an-section-title {
         font-size: 14px;
         font-weight: 600;
         color: #111827;
         margin-bottom: 16px;
         display: flex;
         align-items: center;
         gap: 8px;
      }
      .an-section-title i {
         color: #6b7280;
      }

      /* Chart containers */
      .an-chart-wrap {
         position: relative;
         height: 240px;
      }

      /* Health badges */
      .an-health-badge {
         display: inline-block;
         font-size: 11px;
         font-weight: 500;
         padding: 2px 8px;
         border-radius: 99px;
         background: #fef3c7;
         color: #92400e;
      }
      .an-health-badge.danger {
         background: #fee2e2;
         color: #991b1b;
      }

      /* Offer analytics table */
      .an-table {
         width: 100%;
         font-size: 13px;
         border-collapse: collapse;
      }
      .an-table th {
         font-size: 11px;
         font-weight: 600;
         text-transform: uppercase;
         letter-spacing: .04em;
         color: #6b7280;
         padding: 8px 10px;
         border-bottom: 1px solid #e5e7eb;
         white-space: nowrap;
      }
      .an-table td {
         padding: 9px 10px;
         border-bottom: 1px solid #f3f4f6;
         color: #374151;
         vertical-align: middle;
      }
      .an-table tr:last-child td { border-bottom: none; }
      .an-table tr:hover td { background: #f9fafb; }

      /* Route badge */
      .an-route-ok   { color: #16a34a; font-weight: 600; }
      .an-route-none { color: #dc2626; font-weight: 600; }

      /* Day range pill buttons */
      .an-range-btn {
         font-size: 12px;
         padding: 3px 10px;
         border-radius: 99px;
         border: 1px solid #e5e7eb;
         background: #fff;
         color: #6b7280;
         cursor: pointer;
         transition: all .15s;
      }
      .an-range-btn.active, .an-range-btn:hover {
         background: #3b82f6;
         color: #fff;
         border-color: #3b82f6;
      }

      /* Distribution bar pair */
      .an-dist-row { margin-bottom: 14px; }
      .an-dist-label {
         font-size: 12px;
         color: #374151;
         margin-bottom: 4px;
         white-space: nowrap;
         overflow: hidden;
         text-overflow: ellipsis;
         max-width: 100%;
      }
      .an-dist-bars { display: flex; flex-direction: column; gap: 3px; }
      .an-dist-bar-wrap { display: flex; align-items: center; gap: 6px; font-size: 11px; color: #6b7280; }
      .an-dist-bar-track {
         flex: 1;
         height: 8px;
         background: #f3f4f6;
         border-radius: 99px;
         overflow: hidden;
      }
      .an-dist-bar-fill {
         height: 100%;
         border-radius: 99px;
         transition: width .4s ease;
      }
      .an-dist-bar-fill.config  { background: #93c5fd; }
      .an-dist-bar-fill.actual  { background: #3b82f6; }

      /* Responsive */
      @media (max-width: 991px) {
         .an-kpi-grid { grid-template-columns: repeat(2, 1fr); }
      }
      @media (max-width: 575px) {
         .an-kpi-grid { grid-template-columns: 1fr 1fr; }
         .an-kpi-value { font-size: 22px; }
      }
   </style>
</head>
<body>
<div class="main closeBar" id="mainArea">

   <!-- Top bar -->
   <div class="topBar">
      <button id="open_sidebar" class="IconClick">
         <img src="assets/images/menu.png" alt="Menu">
      </button>
      <span class="ds2-topbar-title">Analytics</span>
      <span class="ds2-topbar-live"><span class="ds2-live-dot"></span> Live</span>
   </div>

   <!-- Sidebar -->
   <div class="sidebar">
      <button id="close_sidebar" class="IconClick">
         <img src="assets/images/cancel.png" alt="Close">
      </button>
      <div class="sideMenu_ottr">
         <ul>
            <li><a href="<?= BASE_URL ?>/portal/dashboard.php"><i class="fas fa-th-list ds2-nav-icon"></i> Offers</a></li>
            <li><a href="<?= BASE_URL ?>/portal/analytics.php"><i class="fas fa-chart-bar ds2-nav-icon"></i> Analytics</a></li>
            <li><a href="<?= BASE_URL ?>/portal/import.php"><i class="fas fa-file-import ds2-nav-icon"></i> Import</a></li>
            <li><a href="<?= BASE_URL ?>/portal/export/"><i class="fas fa-file-export ds2-nav-icon"></i> Export</a></li>
         </ul>
      </div>
   </div>

   <!-- Main content -->
   <div class="mainArea">
      <div class="inner_main mt-2" style="padding: 0 16px 40px;">

         <!-- KPI Cards -->
         <div class="an-kpi-grid" id="kpi-grid">
            <div class="an-kpi-card">
               <div class="an-kpi-icon blue"><i class="fas fa-mouse-pointer"></i></div>
               <div>
                  <div class="an-kpi-label">Clicks Today</div>
                  <div class="an-kpi-value" id="kpi-clicks-today"><span class="ds2-skel ds2-skel-val"></span></div>
                  <div id="kpi-delta-clicks"></div>
               </div>
            </div>
            <div class="an-kpi-card">
               <div class="an-kpi-icon green"><i class="fas fa-bullseye"></i></div>
               <div>
                  <div class="an-kpi-label">Active Offers</div>
                  <div class="an-kpi-value" id="kpi-active-offers"><span class="ds2-skel ds2-skel-val"></span></div>
               </div>
            </div>
            <div class="an-kpi-card">
               <div class="an-kpi-icon purple"><i class="fas fa-route"></i></div>
               <div>
                  <div class="an-kpi-label">Active Routes</div>
                  <div class="an-kpi-value" id="kpi-active-routes"><span class="ds2-skel ds2-skel-val"></span></div>
               </div>
            </div>
            <div class="an-kpi-card">
               <div class="an-kpi-icon orange"><i class="fas fa-users"></i></div>
               <div>
                  <div class="an-kpi-label">Unique IPs Today</div>
                  <div class="an-kpi-value" id="kpi-unique-ips"><span class="ds2-skel ds2-skel-val"></span></div>
                  <div id="kpi-delta-ips"></div>
               </div>
            </div>
         </div>

         <!-- Trend + Network row -->
         <div class="row g-3 mb-3">
            <div class="col-lg-8">
               <div class="an-section">
                  <div class="an-section-title">
                     <i class="fas fa-chart-line"></i> Daily Click Trend
                     <span class="ms-auto d-flex gap-1">
                        <button class="an-range-btn active" data-days="30" id="trend-btn-30">30d</button>
                        <button class="an-range-btn" data-days="14" id="trend-btn-14">14d</button>
                        <button class="an-range-btn" data-days="7" id="trend-btn-7">7d</button>
                     </span>
                  </div>
                  <div class="an-chart-wrap" id="trend-chart-wrap">
                     <span class="ds2-skel ds2-skel-chart" id="trend-chart-skel"></span>
                     <canvas id="trendChart" style="display:none;"></canvas>
                  </div>
               </div>
            </div>
            <div class="col-lg-4">
               <div class="an-section" style="height: calc(100% - 0px);">
                  <div class="an-section-title"><i class="fas fa-network-wired"></i> Network Breakdown <small class="text-muted fw-normal ms-1" style="font-size:11px;">(30d)</small></div>
                  <div class="an-chart-wrap" id="network-chart-wrap">
                     <span class="ds2-skel ds2-skel-chart" id="network-chart-skel"></span>
                     <canvas id="networkChart" style="display:none;"></canvas>
                  </div>
               </div>
            </div>
         </div>

         <!-- Top Offers + Routing Distribution row -->
         <div class="row g-3 mb-3">
            <div class="col-lg-6">
               <div class="an-section">
                  <div class="an-section-title">
                     <i class="fas fa-trophy"></i> Top Offers
                     <span class="ms-auto d-flex gap-1">
                        <button class="an-range-btn active" data-days="7" id="top-btn-7">7d</button>
                        <button class="an-range-btn" data-days="14" id="top-btn-14">14d</button>
                        <button class="an-range-btn" data-days="30" id="top-btn-30">30d</button>
                     </span>
                  </div>
                  <div id="top-offers-wrap" style="max-height:320px; overflow-y:auto;">
                     <table class="an-table" id="top-offers-table">
                        <thead><tr><th>#</th><th>Offer</th><th>Network</th><th>Clicks</th></tr></thead>
                        <tbody id="top-offers-tbody">
                           <?php for ($i = 0; $i < 5; $i++): ?>
                           <tr>
                              <td><span class="ds2-skel" style="width:16px;height:11px;"></span></td>
                              <td><span class="ds2-skel ds2-skel-full"></span></td>
                              <td><span class="ds2-skel ds2-skel-md"></span></td>
                              <td><span class="ds2-skel ds2-skel-sm"></span></td>
                           </tr>
                           <?php endfor; ?>
                        </tbody>
                     </table>
                  </div>
               </div>
            </div>
            <div class="col-lg-6">
               <div class="an-section">
                  <div class="an-section-title">
                     <i class="fas fa-balance-scale"></i> Routing Distribution
                     <span class="ms-auto">
                        <select id="dist-offer-select" class="form-select form-select-sm" style="width:160px;font-size:12px;">
                           <option value="">Select offer…</option>
                        </select>
                     </span>
                  </div>
                  <div id="dist-wrap" style="max-height:320px; overflow-y:auto;">
                     <div class="text-muted text-center py-4" style="font-size:13px;">Select an offer to view distribution</div>
                  </div>
               </div>
            </div>
         </div>

         <!-- Traffic Quality row -->
         <div class="row g-3 mb-3">
            <div class="col-lg-6">
               <div class="an-section">
                  <div class="an-section-title"><i class="fas fa-exclamation-triangle"></i> Duplicate IPs <small class="text-muted fw-normal ms-1" style="font-size:11px;">(last 30d)</small></div>
                  <div style="max-height:240px; overflow-y:auto;">
                     <table class="an-table" id="dup-ips-table">
                        <thead><tr><th>IP Address</th><th>Hits</th></tr></thead>
                        <tbody><tr><td colspan="2" class="text-center text-muted py-3">Loading…</td></tr></tbody>
                     </table>
                  </div>
               </div>
            </div>
            <div class="col-lg-6">
               <div class="an-section">
                  <div class="an-section-title"><i class="fas fa-fingerprint"></i> Repeated Click IDs <small class="text-muted fw-normal ms-1" style="font-size:11px;">(last 30d, excludes default '0')</small></div>
                  <div style="max-height:240px; overflow-y:auto;">
                     <table class="an-table" id="dup-clicks-table">
                        <thead><tr><th>Click ID</th><th>Hits</th></tr></thead>
                        <tbody><tr><td colspan="2" class="text-center text-muted py-3">Loading…</td></tr></tbody>
                     </table>
                  </div>
               </div>
            </div>
         </div>

         <!-- Offer Health row -->
         <div class="an-section mb-3">
            <div class="an-section-title"><i class="fas fa-heartbeat"></i> Offer Health Issues</div>
            <div id="health-wrap">
               <div class="ds2-skel-row"><div class="ds2-skel ds2-skel-col"></div><div class="ds2-skel ds2-skel-col-sm"></div></div>
               <div class="ds2-skel-row"><div class="ds2-skel ds2-skel-col"></div><div class="ds2-skel ds2-skel-col-sm"></div></div>
               <div class="ds2-skel-row"><div class="ds2-skel ds2-skel-col" style="width:60%;"></div><div class="ds2-skel ds2-skel-col-sm"></div></div>
            </div>
         </div>

         <!-- Offer Analytics table -->
         <div class="an-section">
            <div class="an-section-title"><i class="fas fa-table"></i> Offer Analytics — All Active Offers</div>
            <div style="overflow-x:auto;">
               <table class="an-table" id="offer-analytics-table">
                  <thead>
                     <tr>
                        <th>#</th>
                        <th>Offer</th>
                        <th>Slug</th>
                        <th>Tag</th>
                        <th>Network</th>
                        <th>Sub-URLs</th>
                        <th>Active Routes</th>
                        <th>Total Clicks</th>
                     </tr>
                  </thead>
                  <tbody><tr><td colspan="8" class="text-center text-muted py-3">Loading…</td></tr></tbody>
               </table>
            </div>
         </div>

      </div><!-- /inner_main -->
   </div><!-- /mainArea -->
</div><!-- /main -->

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
/* ── Sidebar toggle ── */
document.getElementById('open_sidebar').addEventListener('click', function () {
   document.getElementById('mainArea').classList.replace('closeBar', 'openBar');
});
document.getElementById('close_sidebar').addEventListener('click', function () {
   document.getElementById('mainArea').classList.replace('openBar', 'closeBar');
});

/* ── AJAX helper ── */
function ajaxPost(requestMethod, extraData, callback) {
   $.ajax({
      url: 'ajax_analytics.php',
      type: 'POST',
      data: Object.assign({ requestMethod: requestMethod }, extraData),
      success: function (data) {
         var parsed;
         try {
            parsed = (typeof data === 'string') ? JSON.parse(data) : data;
         } catch (e) {
            console.error('[analytics] JSON parse failed for', requestMethod, e);
            parsed = null;
         }
         callback(parsed);
      },
      error: function (xhr, status, err) {
         console.error('[analytics] Request failed for', requestMethod, status, err);
         callback(null);
      }
   });
}

/* ── Chart instances ── */
var trendChart = null;
var networkChart = null;

/* ── Delta badge builder ── */
function buildDelta(today, yesterday) {
   if (yesterday === 0 && today === 0) return '<span class="ds2-delta ds2-delta-flat"><i class="fas fa-minus"></i> No data yet</span>';
   if (yesterday === 0) return '<span class="ds2-delta ds2-delta-new"><i class="fas fa-circle"></i> New today</span>';
   var diff = today - yesterday;
   var pct  = Math.round(Math.abs(diff) / yesterday * 100);
   if (diff > 0)  return '<span class="ds2-delta ds2-delta-up"><i class="fas fa-arrow-up"></i> +' + pct + '% vs yesterday</span>';
   if (diff < 0)  return '<span class="ds2-delta ds2-delta-down"><i class="fas fa-arrow-down"></i> ' + pct + '% vs yesterday</span>';
   return '<span class="ds2-delta ds2-delta-flat"><i class="fas fa-minus"></i> Same as yesterday</span>';
}

/* ── KPI cards ── */
ajaxPost('getKpiCards', {}, function (d) {
   document.getElementById('kpi-clicks-today').textContent  = d.clicks_today.toLocaleString();
   document.getElementById('kpi-active-offers').textContent = d.active_offers.toLocaleString();
   document.getElementById('kpi-active-routes').textContent = d.active_routes.toLocaleString();
   document.getElementById('kpi-unique-ips').textContent    = d.unique_ips_today.toLocaleString();

   // Trend deltas (only shown for metrics with yesterday comparison)
   if (d.clicks_yesterday !== undefined) {
      document.getElementById('kpi-delta-clicks').innerHTML = buildDelta(d.clicks_today, d.clicks_yesterday);
   }
   if (d.unique_ips_yesterday !== undefined) {
      document.getElementById('kpi-delta-ips').innerHTML = buildDelta(d.unique_ips_today, d.unique_ips_yesterday);
   }
});

/* ── Daily trend chart ── */
var trendActiveDays = 30;

function loadTrendChart(days) {
   ajaxPost('getDailyClickTrend', { days: days }, function (rows) {
      var labels = rows.map(r => r.day);
      var data   = rows.map(r => parseInt(r.clicks));
      var ctx    = document.getElementById('trendChart').getContext('2d');

      if (trendChart) trendChart.destroy();
      document.getElementById('trend-chart-skel').style.display = 'none';
      document.getElementById('trendChart').style.display = '';
      trendChart = new Chart(ctx, {
         type: 'line',
         data: {
            labels: labels,
            datasets: [{
               label: 'Clicks',
               data: data,
               fill: true,
               backgroundColor: 'rgba(59,130,246,.10)',
               borderColor: '#3b82f6',
               borderWidth: 2,
               pointRadius: labels.length > 20 ? 0 : 3,
               pointHoverRadius: 4,
               tension: 0.35
            }]
         },
         options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
               legend: { display: false },
               tooltip: { mode: 'index', intersect: false }
            },
            scales: {
               x: {
                  grid: { display: false },
                  ticks: { font: { size: 11 }, maxTicksLimit: 8 }
               },
               y: {
                  beginAtZero: true,
                  ticks: { font: { size: 11 }, precision: 0 }
               }
            }
         }
      });
   });
}

loadTrendChart(trendActiveDays);

document.querySelectorAll('[id^="trend-btn-"]').forEach(function (btn) {
   btn.addEventListener('click', function () {
      document.querySelectorAll('[id^="trend-btn-"]').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      trendActiveDays = parseInt(this.dataset.days);
      loadTrendChart(trendActiveDays);
   });
});

/* ── Network breakdown doughnut ── */
var DOUGHNUT_COLORS = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316','#14b8a6'];

ajaxPost('getNetworkBreakdown', { days: 30 }, function (rows) {
   var ctx = document.getElementById('networkChart').getContext('2d');
   if (networkChart) networkChart.destroy();
   if (!rows.length) {
      ctx.canvas.parentElement.innerHTML = '<div class="text-muted text-center pt-5" style="font-size:13px;">No data</div>';
      return;
   }
   document.getElementById('network-chart-skel').style.display = 'none';
   document.getElementById('networkChart').style.display = '';
   networkChart = new Chart(ctx, {
      type: 'doughnut',
      data: {
         labels: rows.map(r => r.network_name),
         datasets: [{
            data: rows.map(r => parseInt(r.clicks)),
            backgroundColor: rows.map((_, i) => DOUGHNUT_COLORS[i % DOUGHNUT_COLORS.length]),
            borderWidth: 2,
            borderColor: '#fff'
         }]
      },
      options: {
         responsive: true,
         maintainAspectRatio: false,
         cutout: '60%',
         plugins: {
            legend: {
               position: 'bottom',
               labels: { font: { size: 11 }, padding: 12, boxWidth: 12 }
            }
         }
      }
   });
});

/* ── Top Offers ── */
function loadTopOffers(days) {
   ajaxPost('getTopOffers', { days: days }, function (rows) {
      var tbody = document.getElementById('top-offers-tbody');
      if (!rows.length) {
         tbody.innerHTML = '<tr><td colspan="4"><div class="ds2-empty"><div class="ds2-empty-icon"><i class="fas fa-trophy"></i></div><div class="ds2-empty-title">No offers found</div><div class="ds2-empty-body">No click data for this period</div></div></td></tr>';
         return;
      }
      tbody.innerHTML = rows.map(function (r, i) {
         return '<tr>' +
            '<td>' + (i + 1) + '</td>' +
            '<td>' + escHtml(r.offer) + '</td>' +
            '<td>' + escHtml(r.network_name || '—') + '</td>' +
            '<td><strong>' + parseInt(r.clicks).toLocaleString() + '</strong></td>' +
            '</tr>';
      }).join('');
   });
}

loadTopOffers(7);

document.querySelectorAll('[id^="top-btn-"]').forEach(function (btn) {
   btn.addEventListener('click', function () {
      document.querySelectorAll('[id^="top-btn-"]').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      loadTopOffers(parseInt(this.dataset.days));
   });
});

/* ── Routing Distribution ── */
ajaxPost('getOfferListForDropdown', {}, function (offers) {
   var sel = document.getElementById('dist-offer-select');
   offers.forEach(function (o) {
      var opt = document.createElement('option');
      opt.value = o.id;
      opt.textContent = o.offer;
      sel.appendChild(opt);
   });
});

document.getElementById('dist-offer-select').addEventListener('change', function () {
   var offer_id = this.value;
   var wrap = document.getElementById('dist-wrap');
   if (!offer_id) {
      wrap.innerHTML = '<div class="text-muted text-center py-4" style="font-size:13px;">Select an offer to view distribution</div>';
      return;
   }
   wrap.innerHTML = '<div class="text-muted text-center py-4">Loading…</div>';
   ajaxPost('getRoutingDistribution', { offer_id: offer_id }, function (rows) {
      if (!rows.length) {
         wrap.innerHTML = '<div class="text-muted text-center py-4" style="font-size:13px;">No sub-URLs found</div>';
         return;
      }
      wrap.innerHTML = rows.map(function (r) {
         var url = r.sub_url.length > 45 ? r.sub_url.substring(0, 45) + '…' : r.sub_url;
         return '<div class="an-dist-row">' +
            '<div class="an-dist-label" title="' + escHtml(r.sub_url) + '">' + escHtml(url) + '</div>' +
            '<div class="an-dist-bars">' +
            '<div class="an-dist-bar-wrap"><span style="width:60px;text-align:right;">Config</span>' +
            '<div class="an-dist-bar-track"><div class="an-dist-bar-fill config" style="width:' + r.config_pct + '%"></div></div>' +
            '<span>' + r.config_pct + '%</span></div>' +
            '<div class="an-dist-bar-wrap"><span style="width:60px;text-align:right;">Actual</span>' +
            '<div class="an-dist-bar-track"><div class="an-dist-bar-fill actual" style="width:' + r.actual_pct + '%"></div></div>' +
            '<span>' + r.actual_pct + '% (' + parseInt(r.actual_clicks) + ' clicks)</span></div>' +
            '</div></div>';
      }).join('');
   });
});

/* ── Traffic Quality ── */
ajaxPost('getTrafficQuality', { days: 30 }, function (d) {
   var ipTbody = document.querySelector('#dup-ips-table tbody');
   if (!d.duplicate_ips.length) {
      ipTbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3">No duplicate IPs — traffic looks clean</td></tr>';
   } else {
      ipTbody.innerHTML = d.duplicate_ips.map(function (r) {
         return '<tr><td>' + escHtml(r.ip_address) + '</td><td><strong>' + r.hits + '</strong></td></tr>';
      }).join('');
   }

   var cTbody = document.querySelector('#dup-clicks-table tbody');
   if (!d.repeated_click_ids.length) {
      cTbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3">No repeated click IDs</td></tr>';
   } else {
      cTbody.innerHTML = d.repeated_click_ids.map(function (r) {
         return '<tr><td>' + escHtml(r.click_id) + '</td><td><strong>' + r.hits + '</strong></td></tr>';
      }).join('');
   }
});

/* ── Offer Health ── */
ajaxPost('getOfferHealthIssues', {}, function (issues) {
   var wrap = document.getElementById('health-wrap');
   if (!Array.isArray(issues)) {
      wrap.innerHTML = '<div class="ds2-empty" style="padding:32px 24px;">'
         + '<div class="ds2-empty-icon"><i class="fas fa-exclamation-circle" style="color:#f59e0b;font-size:28px;"></i></div>'
         + '<div class="ds2-empty-title">Health check unavailable</div>'
         + '<div class="ds2-empty-body">Could not load health data. Check server connectivity.</div>'
         + '</div>';
      return;
   }
   if (!issues.length) {
      wrap.innerHTML = '<div style="display:flex;align-items:center;gap:8px;font-size:13px;color:#166534;">'
         + '<span class="ds2-health-dot ok"></span>'
         + '<span class="ds2-anomaly-ok" style="display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:600;padding:3px 9px;border-radius:99px;background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;">'
         + '<i class="fas fa-check-circle"></i> All active offers are healthy</span></div>';
      return;
   }
   var getBadgeClass = function (issue) {
      if (issue.indexOf('No active') !== -1 || issue.indexOf('Expired') !== -1) return 'ds2-anomaly-critical';
      return 'ds2-anomaly';
   };
   var getDotClass = function (issue) {
      if (issue.indexOf('No active') !== -1 || issue.indexOf('Expired') !== -1) return 'danger';
      return 'warn';
   };
   wrap.innerHTML = '<table class="an-table"><thead><tr><th style="width:24px;"></th><th>Offer</th><th>Issue</th></tr></thead><tbody>' +
      issues.map(function (r) {
         return '<tr>'
            + '<td><span class="ds2-health-dot ' + getDotClass(r.issue) + '"></span></td>'
            + '<td>' + escHtml(r.offer) + '</td>'
            + '<td><span class="' + getBadgeClass(r.issue) + '">' + escHtml(r.issue) + '</span></td>'
            + '</tr>';
      }).join('') + '</tbody></table>';
});

/* ── Offer Analytics table ── */
ajaxPost('getOfferAnalytics', {}, function (rows) {
   var tbody = document.querySelector('#offer-analytics-table tbody');
   if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">No active offers</td></tr>';
      return;
   }
   tbody.innerHTML = rows.map(function (r, i) {
      var routeCls = parseInt(r.active_routes) > 0 ? 'an-route-ok' : 'an-route-none';
      return '<tr>' +
         '<td>' + (i + 1) + '</td>' +
         '<td><strong>' + escHtml(r.offer) + '</strong></td>' +
         '<td><code style="font-size:11px;color:#6b7280;">' + escHtml(r.slug_name) + '</code></td>' +
         '<td>' + escHtml(r.tag_name || '—') + '</td>' +
         '<td>' + escHtml(r.network_name || '—') + '</td>' +
         '<td class="text-center">' + r.sub_offer_count + '</td>' +
         '<td class="text-center ' + routeCls + '">' + r.active_routes + '</td>' +
         '<td class="text-end"><strong>' + parseInt(r.total_clicks).toLocaleString() + '</strong></td>' +
         '</tr>';
   }).join('');
});

/* ── XSS guard ── */
function escHtml(str) {
   if (str == null) return '—';
   return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── DS2: active nav state ── */
(function () {
   var path = window.location.pathname;
   document.querySelectorAll('.sideMenu_ottr a').forEach(function (a) {
      try {
         var ap = new URL(a.href).pathname;
         if (path === ap || path.endsWith(ap.replace(/^.*\/portal\//, '/portal/'))) {
            a.classList.add('ds2-active');
         }
      } catch (e) {}
   });
})();
</script>
</body>
</html>
