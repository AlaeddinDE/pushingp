<?php
require_once '../includes/auth.php';
if(!$isAdmin){
  header('Location: ../member.php');
  exit;
}
require_once '../includes/db.php';
$adminActive = 'members';
$adminSubnav = [
  ['label' => 'Mitglieder', 'href' => '#memberRows'],
  ['label' => 'Aktionen', 'href' => '#memberActions'],
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Pushing P — Mitgliederverwaltung</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
  <link rel="stylesheet" href="theme.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { display: ['Inter','ui-sans-serif','system-ui','sans-serif'] },
          boxShadow: {
            glow: '0 0 40px rgba(244,63,94,.32), inset 0 0 30px rgba(248,113,113,.25)',
            glowStrong: '0 0 60px rgba(244,63,94,.45), inset 0 0 40px rgba(248,113,113,.35)'
          },
          colors: { brand: {600:'#f43f5e', 700:'#be123c'} }
        }
      }
    }
  </script>
  <style>
    :root{ color-scheme:dark }
    body{
      background: var(--admin-bg);
      -webkit-font-smoothing: antialiased; text-rendering: optimizeLegibility;
      background-attachment: fixed;
    }
    .glass{
      background:rgba(248,113,113,.1);
      border:1px solid rgba(248,113,113,.18);
      backdrop-filter: blur(18px);
      -webkit-backdrop-filter: blur(18px);
    }
    .glass-strong {
      background:rgba(15,23,42,.8);
      border:1px solid rgba(248,113,113,.22);
      backdrop-filter: blur(24px);
      -webkit-backdrop-filter: blur(24px);
    }
    .grad-text {
      background: linear-gradient(135deg, #f87171 0%, #f43f5e 35%, #be123c 70%, #fb7185 100%);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }
    .slide-in {
      opacity: 0;
      transform: translateY(30px);
    }
    .slide-in.animated {
      opacity: 1;
      transform: translateY(0);
    }
  </style>
</head>
<body class="font-display text-white min-h-screen">
  <?php $showSidebarToggle = false; include '../includes/admin_header.php'; ?>

  <main class="max-w-7xl mx-auto px-6 py-10">
    <h1 class="text-5xl font-black grad-text mb-8 slide-in">👥 Mitgliederverwaltung</h1>
    
    <div class="glass-strong rounded-2xl p-6 slide-in">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h2 class="text-2xl font-bold">Alle Mitglieder</h2>
          <p class="text-white/60 text-sm mt-1">Gesamt: <span id="memberCount">–</span> Mitglieder</p>
        </div>
        <button onclick="loadMembers()" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 transition-colors text-sm font-medium">
          🔄 Aktualisieren
        </button>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left">
          <thead class="text-white/60 text-sm border-b border-white/10">
            <tr>
              <th class="py-3 px-4 font-semibold">Name</th>
              <th class="py-3 px-4 font-semibold">Flag</th>
              <th class="py-3 px-4 font-semibold">PIN</th>
              <th class="py-3 px-4 font-semibold">Start</th>
              <th class="py-3 px-4 font-semibold w-64">Aktionen</th>
            </tr>
          </thead>
          <tbody id="memberRows" class="text-sm"></tbody>
        </table>
      </div>
    </div>
  </main>

  <script>
    async function loadMembers() {
      try {
        const res = await fetch('/api/get_members.php');
        const list = await res.json();
        const tbody = document.getElementById('memberRows');
        tbody.innerHTML = '';
        
        // Zeige Anzahl
        document.getElementById('memberCount').textContent = list.length;
        
        if (list.length === 0) {
          tbody.innerHTML = '<tr><td colspan="5" class="py-8 text-center text-white/50">Keine Mitglieder gefunden</td></tr>';
          return;
        }
        
        list.forEach((m, i) => {
          const tr = document.createElement('tr');
          tr.className = 'border-b border-white/5 hover:bg-white/5 transition-colors';
          tr.innerHTML = `
            <td class="py-4 px-4 font-medium">${m.name || 'N/A'}</td>
            <td class="py-4 px-4 text-lg">${m.flag || '🌍'}</td>
            <td class="py-4 px-4">
              <span class="font-mono text-rose-200">${m.pin || '<span class="text-white/40">Keine PIN</span>'}</span>
            </td>
            <td class="py-4 px-4 text-white/60">
              ${m.start_date || 'N/A'}
            </td>
            <td class="py-4 px-4">
              <div class="flex gap-2 flex-wrap">
                <button onclick="changePin('${m.name}', '${m.pin || ''}')" class="px-3 py-1.5 rounded-lg bg-rose-600/20 hover:bg-rose-600/30 text-rose-200 text-xs font-medium transition-colors">
                  ${m.pin ? '✏️ PIN ändern' : '➕ PIN setzen'}
                </button>
                <button onclick="viewPin('${m.name}', '${m.pin || ''}')" class="px-3 py-1.5 rounded-lg bg-amber-500/20 hover:bg-amber-500/30 text-amber-200 text-xs font-medium transition-colors">
                  👁️ PIN anzeigen
                </button>
                <button onclick="banUser('${m.name}')" class="px-3 py-1.5 rounded-lg bg-red-600/20 hover:bg-red-600/30 text-red-200 text-xs font-medium transition-colors">
                  🚫 Sperren
                </button>
              </div>
            </td>
          `;
          tbody.appendChild(tr);
        });
      } catch (e) {
        console.error('Fehler:', e);
        alert('Fehler beim Laden der Mitglieder');
      }
    }

    async function banUser(name) {
      if (!confirm(`${name} wirklich sperren? Diese Aktion kann nicht rückgängig gemacht werden.`)) return;
      try {
        const res = await fetch('/api/admin_ban_user.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ name })
        });
        const data = await res.json();
        if (data.status === 'ok') {
          alert('Benutzer gesperrt ✅');
          loadMembers();
        } else {
          alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
        }
      } catch (e) {
        alert('Fehler beim Sperren');
      }
    }

    function viewPin(name, currentPin) {
      if (!currentPin) {
        alert(`${name} hat noch keine PIN gesetzt.`);
        return;
      }
      alert(`PIN für ${name}:\n\n${currentPin}`);
    }

    async function changePin(name, currentPin) {
      const promptText = currentPin 
        ? `Aktuelle PIN: ${currentPin}\n\nNeue PIN für ${name} (4–6 Ziffern):`
        : `Neue PIN für ${name} setzen (4–6 Ziffern):`;
      
      const pin = prompt(promptText);
      if (!pin) return;
      
      if (pin.length < 4 || pin.length > 6 || !/^\d+$/.test(pin)) {
        alert('PIN muss 4–6 Ziffern lang sein (nur Zahlen)');
        return;
      }
      
      if (!confirm(`PIN für ${name} wirklich ${currentPin ? 'ändern' : 'setzen'}?\n\nNeue PIN: ${pin}`)) {
        return;
      }
      
      try {
        const res = await fetch('/api/admin_change_pin.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ name, pin })
        });
        const data = await res.json();
        if (data.status === 'ok') {
          alert(`PIN ${currentPin ? 'geändert' : 'gesetzt'} ✅\n\nName: ${name}\nPIN: ${pin}`);
          loadMembers();
        } else {
          alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
        }
      } catch (e) {
        alert('Fehler beim Ändern der PIN: ' + e.message);
      }
    }

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

    loadMembers();
  </script>
</body>
</html>
