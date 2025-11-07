<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

if (!is_admin()) {
    header('Location: dashboard.php');
    exit;
}

$mitglieder=$conn->query("SELECT id,name FROM mitglieder ORDER BY name ASC");
$mitglieder_list = [];
while($m=$mitglieder->fetch_assoc()) {
    $mitglieder_list[] = $m;
}

// Stats for admin dashboard
$stats = [];
$result = $conn->query("SELECT COUNT(*) as cnt FROM mitglieder");
if ($result && $row = $result->fetch_assoc()) $stats['members'] = $row['cnt'];
$result = $conn->query("SELECT COUNT(*) as cnt FROM transaktionen WHERE MONTH(datum) = MONTH(NOW())");
if ($result && $row = $result->fetch_assoc()) $stats['transactions'] = $row['cnt'];
$result = $conn->query("SELECT SUM(betrag) as sum FROM transaktionen");
if ($result && $row = $result->fetch_assoc()) $stats['total'] = floatval($row['sum']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin-Panel – PUSHING P</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .admin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
            margin-top: 24px;
        }
        
        .admin-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }
        
        .admin-stat {
            background: linear-gradient(135deg, var(--bg-secondary), var(--bg-tertiary));
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.6s ease;
        }
        
        .admin-stat::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(88,101,242,0.1) 0%, transparent 70%);
            animation: pulse 3s ease-in-out infinite;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--accent), #7c89ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .form-grid {
            display: grid;
            gap: 16px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        @media (max-width: 768px) {
            .admin-grid { grid-template-columns: 1fr; }
            .admin-stats { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
        }
        
        .success-message {
            padding: 16px;
            background: rgba(59, 165, 93, 0.1);
            border: 1px solid var(--success);
            border-radius: 12px;
            color: var(--success);
            margin-bottom: 24px;
            animation: slideIn 0.5s ease;
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
                <a href="admin_kasse.php" class="nav-item">Admin</a>
                <a href="settings.php" class="nav-item">Settings</a>
                <a href="logout.php" class="nav-item">Logout</a>
            </nav>
        </div>
    </div>

    <div class="container">
        <div class="welcome">
            <h1>🛠️ Admin-Panel</h1>
            <p class="text-secondary">Kassenverwaltung und Transaktionen</p>
        </div>

        <div class="admin-stats">
            <div class="admin-stat">
                <div class="stat-value"><?= $stats['members'] ?? 0 ?></div>
                <div class="stat-label">👥 Mitglieder</div>
            </div>
            <div class="admin-stat">
                <div class="stat-value"><?= $stats['transactions'] ?? 0 ?></div>
                <div class="stat-label">📊 Transaktionen (Monat)</div>
            </div>
            <div class="admin-stat">
                <div class="stat-value"><?= number_format($stats['total'] ?? 0, 0) ?>€</div>
                <div class="stat-label">💰 Gesamtvolumen</div>
            </div>
        </div>

        <div class="admin-grid">
            <div class="section">
                <div class="section-header">
                    <span>💸</span>
                    <h2 class="section-title">Einzahlung hinzufügen</h2>
                </div>
                
                <form method="post" action="api/add_payment.php" class="form-grid">
                    <div class="form-group">
                        <label>Mitglied auswählen</label>
                        <select name="mitglied_id" required>
                            <option value="">-- Mitglied wählen --</option>
                            <?php foreach($mitglieder_list as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Betrag (€)</label>
                        <input type="number" name="amount" step="0.01" placeholder="0.00" required>
                    </div>
                    
                    <button type="submit" class="btn">💰 Einzahlung buchen</button>
                </form>
            </div>

            <div class="section">
                <div class="section-header">
                    <span>💳</span>
                    <h2 class="section-title">Schaden / Ausgabe erfassen</h2>
                </div>
                
                <form method="post" action="api/add_damage.php" class="form-grid">
                    <div class="form-group">
                        <label>Mitglied auswählen</label>
                        <select name="mitglied_id" required>
                            <option value="">-- Mitglied wählen --</option>
                            <?php foreach($mitglieder_list as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Betrag (€)</label>
                        <input type="number" name="amount" step="0.01" placeholder="0.00" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Notiz / Beschreibung</label>
                        <input type="text" name="note" placeholder="z.B. Equipment-Schaden">
                    </div>
                    
                    <button type="submit" class="btn" style="background: var(--warning);">📝 Ausgabe buchen</button>
                </form>
            </div>
        </div>
        
        <div class="section" style="margin-top: 32px;">
            <div class="section-header">
                <span>ℹ️</span>
                <h2 class="section-title">Quick-Tipps</h2>
            </div>
            <div style="display: grid; gap: 12px; font-size: 0.875rem; color: var(--text-secondary);">
                <div style="padding: 12px; background: var(--bg-tertiary); border-radius: 8px;">
                    💡 <strong>Einzahlungen</strong> werden als positiver Betrag auf das Mitgliedskonto gebucht
                </div>
                <div style="padding: 12px; background: var(--bg-tertiary); border-radius: 8px;">
                    💡 <strong>Schäden/Ausgaben</strong> werden als negativer Betrag vom Konto abgezogen
                </div>
                <div style="padding: 12px; background: var(--bg-tertiary); border-radius: 8px;">
                    💡 Alle Transaktionen sind in der <a href="kasse.php" style="color: var(--accent);">Kassenübersicht</a> einsehbar
                </div>
            </div>
        </div>
    </div>
</body>
</html>
