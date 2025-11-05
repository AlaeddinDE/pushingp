<?php require_once '../includes/auth.php'; if(!$isAdmin) die('Zugriff verweigert'); require_once '../includes/db.php'; ?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Pushing P — Mitgliederverwaltung</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { display: ['Inter','ui-sans-serif','system-ui','sans-serif'] },
          boxShadow: { 
            glow: '0 0 40px rgba(99,102,241,.3), inset 0 0 30px rgba(236,72,153,.2)',
            glowStrong: '0 0 60px rgba(99,102,241,.5), inset 0 0 40px rgba(236,72,153,.3)'
          },
          colors: { brand: {600:'#6366f1', 700:'#4f46e5'} }
        }
      }
    }
  </script>
  <style>
    :root{ color-scheme:dark }
    body{
      background: radial-gradient(1400px 900px at 10% 10%, rgba(99,102,241,.15), transparent 60%),
                  radial-gradient(1200px 700px at 90% 30%, rgba(236,72,153,.12), transparent 60%),
                  radial-gradient(800px 600px at 50% 80%, rgba(59,130,246,.08), transparent 50%),
                  #0a0b10;
      -webkit-font-smoothing: antialiased; text-rendering: optimizeLegibility;
      background-attachment: fixed;
    }
    .glass{ 
      background:rgba(255,255,255,.06); 
      border:1px solid rgba(255,255,255,.12); 
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
    }
    .glass-strong {
      background:rgba(255,255,255,.08);
      border:1px solid rgba(255,255,255,.15);
      backdrop-filter: blur(25px);
      -webkit-backdrop-filter: blur(25px);
    }
    .grad-text {
      background: linear-gradient(135deg, #60a5fa 0%, #a78bfa 30%, #f472b6 60%, #22d3ee 90%);
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
  <header class="sticky top-0 left-0 w-full z-50">
    <nav class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pt-4">
      <div class="flex items-center justify-between glass-strong rounded-2xl px-6 py-4 shadow-glow">
        <a href="/admin/" class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-blue-600 to-purple-600 grid place-items-center shadow-lg">
            <span class="text-xl font-black">P</span>
          </div>
          <span class="font-extrabold tracking-tight text-lg">Mitgliederverwaltung</span>
        </a>
        <div class="flex items-center gap-4">
          <a href="/admin/" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 transition-colors text-sm font-medium">Dashboard</a>
          <a href="/logout.php" class="px-4 py-2 rounded-xl bg-red-600/20 hover:bg-red-600/30 text-red-400 transition-colors text-sm font-medium">Logout</a>
        </div>
      </div>
    </nav>
  </header>

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
              <span class="font-mono text-blue-400">${m.pin || '<span class="text-white/40">Keine PIN</span>'}</span>
            </td>
            <td class="py-4 px-4 text-white/60">
              ${m.start_date || 'N/A'}
            </td>
            <td class="py-4 px-4">
              <div class="flex gap-2 flex-wrap">
                <button onclick="changePin('${m.name}', '${m.pin || ''}')" class="px-3 py-1.5 rounded-lg bg-blue-600/20 hover:bg-blue-600/30 text-blue-400 text-xs font-medium transition-colors">
                  ${m.pin ? '✏️ PIN ändern' : '➕ PIN setzen'}
                </button>
                <button onclick="viewPin('${m.name}', '${m.pin || ''}')" class="px-3 py-1.5 rounded-lg bg-purple-600/20 hover:bg-purple-600/30 text-purple-400 text-xs font-medium transition-colors">
                  👁️ PIN anzeigen
                </button>
                <button onclick="banUser('${m.name}')" class="px-3 py-1.5 rounded-lg bg-red-600/20 hover:bg-red-600/30 text-red-400 text-xs font-medium transition-colors">
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
