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
            grid-template-columns: 150px repeat(7, 1fr);
            gap: 8px;
            margin-top: 24px;
        }
        
        .shift-header {
            background: var(--bg-tertiary);
            padding: 12px;
            border-radius: 8px;
            font-weight: 700;
            text-align: center;
            font-size: 0.875rem;
        }
        
        .shift-member-name {
            background: var(--bg-secondary);
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            display: flex;
            align-items: center;
            border: 1px solid var(--border);
        }
        
        .shift-cell {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px;
            min-height: 60px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        
        .shift-cell:hover {
            background: var(--bg-tertiary);
            border-color: var(--accent);
            transform: translateY(-2px);
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
        
        .shift-free {
            background: linear-gradient(135deg, #4caf50, #66bb6a);
            color: #fff;
        }
        
        .shift-vacation {
            background: linear-gradient(135deg, #f44336, #ef5350);
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
        
        .shift-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }
        
        .shift-modal.active {
            display: flex;
        }
        
        .shift-modal-content {
            background: var(--bg-secondary);
            padding: 32px;
            border-radius: 16px;
            max-width: 700px;
            width: 90%;
            border: 1px solid var(--accent);
            animation: scaleIn 0.3s ease;
        }
        
        .shift-modal-header {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 24px;
            color: var(--accent);
        }
        
        .shift-type-selector {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .shift-type-btn {
            padding: 16px;
            border-radius: 12px;
            border: 3px solid transparent;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            font-weight: 700;
            font-size: 0.875rem;
        }
        
        .shift-type-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        
        .shift-type-btn.selected {
            border-color: #fff;
            box-shadow: 0 0 20px currentColor;
            transform: scale(1.1);
            animation: pulse 1s infinite;
        }
        
        .shift-type-btn.early {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #000;
        }
        
        .shift-type-btn.day {
            background: linear-gradient(135deg, #2196F3, #42a5f5);
            color: #fff;
        }
        
        .shift-type-btn.late {
            background: linear-gradient(135deg, #ff8c00, #ffa500);
            color: #fff;
        }
        
        .shift-type-btn.night {
            background: linear-gradient(135deg, #4a148c, #6a1b9a);
            color: #fff;
        }
        
        .shift-type-btn.free {
            background: linear-gradient(135deg, #4caf50, #66bb6a);
            color: #fff;
        }
        
        .shift-type-btn.vacation {
            background: linear-gradient(135deg, #f44336, #ef5350);
            color: #fff;
        }
        
        .date-range-selector {
            background: var(--bg-tertiary);
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid var(--border);
        }
        
        .date-range-selector h3 {
            font-size: 0.9rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--accent);
        }
        
        .date-checkboxes {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }
        
        .date-checkbox {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px;
            background: var(--bg-secondary);
            border: 2px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.8rem;
        }
        
        .date-checkbox:hover {
            border-color: var(--accent);
            background: var(--bg-primary);
        }
        
        .date-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--accent);
        }
        
        .date-checkbox.selected {
            border-color: var(--accent);
            background: var(--accent);
            color: white;
            font-weight: 700;
        }
        
        .select-all-btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 12px;
        }
        
        .select-all-btn:hover {
            background: var(--accent-hover);
            transform: scale(1.05);
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
                <span>Frühschicht (06:00-14:00)</span>
            </div>
            <div class="legend-item">
                <span class="shift-badge shift-late">Spät</span>
                <span>Spätschicht (14:00-22:00)</span>
            </div>
            <div class="legend-item">
                <span class="shift-badge shift-night">Nacht</span>
                <span>Nachtschicht (22:00-06:00)</span>
            </div>
            <div class="legend-item">
                <span class="shift-badge shift-free">Frei</span>
                <span>Frei / Kein Dienst</span>
            </div>
            <div class="legend-item">
                <span class="shift-badge shift-vacation">Urlaub</span>
                <span>Urlaub / Abwesend</span>
            </div>
        </div>
    </div>

    <!-- Modal für Schicht hinzufügen/bearbeiten -->
    <div id="shiftModal" class="shift-modal">
        <div class="shift-modal-content">
            <div class="shift-modal-header" id="modalHeader">Schicht bearbeiten</div>
            <form id="shiftForm">
                <input type="hidden" id="modalUserId" name="user_id">
                <input type="hidden" id="modalDate" name="date">
                <input type="hidden" id="modalType" name="type">
                <input type="hidden" id="selectedDates" name="selected_dates">
                
                <?php if ($is_admin): ?>
                <div class="form-group">
                    <label>Mitglied auswählen</label>
                    <select id="modalUserSelect" onchange="changeMember()" style="padding: 10px; font-size: 1rem;">
                        <option value="">-- Mitglied wählen --</option>
                    </select>
                </div>
                <?php else: ?>
                <div class="form-group">
                    <label>Mitglied</label>
                    <input type="text" id="modalUserName" disabled style="opacity: 0.6;">
                </div>
                <?php endif; ?>
                
                <div class="date-range-selector">
                    <h3>📅 Mehrere Tage auswählen (optional)</h3>
                    <button type="button" class="select-all-btn" onclick="toggleAllDates()">Alle auswählen / abwählen</button>
                    <div id="dateCheckboxes" class="date-checkboxes"></div>
                </div>
                
                <div class="form-group">
                    <label>Schichttyp auswählen (Farbe anklicken)</label>
                    <div class="shift-type-selector">
                        <div class="shift-type-btn early" onclick="selectShiftType('early')" data-shift-type="early">
                            🌅 Früh
                        </div>
                        <div class="shift-type-btn late" onclick="selectShiftType('late')" data-shift-type="late">
                            🌆 Spät
                        </div>
                        <div class="shift-type-btn night" onclick="selectShiftType('night')" data-shift-type="night">
                            🌙 Nacht
                        </div>
                        <div class="shift-type-btn free" onclick="selectShiftType('free')" data-shift-type="free">
                            ✅ Frei
                        </div>
                        <div class="shift-type-btn vacation" onclick="selectShiftType('vacation')" data-shift-type="vacation">
                            🏖️ Urlaub
                        </div>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 700;">
                        <input type="checkbox" id="enableMultiShift" style="width: 20px; height: 20px; cursor: pointer; accent-color: var(--accent);">
                        <span>🔄 Mehrere Schichttypen gleichzeitig eintragen</span>
                    </label>
                    <div id="multiShiftContainer" style="display: none; margin-top: 12px;">
                        <div style="background: var(--bg-tertiary); padding: 16px; border-radius: 12px; border: 1px solid var(--border);">
                            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 12px;">
                                ℹ️ Klicke auf die Schichttypen, die du für die ausgewählten Tage eintragen möchtest:
                            </p>
                            <div class="shift-type-selector" id="multiShiftSelector">
                                <div class="shift-type-btn early" onclick="toggleMultiShift('early')" data-shift-type="early">
                                    🌅 Früh
                                </div>
                                <div class="shift-type-btn late" onclick="toggleMultiShift('late')" data-shift-type="late">
                                    🌆 Spät
                                </div>
                                <div class="shift-type-btn night" onclick="toggleMultiShift('night')" data-shift-type="night">
                                    🌙 Nacht
                                </div>
                                <div class="shift-type-btn free" onclick="toggleMultiShift('free')" data-shift-type="free">
                                    ✅ Frei
                                </div>
                                <div class="shift-type-btn vacation" onclick="toggleMultiShift('vacation')" data-shift-type="vacation">
                                    🏖️ Urlaub
                                </div>
                            </div>
                            <div id="multiShiftPreview" style="margin-top: 12px; font-size: 0.85rem; color: var(--accent); font-weight: 700;"></div>
                        </div>
                    </div>
                </div>
                
                <input type="hidden" name="start_time" id="modalStartTime">
                <input type="hidden" name="end_time" id="modalEndTime">
                
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

let selectedMultiShifts = [];

const weekdays = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
const shiftTypes = {
    'early': { label: 'Früh', class: 'shift-early', emoji: '🌅', start: '06:00', end: '14:00' },
    'late': { label: 'Spät', class: 'shift-late', emoji: '🌆', start: '14:00', end: '22:00' },
    'night': { label: 'Nacht', class: 'shift-night', emoji: '🌙', start: '22:00', end: '06:00' },
    'free': { label: 'Frei', class: 'shift-free', emoji: '✅', start: '00:00', end: '00:00' },
    'vacation': { label: 'Urlaub', class: 'shift-vacation', emoji: '🏖️', start: '00:00', end: '00:00' }
};

// Toggle multi-shift mode
document.getElementById('enableMultiShift').addEventListener('change', (e) => {
    const container = document.getElementById('multiShiftContainer');
    const singleSelector = document.querySelector('.shift-type-selector');
    
    if (e.target.checked) {
        container.style.display = 'block';
        singleSelector.style.opacity = '0.3';
        singleSelector.style.pointerEvents = 'none';
    } else {
        container.style.display = 'none';
        singleSelector.style.opacity = '1';
        singleSelector.style.pointerEvents = 'auto';
        selectedMultiShifts = [];
        updateMultiShiftPreview();
    }
});

function toggleMultiShift(type) {
    const btn = document.querySelector(`#multiShiftSelector .shift-type-btn[data-shift-type="${type}"]`);
    
    if (selectedMultiShifts.includes(type)) {
        selectedMultiShifts = selectedMultiShifts.filter(t => t !== type);
        btn.classList.remove('selected');
    } else {
        selectedMultiShifts.push(type);
        btn.classList.add('selected');
    }
    
    updateMultiShiftPreview();
}

function updateMultiShiftPreview() {
    const preview = document.getElementById('multiShiftPreview');
    
    if (selectedMultiShifts.length === 0) {
        preview.textContent = '';
    } else {
        const labels = selectedMultiShifts.map(type => shiftTypes[type].emoji + ' ' + shiftTypes[type].label).join(' + ');
        preview.textContent = `✨ Ausgewählt: ${labels}`;
    }
}

function selectShiftType(type) {
    // Remove selected class from all in single selector
    document.querySelectorAll('.shift-type-selector .shift-type-btn').forEach(btn => {
        btn.classList.remove('selected');
    });
    
    // Add selected to clicked
    document.querySelector(`.shift-type-selector .shift-type-btn[data-shift-type="${type}"]`).classList.add('selected');
    
    // Set hidden input
    document.getElementById('modalType').value = type;
    
    // Set times
    if (shiftTypes[type]) {
        document.getElementById('modalStartTime').value = shiftTypes[type].start;
        document.getElementById('modalEndTime').value = shiftTypes[type].end;
    }
}

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
                
                // Emoji und Label
                const displayLabel = `${typeInfo.emoji} ${typeInfo.label}`;
                
                // Zeit nur anzeigen wenn nicht Frei/Urlaub und nicht 00:00
                let timeDisplay = '';
                if (shift.type !== 'free' && shift.type !== 'vacation') {
                    if (shift.start_time && shift.end_time && shift.start_time !== '00:00:00') {
                        timeDisplay = `<div class="shift-time">${shift.start_time.slice(0,5)} - ${shift.end_time.slice(0,5)}</div>`;
                    }
                }
                
                cell.innerHTML = `
                    <div class="shift-badge ${typeInfo.class}">${displayLabel}</div>
                    ${timeDisplay}
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
    
    // Reset all selected buttons
    document.querySelectorAll('.shift-type-btn').forEach(btn => {
        btn.classList.remove('selected');
    });
    
    // Reset multi-shift mode
    document.getElementById('enableMultiShift').checked = false;
    document.getElementById('multiShiftContainer').style.display = 'none';
    document.querySelector('.shift-type-selector').style.opacity = '1';
    document.querySelector('.shift-type-selector').style.pointerEvents = 'auto';
    selectedMultiShifts = [];
    updateMultiShiftPreview();
    
    // Populate user selector for admins
    if (isAdmin) {
        const select = document.getElementById('modalUserSelect');
        select.innerHTML = '<option value="">-- Mitglied wählen --</option>';
        allUsers.forEach(u => {
            const option = document.createElement('option');
            option.value = u.id;
            option.textContent = u.name || u.username;
            if (u.id == user.id) option.selected = true;
            select.appendChild(option);
        });
    } else {
        document.getElementById('modalUserName').value = user.name || user.username;
    }
    
    document.getElementById('modalUserId').value = user.id;
    document.getElementById('modalDate').value = dateStr;
    
    // Populate date checkboxes for the current week
    populateDateCheckboxes(dateStr);
    
    // Update modal header with date
    const formattedDate = new Date(dateStr).toLocaleDateString('de-DE', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric'
    });
    document.getElementById('modalHeader').textContent = `📅 Schicht für ${formattedDate}`;
    
    if (existingShift) {
        selectShiftType(existingShift.type);
    } else {
        form.reset();
        // Re-populate select after reset
        if (isAdmin) {
            const select = document.getElementById('modalUserSelect');
            select.innerHTML = '<option value="">-- Mitglied wählen --</option>';
            allUsers.forEach(u => {
                const option = document.createElement('option');
                option.value = u.id;
                option.textContent = u.name || u.username;
                if (u.id == user.id) option.selected = true;
                select.appendChild(option);
            });
        }
        document.getElementById('modalUserId').value = user.id;
        document.getElementById('modalDate').value = dateStr;
        populateDateCheckboxes(dateStr);
    }
    
    modal.classList.add('active');
}

function populateDateCheckboxes(clickedDate) {
    const container = document.getElementById('dateCheckboxes');
    container.innerHTML = '';
    
    // Generate 7 days from current week
    for (let i = 0; i < 7; i++) {
        const date = new Date(currentWeekStart);
        date.setDate(date.getDate() + i);
        const dateStr = date.toISOString().slice(0, 10);
        
        const label = document.createElement('label');
        label.className = 'date-checkbox';
        
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.value = dateStr;
        checkbox.name = 'dates[]';
        
        // Auto-select the clicked date
        if (dateStr === clickedDate) {
            checkbox.checked = true;
            label.classList.add('selected');
        }
        
        checkbox.addEventListener('change', (e) => {
            if (e.target.checked) {
                label.classList.add('selected');
            } else {
                label.classList.remove('selected');
            }
            updateSelectedDates();
        });
        
        const dateLabel = document.createElement('span');
        dateLabel.textContent = `${weekdays[i]} ${date.getDate()}.${date.getMonth() + 1}.`;
        
        label.appendChild(checkbox);
        label.appendChild(dateLabel);
        container.appendChild(label);
    }
    
    updateSelectedDates();
}

function toggleAllDates() {
    const checkboxes = document.querySelectorAll('#dateCheckboxes input[type="checkbox"]');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    
    checkboxes.forEach(cb => {
        cb.checked = !allChecked;
        const label = cb.closest('.date-checkbox');
        if (cb.checked) {
            label.classList.add('selected');
        } else {
            label.classList.remove('selected');
        }
    });
    
    updateSelectedDates();
}

function updateSelectedDates() {
    const checkboxes = document.querySelectorAll('#dateCheckboxes input[type="checkbox"]:checked');
    const dates = Array.from(checkboxes).map(cb => cb.value);
    document.getElementById('selectedDates').value = JSON.stringify(dates);
}

function changeMember() {
    const select = document.getElementById('modalUserSelect');
    const userId = select.value;
    if (userId) {
        document.getElementById('modalUserId').value = userId;
        
        // Load existing shift for selected member on this date
        const dateStr = document.getElementById('modalDate').value;
        const shift = allShifts.find(s => s.user_id == userId && s.date === dateStr);
        
        // Reset selection
        document.querySelectorAll('.shift-type-btn').forEach(btn => {
            btn.classList.remove('selected');
        });
        
        if (shift) {
            selectShiftType(shift.type);
        } else {
            document.getElementById('modalStartTime').value = '';
            document.getElementById('modalEndTime').value = '';
        }
    }
}

function closeModal() {
    document.getElementById('shiftModal').classList.remove('active');
}

document.getElementById('shiftForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const fd = new FormData(e.target);
    const isMultiShiftMode = document.getElementById('enableMultiShift').checked;
    
    // Get selected dates
    const selectedDates = JSON.parse(document.getElementById('selectedDates').value || '[]');
    
    if (selectedDates.length === 0) {
        alert('Bitte mindestens einen Tag auswählen!');
        return;
    }
    
    let shiftsToSave = [];
    
    if (isMultiShiftMode) {
        // Multi-shift mode: save multiple shift types
        if (selectedMultiShifts.length === 0) {
            alert('Bitte mindestens einen Schichttyp auswählen!');
            return;
        }
        
        // Create all combinations of dates and shift types
        for (const date of selectedDates) {
            for (const shiftType of selectedMultiShifts) {
                shiftsToSave.push({
                    user_id: fd.get('user_id'),
                    date: date,
                    type: shiftType,
                    start_time: shiftTypes[shiftType].start,
                    end_time: shiftTypes[shiftType].end
                });
            }
        }
    } else {
        // Single shift mode: save one type for all dates
        if (!fd.get('type')) {
            await deleteShift();
            return;
        }
        
        for (const date of selectedDates) {
            shiftsToSave.push({
                user_id: fd.get('user_id'),
                date: date,
                type: fd.get('type'),
                start_time: fd.get('start_time'),
                end_time: fd.get('end_time')
            });
        }
    }
    
    // Save all shifts
    let successCount = 0;
    for (const shift of shiftsToSave) {
        const shiftData = new FormData();
        shiftData.set('user_id', shift.user_id);
        shiftData.set('date', shift.date);
        shiftData.set('type', shift.type);
        shiftData.set('start_time', shift.start_time);
        shiftData.set('end_time', shift.end_time);
        
        const resp = await fetch('/api/shift_save.php', { method: 'POST', body: shiftData });
        const res = await resp.json();
        
        if (res.ok) {
            successCount++;
        }
    }
    
    if (successCount > 0) {
        closeModal();
        loadData();
        
        if (isMultiShiftMode) {
            const shiftTypeLabels = selectedMultiShifts.map(t => shiftTypes[t].emoji + ' ' + shiftTypes[t].label).join(', ');
            alert(`✅ ${successCount} Schicht(en) erfolgreich gespeichert!\n(${selectedDates.length} Tag(e) × ${selectedMultiShifts.length} Schichttyp(en))\n\nSchichten: ${shiftTypeLabels}`);
        } else {
            alert(`✅ ${successCount} Schicht(en) erfolgreich gespeichert!`);
        }
    } else {
        alert('❌ Fehler beim Speichern der Schichten!');
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
