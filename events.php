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
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding: 12px;
            background: var(--bg-tertiary);
            border-radius: 8px;
        }
        
        .calendar-nav-btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .calendar-nav-btn:hover {
            background: var(--accent-hover);
            transform: scale(1.05);
            box-shadow: 0 4px 12px var(--accent-glow);
        }
        
        .calendar-month {
            font-weight: 700;
            font-size: 1.125rem;
        }
        
        .weekday-header {
            text-align: center;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            padding: 8px;
            text-transform: uppercase;
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
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .day-box:hover {
            background: var(--bg-tertiary);
            border-color: var(--accent);
            transform: translateY(-4px);
            box-shadow: 0 8px 20px var(--accent-glow);
        }
        
        .day-box.today {
            border: 2px solid var(--accent);
            background: var(--bg-tertiary);
            box-shadow: 0 0 20px var(--accent-glow);
        }
        
        .day-box.selected {
            background: var(--accent);
            border-color: var(--accent);
            transform: scale(1.05);
        }
        
        .day-box.selected .day-number {
            color: white;
            font-weight: 800;
        }
        
        .day-box.has-event {
            border-color: var(--accent);
        }
        
        .day-number {
            position: absolute;
            top: 6px;
            right: 8px;
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 600;
        }
        
        .event-pill {
            margin-top: 20px;
            background: var(--accent);
            color: white;
            border-radius: 6px;
            padding: 4px 6px;
            font-size: 0.7rem;
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            animation: scaleIn 0.3s ease;
        }
        
        .date-picker-info {
            margin-top: 16px;
            padding: 12px;
            background: var(--bg-tertiary);
            border: 1px solid var(--accent);
            border-radius: 8px;
            text-align: center;
            font-size: 0.875rem;
            animation: fadeIn 0.3s ease;
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
                <a href="schichten.php" class="nav-item">Schichten</a>
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
            <p class="text-secondary">Gruppenevents planen und verwalten</p>
        </div>

        <div class="events-grid">
            <div>
                <div class="section">
                    <div class="section-header">
                        <span>📆</span>
                        <h2 class="section-title">Event-Kalender</h2>
                    </div>
                    <div class="calendar-header">
                        <button class="calendar-nav-btn" onclick="changeMonth(-1)">◀ Zurück</button>
                        <div class="calendar-month" id="monthLabel"></div>
                        <button class="calendar-nav-btn" onclick="changeMonth(1)">Weiter ▶</button>
                    </div>
                    <div id="calendar" class="calendar"></div>
                    <div id="selectedInfo"></div>
                </div>
            </div>
            
            <div>
                <div class="section">
                    <div class="section-header">
                        <span>📋</span>
                        <h2 class="section-title">Kommende Events</h2>
                    </div>
                    <div id="upcomingList" class="upcoming-events-list"></div>
                </div>
            </div>
        </div>
    </div>
                </div>
            </div>
        </div>
    </div>

<script>
const cal = document.getElementById('calendar');
const upcomingList = document.getElementById('upcomingList');
const monthLabel = document.getElementById('monthLabel');
const selectedInfo = document.getElementById('selectedInfo');

let currentYear = new Date().getFullYear();
let currentMonth = new Date().getMonth();
let selectedDate = null;
let allEvents = [];

const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
const today = new Date();
const maxDate = new Date(today.getFullYear(), today.getMonth() + 1, today.getDate());

const monthNames = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 
                    'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
const weekdays = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

async function loadEvents() {
  try {
    const r = await fetch('/api/events_list.php');
    allEvents = await r.json();
    renderUpcomingEvents(allEvents);
    renderCalendar();
  } catch (e) {
    console.error('Fehler:', e);
  }
}

// Monat wechseln
function changeMonth(delta) {
    currentMonth += delta;
    if (currentMonth > 11) { currentMonth = 0; currentYear++; }
    else if (currentMonth < 0) { currentMonth = 11; currentYear--; }
    renderCalendar();
}

// --- 2️⃣ Kommende Events anzeigen ---
function renderUpcomingEvents(events) {
  upcomingList.innerHTML = '';
  const upcoming = events.filter(e => new Date(e.datum) >= new Date().setHours(0,0,0,0))
                         .sort((a, b) => new Date(a.datum) - new Date(b.datum));
  
  if (upcoming.length === 0) {
    upcomingList.innerHTML = '<div class="empty-state">Keine kommenden Events</div>';
    return;
  }
  
  upcoming.forEach(e => {
    const card = document.createElement('div');
    card.className = 'event-card';
    card.innerHTML = `
      <div class="event-title">${e.title}</div>
      <div class="event-meta">
        📅 ${new Date(e.datum).toLocaleDateString('de-DE', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' })}
        ${e.start_time ? '⏰ ' + e.start_time : ''} ${e.end_time ? '– ' + e.end_time : ''}<br>
        ${e.location ? '📍 ' + e.location : ''}
      </div>
      <div id="p-${e.id}" class="participants"></div>
      <div class="event-actions">
        <button onclick="join(${e.id})" class="btn-join">✓ Ich komme</button>
        <button onclick="leave(${e.id})" class="btn-leave">✗ Absagen</button>
        ${isAdmin ? '<button onclick="deleteEvent(' + e.id + ')" class="btn-leave" style="background: var(--error);">🗑️</button>' : ''}
      </div>
    `;
    upcomingList.appendChild(card);
    loadParticipants(e.id);
  });
}

// --- 3️⃣ Interaktiver Kalender ---
function renderCalendar() {
  cal.innerHTML = '';
  monthLabel.textContent = `${monthNames[currentMonth]} ${currentYear}`;
  
  // Wochentage-Header
  weekdays.forEach(day => {
    const header = document.createElement('div');
    header.className = 'weekday-header';
    header.textContent = day;
    cal.appendChild(header);
  });
  
  const start = new Date(currentYear, currentMonth, 1);
  const startDay = start.getDay() === 0 ? 6 : start.getDay() - 1;
  const days = new Date(currentYear, currentMonth + 1, 0).getDate();
  
  const todayStr = today.toISOString().slice(0, 10);

  for (let i = 0; i < startDay; i++) {
    cal.appendChild(document.createElement('div'));
  }

  for (let d = 1; d <= days; d++) {
    const box = document.createElement('div');
    box.className = 'day-box';
    
    const iso = new Date(currentYear, currentMonth, d).toISOString().slice(0, 10);
    const dateObj = new Date(currentYear, currentMonth, d);
    const dayEvents = allEvents.filter(e => e.datum === iso);
    
    if (iso === todayStr) box.classList.add('today');
    if (selectedDate === iso) box.classList.add('selected');
    if (dayEvents.length > 0) box.classList.add('has-event');
    
    box.innerHTML = `<div class="day-number">${d}</div>`;
    
    dayEvents.forEach(e => {
      const pill = document.createElement('div');
      pill.className = 'event-pill';
      pill.textContent = e.title;
      box.appendChild(pill);
    });
    
    box.addEventListener('click', () => selectDate(iso, dayEvents, dateObj));
    
    cal.appendChild(box);
  }
}

// Datum auswählen
function selectDate(dateStr, events, dateObj) {
    selectedDate = dateStr;
    renderCalendar();
    
    const canCreate = isAdmin && dateObj >= today && dateObj <= maxDate;
    
    selectedInfo.innerHTML = `
        <div class="date-picker-info">
            <strong>📅 ${new Date(dateStr).toLocaleDateString('de-DE', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</strong><br>
            ${events.length > 0 ? '<span style="color: var(--accent);">✓ ' + events.length + ' Event(s)</span>' : '<span style="color: var(--text-secondary);">Keine Events</span>'}
        </div>
    `;
    
    if (canCreate) {
        selectedInfo.innerHTML += `
            <div class="event-form-inline">
                <h3 style="margin-bottom: 12px; font-size: 1rem;">➕ Event erstellen</h3>
                <form id="quickEventForm" onsubmit="createQuickEvent(event, '${dateStr}')">
                    <input type="text" name="title" placeholder="Event-Titel" required>
                    <input type="time" name="start_time" placeholder="Startzeit">
                    <input type="time" name="end_time" placeholder="Endzeit">
                    <input type="text" name="location" placeholder="Ort">
                    <button type="submit" class="btn" style="width: 100%;">Event erstellen</button>
                    <div id="quickEventMsg" style="margin-top: 8px; font-size: 0.875rem;"></div>
                </form>
            </div>
        `;
    } else if (isAdmin && dateObj > maxDate) {
        selectedInfo.innerHTML += '<div class="date-picker-info" style="margin-top: 12px; border-color: var(--warning); color: var(--warning);">⚠️ Events nur 1 Monat im Voraus</div>';
    } else if (isAdmin && dateObj < today) {
        selectedInfo.innerHTML += '<div class="date-picker-info" style="margin-top: 12px; border-color: var(--text-tertiary);">Vergangenes Datum</div>';
    }
}

async function createQuickEvent(e, dateStr) {
    e.preventDefault();
    const form = e.target;
    const fd = new FormData(form);
    fd.set('datum', dateStr);
    
    const resp = await fetch('/api/events_create.php', { method: 'POST', body: fd });
    const res = await resp.json();
    
    const msg = document.getElementById('quickEventMsg');
    if (res.ok) {
        msg.textContent = '✔ Event erstellt!';
        msg.style.color = 'var(--success)';
        form.reset();
        loadEvents();
    } else {
        msg.textContent = '✖ Fehler';
        msg.style.color = 'var(--error)';
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
  } catch { }
}

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

async function deleteEvent(id) {
    if (!confirm('Event wirklich löschen?')) return;
    const fd = new FormData();
    fd.set('event_id', id);
    await fetch('/api/events_delete.php', { method: 'POST', body: fd });
    loadEvents();
}

loadEvents();
renderCalendar(); // Kalender sofort rendern, nicht erst nach Events laden
</script>
</body>
</html>
