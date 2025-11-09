<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

$is_admin = is_admin();
$current_user_id = get_current_user_id();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schichtplan – PUSHING P</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .shift-grid {
            display: grid;
            grid-template-columns: 120px repeat(auto-fill, minmax(35px, 1fr));
            gap: 4px;
            margin-top: 24px;
        }
        
        .shift-header {
            background: var(--bg-tertiary);
            padding: 8px 4px;
            border-radius: 6px;
            font-weight: 700;
            text-align: center;
            font-size: 0.7rem;
        }
        
        .shift-member-name {
            background: var(--bg-secondary);
            padding: 8px;
            border-radius: 8px;
            font-weight: 600;
            display: flex;
            align-items: center;
            border: 1px solid var(--border);
            font-size: 0.875rem;
        }
        
        .shift-cell {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 4px;
            min-height: 35px;
            width: 35px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            cursor: default;
        }
        
        .shift-cell:hover {
            transform: scale(1.2);
            z-index: 10;
            box-shadow: 0 4px 12px var(--accent-glow);
        }
        
        .shift-cell.has-shift {
            border-color: var(--accent);
        }
        
        .shift-cell.today {
            border: 2px solid var(--accent);
            box-shadow: 0 0 10px var(--accent-glow);
        }
        
        .shift-badge {
            display: block;
            width: 100%;
            height: 100%;
            border-radius: 4px;
            font-size: 0.65rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .shift-early {
            background: #ffd700;
            color: #000;
        }
        
        .shift-late {
            background: #ff8c00;
            color: #fff;
        }
        
        .shift-night {
            background: #4a148c;
            color: #fff;
        }
        
        .shift-day {
            background: #2196F3;
            color: #fff;
        }
        
        .week-nav-btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .week-nav-btn:hover {
            background: var(--accent-hover);
            transform: scale(1.05);
            box-shadow: 0 4px 12px var(--accent-glow);
        }
        
        .week-label {
            font-weight: 700;
            font-size: 1.125rem;
        }
        
        .legend {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 24px;
            padding: 16px;
            background: var(--bg-secondary);
            border-radius: 8px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.875rem;
        }
        
        .tooltip {
            position: absolute;
            background: var(--bg-primary);
            border: 1px solid var(--accent);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.75rem;
            pointer-events: none;
            z-index: 100;
            white-space: nowrap;
            display: none;
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
            <h1>📅 Meine Schichten bearbeiten</h1>
            <p class="text-secondary">Trage deine Arbeitszeiten ein</p>
        </div>

        <div style="margin-bottom: 24px;">
            <a href="schichten.php" class="btn btn-secondary">← Zurück zur Übersicht</a>
        </div>

        <?php if ($is_admin): ?>
        <div class="alert alert-success" style="margin-bottom: 24px;">
            ✅ <strong>Admin-Modus:</strong> Du kannst die Schichten aller Mitglieder bearbeiten
        </div>
        <?php endif; ?>

        <div class="week-nav">
            <button class="week-nav-btn" onclick="changeWeek(-1)">◀ Vorherige Woche</button>
            <div class="week-label" id="weekLabel"></div>
            <button class="week-nav-btn" onclick="changeWeek(1)">Nächste Woche ▶</button>
        </div>

        <div class="section">
            <div id="shiftGrid" class="shift-grid"></div>
        </div>

        <div class="legend">
            <div class="legend-item">
                <span class="shift-badge shift-early">Früh</span>
                <span>Frühschicht (z.B. 06:00-14:00)</span>
            </div>
            <div class="legend-item">
                <span class="shift-badge shift-day">Tag</span>
                <span>Tagschicht (z.B. 08:00-16:00)</span>
            </div>
            <div class="legend-item">
                <span class="shift-badge shift-late">Spät</span>
                <span>Spätschicht (z.B. 14:00-22:00)</span>
            </div>
            <div class="legend-item">
                <span class="shift-badge shift-night">Nacht</span>
                <span>Nachtschicht (z.B. 22:00-06:00)</span>
            </div>
        </div>
    </div>

    <!-- Modal für Schicht hinzufügen/bearbeiten -->
    <div id="shiftModal" class="shift-modal">
        <div class="shift-modal-content">
            <div class="shift-modal-header">Schicht bearbeiten</div>
            <form id="shiftForm">
                <input type="hidden" id="modalUserId" name="user_id">
                <input type="hidden" id="modalDate" name="date">
                
                <div class="form-group">
                    <label>Mitglied</label>
                    <input type="text" id="modalUserName" disabled style="opacity: 0.6;">
                </div>
                
                <div class="form-group">
                    <label>Datum</label>
                    <input type="text" id="modalDateDisplay" disabled style="opacity: 0.6;">
                </div>
                
                <div class="form-group">
                    <label>Schichttyp</label>
                    <select name="type" id="modalType" required>
                        <option value="">-- Keine Schicht --</option>
                        <option value="early">Frühschicht</option>
                        <option value="day">Tagschicht</option>
                        <option value="late">Spätschicht</option>
                        <option value="night">Nachtschicht</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Startzeit</label>
                    <input type="time" name="start_time" id="modalStartTime">
                </div>
                
                <div class="form-group">
                    <label>Endzeit</label>
                    <input type="time" name="end_time" id="modalEndTime">
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn" style="flex: 1;">Speichern</button>
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal()">Abbrechen</button>
                    <button type="button" class="btn-leave" onclick="deleteShift()" style="flex: 0;">🗑️</button>
                </div>
            </form>
        </div>
    </div>

<script>
let currentWeekStart = new Date();
currentWeekStart.setDate(currentWeekStart.getDate() - currentWeekStart.getDay() + 1); // Montag

let allUsers = [];
let allShifts = [];

const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
const currentUserId = <?php echo $current_user_id; ?>;

const weekdays = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
const shiftTypes = {
    'early': { label: 'Früh', class: 'shift-early' },
    'day': { label: 'Tag', class: 'shift-day' },
    'late': { label: 'Spät', class: 'shift-late' },
    'night': { label: 'Nacht', class: 'shift-night' }
};

async function loadData() {
    try {
        const [usersRes, shiftsRes] = await Promise.all([
            fetch('/api/users_list.php'),
            fetch('/api/shifts_list.php')
        ]);
        
        allUsers = await usersRes.json();
        allShifts = await shiftsRes.json();
        
        renderShiftGrid();
    } catch (e) {
        console.error('Fehler:', e);
        allUsers = <?php
            $users = $conn->query("SELECT id, username, name FROM users WHERE status='active' ORDER BY name ASC");
            $userList = [];
            while ($u = $users->fetch_assoc()) {
                $userList[] = $u;
            }
            echo json_encode($userList);
        ?>;
        renderShiftGrid();
    }
}

function changeWeek(delta) {
    currentWeekStart.setDate(currentWeekStart.getDate() + (delta * 7));
    renderShiftGrid();
}

function renderShiftGrid() {
    const grid = document.getElementById('shiftGrid');
    const weekLabel = document.getElementById('weekLabel');
    
    grid.innerHTML = '';
    
    const weekEnd = new Date(currentWeekStart);
    weekEnd.setDate(weekEnd.getDate() + 6);
    
    weekLabel.textContent = `KW ${getWeekNumber(currentWeekStart)} – ${formatDate(currentWeekStart)} bis ${formatDate(weekEnd)}`;
    
    // Header-Zeile
    grid.appendChild(createCell('Mitglied', 'shift-header'));
    
    for (let i = 0; i < 7; i++) {
        const date = new Date(currentWeekStart);
        date.setDate(date.getDate() + i);
        
        const header = createCell(`${weekdays[i]}<br>${date.getDate()}.${date.getMonth() + 1}.`, 'shift-header');
        grid.appendChild(header);
    }
    
    // Mitglieder-Zeilen
    allUsers.forEach(user => {
        const nameCell = createCell(user.name || user.username, 'shift-member-name');
        grid.appendChild(nameCell);
        
        for (let i = 0; i < 7; i++) {
            const date = new Date(currentWeekStart);
            date.setDate(date.getDate() + i);
            const dateStr = date.toISOString().slice(0, 10);
            
            const shift = allShifts.find(s => s.user_id == user.id && s.date === dateStr);
            
            const cell = document.createElement('div');
            cell.className = 'shift-cell';
            
            if (shift) {
                cell.classList.add('has-shift');
                const typeInfo = shiftTypes[shift.type];
                cell.innerHTML = `
                    <div class="shift-badge ${typeInfo.class}">${typeInfo.label}</div>
                    <div class="shift-time">${shift.start_time.slice(0,5)} - ${shift.end_time.slice(0,5)}</div>
                `;
            }
            
            // Admins können alle Schichten bearbeiten, normale User nur ihre eigenen
            if (isAdmin || user.id == currentUserId) {
                cell.addEventListener('click', () => openModal(user, dateStr, shift));
                cell.style.cursor = 'pointer';
            } else {
                cell.style.cursor = 'not-allowed';
                cell.style.opacity = '0.6';
            }
            
            grid.appendChild(cell);
        }
    });
}

function openModal(user, dateStr, existingShift) {
    const modal = document.getElementById('shiftModal');
    const form = document.getElementById('shiftForm');
    
    document.getElementById('modalUserId').value = user.id;
    document.getElementById('modalDate').value = dateStr;
    document.getElementById('modalUserName').value = user.name || user.username;
    document.getElementById('modalDateDisplay').value = new Date(dateStr).toLocaleDateString('de-DE', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric'
    });
    
    if (existingShift) {
        document.getElementById('modalType').value = existingShift.type;
        document.getElementById('modalStartTime').value = existingShift.start_time;
        document.getElementById('modalEndTime').value = existingShift.end_time;
    } else {
        form.reset();
        document.getElementById('modalUserId').value = user.id;
        document.getElementById('modalDate').value = dateStr;
    }
    
    modal.classList.add('active');
}

function closeModal() {
    document.getElementById('shiftModal').classList.remove('active');
}

document.getElementById('shiftForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const fd = new FormData(e.target);
    
    if (!fd.get('type')) {
        await deleteShift();
        return;
    }
    
    const resp = await fetch('/api/shift_save.php', { method: 'POST', body: fd });
    const res = await resp.json();
    
    if (res.ok) {
        closeModal();
        loadData();
    } else {
        alert('Fehler beim Speichern: ' + (res.error || 'Unbekannt'));
    }
});

async function deleteShift() {
    if (!confirm('Schicht wirklich löschen?')) return;
    
    const fd = new FormData();
    fd.set('user_id', document.getElementById('modalUserId').value);
    fd.set('date', document.getElementById('modalDate').value);
    
    await fetch('/api/shift_delete.php', { method: 'POST', body: fd });
    closeModal();
    loadData();
}

// Modal schließen bei Klick außerhalb
document.getElementById('shiftModal').addEventListener('click', (e) => {
    if (e.target.id === 'shiftModal') closeModal();
});

function getWeekNumber(date) {
    const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
    const dayNum = d.getUTCDay() || 7;
    d.setUTCDate(d.getUTCDate() + 4 - dayNum);
    const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
    return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
}

function formatDate(date) {
    return date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function createCell(html, className) {
    const cell = document.createElement('div');
    cell.className = className;
    cell.innerHTML = html;
    return cell;
}

loadData();
</script>
</body>
</html>
