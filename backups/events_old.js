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
    d.style.cssText = 'padding:8px 10px;border:1px solid rgba(255,255,255,.08);border-radius:10px;margin-bottom:8px;';
    d.innerHTML = `
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <div style="font-weight:700;">${e.title}</div>
          <div style="opacity:.8;font-size:13px;">
            ${e.datum} ${e.start_time || ''} ${e.end_time ? '– ' + e.end_time : ''} ${e.location ? '· ' + e.location : ''}
          </div>
          <div id="p-${e.id}" style="font-size:12px;margin-top:4px;opacity:.8;">Lädt Teilnehmer...</div>
        </div>
        <div>
          <button onclick="join(${e.id})" style="padding:6px 10px;border:0;border-radius:8px;background:#2ccfa5;color:#021;">Ich komme</button>
          <button onclick="leave(${e.id})" style="padding:6px 10px;border:0;border-radius:8px;background:#ffb6b6;color:#210;margin-left:6px;">Absagen</button>
        </div>
      </div>`;
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
    box.style.cssText = 'min-height:70px;padding:8px;border:1px solid rgba(255,255,255,.08);border-radius:8px;position:relative;';
    box.innerHTML = `<div style="opacity:.65;font-size:12px;position:absolute;top:6px;right:8px;">${d}</div>`;
    const iso = new Date(y, m, d).toISOString().slice(0, 10);
    ev.filter(e => e.datum === iso).forEach(e => {
      const pill = document.createElement('div');
      pill.style.cssText = 'margin-top:18px;background:#1fb58a;color:#021;border-radius:6px;padding:4px 6px;font-size:12px;';
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
    div.textContent = names.length ? `Dabei: ${names.join(', ')}` : 'Noch keine Zusagen';
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
<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
document.getElementById('f-new').addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  const resp = await fetch('/api/events_create.php', { method: 'POST', body: fd });
  const res = await resp.json();
  document.getElementById('okNew').textContent = res.ok ? '✔ erstellt' : '✖ Fehler';
  loadEvents();
});
<?php endif; ?>

// --- 7️⃣ Initialer Start ---
loadEvents();
</script>
