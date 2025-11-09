<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

$is_admin = is_admin();

// PayPal Pool Betrag holen
$paypal_result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'paypal_pool_amount'");
$paypal_amount = 0.00;
if ($paypal_result && $row = $paypal_result->fetch_assoc()) {
    $paypal_amount = floatval($row['setting_value']);
}

// Member Payment Overview holen
$payment_overview = $conn->query("SELECT * FROM v_member_payment_overview ORDER BY tage_bis_ablauf ASC");

// Letzte Transaktionen
$res2 = $conn->query("
    SELECT 
        t.id,
        DATE_FORMAT(t.datum,'%d.%m.%Y %H:%i') AS datum, 
        u.name, 
        t.typ, 
        t.betrag, 
        t.beschreibung,
        t.status
    FROM transaktionen t 
    LEFT JOIN users u ON u.id=t.mitglied_id
    WHERE t.status = 'gebucht'
    ORDER BY t.datum DESC 
    LIMIT 25
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
            <h1>💼 Kassenübersicht</h1>
            <p class="text-secondary">Mitgliedersalden und Transaktionen im Überblick</p>
        </div>

        <div class="section">
            <div class="section-header">
                <h2>💰 Kassenstand (PayPal Pool)</h2>
            </div>
            <div class="stats-grid" style="margin-bottom: 32px;">
                <div class="stat-card">
                    <div class="stat-label">Aktueller Kassenstand</div>
                    <div class="stat-value" style="color: var(--accent);"><?php echo number_format($paypal_amount, 2, ',', '.'); ?> €</div>
                    <div class="stat-sublabel">
                        <a href="https://www.paypal.com/pool/9etnO1r4Cl?sr=wccr" target="_blank" style="color: var(--text-secondary); text-decoration: none;">
                            🔗 PayPal Pool öffnen
                        </a>
                    </div>
                </div>
                <?php if ($is_admin): ?>
                <div class="stat-card">
                    <div class="stat-label">Admin-Aktion</div>
                    <button onclick="updatePayPalAmount()" class="button" style="margin-top: 8px;">
                        🔄 Betrag aktualisieren
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="section">
            <div class="section-header">
                <span>👥</span>
                <h2 class="section-title">Deckungsstatus (10€/Monat)</h2>
            </div>
            
            <table class="balance-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Guthaben</th>
                        <th>Gedeckt bis</th>
                        <th>Nächste Zahlung</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($m = $payment_overview->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($m['name']) ?></td>
                        <td style="text-align: right;"><?= number_format($m['guthaben'], 2, ',', '.') ?> €</td>
                        <td><?= date('d.m.Y', strtotime($m['gedeckt_bis'])) ?></td>
                        <td><?= date('d.m.Y', strtotime($m['naechste_zahlung_faellig'])) ?></td>
                        <td style="text-align: center; font-size: 1.5rem;">
                            <?= $m['status_icon'] ?>
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
                        <?php if ($is_admin): ?>
                        <th style="text-align: right;">Aktionen</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php while($t = $res2->fetch_assoc()): ?>
                    <tr data-transaction-id="<?= $t['id'] ?? 0 ?>">
                        <td><?= $t['datum'] ?></td>
                        <td><?= htmlspecialchars($t['name'] ?? 'System') ?></td>
                        <td><span class="badge"><?= htmlspecialchars($t['typ']) ?></span></td>
                        <td style="text-align: right;" class="<?= $t['betrag'] >= 0 ? 'balance-positive' : 'balance-negative' ?>">
                            <?= number_format($t['betrag'],2,',','.') ?> €
                        </td>
                        <td style="color: var(--text-secondary); font-size: 0.875rem;">
                            <?= htmlspecialchars($t['beschreibung'] ?? '') ?>
                        </td>
                        <?php if ($is_admin): ?>
                        <td style="text-align: right;">
                            <a href="admin_transaktionen.php?edit=<?= $t['id'] ?? 0 ?>" class="btn" style="font-size: 0.75rem; padding: 4px 8px; text-decoration: none;">✏️ Bearbeiten</a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($is_admin): ?>
    <script>
    async function editTransaction(id, beschreibung, betrag) {
        const newBeschreibung = prompt('Beschreibung ändern:', beschreibung);
        if (newBeschreibung === null) return;
        
        const newBetrag = prompt('Betrag ändern:', betrag);
        if (newBetrag === null) return;
        
        try {
            const response = await fetch('/api/transaktion_bearbeiten.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    id: id, 
                    beschreibung: newBeschreibung, 
                    betrag: parseFloat(newBetrag) 
                })
            });
            
            const data = await response.json();
            if (data.status === 'success') {
                alert('✅ Transaktion aktualisiert!');
                location.reload();
            } else {
                alert('❌ Fehler: ' + data.error);
            }
        } catch (error) {
            alert('❌ Fehler: ' + error.message);
        }
    }
    
    async function deleteTransaction(id) {
        if (!confirm('Transaktion wirklich löschen (stornieren)?')) return;
        
        try {
            const response = await fetch('/api/transaktion_loeschen.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            
            const data = await response.json();
            if (data.status === 'success') {
                alert('✅ Transaktion storniert!');
                location.reload();
            } else {
                alert('❌ Fehler: ' + data.error);
            }
        } catch (error) {
            alert('❌ Fehler: ' + error.message);
        }
    }
    
    async function updatePayPalAmount() {
        const newAmount = prompt('Neuer Kassenstand (aus PayPal Pool):', '<?php echo number_format($paypal_amount, 2, '.', ''); ?>');
        if (newAmount === null) return;
        
        const amount = parseFloat(newAmount.replace(',', '.'));
        if (isNaN(amount) || amount < 0) {
            alert('Ungültiger Betrag!');
            return;
        }
        
        try {
            const response = await fetch('/api/set_paypal_pool.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ amount })
            });
            
            const data = await response.json();
            if (data.status === 'success') {
                alert('Kassenstand aktualisiert: ' + data.formatted);
                location.reload();
            } else {
                alert('Fehler: ' + data.error);
            }
        } catch (error) {
            alert('Fehler beim Aktualisieren: ' + error.message);
        }
    }
    </script>
    <?php endif; ?>
</body>
</html>
