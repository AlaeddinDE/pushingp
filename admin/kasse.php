<?php
require_once '../includes/auth.php';
if (!$isAdmin) {
  header('Location: ../member.php');
  exit;
}

$adminActive = 'kasse';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Pushing P — Kassenverwaltung</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
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
            brand: { 500:'#f87171', 600:'#f43f5e', 700:'#be123c' }
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
<body class="font-display text-white min-h-screen" data-admin-page="kasse">
  <canvas id="particles" aria-hidden="true" class="fixed inset-0 -z-10 pointer-events-none"></canvas>

  <?php include '../includes/admin_header.php'; ?>

  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-10">
    <header class="glass-strong rounded-3xl p-6 lg:p-10 shadow-glass">
      <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6">
        <div>
          <p class="text-sm uppercase tracking-[0.35em] text-white/60 mb-3">Kassenverwaltung</p>
          <h1 class="text-4xl sm:text-5xl font-black leading-tight grad-text">Finanzen & Transaktionen</h1>
          <p class="mt-3 text-white/70 max-w-2xl">
            Verwalte Ein- und Auszahlungen, Gruppentransaktionen sowie Schäden und Gutschriften der Crew.
          </p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="glass rounded-2xl p-4">
            <p class="text-xs uppercase tracking-[0.35em] text-white/40 mb-2">Kassenstand</p>
            <p id="cashPageBalance" class="text-3xl font-black">–</p>
          </div>
          <div class="glass rounded-2xl p-4">
            <p class="text-xs uppercase tracking-[0.35em] text-white/40 mb-2">Schnellaktionen</p>
              <div class="flex flex-wrap gap-2 text-sm">
                <button id="btnOpenTxModal" type="button" class="px-4 py-2 rounded-xl bg-gradient-to-r from-brand-600 to-purple-600 hover:opacity-90 transition shadow-lg">+ Neue Transaktion</button>
                <button id="quickRefresh" type="button" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 transition">🔄 Refresh</button>
              </div>
          </div>
        </div>
      </div>
    </header>

    <section id="transactionsSection" class="glass-strong rounded-3xl p-6 lg:p-8 space-y-6 shadow-glass">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold">💰 Transaktionen</h2>
          <p class="text-sm text-white/60 mt-1">Filtere nach Mitgliedern, Typen oder Stichworten</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <div class="relative">
            <input type="text" id="searchTransactions" placeholder="Suchen..." class="pl-9 pr-4 py-2 rounded-xl bg-white/10 text-white outline-none focus:ring-2 focus:ring-brand-600 w-52" />
            <svg class="w-4 h-4 text-white/40 absolute left-3 top-3" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <select id="transactionFilter" class="px-4 py-2 rounded-xl bg-white/10 text-white outline-none focus:ring-2 focus:ring-brand-600">
            <option value="">Alle Mitglieder</option>
          </select>
          <button id="refreshTransactions" type="button" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 transition">🔄 Aktualisieren</button>
        </div>
      </div>

      <div class="flex flex-wrap gap-2">
        <button data-q="all" class="txchip px-3 py-1.5 rounded-lg bg-white/30 text-white text-sm">Alle</button>
        <button data-q="einzahlung" class="txchip px-3 py-1.5 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-400 text-sm">Einzahlungen</button>
        <button data-q="auszahlung" class="txchip px-3 py-1.5 rounded-lg bg-red-500/20 hover:bg-red-500/30 text-red-400 text-sm">Auszahlungen</button>
        <button data-q="schaden" class="txchip px-3 py-1.5 rounded-lg bg-orange-500/20 hover:bg-orange-500/30 text-orange-300 text-sm">Schäden</button>
        <button data-q="gruppenaktion" class="txchip px-3 py-1.5 rounded-lg bg-purple-500/20 hover:bg-purple-500/30 text-purple-300 text-sm">Gruppenaktionen</button>
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
      </div>

      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 text-sm">
        <div class="text-white/60">Zeige <span id="pageInfo">0–0</span> von <span id="totalTransactions">0</span></div>
        <div class="flex gap-2">
          <button id="btnPrev" type="button" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 disabled:opacity-50">← Zurück</button>
          <button id="btnNext" type="button" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 disabled:opacity-50">Weiter →</button>
        </div>
      </div>
    </section>

    <section class="grid lg:grid-cols-2 gap-6 items-start">
      <article class="glass-strong rounded-3xl p-6 space-y-4">
        <h2 class="text-xl font-bold">Transaktion hinzufügen</h2>
        <p class="text-sm text-white/60">Schnellbuchungen ohne Modal – perfekt für häufige Vorgänge.</p>
        <form id="transactionFormInline" class="grid gap-4">
          <label class="block text-sm text-white/70">
            <span class="block mb-2">Mitglied</span>
            <select name="name" id="transactionMember" class="w-full rounded-xl px-4 py-3 text-black outline-none focus:ring-2 focus:ring-brand-600" required>
              <option value="">Bitte wählen...</option>
            </select>
          </label>
          <label class="block text-sm text-white/70">
            <span class="block mb-2">Typ</span>
            <select id="transactionTypeInline" name="type" class="w-full rounded-xl px-4 py-3 text-black outline-none focus:ring-2 focus:ring-brand-600" required>
              <option value="Einzahlung">Einzahlung</option>
              <option value="Auszahlung">Auszahlung</option>
              <option value="Gutschrift">Gutschrift</option>
              <option value="Schaden">Schaden</option>
              <option value="Gruppenaktion">Gruppenaktion</option>
            </select>
          </label>
          <label class="block text-sm text-white/70">
            <span class="block mb-2">Betrag (€)</span>
            <input type="number" name="amount" step="0.01" min="0.01" placeholder="0.00" class="w-full rounded-xl px-4 py-3 text-black outline-none focus:ring-2 focus:ring-brand-600" required />
          </label>
          <label class="block text-sm text-white/70">
            <span class="block mb-2">Notiz</span>
            <input type="text" name="note" placeholder="Optional" class="w-full rounded-xl px-4 py-3 text-black outline-none focus:ring-2 focus:ring-brand-600" />
          </label>
          <button class="rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-3 font-semibold shadow-lg hover:shadow-glow transition-all" type="submit">
            Transaktion speichern 💰
          </button>
          <p id="transactionMsg" class="text-sm text-center"></p>
        </form>
      </article>
    </section>
  </main>

  <div id="transactionModal" class="fixed inset-0 bg-black/75 z-50 hidden">
    <div class="min-h-screen px-4 text-center flex items-center justify-center">
      <div class="inline-block w-full max-w-xl glass-strong rounded-2xl p-6 text-left shadow-xl relative">
        <button type="button" class="js-close-transaction-modal absolute right-4 top-4 p-2 rounded-xl hover:bg-white/10">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
        <h3 class="text-xl font-bold mb-4">Neue Transaktion</h3>
        <form id="transactionFormModal" class="space-y-4">
          <label class="block text-sm text-white/70">
            <span class="block mb-2">Mitglied *</span>
            <select name="name" class="w-full rounded-xl px-4 py-3 bg-white/10 text-white outline-none focus:ring-2 focus:ring-brand-600" required>
              <option value="">Bitte wählen...</option>
            </select>
          </label>
          <label class="block text-sm text-white/70">
            <span class="block mb-2">Typ *</span>
            <select id="transactionTypeModal" name="type" class="w-full rounded-xl px-4 py-3 bg-white/10 text-white outline-none focus:ring-2 focus:ring-brand-600" required>
              <option value="Einzahlung">💰 Einzahlung</option>
              <option value="Auszahlung">💸 Auszahlung</option>
              <option value="Gutschrift">✨ Gutschrift</option>
              <option value="Schaden">⚠️ Schaden</option>
              <option value="Gruppenaktion">👥 Gruppenaktion</option>
            </select>
          </label>
          <label class="block text-sm text-white/70">
            <span class="block mb-2">Betrag (€) *</span>
            <input type="number" name="amount" step="0.01" min="0.01" placeholder="0.00" class="w-full rounded-xl px-4 py-3 bg-white/10 text-white outline-none focus:ring-2 focus:ring-brand-600" required />
          </label>
          <label class="block text-sm text-white/70">
            <span class="block mb-2">Notiz</span>
            <input type="text" name="note" maxlength="100" placeholder="Optional" class="w-full rounded-xl px-4 py-3 bg-white/10 text-white outline-none focus:ring-2 focus:ring-brand-600" />
          </label>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" class="js-close-transaction-modal px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20">Abbrechen</button>
            <button type="submit" class="px-4 py-2 rounded-xl bg-gradient-to-r from-brand-600 to-purple-600 font-medium">Speichern</button>
          </div>
          <p id="transactionModalMsg" class="text-sm text-center"></p>
        </form>
      </div>
    </div>
  </div>

  <script src="/assets/js/admin.js" defer></script>
</body>
</html>
