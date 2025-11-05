<?php
session_start();
if (empty($_SESSION['user']) || empty($_SESSION['is_admin'])) {
  header('Location: ../login.php');
  exit;
}
$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Pushing P — Admin</title>

  <!-- Tailwind, GSAP, Chart.js -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet" />

  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { display: ['Inter','ui-sans-serif','system-ui','sans-serif'] },
          colors: {
            brand: { 600:'#6366f1', 700:'#4f46e5', 800:'#4338ca' },
            glass: { card: 'rgba(255,255,255,.08)', edge: 'rgba(255,255,255,.14)' }
          },
          boxShadow: {
            glow: '0 20px 60px rgba(99,102,241,.25)',
            glass: '0 10px 40px rgba(0,0,0,.35)'
          }
        }
      }
    }
  </script>

  <style>
    :root { color-scheme: dark }
    html, body { height: 100% }
    body{
      margin:0;
      background:
        radial-gradient(1400px 900px at 10% 10%, rgba(99,102,241,.14), transparent 60%),
        radial-gradient(1200px 700px at 90% 30%, rgba(236,72,153,.12), transparent 60%),
        radial-gradient(900px 700px at 50% 85%, rgba(34,211,238,.09), transparent 50%),
        #06060a;
      -webkit-font-smoothing: antialiased; text-rendering: optimizeLegibility;
      overflow: hidden;
    }
    .grad-text{
      background: linear-gradient(135deg,#60a5fa 0%, #a78bfa 30%, #f472b6 60%, #22d3ee 90%);
      -webkit-background-clip: text; background-clip: text; color: transparent;
      background-size: 200% 200%;
      animation: gradientShift 8s linear infinite;
    }
    @keyframes gradientShift { 0%,100%{background-position:0% 50%}50%{background-position:100% 50%} }

    .glass{ background: var(--glass, rgba(255,255,255,.06)); border:1px solid rgba(255,255,255,.12); backdrop-filter: blur(16px) saturate(120%); -webkit-backdrop-filter: blur(16px) saturate(120%)}
    .glass-strong{ background: rgba(255,255,255,.09); border:1px solid rgba(255,255,255,.16); backdrop-filter: blur(20px) saturate(140%) }
    .stat-card{ transition: transform .35s cubic-bezier(.2,.9,.2,1), box-shadow .35s }
    .stat-card:hover{ transform: translateY(-6px) scale(1.015); box-shadow: 0 28px 80px rgba(99,102,241,.28) }

    /* Layout */
    .layout { display: grid; grid-template-columns: 280px 1fr; height: 100vh; }
    @media (max-width: 1024px){ .layout { grid-template-columns: 0 1fr } #sidebar { transform: translateX(-100%) } #sidebar.open { transform: translateX(0) } }
    .sidebar-link{ display:flex; align-items:center; gap:.7rem; padding:.7rem .9rem; border-radius: .9rem; transition: background .2s, transform .2s; }
    .sidebar-link:hover{ background: rgba(255,255,255,.08); transform: translateX(2px) }

    /* Particles */
    #particles { position: fixed; inset: 0; z-index: 0; pointer-events: none; }

    /* Utility */
    .number-counter { font-variant-numeric: tabular-nums }
    .section { position: relative; z-index: 5 } /* above particles */
  </style>
</head>
<body class="font-display text-white">
  <!-- Particles canvas -->
  <canvas id="particles" aria-hidden="true"></canvas>

  <div class="layout relative">
    <!-- Sidebar -->
    <aside id="sidebar" class="glass-strong border-r border-white/10 shadow-glass px-4 py-5 sticky top-0 h-screen z-10 transition-transform duration-300">
      <div class="flex items-center gap-3 mb-6 px-2">
        <div class="h-9 w-9 rounded-xl bg-blue-600/80 grid place-items-center shadow-md"><span class="text-xl font-black">P</span></div>
        <div>
          <div class="text-sm text-white/60 leading-none mb-1">Pushing P</div>
          <div class="text-lg font-extrabold grad-text leading-none">Admin</div>
        </div>
      </div>

      <nav class="space-y-1">
        <a href="#dashboard" class="sidebar-link"><span>📊</span><span>Dashboard</span></a>
        <a href="#transactions" class="sidebar-link"><span>💰</span><span>Kasse</span></a>
        <a href="#members" class="sidebar-link"><span>👥</span><span>Mitglieder</span></a>
        <a href="#shifts" class="sidebar-link"><span>🕓</span><span>Schichten</span></a>
      </nav>

      <div class="mt-8 p-3 rounded-xl bg-white/5 text-sm">
        <div class="text-white/60">Angemeldet als</div>
        <div class="font-bold mt-1"><?=htmlspecialchars($user)?></div>
      </div>

      <div class="mt-6 grid gap-2">
        <a href="../index.html" class="w-full text-center rounded-xl bg-white/10 hover:bg-white/20 px-3 py-2">🏠 Start</a>
        <a href="../logout.php" class="w-full text-center rounded-xl bg-red-600/20 hover:bg-red-600/30 text-red-300 px-3 py-2">Abmelden</a>
      </div>
    </aside>

    <!-- Main -->
    <main class="relative z-5 overflow-y-auto">
      <!-- Topbar -->
      <header class="sticky top-0 z-20">
        <div class="glass border-b border-white/10 backdrop-blur supports-backdrop-blur:bg-white/5">
          <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <button id="toggleSidebar" class="lg:hidden rounded-xl p-2 hover:bg-white/10">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/></svg>
              </button>
              <h1 class="text-xl md:text-2xl font-black grad-text">PUSHING <span style="color:#3b82f6">P</span> — Admin Dashboard</h1>
            </div>
            <div class="hidden md:flex items-center gap-2 text-sm">
              <span class="px-3 py-1.5 rounded-xl bg-white/10">Hallo, <?=htmlspecialchars($user)?> 👋</span>
            </div>
          </div>
        </div>
      </header>

      <div class="max-w-7xl mx-auto px-4 py-8 space-y-10">
        <!-- KPI Cards -->
        <section id="dashboard" class="section grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <div class="stat-card glass-strong rounded-2xl p-6">
            <div class="flex items-center justify-between mb-4">
              <div class="text-3xl">💰</div>
              <div class="h-8 w-8 rounded-lg bg-emerald-500/20 grid place-items-center">
                <div class="h-2.5 w-2.5 rounded-full bg-emerald-400 animate-pulse"></div>
              </div>
            </div>
            <div class="text-4xl font-black number-counter mb-2" id="kassenstand">–</div>
            <div class="text-white/60 text-sm">Kassenstand</div>
          </div>

          <div class="stat-card glass-strong rounded-2xl p-6">
            <div class="flex items-center justify-between mb-4">
              <div class="text-3xl">👥</div>
              <div class="h-8 w-8 rounded-lg bg-blue-500/20 grid place-items-center">
                <svg class="w-4 h-4 text-blue-400" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>
              </div>
            </div>
            <div class="text-4xl font-black number-counter mb-2" id="anzMembers">–</div>
            <div class="text-white/60 text-sm">Mitglieder</div>
          </div>

          <div class="stat-card glass-strong rounded-2xl p-6">
            <div class="flex items-center justify-between mb-4">
              <div class="text-3xl">🟢</div>
              <div class="h-8 w-8 rounded-lg bg-emerald-500/20 grid place-items-center">
                <div class="h-2.5 w-2.5 rounded-full bg-emerald-400 animate-pulse"></div>
              </div>
            </div>
            <div class="text-2xl font-black mb-2">LIVE</div>
            <div class="text-white/60 text-sm">Status</div>
          </div>

          <div class="stat-card glass-strong rounded-2xl p-6">
            <div class="flex items-center justify-between mb-4">
              <div class="text-3xl">🕓</div>
              <div class="h-8 w-8 rounded-lg bg-purple-500/20 grid place-items-center">
                <svg class="w-4 h-4 text-purple-400" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              </div>
            </div>
            <div class="text-4xl font-black number-counter mb-2" id="shiftsToday">–</div>
            <div class="text-white/60 text-sm">Schichten heute</div>
          </div>
        </section>

        <!-- Charts -->
        <section class="section grid lg:grid-cols-2 gap-6">
          <div class="glass-strong rounded-2xl p-6">
            <h3 class="text-xl font-bold mb-6">Kassenübersicht</h3>
            <div class="h-64"><canvas id="balanceChart"></canvas></div>
          </div>
          <div class="glass-strong rounded-2xl p-6">
            <h3 class="text-xl font-bold mb-6">Mitgliederverteilung</h3>
            <div class="h-64"><canvas id="membersChart"></canvas></div>
          </div>
        </section>

        <!-- Transaktionen -->
        <section id="transactions" class="section glass-strong rounded-2xl p-6">
          <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-6">
            <div>
              <h2 class="text-2xl font-bold">💰 Transaktionen</h2>
              <p class="text-sm text-white/60 mt-1">Verwalte Ein-, Auszahlungen und Gutschriften</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
              <div class="relative">
                <input type="text" id="searchTransactions" placeholder="Suchen..."
                       class="pl-9 pr-4 py-2 rounded-xl bg-white/10 text-white outline-none focus:ring-2 focus:ring-brand-600 w-52" />
                <svg class="w-4 h-4 text-white/40 absolute left-3 top-3" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
              </div>
              <select id="transactionFilter" class="px-4 py-2 rounded-xl bg-white/10 text-white outline-none focus:ring-2 focus:ring-brand-600">
                <option value="">Alle Mitglieder</option>
              </select>
              <div class="flex gap-2">
                <button id="btnOpenTxModal" class="px-4 py-2 rounded-xl bg-gradient-to-r from-brand-600 to-purple-600 hover:opacity-90 transition text-sm font-medium shadow-lg">+ Neue Transaktion</button>
                <button onclick="loadTransactions()" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 transition text-sm font-medium">🔄 Aktualisieren</button>
              </div>
            </div>
          </div>

          <div class="mb-4 flex flex-wrap gap-2">
            <button data-q="all" class="txchip px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-sm">Alle</button>
            <button data-q="einzahlung" class="txchip px-3 py-1.5 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-400 text-sm">Einzahlungen</button>
            <button data-q="auszahlung" class="txchip px-3 py-1.5 rounded-lg bg-red-500/20 hover:bg-red-500/30 text-red-400 text-sm">Auszahlungen</button>
            <button data-q="heute" class="txchip px-3 py-1.5 rounded-lg bg-blue-500/20 hover:bg-blue-500/30 text-blue-400 text-sm">Heute</button>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left">
              <thead class="text-white/60 text-sm border-b border-white/10">
                <tr>
                  <th class="py-3 px-2 font-semibold">Datum</th>
                  <th class="py-3 px-2 font-semibold">Name</th>
                  <th class="py-3 px-2 font-semibold">Typ</th>
                  <th class="py-3 px-2 font-semibold">Betrag</th>
                  <th class="py-3 px-2 font-semibold">Notiz</th>
                  <th class="py-3 px-2 font-semibold text-right w-28">Aktionen</th>
                </tr>
              </thead>
              <tbody id="transactionsTable" class="text-sm divide-y divide-white/10">
                <tr><td colspan="6" class="py-8 text-center text-white/50">Lädt...</td></tr>
              </tbody>
            </table>
            <div class="mt-4 flex items-center justify-between text-sm">
              <div class="text-white/60">Zeige <span id="pageInfo">0–0</span> von <span id="totalTransactions">0</span></div>
              <div class="flex gap-2">
                <button onclick="prevPage()" id="btnPrev" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 disabled:opacity-50">← Zurück</button>
                <button onclick="nextPage()" id="btnNext" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 disabled:opacity-50">Weiter →</button>
              </div>
            </div>
          </div>
        </section>

        <!-- Mitglieder -->
        <section id="members" class="section grid lg:grid-cols-3 gap-6 items-start">
          <div class="lg:col-span-2 glass-strong rounded-2xl p-6">
            <div class="flex items-center justify-between mb-6">
              <h2 class="text-2xl font-bold">Mitgliederverwaltung</h2>
              <button id="reloadMembers" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 transition flex items-center gap-2 text-sm font-medium">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Neu laden
              </button>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-left">
                <thead class="text-white/60 text-sm border-b border-white/10">
                  <tr>
                    <th class="py-3 px-2 font-semibold">Name</th>
                    <th class="py-3 px-2 font-semibold">Flag</th>
                    <th class="py-3 px-2 font-semibold w-72">Aktionen</th>
                  </tr>
                </thead>
                <tbody id="memberRows" class="text-sm"></tbody>
              </table>
            </div>
          </div>

          <div class="space-y-6">
            <div class="glass-strong rounded-2xl p-6">
              <h3 class="text-xl font-bold mb-4">Mitglied anlegen</h3>
              <form id="addMemberForm" class="grid gap-4">
                <div>
                  <label class="block text-sm text-white/70 mb-2">Name</label>
                  <input name="name" placeholder="Name eingeben" class="w-full rounded-xl px-4 py-3 text-black outline-none focus:ring-2 focus:ring-brand-600" required />
                </div>
                <div>
                  <label class="block text-sm text-white/70 mb-2">Flag</label>
                  <input name="flag" placeholder="🇩🇪" class="w-full rounded-xl px-4 py-3 text-black outline-none focus:ring-2 focus:ring-brand-600" />
                </div>
                <div>
                  <label class="block text-sm text-white/70 mb-2">PIN</label>
                  <input name="pin" type="password" placeholder="4–6 Ziffern" minlength="4" maxlength="6" class="w-full rounded-xl px-4 py-3 text-black outline-none focus:ring-2 focus:ring-brand-600" required />
                </div>
                <button class="rounded-xl bg-gradient-to-r from-brand-600 to-purple-600 px-4 py-3 font-semibold shadow-lg hover:shadow-glow transition-all">
                  Anlegen ✨
                </button>
                <p id="addMsg" class="text-sm text-center"></p>
              </form>
            </div>

            <div class="glass-strong rounded-2xl p-6">
              <h3 class="text-xl font-bold mb-4">Transaktion hinzufügen</h3>
              <form id="transactionFormInline" class="grid gap-4">
                <div>
                  <label class="block text-sm text-white/70 mb-2">Mitglied</label>
                  <select name="name" id="transactionMember" class="w-full rounded-xl px-4 py-3 text-black outline-none focus:ring-2 focus:ring-brand-600" required>
                    <option value="">Bitte wählen...</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm text-white/70 mb-2">Typ</label>
                  <select name="type" class="w-full rounded-xl px-4 py-3 text-black outline-none focus:ring-2 focus:ring-brand-600" required>
                    <option value="Einzahlung">Einzahlung</option>
                    <option value="Auszahlung">Auszahlung</option>
                    <option value="Gutschrift">Gutschrift</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm text-white/70 mb-2">Betrag (€)</label>
                  <input type="number" name="amount" step="0.01" min="0.01" placeholder="0.00" class="w-full rounded-xl px-4 py-3 text-black outline-none focus:ring-2 focus:ring-brand-600" required />
                </div>
                <div>
                  <label class="block text-sm text-white/70 mb-2">Notiz</label>
                  <input type="text" name="note" placeholder="Optional" class="w-full rounded-xl px-4 py-3 text-black outline-none focus:ring-2 focus:ring-brand-600" />
                </div>
                <button class="rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-3 font-semibold shadow-lg hover:shadow-glow transition-all">
                  Transaktion hinzufügen 💰
                </button>
                <p id="transactionMsg" class="text-sm text-center"></p>
              </form>
            </div>
          </div>
        </section>

        <!-- Schichten (kompakte Übersicht) -->
        <section id="shifts" class="section glass-strong rounded-2xl p-6">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold">Schichten (Heute)</h2>
            <button id="reloadShifts" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20">Neu laden</button>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-left">
              <thead class="text-white/60 text-sm border-b border-white/10">
                <tr>
                  <th class="py-3 px-2 font-semibold">Mitglied</th>
                  <th class="py-3 px-2 font-semibold">Datum</th>
                  <th class="py-3 px-2 font-semibold">Start</th>
                  <th class="py-3 px-2 font-semibold">Ende</th>
                  <th class="py-3 px-2 font-semibold text-right w-28">Aktionen</th>
                </tr>
              </thead>
              <tbody id="shiftsRows" class="text-sm divide-y divide-white/10">
                <tr><td colspan="5" class="py-8 text-center text-white/50">Lädt...</td></tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </main>
  </div>

  <!-- Transaktion Modal -->
  <div id="transactionModal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 hidden">
    <div class="min-h-screen px-4 text-center">
      <div class="fixed inset-0" onclick="closeTransactionModal()"></div>
      <div class="inline-block align-middle my-10 glass-strong rounded-2xl p-6 text-left shadow-xl transform w-full max-w-lg relative">
        <button onclick="closeTransactionModal()" class="absolute right-4 top-4 p-2 rounded-xl hover:bg-white/10">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>

        <h3 class="text-xl font-bold mb-4">Neue Transaktion</h3>
        <form id="transactionFormModal" class="space-y-4">
          <div>
            <label class="block text-sm text-white/70 mb-2">Mitglied *</label>
            <select name="name" class="w-full rounded-xl px-4 py-3 bg-white/10 text-white outline-none focus:ring-2 focus:ring-brand-600" required>
              <option value="">Bitte wählen...</option>
            </select>
          </div>
          <div>
            <label class="block text-sm text-white/70 mb-2">Typ *</label>
            <select name="type" class="w-full rounded-xl px-4 py-3 bg-white/10 text-white outline-none focus:ring-2 focus:ring-brand-600" required>
              <option value="Einzahlung">💰 Einzahlung</option>
              <option value="Auszahlung">💸 Auszahlung</option>
              <option value="Gutschrift">✨ Gutschrift</option>
            </select>
          </div>
          <div>
            <label class="block text-sm text-white/70 mb-2">Betrag (€) *</label>
            <input type="number" name="amount" step="0.01" min="0.01" placeholder="0.00"
                   class="w-full rounded-xl px-4 py-3 bg-white/10 text-white outline-none focus:ring-2 focus:ring-brand-600" required />
          </div>
          <div>
            <label class="block text-sm text-white/70 mb-2">Notiz</label>
            <input type="text" name="note" placeholder="Optional" maxlength="100"
                   class="w-full rounded-xl px-4 py-3 bg-white/10 text-white outline-none focus:ring-2 focus:ring-brand-600" />
          </div>
          <div class="pt-4 flex justify-end gap-3">
            <button type="button" onclick="closeTransactionModal()" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20">Abbrechen</button>
            <button type="submit" class="px-4 py-2 rounded-xl bg-gradient-to-r from-brand-600 to-purple-600 font-medium">Transaktion speichern</button>
          </div>
          <p id="transactionModalMsg" class="text-sm text-center mt-2"></p>
        </form>
      </div>
    </div>
  </div>

  <script>
    gsap.registerPlugin(ScrollTrigger);

    // Sidebar toggle (mobile)
    const sidebar = document.getElementById('sidebar');
    document.getElementById('toggleSidebar').addEventListener('click', () => {
      sidebar.classList.toggle('open');
    });

    // Particles like landing
    (function particleBg(){
      const canvas = document.getElementById('particles');
      const ctx = canvas.getContext('2d');
      let w = canvas.width = innerWidth, h = canvas.height = innerHeight, DPR = Math.max(1, devicePixelRatio||1);
      canvas.width = w*DPR; canvas.height = h*DPR; canvas.style.width = w+'px'; canvas.style.height = h+'px'; ctx.scale(DPR,DPR);
      const particles = []; const count = Math.round((w*h)/90000);
      for(let i=0;i<count;i++){ particles.push({ x:Math.random()*w, y:Math.random()*h, vx:(Math.random()-0.5)*0.3, vy:(Math.random()-0.5)*0.3, r:1+Math.random()*2 }) }
      function tick(){
        ctx.clearRect(0,0,w,h);
        for(const p of particles){ p.x+=p.vx; p.y+=p.vy; if(p.x<0)p.x=w; if(p.x>w)p.x=0; if(p.y<0)p.y=h; if(p.y>h)p.y=0;
          ctx.beginPath(); ctx.fillStyle='rgba(255,255,255,0.06)'; ctx.arc(p.x,p.y,p.r,0,Math.PI*2); ctx.fill();
        }
        for(let i=0;i<particles.length;i++){ for(let j=i+1;j<particles.length;j++){ const a=particles[i], b=particles[j]; const dx=a.x-b.x, dy=a.y-b.y, d=dx*dx+dy*dy; if(d<9000){ const alpha=0.03*(1-d/9000); ctx.strokeStyle='rgba(140,160,255,'+alpha+')'; ctx.lineWidth=1; ctx.beginPath(); ctx.moveTo(a.x,a.y); ctx.lineTo(b.x,b.y); ctx.stroke(); } } }
        requestAnimationFrame(tick);
      }
      tick();
      addEventListener('resize',()=>{ w=canvas.width=innerWidth; h=canvas.height=innerHeight; canvas.width=w*DPR; canvas.height=h*DPR; canvas.style.width=w+'px'; canvas.style.height=h+'px'; ctx.scale(DPR,DPR); });
    })();

    // Global state
    let members = [];
    let admins = [];
    let membersChart = null;
    let balanceChart = null;
    let transactions = [];
    let currentPage = 1;
    const perPage = 10;
    let txFilter = 'all';
    let txSearch = '';

    // Helpers
    const $ = (sel) => document.querySelector(sel);
    const $$ = (sel) => Array.from(document.querySelectorAll(sel));
    const fmtEuro = (v) => '€ ' + Number(v||0).toFixed(2);

    function animateCounter(el, start, end, duration, formatter = (v)=>v){
      const t0 = performance.now();
      function up(t){
        const p = Math.min((t-t0)/duration, 1);
        const ease = 1 - Math.pow(1-p, 4);
        const val = start + (end-start)*ease;
        el.textContent = formatter(val);
        if(p<1) requestAnimationFrame(up);
      }
      requestAnimationFrame(up);
    }

    // Init
    document.addEventListener('DOMContentLoaded', () => {
      initApp();
      $('#searchTransactions').addEventListener('input', debounce((e)=>{ txSearch = e.target.value.toLowerCase(); renderTransactions(); }, 250));
      $('#transactionFilter').addEventListener('change', loadTransactions);
      $$('#transactions .txchip').forEach(btn => btn.addEventListener('click', () => { txFilter = btn.dataset.q; renderTransactions(); }));
      $('#reloadMembers').addEventListener('click', loadMembers);
      $('#reloadShifts').addEventListener('click', loadShiftsToday);
      $('#btnOpenTxModal').addEventListener('click', openTransactionModal);

      // Forms
      $('#addMemberForm').addEventListener('submit', onAddMember);
      $('#transactionFormInline').addEventListener('submit', onAddTransactionInline);
      $('#transactionFormModal').addEventListener('submit', onAddTransactionModal);
    });

    function debounce(fn, wait){
      let to; return (...a)=>{ clearTimeout(to); to=setTimeout(()=>fn(...a), wait) }
    }

    async function initApp(){
      try{
        await Promise.all([loadMembers(), loadBalance(), loadShiftsToday(), loadTransactions()]);
        initCharts();
        gsap.utils.toArray('.section, .stat-card, .glass-strong').forEach((el,i)=>{
          gsap.from(el,{ scrollTrigger:{trigger:el, start:'top 90%', once:true }, duration:.7, y:30, opacity:0, ease:'power3.out', delay:i*0.03 });
        });
      }catch(e){ console.error(e); }
    }

    // API Loads
    async function loadMembers(){
      const [mRes, aRes] = await Promise.all([
        fetch('../api/get_members.php'),
        fetch('../api/get_admins.php')
      ]);
      const m = await mRes.json();
      const a = await aRes.json();
      members = Array.isArray(m) ? m : [];
      admins  = Array.isArray(a) ? a.map(x=>x.member_name) : [];

      // mark admins
      members = members.map(x => ({...x, is_admin: admins.includes(x.name)}));

      // Render table
      const tbody = $('#memberRows');
      tbody.innerHTML = '';
      if(members.length===0){
        tbody.innerHTML = '<tr><td colspan="3" class="py-8 text-center text-white/50">Keine Mitglieder gefunden</td></tr>';
      } else {
        members.forEach((m, i) => {
          const tr = document.createElement('tr');
          tr.className = 'border-b border-white/5 hover:bg-white/5 transition-colors';
          tr.innerHTML = `
            <td class="py-3 px-2 font-medium">${m.name} ${m.is_admin ? '👑' : ''}</td>
            <td class="py-3 px-2 text-lg">${m.flag || '🌍'}</td>
            <td class="py-3 px-2">
              <div class="flex gap-2 flex-wrap">
                <button class="px-3 py-1.5 rounded-lg ${m.is_admin ? 'bg-purple-600/20 hover:bg-purple-600/30 text-purple-400' : 'bg-green-600/20 hover:bg-green-600/30 text-green-400'} text-xs font-medium transition-colors" data-act="toggle-admin" data-name="${m.name}" data-current="${m.is_admin ? '1' : '0'}">
                  ${m.is_admin ? '👑 Admin entfernen' : '⚡ Admin machen'}
                </button>
                <button class="px-3 py-1.5 rounded-lg bg-blue-600/20 hover:bg-blue-600/30 text-blue-400 text-xs font-medium transition-colors" data-act="pin" data-name="${m.name}">
                  PIN ändern
                </button>
                <button class="px-3 py-1.5 rounded-lg bg-red-600/20 hover:bg-red-600/30 text-red-400 text-xs font-medium transition-colors" data-act="ban" data-name="${m.name}">
                  Sperren
                </button>
              </div>
            </td>
          `;
          tbody.appendChild(tr);
        });
      }

      // Actions
      tbody.addEventListener('click', onMemberAction);

      // Fill selects
      const select = $('#transactionMember');
      if(select){
        const current = select.value;
        select.innerHTML = '<option value="">Bitte wählen...</option>';
        members.forEach(m => {
          const opt = document.createElement('option');
          opt.value = m.name; opt.textContent = m.name;
          if(opt.value === current) opt.selected = true;
          select.appendChild(opt);
        });
      }

      // KPI
      animateCounter($('#anzMembers'), 0, members.length, 800, v => Math.floor(v));
      updateMembersChart(members);
    }

    async function loadBalance(){
      const res = await fetch('/api/get_balance.php');
      const data = await res.json();
      const total = Array.isArray(data) ? data.reduce((s,i)=>s+(parseFloat(i.balance||0)||0),0) : 0;
      animateCounter($('#kassenstand'), 0, total, 1200, v => fmtEuro(v));
      updateBalanceChart(data);
    }

    async function loadShiftsToday(){
      try{
        const res = await fetch('/api/get_shifts.php');
        const data = await res.json();
        const today = new Date().toISOString().split('T')[0];
        const todayShifts = (Array.isArray(data)?data:[]).filter(s => s.shift_date === today);

        // KPI
        animateCounter($('#shiftsToday'), 0, todayShifts.length, 800, v => Math.floor(v));

        // Table
        const tbody = $('#shiftsRows');
        tbody.innerHTML = '';
        if(todayShifts.length===0){
          tbody.innerHTML = '<tr><td colspan="5" class="py-8 text-center text-white/50">Keine Schichten für heute</td></tr>';
        } else {
          todayShifts.forEach(s=>{
            const tr = document.createElement('tr');
            tr.className = 'border-b border-white/5 hover:bg-white/5 transition-colors';
            tr.innerHTML = `
              <td class="py-3 px-2">${s.member_name || s.name || 'N/A'}</td>
              <td class="py-3 px-2">${s.shift_date || 'N/A'}</td>
              <td class="py-3 px-2">${s.shift_start || '—'}</td>
              <td class="py-3 px-2">${s.shift_end || '—'}</td>
              <td class="py-3 px-2 text-right">
                <button data-del-shift="${s.id}" class="px-2 py-1 rounded-lg bg-red-600/20 hover:bg-red-600/30 text-red-400 text-xs font-medium">🗑️</button>
              </td>`;
            tbody.appendChild(tr);
          });
          tbody.addEventListener('click', async (e)=>{
            const btn = e.target.closest('button[data-del-shift]');
            if(!btn) return;
            if(!confirm('Schicht wirklich löschen?')) return;
            const id = btn.getAttribute('data-del-shift');
            const r = await fetch('/api/delete_shift.php',{method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({id})});
            const j = await r.json();
            if(j.status==='ok'){ loadShiftsToday(); } else { alert(j.error||'Fehler'); }
          });
        }
      }catch(e){
        $('#shiftsRows').innerHTML = '<tr><td colspan="5" class="py-8 text-center text-red-400">Fehler beim Laden</td></tr>';
      }
    }

    async function loadTransactions(){
      const filter = $('#transactionFilter')?.value || '';
      const url = filter ? `/api/get_transactions.php?name=${encodeURIComponent(filter)}&limit=200` : '/api/get_all_transactions.php?limit=200';
      const res = await fetch(url);
      const data = await res.json();
      transactions = Array.isArray(data) ? data : [];
      currentPage = 1;

      // Populate filter list (ensure unique names)
      const sel = $('#transactionFilter');
      if(sel && sel.options.length <= 1){
        const names = [...new Set(transactions.map(t=>t.name).filter(Boolean))].sort();
        names.forEach(n=>{ const o=document.createElement('option'); o.value=n; o.textContent=n; sel.appendChild(o); });
      }
      renderTransactions();
    }

    function renderTransactions(){
      const tbody = $('#transactionsTable');
      if(!transactions.length){
        tbody.innerHTML = '<tr><td colspan="6" class="py-8 text-center text-white/50">Keine Transaktionen</td></tr>';
        $('#totalTransactions').textContent = '0';
        $('#pageInfo').textContent = '0–0';
        $('#btnPrev').disabled = true; $('#btnNext').disabled = true;
        return;
      }
      // filter by chip
      let list = transactions.slice();
      if(txFilter==='einzahlung'){ list = list.filter(t => t.type==='Einzahlung' || t.type==='Gutschrift'); }
      if(txFilter==='auszahlung'){ list = list.filter(t => t.type==='Auszahlung'); }
      if(txFilter==='heute'){ const today = new Date().toISOString().split('T')[0]; list = list.filter(t => (t.date||'').startsWith(today)); }
      if(txSearch){ list = list.filter(t => (t.name||'').toLowerCase().includes(txSearch) || (t.note||t.reason||'').toLowerCase().includes(txSearch)); }

      const total = list.length;
      $('#totalTransactions').textContent = total.toString();
      const start = (currentPage-1)*perPage;
      const pageItems = list.slice(start, start+perPage);
      tbody.innerHTML = '';
      pageItems.forEach(t=>{
        const tr = document.createElement('tr');
        const isPos = (t.type==='Einzahlung' || t.type==='Gutschrift');
        tr.className = 'border-b border-white/5 hover:bg-white/5 transition-colors';
        tr.innerHTML = `
          <td class="py-3 px-2 text-white/70">${t.date || 'N/A'}</td>
          <td class="py-3 px-2 font-medium">${t.name || 'N/A'}</td>
          <td class="py-3 px-2">
            <span class="px-2 py-1 rounded-lg text-xs font-medium ${isPos ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'}">${t.type || 'N/A'}</span>
          </td>
          <td class="py-3 px-2 font-bold ${isPos ? 'text-emerald-400' : 'text-red-400'}">${isPos?'+':'-'}${Number(t.amount||0).toFixed(2)} €</td>
          <td class="py-3 px-2 text-white/60 text-xs max-w-xs truncate" title="${t.note || t.reason || ''}">${t.note || t.reason || '–'}</td>
          <td class="py-3 px-2 text-right">
            <button data-del-tx="${t.id}" class="px-2 py-1 rounded-lg bg-red-600/20 hover:bg-red-600/30 text-red-400 text-xs font-medium">🗑️</button>
          </td>`;
        tbody.appendChild(tr);
      });

      // Pagination
      const end = Math.min(start + pageItems.length, total);
      $('#pageInfo').textContent = total ? `${start+1}–${end}` : '0–0';
      $('#btnPrev').disabled = currentPage<=1;
      $('#btnNext').disabled = end>=total;

      tbody.onclick = async (e)=>{
        const btn = e.target.closest('button[data-del-tx]');
        if(!btn) return;
        if(!confirm('Transaktion wirklich löschen?')) return;
        const id = btn.getAttribute('data-del-tx');
        const r = await fetch('/api/delete_transaction.php', {
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body: new URLSearchParams({ id })
        });
        const j = await r.json();
        if(j.status==='ok'){ await loadBalance(); await loadTransactions(); } else { alert(j.error||'Fehler'); }
      };
    }

    function prevPage(){ if(currentPage>1){ currentPage--; renderTransactions(); } }
    function nextPage(){
      const total = Number($('#totalTransactions').textContent||0);
      if((currentPage*perPage) < total){ currentPage++; renderTransactions(); }
    }

    // Member actions
    async function onMemberAction(e){
      const btn = e.target.closest('button'); if(!btn) return;
      const act = btn.dataset.act; const name = btn.dataset.name;
      if(!act || !name) return;

      if(act==='toggle-admin'){
        const isAdmin = btn.dataset.current==='1';
        if(!confirm(isAdmin ? `${name} Admin-Rechte entziehen?` : `${name} Admin-Rechte geben?`)) return;
        const fd = new FormData();
        fd.append('member_name', name);
        fd.append('action', isAdmin ? 'remove' : 'add');
        const r = await fetch('/api/admin_toggle_admin.php', { method:'POST', body: fd });
        const j = await r.json();
        if(j.status==='ok'){ alert(j.message||'Admin-Rechte aktualisiert ✅'); loadMembers(); }
        else { alert(j.error||'Fehler'); }
      }

      if(act==='pin'){
        const pin = prompt(`Neue PIN für ${name} (4–6 Ziffern)`);
        if(!pin) return;
        const fd = new FormData();
        fd.append('name', name);
        fd.append('pin', pin);
        const r = await fetch('/api/admin_change_pin.php', { method:'POST', body: fd });
        const j = await r.json();
        alert(j.status==='ok' ? 'PIN aktualisiert ✅' : (j.error||'Fehler'));
      }

      if(act==='ban'){
        if(!confirm(`${name} wirklich sperren?`)) return;
        const fd = new FormData();
        fd.append('name', name);
        const r = await fetch('/api/admin_ban_user.php', { method:'POST', body: fd });
        const j = await r.json();
        alert(j.status==='ok' ? 'Benutzer gesperrt ✅' : (j.error||'Fehler'));
        loadMembers();
      }
    }

    // Add member
    async function onAddMember(e){
      e.preventDefault();
      const fd = new FormData(e.target);
      const r = await fetch('/admin_add_user.php', { method:'POST', body: fd });
      const j = await r.json().catch(()=>({error:'Serverfehler'}));
      const msg = $('#addMsg');
      if(j.status==='ok'){
        msg.textContent = 'Angelegt ✅'; msg.className = 'text-emerald-400 text-sm text-center';
        e.target.reset(); await loadMembers(); await loadBalance(); await loadTransactions();
      } else {
        msg.textContent = j.error || 'Fehler'; msg.className = 'text-red-400 text-sm text-center';
      }
    }

    // Add transaction (inline)
    async function onAddTransactionInline(e){
      e.preventDefault();
      const fd = new FormData(e.target);
      const r = await fetch('/api/add_transaction.php', { method:'POST', body: fd });
      const j = await r.json().catch(()=>({error:'Serverfehler'}));
      const msg = $('#transactionMsg');
      if(j.status==='ok'){
        msg.textContent = 'Transaktion hinzugefügt ✅'; msg.className = 'text-emerald-400 text-sm text-center';
        e.target.reset(); await loadBalance(); await loadTransactions();
      } else {
        msg.textContent = j.error || 'Fehler'; msg.className = 'text-red-400 text-sm text-center';
      }
    }

    // Modal controls
    function openTransactionModal(){
      const modal = $('#transactionModal');
      // sync member options
      const sel = modal.querySelector('select[name="name"]');
      sel.innerHTML = '<option value="">Bitte wählen...</option>';
      members.forEach(m => { const o=document.createElement('option'); o.value=m.name; o.textContent=m.name; sel.appendChild(o); });
      modal.classList.remove('hidden');
      gsap.fromTo(modal.querySelector('.inline-block'), {y:30, opacity:0}, {y:0, opacity:1, duration:.25, ease:'power2.out'});
    }
    function closeTransactionModal(){ $('#transactionModal').classList.add('hidden'); }
    async function onAddTransactionModal(e){
      e.preventDefault();
      const fd = new FormData(e.target);
      const r = await fetch('/api/add_transaction.php', { method:'POST', body: fd });
      const j = await r.json().catch(()=>({error:'Serverfehler'}));
      const msg = $('#transactionModalMsg');
      if(j.status==='ok'){
        msg.textContent = 'Transaktion hinzugefügt ✅'; msg.className = 'text-emerald-400 text-sm text-center';
        e.target.reset(); await loadBalance(); await loadTransactions(); setTimeout(closeTransactionModal, 400);
      } else {
        msg.textContent = j.error || 'Fehler'; msg.className = 'text-red-400 text-sm text-center';
      }
    }

    // Charts
    function initCharts(){ updateMembersChart(members); /* balance chart set by loadBalance */ }
    function updateMembersChart(list){
      const ctx = document.getElementById('membersChart').getContext('2d');
      if(membersChart) membersChart.destroy();
      const counts = {};
      list.forEach(m => { const f=m.flag||'🌍'; counts[f]=(counts[f]||0)+1; });
      membersChart = new Chart(ctx, {
        type: 'doughnut',
        data: { labels:Object.keys(counts), datasets:[{ data:Object.values(counts), borderWidth:0 }] },
        options: {
          responsive:true, maintainAspectRatio:false,
          plugins:{ legend:{ position:'bottom', labels:{ color:'rgba(255,255,255,.85)' } } }
        }
      });
    }
    function updateBalanceChart(data){
      const ctx = document.getElementById('balanceChart').getContext('2d');
      if(balanceChart) balanceChart.destroy();
      const sorted = (Array.isArray(data)?data:[]).slice().sort((a,b)=>parseFloat(b.balance||0)-parseFloat(a.balance||0)).slice(0,6);
      balanceChart = new Chart(ctx, {
        type:'bar',
        data:{ labels:sorted.map(d=>d.name), datasets:[{ data:sorted.map(d=>parseFloat(d.balance||0)), borderRadius:8, borderSkipped:false }] },
        options:{
          responsive:true, maintainAspectRatio:false,
          plugins:{ legend:{ display:false }, tooltip:{ backgroundColor:'rgba(0,0,0,.85)', titleColor:'#fff', bodyColor:'#fff' } },
          scales:{
            y:{ beginAtZero:true, grid:{ color:'rgba(255,255,255,.12)' }, ticks:{ color:'rgba(255,255,255,.75)', callback:v=>'€ '+v } },
            x:{ grid:{ display:false }, ticks:{ color:'rgba(255,255,255,.75)' } }
          }
        }
      });
    }
  </script>
</body>
</html>
