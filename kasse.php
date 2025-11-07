<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

$is_admin = is_admin();

$res1 = $conn->query("
  SELECT m.name, ROUND(SUM(t.betrag),2) AS saldo
  FROM transaktionen t JOIN mitglieder m ON m.id=t.mitglied_id
  GROUP BY m.name ORDER BY saldo DESC
");
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasse – PUSHING P</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .balance-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .balance-table thead th {
            background: var(--bg-tertiary);
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border);
        }
        
        .balance-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background 0.2s ease;
        }
        
        .balance-table tbody tr:hover {
            background: var(--bg-tertiary);
        }
        
        .balance-table tbody td {
            padding: 14px 16px;
        }
        
        .balance-positive {
            color: var(--success);
            font-weight: 600;
        }
        
        .balance-negative {
            color: var(--error);
            font-weight: 600;
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
            <h1>💼 Kassenübersicht</h1>
            <p class="text-secondary">Mitgliedersalden und Transaktionen im Überblick</p>
        </div>

        <div class="section">
            <div class="section-header">
                <span>👥</span>
                <h2 class="section-title">Mitgliedersalden</h2>
            </div>
            
            <table class="balance-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th style="text-align: right;">Saldo (€)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($r=$res1->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['name']) ?></td>
                        <td style="text-align: right;" class="<?= $r['saldo'] >= 0 ? 'balance-positive' : 'balance-negative' ?>">
                            <?= number_format($r['saldo'],2,',','.') ?> €
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="section" style="margin-top: 24px;">
            <div class="section-header">
                <span>📋</span>
                <h2 class="section-title">Letzte Transaktionen</h2>
            </div>
            
            <table class="balance-table">
                <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Name</th>
                        <th>Typ</th>
                        <th style="text-align: right;">Betrag (€)</th>
                        <th>Beschreibung</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $res2=$conn->query("SELECT DATE_FORMAT(t.datum,'%d.%m.%Y') AS datum, m.name, t.typ, t.betrag, t.beschreibung
                                        FROM transaktionen t JOIN mitglieder m ON m.id=t.mitglied_id
                                        ORDER BY t.datum DESC LIMIT 25");
                    while($t=$res2->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?= $t['datum'] ?></td>
                        <td><?= htmlspecialchars($t['name']) ?></td>
                        <td><span class="badge"><?= htmlspecialchars($t['typ']) ?></span></td>
                        <td style="text-align: right;" class="<?= $t['betrag'] >= 0 ? 'balance-positive' : 'balance-negative' ?>">
                            <?= number_format($t['betrag'],2,',','.') ?> €
                        </td>
                        <td style="color: var(--text-secondary); font-size: 0.875rem;">
                            <?= htmlspecialchars($t['beschreibung']) ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
