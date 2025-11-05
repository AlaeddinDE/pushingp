<?php require_once 'includes/auth.php'; ?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Pushing P — Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { display: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
          boxShadow: { 
            glow: '0 0 40px rgba(99,102,241,0.3), inset 0 0 30px rgba(236,72,153,0.2)',
            glowStrong: '0 0 60px rgba(99,102,241,0.5), inset 0 0 40px rgba(236,72,153,0.3)'
          },
          colors: { brand: {600:'#6366f1', 700:'#4f46e5'} }
        }
      }
    }
  </script>
  <style>
    :root { color-scheme: dark; }
    html, body { height: 100%; -webkit-font-smoothing: antialiased; text-rendering: optimizeLegibility; }
    body {
      background: radial-gradient(1400px 900px at 10% 10%, rgba(99,102,241,0.15), transparent 60%),
                  radial-gradient(1200px 700px at 90% 30%, rgba(236,72,153,0.12), transparent 60%),
                  radial-gradient(800px 600px at 50% 80%, rgba(59,130,246,0.08), transparent 50%),
                  #0a0b10;
      background-attachment: fixed;
    }
    .grad-text {
      background: linear-gradient(135deg, #60a5fa 0%, #a78bfa 30%, #f472b6 60%, #22d3ee 90%);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      background-size: 200% 200%;
      animation: gradientShift 8s ease infinite;
    }
    @keyframes gradientShift {
      0%, 100% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
    }
    .glass {
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.1);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
    }
    .glass-strong {
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.15);
      backdrop-filter: blur(25px);
      -webkit-backdrop-filter: blur(25px);
    }
    .magnet {
      transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .magnet:hover {
      transform: scale(1.05) translateY(-2px);
    }
    .pulse-glow {
      animation: pulseGlow 2s ease-in-out infinite;
    }
    @keyframes pulseGlow {
      0%, 100% { box-shadow: 0 0 20px rgba(99,102,241,0.3); }
      50% { box-shadow: 0 0 40px rgba(99,102,241,0.6); }
    }
    .float {
      animation: float 6s ease-in-out infinite;
    }
    @keyframes float {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-20px); }
    }
    .slide-in {
      opacity: 0;
      transform: translateY(30px);
    }
    .slide-in.animated {
      opacity: 1 !important;
      transform: translateY(0) !important;
    }
    .stat-card {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .stat-card:hover {
      transform: translateY(-8px) scale(1.02);
      box-shadow: 0 20px 60px rgba(99,102,241,0.4);
    }
    .number-counter {
      font-variant-numeric: tabular-nums;
    }
  </style>
</head>
<body class="font-display text-white min-h-screen">
  <!-- Header -->
  <header class="fixed top-0 left-0 w-full z-50">
    <nav class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pt-4">
      <div class="flex items-center justify-between glass-strong rounded-2xl px-6 py-4 shadow-glow">
        <a href="/" class="flex items-center gap-3 group">
          <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-blue-600 to-purple-600 grid place-items-center shadow-lg group-hover:scale-110 transition-transform">
            <span class="text-xl font-black">P</span>
          </div>
          <div>
            <span class="font-extrabold tracking-tight text-lg">Pushing P</span>
            <div class="text-xs text-white/50">Member Dashboard</div>
          </div>
        </a>
        <div class="flex items-center gap-4">
          <div class="hidden sm:flex items-center gap-2 px-4 py-2 rounded-xl bg-white/10">
            <div class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></div>
            <span class="text-sm font-medium"><?= htmlspecialchars($user) ?></span>
          </div>
          <?php if ($isAdmin): ?>
            <a href="admin/" class="px-4 py-2 rounded-xl bg-rose-600/20 hover:bg-rose-600/30 text-rose-200 transition-colors text-sm font-medium">Admin</a>
          <?php endif; ?>
          <a href="index.html" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 transition-colors text-sm font-medium">🏠 Start</a>
          <a href="logout.php" class="px-4 py-2 rounded-xl bg-red-600/20 hover:bg-red-600/30 text-red-400 transition-colors text-sm font-medium">Abmelden</a>
        </div>
      </div>
    </nav>
  </header>

  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-32 pb-16">
    <!-- Welcome Section -->
    <section class="mb-12 slide-in">
      <h1 class="text-5xl sm:text-6xl font-black grad-text mb-3">Willkommen zurück,</h1>
      <p class="text-2xl sm:text-3xl font-bold text-white/90"><?= htmlspecialchars($user) ?> 👋</p>
      <p class="text-white/60 mt-2">Hier ist dein persönliches Dashboard</p>
    </section>

    <!-- Stats Grid -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
      <div class="stat-card glass-strong rounded-2xl p-6 slide-in" style="animation-delay: 0.1s">
        <div class="flex items-center justify-between mb-3">
          <div class="text-3xl">💰</div>
          <div class="h-8 w-8 rounded-lg bg-emerald-500/20 flex items-center justify-center">
            <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
          </div>
        </div>
        <div class="text-3xl font-black number-counter mb-1" id="balance">–</div>
        <div class="text-white/50 text-sm">Dein Kassenstand</div>
      </div>

      <div class="stat-card glass-strong rounded-2xl p-6 slide-in" style="animation-delay: 0.2s">
        <div class="flex items-center justify-between mb-3">
          <div class="text-3xl">🕓</div>
          <div class="h-8 w-8 rounded-lg bg-blue-500/20 flex items-center justify-center">
            <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
          </div>
        </div>
        <div class="text-3xl font-black number-counter mb-1" id="shiftCount">–</div>
        <div class="text-white/50 text-sm">Schichten</div>
      </div>

      <div class="stat-card glass-strong rounded-2xl p-6 slide-in" style="animation-delay: 0.3s">
        <div class="flex items-center justify-between mb-3">
          <div class="text-3xl">📊</div>
          <div class="h-8 w-8 rounded-lg bg-purple-500/20 flex items-center justify-center">
            <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
          </div>
        </div>
        <div class="text-3xl font-black number-counter mb-1" id="totalShifts">–</div>
        <div class="text-white/50 text-sm">Gesamt</div>
      </div>

      <div class="stat-card glass-strong rounded-2xl p-6 slide-in pulse-glow" style="animation-delay: 0.4s">
        <div class="flex items-center justify-between mb-3">
          <div class="text-3xl">🟢</div>
          <div class="h-8 w-8 rounded-lg bg-emerald-500/20 flex items-center justify-center">
            <div class="h-3 w-3 rounded-full bg-emerald-400 animate-pulse"></div>
          </div>
        </div>
        <div class="text-3xl font-black mb-1">LIVE</div>
        <div class="text-white/50 text-sm">Status</div>
      </div>
    </section>

    <!-- Main Content Grid -->
    <section class="grid lg:grid-cols-3 gap-6 mb-8">
      <!-- Balance Chart -->
      <div class="lg:col-span-2 glass-strong rounded-2xl p-6 slide-in">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-2xl font-bold">Finanzübersicht</h2>
          <button id="refreshBalance" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 transition-colors text-sm font-medium flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            Aktualisieren
          </button>
        </div>
        <div class="h-64">
          <canvas id="balanceChart"></canvas>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="glass-strong rounded-2xl p-6 slide-in">
        <h2 class="text-xl font-bold mb-6">Schnellaktionen</h2>
        <div class="space-y-3">
          <button onclick="openShiftModal()" class="magnet w-full px-4 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-purple-600 font-semibold shadow-lg hover:shadow-glow transition-all">
            ➕ Schicht eintragen
          </button>
          <button onclick="openVacationModal()" class="magnet w-full px-4 py-3 rounded-xl glass hover:glass-strong font-semibold transition-all">
            🛫 Urlaub eintragen
          </button>
          <button onclick="openSickModal()" class="magnet w-full px-4 py-3 rounded-xl glass hover:glass-strong font-semibold transition-all">
            🤒 Krank melden
          </button>
          <button onclick="toggleTransactions()" class="magnet w-full px-4 py-3 rounded-xl glass hover:glass-strong font-semibold transition-all">
            💰 Transaktionen anzeigen
          </button>
        </div>
      </div>
    </section>

    <!-- Shifts Section -->
    <section class="glass-strong rounded-2xl p-6 slide-in">
      <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold">🕓 Deine Schichten</h2>
        <button id="refreshShifts" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 transition-colors text-sm font-medium">
          Aktualisieren
        </button>
      </div>
      <div id="shiftsList" class="space-y-3">
        <div class="text-center py-12 text-white/50">
          <div class="text-4xl mb-3">⏳</div>
          <p>Lädt Schichten...</p>
        </div>
      </div>
    </section>

    <!-- Absences Section -->
    <section class="glass-strong rounded-2xl p-6 slide-in">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
          <h2 class="text-2xl font-bold">🗓️ Abwesenheiten</h2>
          <p class="text-white/60 text-sm">Urlaub und Krankmeldungen im Überblick</p>
        </div>
        <div class="flex gap-2 flex-wrap">
          <button onclick="openVacationModal()" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 transition-colors text-sm font-medium">🛫 Urlaub eintragen</button>
          <button onclick="openSickModal()" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 transition-colors text-sm font-medium">🤒 Krank melden</button>
        </div>
      </div>
      <div class="grid md:grid-cols-2 gap-6">
        <div>
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-semibold">Urlaub</h3>
            <span class="text-white/50 text-sm" id="vacationCount">– Einträge</span>
          </div>
          <ul id="vacationList" class="space-y-3">
            <li class="text-white/50 text-sm text-center py-6">Noch keine Urlaube geplant</li>
          </ul>
        </div>
        <div>
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-semibold">Krankmeldungen</h3>
            <span class="text-white/50 text-sm" id="sickdayCount">– Einträge</span>
          </div>
          <ul id="sickdayList" class="space-y-3">
            <li class="text-white/50 text-sm text-center py-6">Keine Krankmeldungen hinterlegt</li>
          </ul>
        </div>
      </div>
    </section>

    <!-- Transactions Section -->
    <section id="transactionsSection" class="glass-strong rounded-2xl p-6 slide-in hidden">
      <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold">💰 Transaktionen</h2>
        <button onclick="toggleTransactions()" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 transition-colors text-sm font-medium">
          Schließen
        </button>
      </div>
      <div id="transactionsList" class="space-y-3">
        <div class="text-center py-12 text-white/50">
          <div class="text-4xl mb-3">⏳</div>
          <p>Lädt Transaktionen...</p>
        </div>
      </div>
    </section>
  </main>

  <!-- Shift Modal -->
  <div id="shiftModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="glass-strong rounded-2xl p-8 max-w-md w-full slide-in">
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-2xl font-bold">Schicht eintragen</h3>
        <button onclick="closeShiftModal()" class="text-white/60 hover:text-white">✕</button>
      </div>
      <form id="shiftForm" class="space-y-4">
        <div>
          <label class="block text-sm text-white/70 mb-2">Datum</label>
          <input type="date" name="shift_date" required class="w-full rounded-xl px-4 py-3 text-black outline-none focus:ring-2 focus:ring-brand-600" value="<?= date('Y-m-d') ?>">
        </div>
        <div>
          <label class="block text-sm text-white/70 mb-2">Schichttyp</label>
          <select name="shift_type" id="shiftType" class="w-full rounded-xl px-4 py-3 text-black outline-none focus:ring-2 focus:ring-brand-600">
            <option value="early">Früh (06:00 – 14:00)</option>
            <option value="late">Spät (14:00 – 22:00)</option>
            <option value="night">Nacht (22:00 – 06:00)</option>
            <option value="day">Tag (07:00 – 17:30)</option>
            <option value="custom" selected>Eigene Zeiten</option>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm text-white/70 mb-2">Start</label>
            <input type="time" name="shift_start" required class="w-full rounded-xl px-4 py-3 text-black outline-none focus:ring-2 focus:ring-brand-600">
          </div>
          <div>
            <label class="block text-sm text-white/70 mb-2">Ende</label>
            <input type="time" name="shift_end" required class="w-full rounded-xl px-4 py-3 text-black outline-none focus:ring-2 focus:ring-brand-600">
          </div>
        </div>
        <div class="flex gap-3">
          <button type="submit" class="flex-1 rounded-xl bg-gradient-to-r from-brand-600 to-purple-600 px-4 py-3 font-semibold shadow-lg hover:shadow-glow transition-all">
            Speichern ✨
          </button>
          <button type="button" onclick="closeShiftModal()" class="px-4 py-3 rounded-xl glass hover:glass-strong font-semibold transition-all">
            Abbrechen
          </button>
        </div>
        <div class="text-xs text-white/50 text-center">
          Du kannst eigene Schichten jederzeit löschen
        </div>
        <p id="shiftMsg" class="text-sm text-center min-h-[20px]"></p>
      </form>
    </div>
  </div>

  <!-- Vacation Modal -->
  <div id="vacationModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="glass-strong rounded-2xl p-8 max-w-md w-full slide-in">
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-2xl font-bold">Urlaub eintragen</h3>
        <button onclick="closeVacationModal()" class="text-white/60 hover:text-white">✕</button>
      </div>
      <form id="vacationForm" class="space-y-4">
        <div>
          <label class="block text-sm text-white/70 mb-2">Startdatum</label>
          <input type="date" name="start" required class="w-full rounded-xl px-4 py-3 text-black outline-none focus:ring-2 focus:ring-brand-600" value="<?= date('Y-m-d') ?>">
        </div>
        <div>
          <label class="block text-sm text-white/70 mb-2">Enddatum</label>
          <input type="date" name="end" required class="w-full rounded-xl px-4 py-3 text-black outline-none focus:ring-2 focus:ring-brand-600" value="<?= date('Y-m-d', strtotime('+1 day')) ?>">
        </div>
        <div class="flex gap-3">
          <button type="submit" class="flex-1 rounded-xl bg-gradient-to-r from-brand-600 to-purple-600 px-4 py-3 font-semibold shadow-lg hover:shadow-glow transition-all">Speichern ✨</button>
          <button type="button" onclick="closeVacationModal()" class="px-4 py-3 rounded-xl glass hover:glass-strong font-semibold transition-all">Abbrechen</button>
        </div>
        <p id="vacationMsg" class="text-sm text-center min-h-[20px]"></p>
      </form>
    </div>
  </div>

  <!-- Sickday Modal -->
  <div id="sickModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="glass-strong rounded-2xl p-8 max-w-md w-full slide-in">
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-2xl font-bold">Krank melden</h3>
        <button onclick="closeSickModal()" class="text-white/60 hover:text-white">✕</button>
      </div>
      <form id="sickForm" class="space-y-4">
        <div>
          <label class="block text-sm text-white/70 mb-2">Startdatum</label>
          <input type="date" name="start" required class="w-full rounded-xl px-4 py-3 text-black outline-none focus:ring-2 focus:ring-brand-600" value="<?= date('Y-m-d') ?>">
        </div>
        <div>
          <label class="block text-sm text-white/70 mb-2">Enddatum</label>
          <input type="date" name="end" required class="w-full rounded-xl px-4 py-3 text-black outline-none focus:ring-2 focus:ring-brand-600" value="<?= date('Y-m-d', strtotime('+1 day')) ?>">
        </div>
        <div class="flex gap-3">
          <button type="submit" class="flex-1 rounded-xl bg-gradient-to-r from-brand-600 to-purple-600 px-4 py-3 font-semibold shadow-lg hover:shadow-glow transition-all">Speichern ✨</button>
          <button type="button" onclick="closeSickModal()" class="px-4 py-3 rounded-xl glass hover:glass-strong font-semibold transition-all">Abbrechen</button>
        </div>
        <p id="sickMsg" class="text-sm text-center min-h-[20px]"></p>
      </form>
    </div>
  </div>

  <script>
    const user = '<?= $user ?>';
    const memberId = <?= (int) $memberId ?>;
    const csrfToken = '<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>';
    let balanceChart = null;

    const shiftDefaults = {
      early: ['06:00', '14:00'],
      late: ['14:00', '22:00'],
      night: ['22:00', '06:00'],
      day: ['07:00', '17:30']
    };

    const shiftTypeLabels = {
      early: 'Frühschicht',
      late: 'Spätschicht',
      night: 'Nachtschicht',
      day: 'Tagschicht',
      custom: 'Eigene Zeiten'
    };

    const shiftTypeClasses = {
      early: 'bg-blue-500/20 text-blue-200',
      late: 'bg-purple-500/20 text-purple-200',
      night: 'bg-slate-500/20 text-slate-200',
      day: 'bg-emerald-500/20 text-emerald-200',
      custom: 'bg-white/10 text-white/70'
    };

    const dateRangeFormatter = new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
    const dateLabelFormatter = new Intl.DateTimeFormat('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit' });

    const absenceState = {
      vacations: [],
      sickdays: []
    };

    function parseDate(dateStr) {
      if (!dateStr) return null;
      const iso = `${dateStr}T00:00:00`;
      const date = new Date(iso);
      return Number.isNaN(date.getTime()) ? null : date;
    }

    function formatDateLabel(dateStr) {
      const date = parseDate(dateStr);
      return date ? dateLabelFormatter.format(date) : dateStr || 'n/a';
    }

    function formatDateRangeText(start, end) {
      const startDate = parseDate(start);
      const endDate = parseDate(end || start);
      if (!startDate) return '—';
      if (!endDate || start === end) {
        return dateRangeFormatter.format(startDate);
      }
      return `${dateRangeFormatter.format(startDate)} – ${dateRangeFormatter.format(endDate)}`;
    }

    function calcDurationDays(start, end) {
      const startDate = parseDate(start);
      const endDate = parseDate(end || start);
      if (!startDate) return 0;
      if (!endDate) return 1;
      const diff = Math.round((endDate - startDate) / (1000 * 60 * 60 * 24));
      return diff >= 0 ? diff + 1 : 1;
    }

    function formatTimeLabel(value) {
      if (!value) return '—';
      return value.slice(0, 5);
    }

    // Balance laden
    async function loadBalance() {
      try {
        const res = await fetch('api/get_balance.php');
        const data = await res.json();
        const entry = data.find(x => x.name === user);
        const balance = entry ? parseFloat(entry.balance || 0) : 0;
        
        // Counter Animation
        const el = document.getElementById('balance');
        const target = balance.toFixed(2);
        animateCounter(el, 0, balance, 1500, (val) => `${val.toFixed(2)} €`);
        el.textContent = target + ' €';
        
        // Chart update
        updateChart(balance);
      } catch (e) {
        console.error('Balance error:', e);
        document.getElementById('balance').textContent = 'Fehler';
      }
    }

    // Counter Animation
    function animateCounter(element, start, end, duration, formatter = (v) => v) {
      const startTime = performance.now();
      function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const easeOutQuart = 1 - Math.pow(1 - progress, 4);
        const value = start + (end - start) * easeOutQuart;
        element.textContent = formatter(value);
        if (progress < 1) requestAnimationFrame(update);
      }
      requestAnimationFrame(update);
    }

    // Chart
    function updateChart(balance) {
      const ctx = document.getElementById('balanceChart').getContext('2d');
      if (balanceChart) balanceChart.destroy();
      
      balanceChart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun'],
          datasets: [{
            label: 'Kassenstand',
            data: [0, 0, 0, 0, 0, balance],
            borderColor: 'rgb(99, 102, 241)',
            backgroundColor: 'rgba(99, 102, 241, 0.1)',
            borderWidth: 3,
            tension: 0.4,
            fill: true,
            pointRadius: 6,
            pointHoverRadius: 8,
            pointBackgroundColor: 'rgb(99, 102, 241)',
            pointBorderColor: '#fff',
            pointBorderWidth: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              backgroundColor: 'rgba(0, 0, 0, 0.8)',
              titleColor: '#fff',
              bodyColor: '#fff',
              borderColor: 'rgba(99, 102, 241, 0.5)',
              borderWidth: 1,
              padding: 12,
              displayColors: false
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              grid: { color: 'rgba(255, 255, 255, 0.1)' },
              ticks: { color: 'rgba(255, 255, 255, 0.6)' }
            },
            x: {
              grid: { display: false },
              ticks: { color: 'rgba(255, 255, 255, 0.6)' }
            }
          }
        }
      });
    }

    // Shifts laden
    async function loadShifts() {
      try {
        const res = await fetch('api/get_shifts.php');
        const data = await res.json();
        const userShifts = data
          .filter(s => s.member_name === user)
          .sort((a, b) => {
            if (a.shift_date === b.shift_date) {
              return (a.start_time || a.shift_start || '').localeCompare(b.start_time || b.shift_start || '');
            }
            return (a.shift_date || '').localeCompare(b.shift_date || '');
          });

        document.getElementById('shiftCount').textContent = userShifts.length;
        document.getElementById('totalShifts').textContent = userShifts.length;

        const container = document.getElementById('shiftsList');
        if (userShifts.length === 0) {
          container.innerHTML = `
            <div class="text-center py-12 text-white/50">
              <div class="text-4xl mb-3">📅</div>
              <p>Noch keine Schichten eingetragen</p>
            </div>
          `;
          return;
        }

        container.innerHTML = '';
        userShifts.slice(0, 10).forEach((s, i) => {
          const start = formatTimeLabel(s.start_time || s.shift_start);
          const end = formatTimeLabel(s.end_time || s.shift_end);
          const typeKey = (s.shift_type || 'custom');
          const typeLabel = shiftTypeLabels[typeKey] || shiftTypeLabels.custom;
          const typeClass = shiftTypeClasses[typeKey] || shiftTypeClasses.custom;
          const dateLabel = formatDateLabel(s.shift_date);

          const shiftEl = document.createElement('div');
          shiftEl.className = 'glass rounded-xl p-4 hover:glass-strong transition-all';
          shiftEl.innerHTML = `
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <div class="font-semibold">${dateLabel}</div>
                <div class="text-sm text-white/60">${start} – ${end}</div>
              </div>
              <div class="flex items-center gap-2">
                <div class="px-3 py-1 rounded-lg text-sm font-medium ${typeClass}">
                  ${typeLabel}
                </div>
                <button onclick="deleteShift(${s.id})" class="px-2 py-1 rounded-lg bg-red-600/20 hover:bg-red-600/30 text-red-400 text-xs transition-colors" title="Schicht löschen">
                  🗑️
                </button>
              </div>
            </div>
          `;
          container.appendChild(shiftEl);
          gsap.from(shiftEl, {
            duration: 0.5,
            y: 20,
            opacity: 0,
            delay: i * 0.05,
            ease: 'power2.out'
          });
        });
      } catch (e) {
        console.error('Shifts error:', e);
        document.getElementById('shiftsList').innerHTML = `
          <div class="text-center py-12 text-red-400">
            <p>Fehler beim Laden der Schichten</p>
          </div>
        `;
      }
    }

    async function loadAbsences() {
      try {
        const [vacations, sickdays] = await Promise.all([
          fetch('api/v2/get_vacations.php').then(res => res.json()),
          fetch('api/v2/get_sickdays.php').then(res => res.json())
        ]);

        const vacationItems = vacations.status === 'success' ? (vacations.data?.vacations || []) : [];
        const sickItems = sickdays.status === 'success' ? (sickdays.data?.sickdays || []) : [];

        absenceState.vacations = vacationItems;
        absenceState.sickdays = sickItems;

        renderAbsenceList('vacationList', 'vacationCount', vacationItems, 'Noch keine Urlaube geplant', removeVacation);
        renderAbsenceList('sickdayList', 'sickdayCount', sickItems, 'Keine Krankmeldungen hinterlegt', removeSickday);
      } catch (error) {
        console.error('Absence error:', error);
        renderAbsenceList('vacationList', 'vacationCount', [], 'Fehler beim Laden der Urlaube', null);
        renderAbsenceList('sickdayList', 'sickdayCount', [], 'Fehler beim Laden der Krankmeldungen', null);
      }
    }

    function renderAbsenceList(listId, countId, items, emptyText, removeHandler) {
      const list = document.getElementById(listId);
      const counter = document.getElementById(countId);
      if (!list || !counter) return;

      counter.textContent = `${items.length} ${items.length === 1 ? 'Eintrag' : 'Einträge'}`;
      list.innerHTML = '';

      if (!items.length) {
        list.innerHTML = `<li class="text-white/50 text-sm text-center py-6">${emptyText}</li>`;
        return;
      }

      items.forEach(item => {
        const li = document.createElement('li');
        li.className = 'glass rounded-xl p-4 flex items-center justify-between gap-4 hover:glass-strong transition-all';
        const duration = calcDurationDays(item.start, item.end);
        li.innerHTML = `
          <div>
            <div class="font-semibold">${formatDateRangeText(item.start, item.end)}</div>
            <div class="text-xs text-white/50">${duration} ${duration === 1 ? 'Tag' : 'Tage'}</div>
          </div>
          ${removeHandler ? `<button class="px-2 py-1 rounded-lg bg-red-600/20 hover:bg-red-600/30 text-red-200 text-xs transition-colors" title="Eintrag löschen">🗑️</button>` : ''}
        `;
        if (removeHandler) {
          li.querySelector('button')?.addEventListener('click', () => removeHandler(item.id));
        }
        list.appendChild(li);
      });
    }

    async function removeVacation(id) {
      if (!confirm('Urlaub wirklich löschen?')) return;
      try {
        const res = await fetch('api/v2/save_vacation.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'delete', id, csrf_token: csrfToken })
        });
        const payload = await res.json();
        if (payload.status === 'success') {
          loadAbsences();
        } else {
          alert(payload.error || 'Urlaub konnte nicht gelöscht werden');
        }
      } catch (error) {
        alert('Fehler beim Löschen des Urlaubs');
      }
    }

    async function removeSickday(id) {
      if (!confirm('Krankmeldung wirklich löschen?')) return;
      try {
        const res = await fetch('api/v2/save_sickday.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'delete', id, csrf_token: csrfToken })
        });
        const payload = await res.json();
        if (payload.status === 'success') {
          loadAbsences();
        } else {
          alert(payload.error || 'Krankmeldung konnte nicht gelöscht werden');
        }
      } catch (error) {
        alert('Fehler beim Löschen der Krankmeldung');
      }
    }

    // Transactions laden
    async function loadTransactions() {
      try {
        const res = await fetch('api/get_transactions.php');
        const data = await res.json();
        const container = document.getElementById('transactionsList');
        
        if (data.length === 0) {
          container.innerHTML = `
            <div class="text-center py-12 text-white/50">
              <div class="text-4xl mb-3">📝</div>
              <p>Noch keine Transaktionen</p>
            </div>
          `;
          return;
        }
        
        container.innerHTML = '';
        data.forEach((t, i) => {
          const transEl = document.createElement('div');
          transEl.className = 'glass rounded-xl p-4 hover:glass-strong transition-all';
          transEl.innerHTML = `
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <div class="font-semibold">${t.type || 'N/A'}</div>
                <div class="text-sm text-white/60">${t.reason || 'Keine Notiz'}</div>
                <div class="text-xs text-white/50 mt-1">${t.date ? new Date(t.date).toLocaleDateString('de-DE') : 'N/A'}</div>
              </div>
              <div class="text-right">
                <div class="font-bold text-lg ${(t.type === 'Einzahlung' || t.type === 'Gutschrift') ? 'text-emerald-400' : 'text-red-400'}">
                  ${(t.type === 'Einzahlung' || t.type === 'Gutschrift') ? '+' : '-'}${parseFloat(t.amount || 0).toFixed(2)} €
                </div>
              </div>
            </div>
          `;
          container.appendChild(transEl);
          gsap.from(transEl, {
            duration: 0.5,
            y: 20,
            opacity: 0,
            delay: i * 0.05,
            ease: 'power2.out'
          });
        });
      } catch (e) {
        console.error('Transactions error:', e);
        document.getElementById('transactionsList').innerHTML = `
          <div class="text-center py-12 text-red-400">
            <p>Fehler beim Laden der Transaktionen</p>
          </div>
        `;
      }
    }

    // Shift Modal
    function openShiftModal() {
      document.getElementById('shiftModal').classList.remove('hidden');
      document.getElementById('shiftModal').classList.add('flex');
    }

    function closeShiftModal() {
      document.getElementById('shiftModal').classList.add('hidden');
      document.getElementById('shiftModal').classList.remove('flex');
      document.getElementById('shiftForm').reset();
      document.getElementById('shiftMsg').textContent = '';
      document.getElementById('shiftType').value = 'custom';
    }

    function openVacationModal() {
      const modal = document.getElementById('vacationModal');
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }

    function closeVacationModal() {
      const modal = document.getElementById('vacationModal');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      document.getElementById('vacationForm').reset();
      document.getElementById('vacationMsg').textContent = '';
    }

    function openSickModal() {
      const modal = document.getElementById('sickModal');
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }

    function closeSickModal() {
      const modal = document.getElementById('sickModal');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      document.getElementById('sickForm').reset();
      document.getElementById('sickMsg').textContent = '';
    }

    function toggleTransactions() {
      const section = document.getElementById('transactionsSection');
      if (section.classList.contains('hidden')) {
        section.classList.remove('hidden');
        loadTransactions();
      } else {
        section.classList.add('hidden');
      }
    }

    // Schicht löschen
    async function deleteShift(id) {
      if (!confirm('Schicht wirklich löschen?')) return;
      try {
        const res = await fetch('api/delete_shift.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ id })
        });
        const data = await res.json();
        if (data.status === 'ok') {
          alert('Schicht gelöscht ✅');
          loadShifts();
        } else {
          alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
        }
      } catch (e) {
        alert('Fehler beim Löschen');
      }
    }

    // Shift Form Submit
    document.getElementById('shiftForm')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const formData = new FormData(e.target);
      formData.append('member_name', user);

      const msg = document.getElementById('shiftMsg');
      try {
        const res = await fetch('api/set_shift.php', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();

        if (data.status === 'ok') {
          msg.textContent = 'Schicht gespeichert ✅';
          msg.className = 'text-sm text-center text-emerald-400';
          e.target.reset();
          document.getElementById('shiftType').value = 'custom';
          setTimeout(() => {
            closeShiftModal();
            loadShifts();
          }, 1000);
        } else {
          msg.textContent = data.error || 'Fehler beim Speichern';
          msg.className = 'text-sm text-center text-red-400';
        }
      } catch (e) {
        msg.textContent = 'Fehler beim Speichern';
        msg.className = 'text-sm text-center text-red-400';
      }
    });

    document.getElementById('shiftType')?.addEventListener('change', (event) => {
      const type = event.target.value;
      const defaults = shiftDefaults[type];
      const startInput = document.querySelector('input[name="shift_start"]');
      const endInput = document.querySelector('input[name="shift_end"]');
      if (startInput && endInput) {
        if (defaults) {
          startInput.value = defaults[0];
          endInput.value = defaults[1];
        } else {
          startInput.value = '';
          endInput.value = '';
        }
      }
    });

    document.getElementById('vacationForm')?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const form = event.target;
      const formData = new FormData(form);
      const payload = {
        start: formData.get('start'),
        end: formData.get('end'),
        csrf_token: csrfToken
      };
      const msg = document.getElementById('vacationMsg');

      if (!payload.start || !payload.end || payload.end < payload.start) {
        msg.textContent = 'Bitte gültigen Zeitraum wählen';
        msg.className = 'text-sm text-center text-red-400';
        return;
      }

      try {
        const res = await fetch('api/v2/save_vacation.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.status === 'success') {
          msg.textContent = 'Urlaub gespeichert ✅';
          msg.className = 'text-sm text-center text-emerald-400';
          form.reset();
          setTimeout(() => {
            closeVacationModal();
            loadAbsences();
          }, 900);
        } else {
          msg.textContent = data.error || 'Fehler beim Speichern';
          msg.className = 'text-sm text-center text-red-400';
        }
      } catch (error) {
        msg.textContent = 'Fehler beim Speichern';
        msg.className = 'text-sm text-center text-red-400';
      }
    });

    document.getElementById('sickForm')?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const form = event.target;
      const formData = new FormData(form);
      const payload = {
        start: formData.get('start'),
        end: formData.get('end'),
        csrf_token: csrfToken
      };
      const msg = document.getElementById('sickMsg');

      if (!payload.start || !payload.end || payload.end < payload.start) {
        msg.textContent = 'Bitte gültigen Zeitraum wählen';
        msg.className = 'text-sm text-center text-red-400';
        return;
      }

      try {
        const res = await fetch('api/v2/save_sickday.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.status === 'success') {
          msg.textContent = 'Krankmeldung gespeichert ✅';
          msg.className = 'text-sm text-center text-emerald-400';
          form.reset();
          setTimeout(() => {
            closeSickModal();
            loadAbsences();
          }, 900);
        } else {
          msg.textContent = data.error || 'Fehler beim Speichern';
          msg.className = 'text-sm text-center text-red-400';
        }
      } catch (error) {
        msg.textContent = 'Fehler beim Speichern';
        msg.className = 'text-sm text-center text-red-400';
      }
    });

    // Event Listeners
    document.getElementById('refreshBalance')?.addEventListener('click', loadBalance);
    document.getElementById('refreshShifts')?.addEventListener('click', loadShifts);

    // Initial Load
    loadBalance();
    loadShifts();
    loadAbsences();

    // GSAP Animations - nur einmal beim Laden
    gsap.utils.toArray('.slide-in').forEach((el, i) => {
      gsap.from(el, {
        duration: 0.8,
        y: 50,
        opacity: 0,
        delay: i * 0.1,
        ease: 'power3.out',
        onComplete: () => {
          el.classList.add('animated');
        }
      });
    });
  </script>
</body>
</html>