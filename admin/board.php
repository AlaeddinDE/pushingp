<?php
require_once '../includes/auth.php';
if (!$isAdmin) {
  header('Location: ../member.php');
  exit;
}

$adminActive = 'board';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Pushing P — Crew Board</title>

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
<body class="font-display text-white min-h-screen" data-admin-page="board">
  <canvas id="particles" aria-hidden="true" class="fixed inset-0 -z-10 pointer-events-none"></canvas>

  <?php include '../includes/admin_header.php'; ?>

  <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
    <header class="glass-strong rounded-3xl p-6 lg:p-10 shadow-glass">
      <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6">
        <div>
          <p class="text-sm uppercase tracking-[0.35em] text-white/60 mb-3">Crew Kommunikation</p>
          <h1 class="text-4xl sm:text-5xl font-black leading-tight grad-text">Board & Ankündigungen</h1>
          <p class="mt-3 text-white/70 max-w-2xl">
            Erstelle Events, Ankündigungen und wichtige Notizen für die gesamte Crew.
          </p>
        </div>
        <button id="reloadBoard" type="button" class="self-start px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20">🔄 Aktualisieren</button>
      </div>
    </header>

    <section class="grid lg:grid-cols-2 gap-6 items-start">
      <form id="boardForm" class="glass-strong rounded-3xl p-6 space-y-4">
        <h2 class="text-xl font-bold">Neuer Eintrag</h2>
        <div class="flex gap-3 flex-wrap text-sm">
          <label class="flex items-center gap-2">
            <input type="radio" name="type" value="event" class="accent-rose-500" checked />
            <span>Event</span>
          </label>
          <label class="flex items-center gap-2">
            <input type="radio" name="type" value="announcement" class="accent-rose-500" />
            <span>Ankündigung</span>
          </label>
        </div>
        <label class="block text-sm text-white/70">
          <span class="block mb-2">Titel *</span>
          <input name="title" required maxlength="150" placeholder="Titel" class="w-full rounded-xl px-4 py-3 text-black outline-none focus:ring-2 focus:ring-brand-600" />
        </label>
        <label class="block text-sm text-white/70">
          <span class="block mb-2">Datum &amp; Uhrzeit (optional)</span>
          <input type="datetime-local" name="scheduled_for" class="w-full rounded-xl px-4 py-3 text-black outline-none focus:ring-2 focus:ring-brand-600" />
        </label>
        <label class="block text-sm text-white/70">
          <span class="block mb-2">Beschreibung</span>
          <textarea name="content" rows="4" placeholder="Details, Agenda oder Hinweise" class="w-full rounded-xl px-4 py-3 text-black outline-none focus:ring-2 focus:ring-brand-600"></textarea>
        </label>
        <button class="w-full rounded-xl bg-gradient-to-r from-brand-600 to-brand-700 px-4 py-3 font-semibold shadow-lg hover:shadow-glow transition-all" type="submit">
          Eintrag speichern ✨
        </button>
        <p id="boardFormMsg" class="text-sm text-center"></p>
      </form>

      <div id="boardList" class="glass-strong rounded-3xl p-6 space-y-4 shadow-glass text-sm text-white/70">
        <div class="text-white/50">Noch keine Einträge vorhanden.</div>
      </div>
    </section>
  </main>

  <script src="/assets/js/admin.js" defer></script>
</body>
</html>
