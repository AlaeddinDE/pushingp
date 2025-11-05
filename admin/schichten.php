<?php
require_once '../includes/auth.php';
if (!$isAdmin) {
  header('Location: ../member.php');
  exit;
}

$adminActive = 'shifts';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Pushing P — Schichtübersicht</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
  <link rel="stylesheet" href="theme.css" />

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet" />

  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { display: ['Inter','ui-sans-serif','system-ui','sans-serif'] },
          colors: { brand: { 500:'#f87171', 600:'#f43f5e', 700:'#be123c' } }
        }
      }
    }
  </script>
</head>
<body class="font-display text-white min-h-screen" data-admin-page="schichten">
  <canvas id="particles" aria-hidden="true" class="fixed inset-0 -z-10 pointer-events-none"></canvas>

  <?php include '../includes/admin_header.php'; ?>

  <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
    <header class="glass-strong rounded-3xl p-6 lg:p-10 shadow-glass">
      <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6">
        <div>
          <p class="text-sm uppercase tracking-[0.35em] text-white/60 mb-3">Schichtplanung</p>
          <h1 class="text-4xl sm:text-5xl font-black leading-tight grad-text">Heutige Einsätze</h1>
          <p class="mt-3 text-white/70 max-w-2xl">
            Überblick über alle geplanten Schichten von heute inklusive kurzer Verwaltung.
          </p>
        </div>
        <button id="reloadShifts" type="button" class="self-start px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20">🔄 Aktualisieren</button>
      </div>
    </header>

    <section class="glass-strong rounded-3xl p-6 lg:p-8 shadow-glass">
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
  </main>

  <script src="/assets/js/admin.js" defer></script>
</body>
</html>
