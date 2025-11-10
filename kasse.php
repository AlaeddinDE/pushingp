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
        
        /* Mobile Card Layout */
        .mobile-card {
            display: none;
            background: var(--bg-secondary);
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 12px;
            border-left: 4px solid var(--accent);
        }
        
        .mobile-card-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .mobile-card-row:last-child {
            margin-bottom: 0;
        }
        
        .mobile-card-label {
            color: var(--text-secondary);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .mobile-card-value {
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        /* Mobile Optimierungen */
        @media (max-width: 768px) {
            .container {
                padding: 16px !important;
            }
            
            .welcome h1 {
                font-size: 1.5rem !important;
            }
            
            .section {
                margin-bottom: 24px !important;
            }
            
            .section-title {
                font-size: 1.125rem !important;
            }
            
            .stats-grid {
                grid-template-columns: 1fr !important;
                gap: 12px !important;
            }
            
            .stat-card {
                padding: 16px !important;
            }
            
            .stat-value {
                font-size: 1.5rem !important;
            }
            
            /* Verstecke Tabellen auf Mobile */
            .balance-table {
                display: none;
            }
            
            /* Zeige Cards auf Mobile */
            .mobile-card {
                display: block;
            }
            
            .button, .btn {
                width: 100%;
                text-align: center;
                padding: 12px !important;
            }
        }
        
        /* iPhone spezifisch */
        @media (max-width: 430px) {
            body {
                font-size: 14px;
            }
            
            .welcome h1 {
                font-size: 1.375rem !important;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 8px;
            }
            
            .mobile-card {
                padding: 12px;
            }
            
            .mobile-card-value {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="grain"></div>
    
    <div class="header">
        <div class="header-content">
            <a href="https://pushingp.de" class="logo" style="text-decoration: none; color: inherit; cursor: pointer;">PUSHING P</a>
            <nav class="nav">
                <a href="kasse.php" class="nav-item">Kasse</a>
                <a href="events.php" class="nav-item">Events</a>
                <a href="schichten.php" class="nav-item">Schichten</a>
                <a href="chat.php" class="nav-item">Chat</a>
                <?php if ($is_admin): ?>
                    <a href="admin.php" class="nav-item">Admin</a>
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
            </div>
        </div>

        <div class="section">
            <div class="section-header">
                <span>👥</span>
                <h2 class="section-title">Deckungsstatus (10€/Monat)</h2>
            </div>
            
            <!-- Desktop Tabelle -->
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
                    <?php 
                    $payment_overview->data_seek(0); // Reset pointer
                    while($m = $payment_overview->fetch_assoc()): 
                    ?>
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
            
            <!-- Mobile Cards -->
            <div class="mobile-cards-container">
                <?php 
                $payment_overview->data_seek(0); // Reset pointer
                while($m = $payment_overview->fetch_assoc()): 
                    $balance_class = $m['guthaben'] >= 0 ? 'balance-positive' : 'balance-negative';
                ?>
                <div class="mobile-card">
                    <div class="mobile-card-row" style="margin-bottom: 12px;">
                        <div style="font-weight: 700; font-size: 1.125rem;">
                            <?= htmlspecialchars($m['name']) ?>
                        </div>
                        <div style="font-size: 1.5rem;">
                            <?= $m['status_icon'] ?>
                        </div>
                    </div>
                    <div class="mobile-card-row">
                        <div class="mobile-card-label">Guthaben</div>
                        <div class="mobile-card-value <?= $balance_class ?>">
                            <?= number_format($m['guthaben'], 2, ',', '.') ?> €
                        </div>
                    </div>
                    <div class="mobile-card-row">
                        <div class="mobile-card-label">Gedeckt bis</div>
                        <div class="mobile-card-value">
                            <?= date('d.m.Y', strtotime($m['gedeckt_bis'])) ?>
                        </div>
                    </div>
                    <div class="mobile-card-row">
                        <div class="mobile-card-label">Nächste Zahlung</div>
                        <div class="mobile-card-value">
                            <?= date('d.m.Y', strtotime($m['naechste_zahlung_faellig'])) ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <div class="section" style="margin-top: 24px;">
            <div class="section-header">
                <span>📋</span>
                <h2 class="section-title">Letzte Transaktionen</h2>
            </div>
            
            <!-- Desktop Tabelle -->
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
                    <?php 
                    $res2->data_seek(0); // Reset pointer
                    while($t = $res2->fetch_assoc()): 
                    ?>
                    <tr data-transaction-id="<?= $t['id'] ?? 0 ?>">
                        <td><?= $t['datum'] ?></td>
                        <td><?= htmlspecialchars($t['name'] ?? 'System') ?></td>
                        <td><span class="badge"><?= htmlspecialchars($t['typ']) ?></span></td>
                        <td style="text-align: right;" class="<?= $t['typ'] === 'EINZAHLUNG' ? 'balance-positive' : 'balance-negative' ?>">
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
            
            <!-- Mobile Cards -->
            <div class="mobile-cards-container">
                <?php 
                $res2->data_seek(0); // Reset pointer
                while($t = $res2->fetch_assoc()): 
                    $balance_class = $t['typ'] === 'EINZAHLUNG' ? 'balance-positive' : 'balance-negative';
                    $border_color = $t['typ'] === 'EINZAHLUNG' ? 'var(--success)' : 'var(--error)';
                ?>
                <div class="mobile-card" style="border-left-color: <?= $border_color ?>;">
                    <div class="mobile-card-row" style="margin-bottom: 12px;">
                        <div>
                            <div style="font-weight: 700; font-size: 1rem; margin-bottom: 4px;">
                                <?= htmlspecialchars($t['name'] ?? 'System') ?>
                            </div>
                            <div style="color: var(--text-secondary); font-size: 0.75rem;">
                                <?= $t['datum'] ?>
                            </div>
                        </div>
                        <div class="mobile-card-value <?= $balance_class ?>" style="font-size: 1.125rem;">
                            <?= number_format($t['betrag'],2,',','.') ?> €
                        </div>
                    </div>
                    <div class="mobile-card-row">
                        <div class="mobile-card-label">Typ</div>
                        <div class="mobile-card-value">
                            <span class="badge"><?= htmlspecialchars($t['typ']) ?></span>
                        </div>
                    </div>
                    <?php if (!empty($t['beschreibung'])): ?>
                    <div class="mobile-card-row">
                        <div class="mobile-card-label">Beschreibung</div>
                        <div class="mobile-card-value" style="font-size: 0.75rem; color: var(--text-secondary); text-align: right; max-width: 60%;">
                            <?= htmlspecialchars($t['beschreibung']) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($is_admin): ?>
                    <div class="mobile-card-row" style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border);">
                        <a href="admin_transaktionen.php?edit=<?= $t['id'] ?? 0 ?>" class="btn" style="font-size: 0.75rem; padding: 8px 16px; text-decoration: none; width: 100%; text-align: center; display: block;">
                            ✏️ Bearbeiten
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            </div>
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
