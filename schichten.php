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
        .shift-overview {
            margin-top: 24px;
        }
        
        .shift-row {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 12px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .shift-row:hover {
            border-color: var(--accent);
            box-shadow: 0 4px 12px var(--accent-glow);
        }
        
        .member-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--accent);
            min-width: 100px;
            max-width: 100px;
            text-align: right;
            padding-right: 20px;
            border-right: 2px solid var(--border);
        }
        
        .week-grid {
            display: grid;
            grid-template-columns: repeat(14, 1fr);
            gap: 3px;
            flex: 1;
        }
        
        .day-cell {
            text-align: center;
            position: relative;
            min-height: 20px;
        }
        
        .day-cell.no-header {
            min-height: 0;
        }
        
        .day-header {
            font-size: 0.7rem;
            font-weight: 700;
            margin-bottom: 6px;
            color: var(--text-secondary);
        }
        
        .day-date {
            font-size: 0.65rem;
            color: var(--text-secondary);
            margin-bottom: 3px;
        }
        
        .holiday-name {
            font-size: 0.55rem;
            color: #d32f2f;
            font-weight: 700;
            margin-bottom: 2px;
            line-height: 1.1;
            padding: 1px 3px;
            background: rgba(211, 47, 47, 0.1);
            border-radius: 3px;
        }
        
        .vacation-name {
            font-size: 0.55rem;
            color: #f57c00;
            font-weight: 700;
            margin-bottom: 2px;
            line-height: 1.1;
            padding: 1px 3px;
            background: rgba(255, 193, 7, 0.2);
            border-radius: 3px;
        }
        
        /* Feiertage & Ferienzeiten */
        .day-cell.holiday .day-header {
            background: linear-gradient(135deg, #d32f2f, #f44336);
            color: white;
            padding: 4px;
            border-radius: 4px;
            font-weight: 800;
            position: relative;
        }
        
        .day-cell.holiday .day-header::before {
            content: '🎉';
            margin-right: 4px;
        }
        
        .day-cell.vacation-period {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.1), rgba(255, 235, 59, 0.1));
        }
        
        .day-cell.vacation-period .day-cell {
            border: 1px dashed #ffc107;
        }
        
        .holiday-label {
            font-size: 0.65rem;
            color: #d32f2f;
            font-weight: 700;
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .vacation-indicator {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #ffc107, #ffeb3b);
            border-radius: 4px 4px 0 0;
        }
        
        .shift-cell {
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 6px 2px;
            min-height: 55px;
            height: 55px;
            transition: all 0.3s;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
            box-sizing: border-box;
        }
        
        .shift-cell:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px var(--accent-glow);
        }
        
        .shift-cell.has-shift {
            font-weight: 700;
        }
        
        .shift-cell.today {
            border: 2px solid #fff !important;
            padding: 5px 1px !important;
            box-shadow: 0 0 20px rgba(16, 65, 134, 0.8), 0 0 40px rgba(16, 65, 134, 0.4) !important;
            position: relative;
            animation: pulse-today 2s ease-in-out infinite;
        }
        
        .shift-cell.today::before {
            content: '●';
            position: absolute;
            top: 1px;
            right: 1px;
            color: #fff;
            font-size: 0.5rem;
            text-shadow: 0 0 8px var(--accent);
            animation: blink 1.5s ease-in-out infinite;
        }
        
        .day-cell.today .day-header {
            background: var(--accent);
            color: white;
            padding: 4px;
            border-radius: 4px;
            font-weight: 800;
        }
        
        @keyframes pulse-today {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.03);
            }
        }
        
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        
        .shift-early { 
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            border-color: #ccac00;
            color: #000;
        }
        .shift-late { 
            background: linear-gradient(135deg, #ff8c00, #ffa500);
            border-color: #cc7000;
            color: #fff;
        }
        .shift-night { 
            background: linear-gradient(135deg, #4a148c, #6a1b9a);
            border-color: #311B92;
            color: #fff;
        }
        .shift-free { 
            background: linear-gradient(135deg, #4caf50, #66bb6a);
            border-color: #388e3c;
            color: #fff;
        }
        .shift-vacation { 
            background: linear-gradient(135deg, #f44336, #ef5350);
            border-color: #d32f2f;
            color: #fff;
        }
        
        .nav-buttons {
            display: flex;
            justify-content: center;
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .nav-btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .nav-btn:hover {
            background: var(--accent-hover);
            transform: scale(1.05);
        }
        
        .week-label {
            font-size: 1.25rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 24px;
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
        
        .schichtplan-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
        }
        
        .logo-svg {
            width: 80px;
            height: 80px;
            filter: drop-shadow(0 4px 12px var(--accent-glow));
        }
        
        .logo-text h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--accent), #1a5bb8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -1px;
        }
        
        .logo-text p {
            margin: 5px 0 0 0;
            font-size: 0.875rem;
            opacity: 0.7;
        }
        
        .clock-icon {
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .gear-rotate {
            animation: rotate 8s linear infinite;
            transform-origin: center;
        }
        
        /* Logo Animations */
        .calendar-body {
            animation: float-calendar 3s ease-in-out infinite;
        }
        
        .clock-circle {
            animation: pulse-clock 2s ease-in-out infinite;
        }
        
        .clock-hand-hour {
            transform-origin: 40px 40px;
            animation: rotate-hour 12s linear infinite;
        }
        
        .clock-hand-minute {
            transform-origin: 40px 40px;
            animation: rotate-minute 4s linear infinite;
        }
        
        .user-icon.user-1 {
            animation: bounce-user 2s ease-in-out infinite;
            animation-delay: 0s;
        }
        
        .user-body.user-1 {
            animation: bounce-user 2s ease-in-out infinite;
            animation-delay: 0s;
        }
        
        .user-icon.user-2 {
            animation: bounce-user 2s ease-in-out infinite;
            animation-delay: 0.3s;
        }
        
        .user-body.user-2 {
            animation: bounce-user 2s ease-in-out infinite;
            animation-delay: 0.3s;
        }
        
        .user-icon.user-3 {
            animation: bounce-user 2s ease-in-out infinite;
            animation-delay: 0.6s;
        }
        
        .user-body.user-3 {
            animation: bounce-user 2s ease-in-out infinite;
            animation-delay: 0.6s;
        }
        
        .calendar-ring {
            animation: wiggle 1.5s ease-in-out infinite;
        }
        
        @keyframes float-calendar {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-5px);
            }
        }
        
        @keyframes pulse-clock {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.05);
                opacity: 0.9;
            }
        }
        
        @keyframes rotate-hour {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }
        
        @keyframes rotate-minute {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }
        
        @keyframes bounce-user {
            0%, 100% {
                transform: scale(1);
                opacity: 0.6;
            }
            50% {
                transform: scale(1.5);
                opacity: 1;
            }
        }
        
        @keyframes wiggle {
            0%, 100% {
                transform: rotate(0deg);
            }
            25% {
                transform: rotate(-3deg);
            }
            75% {
                transform: rotate(3deg);
            }
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
            <div class="schichtplan-logo">
                <svg viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg" class="logo-svg">
                    <!-- Outer Circle with Gradient -->
                    <defs>
                        <linearGradient id="circleGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:#104186;stop-opacity:1" />
                            <stop offset="100%" style="stop-color:#1a5bb8;stop-opacity:1" />
                        </linearGradient>
                        <filter id="glow">
                            <feGaussianBlur stdDeviation="2" result="coloredBlur"/>
                            <feMerge>
                                <feMergeNode in="coloredBlur"/>
                                <feMergeNode in="SourceGraphic"/>
                            </feMerge>
                        </filter>
                    </defs>
                    
                    <!-- Rotating outer ring -->
                    <circle cx="40" cy="40" r="35" fill="none" stroke="url(#circleGradient)" stroke-width="3" opacity="0.3" class="gear-rotate"/>
                    
                    <!-- Clock face -->
                    <circle cx="40" cy="40" r="28" fill="url(#circleGradient)" opacity="0.2" class="clock-circle"/>
                    <circle cx="40" cy="40" r="28" fill="none" stroke="url(#circleGradient)" stroke-width="3" filter="url(#glow)"/>
                    
                    <!-- Hour markers -->
                    <line x1="40" y1="15" x2="40" y2="20" stroke="#104186" stroke-width="2.5" stroke-linecap="round"/>
                    <line x1="65" y1="40" x2="60" y2="40" stroke="#104186" stroke-width="2.5" stroke-linecap="round"/>
                    <line x1="40" y1="65" x2="40" y2="60" stroke="#104186" stroke-width="2.5" stroke-linecap="round"/>
                    <line x1="15" y1="40" x2="20" y2="40" stroke="#104186" stroke-width="2.5" stroke-linecap="round"/>
                    
                    <!-- Clock hands -->
                    <line x1="40" y1="40" x2="40" y2="25" stroke="#104186" stroke-width="3" stroke-linecap="round" class="clock-hand-hour"/>
                    <line x1="40" y1="40" x2="52" y2="40" stroke="#104186" stroke-width="2" stroke-linecap="round" class="clock-hand-minute"/>
                    
                    <!-- Center dot -->
                    <circle cx="40" cy="40" r="3" fill="#104186"/>
                    
                    <!-- Animated dots around -->
                    <circle cx="40" cy="8" r="2" fill="#104186" opacity="0.6" class="user-icon user-1"/>
                    <circle cx="56" cy="56" r="2" fill="#104186" opacity="0.6" class="user-icon user-2"/>
                    <circle cx="24" cy="56" r="2" fill="#104186" opacity="0.6" class="user-icon user-3"/>
                </svg>
                <div class="logo-text">
                    <h1>Schichtplan</h1>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <a href="schichten_bearbeiten.php" class="btn">✏️ Meine Schichten bearbeiten</a>
            <div class="week-label" id="weekLabel"></div>
        </div>

        <div class="nav-buttons">
            <button class="nav-btn" onclick="changeWeeks(-2)">◀◀ 2 Wochen zurück</button>
            <button class="nav-btn" onclick="changeWeeks(2)">2 Wochen weiter ▶▶</button>
        </div>

        <div class="shift-overview" id="shiftOverview"></div>

        <div class="legend">
            <div class="legend-item">
                <div class="legend-box shift-early"></div>
                <span>Frühschicht</span>
            </div>
            <div class="legend-item">
                <div class="legend-box shift-late"></div>
                <span>Spätschicht</span>
            </div>
            <div class="legend-item">
                <div class="legend-box shift-night"></div>
                <span>Nachtschicht</span>
            </div>
            <div class="legend-item">
                <div class="legend-box shift-free"></div>
                <span>Frei</span>
            </div>
            <div class="legend-item">
                <div class="legend-box shift-vacation"></div>
                <span>Urlaub</span>
            </div>
        </div>
    </div>

    <div id="tooltip" class="tooltip"></div>

<script>
let currentWeekStart = new Date();
currentWeekStart.setHours(12, 0, 0, 0); // Set to noon to avoid timezone issues
// Start on Monday
const dayOfWeek = currentWeekStart.getDay();
const diff = dayOfWeek === 0 ? -6 : 1 - dayOfWeek; // If Sunday (0), go back 6 days, else go to Monday
currentWeekStart.setDate(currentWeekStart.getDate() + diff);

let allUsers = [];
let allShifts = [];
let allHolidays = [];

const weekdays = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

const shiftTypes = {
    'early': { label: 'Früh', class: 'shift-early', emoji: '🌅' },
    'late': { label: 'Spät', class: 'shift-late', emoji: '🌆' },
    'night': { label: 'Nacht', class: 'shift-night', emoji: '🌙' },
    'free': { label: 'Frei', class: 'shift-free', emoji: '✅' },
    'vacation': { label: 'Urlaub', class: 'shift-vacation', emoji: '🏖️' }
};

async function loadData() {
    try {
        const [usersRes, shiftsRes, holidaysRes] = await Promise.all([
            fetch('/api/users_list.php'),
            fetch('/api/shifts_list.php'),
            fetch('/api/holidays_list.php')
        ]);
        
        allUsers = await usersRes.json();
        allShifts = await shiftsRes.json();
        allHolidays = await holidaysRes.json();
        
        renderShiftOverview();
    } catch (e) {
        console.error('Fehler:', e);
    }
}

function changeWeeks(weeks) {
    currentWeekStart.setDate(currentWeekStart.getDate() + (weeks * 7));
    renderShiftOverview();
}

function renderShiftOverview() {
    const overview = document.getElementById('shiftOverview');
    const weekLabel = document.getElementById('weekLabel');
    
    if (!overview) {
        console.error('shiftOverview element not found');
        return;
    }
    
    overview.innerHTML = '';
    
    // Calculate 2 weeks (14 days)
    const weekEnd = new Date(currentWeekStart);
    weekEnd.setDate(weekEnd.getDate() + 13);
    
    weekLabel.textContent = `${formatDate(currentWeekStart)} – ${formatDate(weekEnd)}`;
    
    // Today in local timezone
    const todayDate = new Date();
    todayDate.setHours(12, 0, 0, 0);
    const today = `${todayDate.getFullYear()}-${String(todayDate.getMonth() + 1).padStart(2, '0')}-${String(todayDate.getDate()).padStart(2, '0')}`;
    
    // Render each member as a row
    allUsers.forEach((user, userIndex) => {
        const row = document.createElement('div');
        row.className = 'shift-row';
        
        // Member name on the left
        const nameDiv = document.createElement('div');
        nameDiv.className = 'member-name';
        nameDiv.textContent = user.name || user.username;
        row.appendChild(nameDiv);
        
        // Week grid (14 days)
        const grid = document.createElement('div');
        grid.className = 'week-grid';
        
        const isFirstRow = userIndex === 0; // Only show dates for first member (Alessio)
        
        for (let i = 0; i < 14; i++) {
            const date = new Date(currentWeekStart);
            date.setDate(date.getDate() + i);
            date.setHours(12, 0, 0, 0); // Set to noon to avoid timezone issues
            
            // Fix timezone offset - use local date
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const dateStr = `${year}-${month}-${day}`;
            
            const dayCell = document.createElement('div');
            dayCell.className = 'day-cell';
            
            // Mark today's column
            if (dateStr === today) {
                dayCell.classList.add('today');
            }
            
            // Check for holidays
            const holiday = allHolidays.find(h => h.date === dateStr && h.type === 'holiday');
            if (holiday) {
                dayCell.classList.add('holiday');
                dayCell.setAttribute('title', holiday.name);
            }
            
            // Check if in vacation period
            const vacationStart = allHolidays.filter(h => h.type === 'vacation_start');
            const vacationEnd = allHolidays.filter(h => h.type === 'vacation_end');
            
            for (let v = 0; v < vacationStart.length; v++) {
                const start = new Date(vacationStart[v].date);
                const end = vacationEnd[v] ? new Date(vacationEnd[v].date) : null;
                if (end && date >= start && date <= end) {
                    dayCell.classList.add('vacation-period');
                    break;
                }
            }
            
            const dayName = weekdays[date.getDay() === 0 ? 6 : date.getDay() - 1];
            const dayDate = `${date.getDate()}.${date.getMonth() + 1}.`;
            
            // Build header HTML - only for first row
            let headerHTML = '';
            let extraHTML = '';
            
            if (isFirstRow) {
                headerHTML = `<div class="day-header">${dayName}</div>`;
                
                // Add holiday name
                if (holiday) {
                    headerHTML = `<div class="day-header">🎉 ${dayName}</div>`;
                    const holidayShortName = holiday.name.length > 12 ? holiday.name.substring(0, 12) + '...' : holiday.name;
                    extraHTML += `<div class="holiday-name">${holidayShortName}</div>`;
                }
                
                // Add vacation period indicator and name
                if (dayCell.classList.contains('vacation-period')) {
                    headerHTML = `<div class="vacation-indicator"></div>` + headerHTML;
                    
                    // Find which vacation period we're in
                    for (let v = 0; v < vacationStart.length; v++) {
                        const start = new Date(vacationStart[v].date);
                        const end = vacationEnd[v] ? new Date(vacationEnd[v].date) : null;
                        if (end && date >= start && date <= end) {
                            // Extract vacation name (e.g. "Sommerferien Start" -> "Sommer")
                            let vacName = vacationStart[v].name.replace(' Start', '').replace(' Ende', '');
                            if (vacName.includes('ferien')) {
                                vacName = vacName.replace('ferien', '');
                            }
                            extraHTML += `<div class="vacation-name">${vacName}</div>`;
                            break;
                        }
                    }
                }
                
                dayCell.innerHTML = headerHTML + extraHTML + `<div class="day-date">${dayDate}</div>`;
            } else {
                // For other rows: no header, no date, no labels
                dayCell.classList.add('no-header');
                dayCell.innerHTML = '';
            }
            
            const shift = allShifts.find(s => s.user_id == user.id && s.date === dateStr);
            
            const cell = document.createElement('div');
            cell.className = 'shift-cell';
            
            if (dateStr === today) {
                cell.classList.add('today');
            }
            
            if (shift && shiftTypes[shift.type]) {
                cell.classList.add('has-shift', shiftTypes[shift.type].class);
                cell.innerHTML = shiftTypes[shift.type].emoji;
            } else {
                cell.innerHTML = '-';
            }
            
            dayCell.appendChild(cell);
            grid.appendChild(dayCell);
        }
        
        row.appendChild(grid);
        overview.appendChild(row);
    });
}

function formatDate(date) {
    return date.toLocaleDateString('de-DE', { day: '2-digit', month: 'long', year: 'numeric' });
}

loadData();
</script>
</body>
</html>
