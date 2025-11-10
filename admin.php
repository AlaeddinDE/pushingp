<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/xp_system.php';
secure_session_start();
require_login();

if (!is_admin()) {
    header('Location: dashboard.php');
    exit;
}

// Statistiken für die Karten
$stats = [];

// Anzahl Benutzer
$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['users'] = $row['total'];
}

// XP System Stats
$result = $conn->query("SELECT COUNT(*) as total FROM xp_history");
if ($result && $row = $result->fetch_assoc()) {
    $stats['xp_transactions'] = $row['total'];
}

$result = $conn->query("SELECT SUM(xp_change) as total FROM xp_history WHERE xp_change > 0");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_xp_awarded'] = $row['total'] ?? 0;
}

$result = $conn->query("SELECT COUNT(*) as total FROM user_badges");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_badges'] = $row['total'];
}

// Anzahl Transaktionen (letzte 30 Tage)
$result = $conn->query("SELECT COUNT(*) as total FROM transaktionen WHERE datum >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
if ($result && $row = $result->fetch_assoc()) {
    $stats['transactions'] = $row['total'];
}

// PayPal Pool
$result = $conn->query("SELECT pool_balance FROM paypal_pool_status ORDER BY last_updated DESC LIMIT 1");
if ($result && $row = $result->fetch_assoc()) {
    $stats['pool'] = floatval($row['pool_balance']);
} else {
    $stats['pool'] = 0.00;
}

// Offene Events
$result = $conn->query("SELECT COUNT(*) as total FROM events WHERE event_status = 'active' AND datum >= CURDATE()");
if ($result && $row = $result->fetch_assoc()) {
    $stats['events'] = $row['total'];
}

// Geplante Schichten (nächste 7 Tage)
$result = $conn->query("SELECT COUNT(*) as total FROM shifts WHERE date >= CURDATE() AND date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
if ($result && $row = $result->fetch_assoc()) {
    $stats['shifts'] = $row['total'];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard – PUSHING P</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        body.admin-page {
            --accent: #7f1010;
            --accent-hover: #650d0d;
        }
        
        .admin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
            margin-top: 32px;
        }
        
        .admin-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 28px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
            overflow: hidden;
        }
        
        .admin-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--accent), var(--accent-hover));
            transform: scaleX(0);
            transition: transform 0.3s;
        }
        
        .admin-card:hover {
            transform: translateY(-4px);
            border-color: var(--accent);
            box-shadow: 0 12px 32px rgba(127, 16, 16, 0.2);
        }
        
        .admin-card:hover::before {
            transform: scaleX(1);
        }
        
        .card-icon {
            font-size: 3rem;
            margin-bottom: 16px;
            display: block;
            filter: grayscale(0.3);
            transition: filter 0.3s;
        }
        
        .admin-card:hover .card-icon {
            filter: grayscale(0);
        }
        
        .card-title {
            font-size: 1.375rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-primary);
        }
        
        .card-description {
            font-size: 0.9375rem;
            color: var(--text-secondary);
            margin-bottom: 16px;
            line-height: 1.5;
        }
        
        .card-stat {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.875rem;
            color: var(--accent);
            font-weight: 600;
        }
        
        .card-stat .number {
            font-size: 1.5rem;
            font-weight: 800;
        }
        
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        
        .quick-stat {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        
        .quick-stat .label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }
        
        .quick-stat .value {
            font-size: 2rem;
            font-weight: 900;
            color: var(--accent);
        }
        
        .section-divider {
            margin: 48px 0 32px;
            text-align: center;
            position: relative;
        }
        
        .section-divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--border);
            z-index: 0;
        }
        
        .section-divider span {
            background: var(--bg-primary);
            padding: 0 20px;
            position: relative;
            z-index: 1;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
    </style>
</head>
<body class="admin-page">
    <div class="grain"></div>
    
    <div class="header">
        <div class="header-content">
            <a href="https://pushingp.de" class="logo" style="text-decoration: none; color: inherit; cursor: pointer;">
                PUSHING P
                <span style="color: #7f1010; margin-left: 12px; font-weight: 700; font-size: 0.9rem; background: rgba(127, 16, 16, 0.1); padding: 4px 12px; border-radius: 6px; border: 1px solid rgba(127, 16, 16, 0.3);">Admin</span>
            </a>
            <nav class="nav">
                <a href="kasse.php" class="nav-item">Kasse</a>
                <a href="events.php" class="nav-item">Events</a>
                <a href="schichten.php" class="nav-item">Schichten</a>
                <a href="chat.php" class="nav-item">Chat</a>
                <a href="admin.php" class="nav-item active">Admin</a>
                <a href="settings.php" class="nav-item">Settings</a>
                <a href="logout.php" class="nav-item">Logout</a>
            </nav>
        </div>
    </div>

    <div class="container">
        <div class="welcome">
            <h1>⚙️ Admin Dashboard</h1>
            <p class="text-secondary">Zentrale Verwaltung aller Admin-Funktionen</p>
        </div>

        <!-- Quick Stats -->
        <div class="quick-stats">
            <div class="quick-stat">
                <div class="label">Aktive Mitglieder</div>
                <div class="value"><?= $stats['users'] ?? 0 ?></div>
            </div>
            <div class="quick-stat">
                <div class="label">PayPal Pool</div>
                <div class="value">€<?= number_format($stats['pool'], 0) ?></div>
            </div>
            <div class="quick-stat">
                <div class="label">Events (bevorstehend)</div>
                <div class="value"><?= $stats['events'] ?? 0 ?></div>
            </div>
            <div class="quick-stat">
                <div class="label">Schichten (7 Tage)</div>
                <div class="value"><?= $stats['shifts'] ?? 0 ?></div>
            </div>
        </div>

        <div class="section-divider">
            <span>Verwaltung</span>
        </div>

        <!-- Admin Cards Grid -->
        <div class="admin-grid">
            <!-- Kassen-Verwaltung -->
            <a href="admin_kasse.php" class="admin-card">
                <span class="card-icon">💰</span>
                <h3 class="card-title">Kassen-Verwaltung</h3>
                <p class="card-description">
                    Transaktionen buchen, bearbeiten und löschen. PayPal Pool verwalten.
                </p>
                <div class="card-stat">
                    <span class="number"><?= $stats['transactions'] ?? 0 ?></span>
                    <span>Transaktionen (30T)</span>
                </div>
            </a>

            <!-- Member-Verwaltung -->
            <a href="admin_members.php" class="admin-card">
                <span class="card-icon">👥</span>
                <h3 class="card-title">Member-Verwaltung</h3>
                <p class="card-description">
                    Benutzer bearbeiten, PINs zurücksetzen, Rollen und Status ändern.
                </p>
                <div class="card-stat">
                    <span class="number"><?= $stats['users'] ?? 0 ?></span>
                    <span>Mitglieder</span>
                </div>
            </a>

            <!-- Transaktionen -->
            <a href="admin_transaktionen.php" class="admin-card">
                <span class="card-icon">📊</span>
                <h3 class="card-title">Transaktionen</h3>
                <p class="card-description">
                    Alle Transaktionen einsehen, filtern, bearbeiten und exportieren.
                </p>
                <div class="card-stat">
                    <span>Vollständige Historie</span>
                </div>
            </a>

            <!-- Events verwalten -->
            <a href="event_manager.php" class="admin-card">
                <span class="card-icon">🎉</span>
                <h3 class="card-title">Event Manager</h3>
                <p class="card-description">
                    Alle Events verwalten, Status ändern und löschen.
                </p>
                <div class="card-stat">
                    <span class="number"><?= $stats['events'] ?? 0 ?></span>
                    <span>Aktive Events</span>
                </div>
            </a>

            <!-- XP System Admin -->
            <a href="admin_xp.php" class="admin-card">
                <span class="card-icon">🎮</span>
                <h3 class="card-title">XP System Admin</h3>
                <p class="card-description">
                    Leveling-System verwalten, XP vergeben, Badges verwalten und Levels konfigurieren.
                </p>
                <div class="card-stat">
                    <span class="number"><?= number_format($stats['total_xp_awarded'] ?? 0) ?></span>
                    <span>XP vergeben</span>
                </div>
                <div class="card-stat" style="margin-top: 8px; opacity: 0.7;">
                    <span><?= $stats['total_badges'] ?? 0 ?> Badges · <?= $stats['xp_transactions'] ?? 0 ?> Transaktionen</span>
                </div>
            </a>

            <!-- Schichten -->
            <a href="schichten.php" class="admin-card">
                <span class="card-icon">📅</span>
                <h3 class="card-title">Schichten</h3>
                <p class="card-description">
                    Schichtpläne erstellen, bearbeiten und Crew-Verfügbarkeit prüfen.
                </p>
                <div class="card-stat">
                    <span class="number"><?= $stats['shifts'] ?? 0 ?></span>
                    <span>Geplant (7T)</span>
                </div>
            </a>

            <!-- System-Einstellungen -->
            <a href="settings.php" class="admin-card">
                <span class="card-icon">⚙️</span>
                <h3 class="card-title">System-Einstellungen</h3>
                <p class="card-description">
                    Globale Einstellungen, Kassen-Parameter und System-Konfiguration.
                </p>
                <div class="card-stat">
                    <span>Konfiguration</span>
                </div>
            </a>
        </div>

        <div class="section-divider" style="margin-top: 64px;">
            <span>Tools & Berichte</span>
        </div>

        <div class="admin-grid">
            <!-- Monatliche Abbuchung -->
            <a href="api/v2/process_monthly_fees.php" class="admin-card" target="_blank">
                <span class="card-icon">💳</span>
                <h3 class="card-title">Monatsbeitrag abbuchen</h3>
                <p class="card-description">
                    Manuelle Ausführung der monatlichen 10€ Abbuchung für alle Mitglieder.
                </p>
                <div class="card-stat">
                    <span>Manueller Trigger</span>
                </div>
            </a>

            <!-- Kassen-Chart API -->
            <a href="api/v2/get_kasse_chart.php?days=30" class="admin-card" target="_blank">
                <span class="card-icon">📈</span>
                <h3 class="card-title">Kassen-Chart Daten</h3>
                <p class="card-description">
                    API-Daten für 30-Tage Kassenverlauf (JSON Format).
                </p>
                <div class="card-stat">
                    <span>API Endpoint</span>
                </div>
            </a>

            <!-- Datenbank-Backup (Platzhalter) -->
            <div class="admin-card" style="opacity: 0.5; cursor: not-allowed;">
                <span class="card-icon">💾</span>
                <h3 class="card-title">Datenbank-Backup</h3>
                <p class="card-description">
                    Manuelles Backup der Datenbank erstellen und herunterladen.
                </p>
                <div class="card-stat">
                    <span style="color: var(--text-secondary);">Bald verfügbar</span>
                </div>
            </div>
        </div>

        <div style="margin-top: 64px; padding: 24px; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; text-align: center;">
            <p style="color: var(--text-secondary); font-size: 0.875rem;">
                <strong>Hinweis:</strong> Alle Admin-Aktionen werden geloggt. Bei Problemen wende dich an den System-Administrator.
            </p>
        </div>
    </div>

    <script>
        // Bestätigung für kritische Aktionen
        document.querySelectorAll('a[href*="process_monthly_fees"]').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!confirm('Monatsbeitrag jetzt für alle Mitglieder abbuchen?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
