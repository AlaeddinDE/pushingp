<?php
require_once '../includes/auth.php';
if (!$isAdmin) {
  header('Location: ../member.php');
  exit;
}

$adminActive = 'dashboard';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Pushing P — Admin Dashboard</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <link rel="stylesheet" href="theme.css" />

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet" />

  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { display: ['Inter','ui-sans-serif','system-ui','sans-serif'] },
          colors: {
            brand: { 500:'#f87171', 600:'#f43f5e', 700:'#be123c' },
            glass: { card: 'rgba(255,255,255,.08)', edge: 'rgba(255,255,255,.14)' }
          },
          boxShadow: {
            glow: '0 20px 60px rgba(244,63,94,.32)',
            glass: '0 10px 40px rgba(15,23,42,.45)'
          }
        }
      }
    }
  </script>
</head>
<body class="font-display text-white min-h-screen" data-admin-page="dashboard">
  <canvas id="particles" aria-hidden="true" class="fixed inset-0 -z-10 pointer-events-none"></canvas>

  <?php include '../includes/admin_header.php'; ?>

  <main id="dashboardRoot" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-10">
    <header class="rounded-3xl glass-strong p-6 lg:p-10 shadow-glass">
      <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6">
        <div>
          <p class="text-sm uppercase tracking-[0.35em] text-white/60 mb-3">Willkommen zurück</p>
          <h1 class="text-4xl sm:text-5xl font-black leading-tight grad-text">Crew Dashboard</h1>
          <p class="mt-3 text-white/70 max-w-2xl">
            Behalte Kennzahlen, Entwicklungen und Live-Aktivitäten der Crew im Blick.
          </p>
        </div>
        <div class="flex flex-wrap gap-3">
          <a href="/admin/kasse.php" class="px-5 py-3 rounded-xl bg-white/10 hover:bg-white/20 transition">💰 Zur Kasse</a>
          <a href="/admin/schichten.php" class="px-5 py-3 rounded-xl bg-white/10 hover:bg-white/20 transition">🕓 Schichten</a>
          <a href="/admin/board.php" class="px-5 py-3 rounded-xl bg-white/10 hover:bg-white/20 transition">📌 Crew Board</a>
        </div>
      </div>
    </header>

    <section id="kennzahlen" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6">
      <article class="stat-card glass-strong rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
          <span class="text-3xl">💰</span>
          <span class="h-8 w-8 rounded-lg bg-emerald-500/20 grid place-items-center">
            <span class="h-2.5 w-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
          </span>
        </div>
        <p class="text-4xl font-black number-counter mb-2" id="kassenstand">–</p>
        <p class="text-white/60 text-sm">Kassenstand</p>
      </article>

      <article class="stat-card glass-strong rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
          <span class="text-3xl">👥</span>
          <span class="h-8 w-8 rounded-lg bg-rose-500/20 grid place-items-center">
            <svg class="w-4 h-4 text-rose-300" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0" />
            </svg>
          </span>
        </div>
        <p class="text-4xl font-black number-counter mb-2" id="anzMembers">–</p>
        <p class="text-white/60 text-sm">Aktive Mitglieder</p>
      </article>

      <article class="stat-card glass-strong rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
          <span class="text-3xl">🟢</span>
          <span class="h-8 w-8 rounded-lg bg-emerald-500/20 grid place-items-center">
            <span class="h-2.5 w-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
          </span>
        </div>
        <p class="text-2xl font-black mb-2" id="dashboardStatus">LIVE</p>
        <p class="text-white/60 text-sm">Status</p>
      </article>

      <article class="stat-card glass-strong rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
          <span class="text-3xl">🕓</span>
          <span class="h-8 w-8 rounded-lg bg-purple-500/20 grid place-items-center">
            <svg class="w-4 h-4 text-purple-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </span>
        </div>
        <p class="text-4xl font-black number-counter mb-2" id="shiftsToday">–</p>
        <p class="text-white/60 text-sm">Schichten heute</p>
      </article>
    </section>

    <section class="grid lg:grid-cols-2 gap-6">
      <article class="glass-strong rounded-2xl p-6">
        <h2 class="text-xl font-bold mb-6">Kassenübersicht</h2>
        <div class="h-64">
          <canvas id="balanceChart" aria-label="Kassenverlauf" role="img"></canvas>
        </div>
      </article>
      <article class="glass-strong rounded-2xl p-6">
        <h2 class="text-xl font-bold mb-6">Mitgliederverteilung</h2>
        <div class="h-64">
          <canvas id="membersChart" aria-label="Mitglieder nach Status" role="img"></canvas>
        </div>
      </article>
    </section>

    <section class="glass-strong rounded-2xl p-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
        <div>
          <h2 class="text-xl font-bold">Aktivität</h2>
          <p class="text-white/60 text-sm">Letzte Transaktionen und Board-Einträge</p>
        </div>
        <a href="/admin/kasse.php" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-sm">Mehr anzeigen</a>
      </div>
      <div id="dashboardActivity" class="grid gap-4 text-sm text-white/70"></div>
    </section>
  </main>

  <script src="/assets/js/admin.js" defer></script>
</body>
</html>
