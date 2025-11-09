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
    <title>Schichtplan-Übersicht – PUSHING P</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .shift-layout {
            display: flex;
            gap: 24px;
            margin-top: 24px;
        }
        
        .members-sidebar {
            width: 250px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            max-height: 600px;
            overflow-y: auto;
            position: sticky;
            top: 80px;
        }
        
        .members-sidebar h3 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 16px;
            color: var(--accent);
        }
        
        .member-item {
            padding: 10px 12px;
            margin-bottom: 8px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .member-item:hover {
            background: var(--bg-primary);
            border-color: var(--accent);
            transform: translateX(4px);
        }
        
        .shift-content {
            flex: 1;
            overflow-x: auto;
        }
        
        .shift-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(45px, 1fr));
            gap: 6px;
            margin-top: 24px;
        }
        
        .shift-header {
            background: var(--bg-tertiary);
            padding: 10px 4px;
            border-radius: 8px;
            font-weight: 700;
            text-align: center;
            font-size: 0.75rem;
            border: 1px solid var(--border);
        }
        
        .shift-row {
            display: contents;
        }
        
        .shift-cell {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 6px;
            min-height: 45px;
            width: 45px;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
        }
        
        .shift-cell:hover {
            transform: scale(1.15);
            z-index: 20;
            box-shadow: 0 6px 16px var(--accent-glow);
        }
        
        .shift-cell.today {
            border: 2px solid var(--accent);
            box-shadow: 0 0 12px var(--accent-glow);
            animation: pulse 2s infinite;
        }
        
        .shift-early { 
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            border-color: #ccac00;
        }
        .shift-day { 
            background: linear-gradient(135deg, #2196F3, #42a5f5);
            border-color: #1976D2;
        }
        .shift-late { 
            background: linear-gradient(135deg, #ff8c00, #ffa500);
            border-color: #cc7000;
        }
        .shift-night { 
            background: linear-gradient(135deg, #4a148c, #6a1b9a);
            border-color: #311B92;
        }
        
        .month-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .month-btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .month-btn:hover {
            background: var(--accent-hover);
            transform: scale(1.05);
            box-shadow: 0 4px 12px var(--accent-glow);
        }
        
        .month-label {
            font-weight: 700;
            font-size: 1.125rem;
        }
        
        .legend {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 20px;
            padding: 12px;
            background: var(--bg-secondary);
            border-radius: 8px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.875rem;
        }
        
        .legend-box {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }
        
        .tooltip {
            position: fixed;
            background: var(--bg-primary);
            border: 1px solid var(--accent);
            border-radius: 8px;
            padding: 12px;
            font-size: 0.875rem;
            pointer-events: none;
            z-index: 1000;
            display: none;
            box-shadow: 0 8px 24px rgba(0,0,0,0.6);
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
            <h1>📅 Schichtplan-Übersicht</h1>
            <p class="text-secondary">Jahresübersicht aller Arbeitsschichten</p>
        </div>

        <div class="month-nav">
            <a href="schichten_bearbeiten.php" class="btn">✏️ Meine Schichten bearbeiten</a>
            <div style="display: flex; gap: 12px; align-items: center;">
                <button class="month-btn" onclick="changeMonth(-1)">◀</button>
                <div class="month-label" id="monthLabel"></div>
                <button class="month-btn" onclick="changeMonth(1)">▶</button>
            </div>
        </div>

        <div class="shift-layout">
            <div class="members-sidebar">
                <h3>👥 Mitglieder</h3>
                <div id="membersList"></div>
            </div>
            
            <div class="shift-content">
                <div class="section">
                    <div id="shiftGrid" class="shift-grid"></div>
                </div>
            </div>
        </div>

        <div class="legend">
            <div class="legend-item">
                <div class="legend-box shift-early"></div>
                <span>Frühschicht</span>
            </div>
            <div class="legend-item">
                <div class="legend-box shift-day"></div>
                <span>Tagschicht</span>
            </div>
            <div class="legend-item">
                <div class="legend-box shift-late"></div>
                <span>Spätschicht</span>
            </div>
            <div class="legend-item">
                <div class="legend-box shift-night"></div>
                <span>Nachtschicht</span>
            </div>
        </div>
    </div>

    <div id="tooltip" class="tooltip"></div>

<script>
let currentYear = new Date().getFullYear();
let currentMonth = new Date().getMonth();

let allUsers = [];
let allShifts = [];

const monthNames = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 
                    'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

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
        
        renderMembersList();
        renderShiftGrid();
    } catch (e) {
        console.error('Fehler:', e);
    }
}

function renderMembersList() {
    const membersList = document.getElementById('membersList');
    membersList.innerHTML = '';
    
    if (allUsers.length === 0) {
        membersList.innerHTML = '<div style="color: var(--text-secondary); font-size: 0.875rem;">Keine Schichtarbeiter</div>';
        return;
    }
    
    allUsers.forEach(user => {
        const item = document.createElement('div');
        item.className = 'member-item';
        item.textContent = user.name || user.username;
        item.dataset.userId = user.id;
        membersList.appendChild(item);
    });
}

function changeMonth(delta) {
    currentMonth += delta;
    if (currentMonth > 11) {
        currentMonth = 0;
        currentYear++;
    } else if (currentMonth < 0) {
        currentMonth = 11;
        currentYear--;
    }
    renderShiftGrid();
}

function renderShiftGrid() {
    const grid = document.getElementById('shiftGrid');
    const monthLabel = document.getElementById('monthLabel');
    
    grid.innerHTML = '';
    monthLabel.textContent = `${monthNames[currentMonth]} ${currentYear}`;
    
    const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
    const today = new Date().toISOString().slice(0, 10);
    
    // Header-Zeile (nur Tage)
    for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(currentYear, currentMonth, day);
        const dayName = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][date.getDay()];
        const isWeekend = date.getDay() === 0 || date.getDay() === 6;
        const header = createCell(`<strong>${day}</strong><br><small>${dayName}</small>`, 'shift-header');
        if (isWeekend) header.style.background = 'var(--bg-primary)';
        grid.appendChild(header);
    }
    
    // Mitglieder-Zeilen (Zellen für jeden Tag)
    allUsers.forEach(user => {
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const shift = allShifts.find(s => s.user_id == user.id && s.date === dateStr);
            
            const cell = document.createElement('div');
            cell.className = 'shift-cell';
            
            if (shift) {
                const typeInfo = shiftTypes[shift.type];
                cell.classList.add(typeInfo.class);
                cell.dataset.shift = JSON.stringify(shift);
                cell.dataset.user = user.name || user.username;
            }
            
            if (dateStr === today) {
                cell.classList.add('today');
            }
            
            cell.addEventListener('mouseenter', showTooltip);
            cell.addEventListener('mouseleave', hideTooltip);
            
            grid.appendChild(cell);
        }
    });
}

function showTooltip(e) {
    const cell = e.target;
    const tooltip = document.getElementById('tooltip');
    
    if (cell.dataset.shift) {
        const shift = JSON.parse(cell.dataset.shift);
        const user = cell.dataset.user;
        const typeInfo = shiftTypes[shift.type];
        
        tooltip.innerHTML = `
            <strong>${user}</strong><br>
            ${typeInfo.label}: ${shift.start_time.slice(0,5)} - ${shift.end_time.slice(0,5)}<br>
            ${shift.date}
        `;
        tooltip.style.display = 'block';
        tooltip.style.left = e.pageX + 10 + 'px';
        tooltip.style.top = e.pageY + 10 + 'px';
    }
}

function hideTooltip() {
    document.getElementById('tooltip').style.display = 'none';
}

function createCell(html, className) {
    const cell = document.createElement('div');
    cell.className = className;
    cell.innerHTML = html;
    return cell;
}

loadData();
renderShiftGrid();
</script>
</body>
</html>
