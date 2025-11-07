<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

$is_admin = is_admin();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events – PUSHING P</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .events-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-top: 24px;
        }
        
        @media (max-width: 968px) {
            .events-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
        }
        
        .event-card {
            padding: 16px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 12px;
            transition: all 0.3s ease;
        }
        
        .event-card:hover {
            background: var(--bg-tertiary);
            border-color: var(--border-hover);
            transform: translateY(-2px);
        }
        
        .event-title {
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .event-meta {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }
        
        .event-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }
        
        .btn-join {
            padding: 8px 16px;
            background: var(--success);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-join:hover {
            background: #2d8a4d;
            transform: scale(1.05);
        }
        
        .btn-leave {
            padding: 8px 16px;
            background: var(--error);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-leave:hover {
            background: #c23538;
            transform: scale(1.05);
        }
        
        .participants {
            font-size: 0.813rem;
            color: var(--text-tertiary);
            margin-top: 8px;
        }
        
        .day-box {
            min-height: 80px;
            padding: 8px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 8px;
            position: relative;
        }
        
        .day-number {
            position: absolute;
            top: 6px;
            right: 8px;
            font-size: 0.75rem;
            color: var(--text-tertiary);
        }
        
        .event-pill {
            margin-top: 20px;
            background: var(--accent);
            color: white;
            border-radius: 6px;
            padding: 4px 6px;
            font-size: 0.75rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="grain"></div>
    
    <div class="header">
        <div class="header-content">
            <div class="logo">PUSHING P</div>
            <nav class="nav">
                <a href="dashboard.php" class="nav-item">Dashboard</a>
                <a href="kasse.php" class="nav-item">Kasse</a>
                <a href="events.php" class="nav-item">Events</a>
                <?php if ($is_admin): ?>
                    <a href="admin_kasse.php" class="nav-item">Admin</a>
                <?php endif; ?>
                <a href="settings.php" class="nav-item">Settings</a>
                <a href="logout.php" class="nav-item">Logout</a>
            </nav>
        </div>
    </div>

    <div class="container">
        <div class="welcome">
            <h1>🎉 Events</h1>
            <p class="text-secondary">Gruppenevents und Zusammenkünfte</p>
        </div>

        <?php if ($is_admin): ?>
        <div class="section">
            <div class="section-header">
                <span>➕</span>
                <h2 class="section-title">Neues Event erstellen</h2>
            </div>
            
            <form id="f-new" style="display: grid; gap: 16px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>Event-Titel</label>
                        <input type="text" name="title" placeholder="z.B. Crew-Meeting" required>
                    </div>
                    <div class="form-group">
                        <label>Datum</label>
                        <input type="date" name="datum" required>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>Startzeit</label>
                        <input type="time" name="start_time">
                    </div>
                    <div class="form-group">
                        <label>Endzeit</label>
                        <input type="time" name="end_time">
                    </div>
                    <div class="form-group">
                        <label>Ort</label>
                        <input type="text" name="location" placeholder="z.B. HQ">
                    </div>
                </div>
                
                <button type="submit" class="btn">Event erstellen</button>
                <div id="okNew" style="color: var(--success); font-size: 0.875rem;"></div>
            </form>
        </div>
        <?php endif; ?>

        <div class="events-grid">
            <div>
                <div class="section">
                    <div class="section-header">
                        <span>📅</span>
                        <h2 class="section-title">Kommende Events</h2>
                    </div>
                    <div id="list"></div>
                </div>
            </div>
            
            <div>
                <div class="section">
                    <div class="section-header">
                        <span>📆</span>
                        <h2 class="section-title">Kalender</h2>
                    </div>
                    <div id="calendar" class="calendar"></div>
                </div>
            </div>
        </div>
    </div>

<script>
const cal = document.getElementById('calendar');
const list = document.getElementById('list');

// --- 1️⃣ Events laden ---
async function loadEvents() {
  try {
    const r = await fetch('/api/events_list.php');
    const ev = await r.json();
    renderList(ev);
    renderCalendar(ev);
  } catch (e) {
    console.error('Fehler beim Laden der Events:', e);
  }
}

// --- 2️⃣ Eventliste (rechts/oben) ---
function renderList(ev) {
  list.innerHTML = '';
  ev.slice(0, 10).forEach(e => {
    const d = document.createElement('div');
    d.className = 'event-card';
    d.innerHTML = `
      <div class="event-title">${e.title}</div>
      <div class="event-meta">
        📅 ${e.datum} ${e.start_time || ''} ${e.end_time ? '– ' + e.end_time : ''}<br>
        ${e.location ? '📍 ' + e.location : ''}
      </div>
      <div id="p-${e.id}" class="participants">Lädt Teilnehmer...</div>
      <div class="event-actions">
        <button onclick="join(${e.id})" class="btn-join">✓ Ich komme</button>
        <button onclick="leave(${e.id})" class="btn-leave">✗ Absagen</button>
      </div>
    `;
    list.appendChild(d);
    loadParticipants(e.id);
  });
}

// --- 3️⃣ Monatskalender ---
function renderCalendar(ev) {
  cal.innerHTML = '';
  const today = new Date();
  const y = today.getFullYear();
  const m = today.getMonth();
  const start = new Date(y, m, 1);
  const startDay = start.getDay() === 0 ? 7 : start.getDay();
  const days = new Date(y, m + 1, 0).getDate();

  for (let i = 1; i < startDay; i++) cal.appendChild(document.createElement('div'));

  for (let d = 1; d <= days; d++) {
    const box = document.createElement('div');
    box.className = 'day-box';
    box.innerHTML = `<div class="day-number">${d}</div>`;
    const iso = new Date(y, m, d).toISOString().slice(0, 10);
    ev.filter(e => e.datum === iso).forEach(e => {
      const pill = document.createElement('div');
      pill.className = 'event-pill';
      pill.textContent = e.title;
      box.appendChild(pill);
    });
    cal.appendChild(box);
  }
}

// --- 4️⃣ Teilnehmerliste dynamisch laden ---
async function loadParticipants(eventId) {
  try {
    const r = await fetch(`/api/event_participants.php?event_id=${eventId}`);
    const data = await r.json();
    const names = data.filter(p => p.status === 'coming').map(p => p.name);
    const div = document.getElementById(`p-${eventId}`);
    div.textContent = names.length ? `👥 Dabei: ${names.join(', ')}` : '👤 Noch keine Zusagen';
  } catch {
    document.getElementById(`p-${eventId}`).textContent = 'Teilnehmer konnten nicht geladen werden';
  }
}

// --- 5️⃣ Teilnahmeaktionen ---
async function join(id) {
  const fd = new FormData();
  fd.set('event_id', id);
  await fetch('/api/event_join.php', { method: 'POST', body: fd });
  loadEvents();
}

async function leave(id) {
  const fd = new FormData();
  fd.set('event_id', id);
  await fetch('/api/event_leave.php', { method: 'POST', body: fd });
  loadEvents();
}

// --- 6️⃣ Nur Admin: neues Event ---
<?php if ($is_admin): ?>
document.getElementById('f-new').addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  const resp = await fetch('/api/events_create.php', { method: 'POST', body: fd });
  const res = await resp.json();
  document.getElementById('okNew').textContent = res.ok ? '✔ Event erstellt!' : '✖ Fehler';
  if (res.ok) {
    e.target.reset();
    loadEvents();
  }
});
<?php endif; ?>

// --- 7️⃣ Initialer Start ---
loadEvents();
</script>
</body>
</html>
