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
            grid-template-columns: 1fr 1fr;
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
        
        .event-form-inline {
            margin-top: 16px;
            padding: 16px;
            background: var(--bg-tertiary);
            border: 2px solid var(--accent);
            border-radius: 12px;
            animation: scaleIn 0.4s ease;
        }
        
        .event-form-inline input,
        .event-form-inline select {
            margin-bottom: 12px;
        }
        
        .upcoming-events-list {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .upcoming-events-list::-webkit-scrollbar {
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
