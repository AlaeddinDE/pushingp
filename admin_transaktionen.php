<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

if (!is_admin()) {
    header('Location: dashboard.php');
    exit;
}

// Transaktion zum Bearbeiten laden (wenn edit Parameter)
$edit_transaction = null;
$is_new = false;
if (isset($_GET['edit'])) {
    if ($_GET['edit'] === 'new') {
        $is_new = true;
        $edit_transaction = [
            'id' => 0,
            'typ' => 'EINZAHLUNG',
            'mitglied_id' => null,
            'betrag' => 0,
            'beschreibung' => '',
            'status' => 'gebucht',
            'datum' => date('Y-m-d H:i:s')
        ];
    } else {
        $edit_id = intval($_GET['edit']);
        $edit_result = $conn->query("SELECT * FROM transaktionen WHERE id = $edit_id");
        if ($edit_result && $edit_result->num_rows > 0) {
            $edit_transaction = $edit_result->fetch_assoc();
        }
    }
}

// Alle Transaktionen holen (auch stornierte)
$filter = $_GET['filter'] ?? 'alle';
$where = "";
if ($filter === 'gebucht') $where = "WHERE t.status = 'gebucht'";
elseif ($filter === 'storniert') $where = "WHERE t.status = 'storniert'";

$transaktionen = $conn->query("
    SELECT 
        t.id,
        t.typ,
        t.betrag,
        t.mitglied_id,
        u.name as mitglied_name,
        t.beschreibung,
        t.status,
        t.datum,
        t.erstellt_von,
        creator.name as erstellt_von_name
    FROM transaktionen t
    LEFT JOIN users u ON u.id = t.mitglied_id
    LEFT JOIN users creator ON creator.id = t.erstellt_von
    $where
    ORDER BY t.datum DESC, t.id DESC
    LIMIT 100
");

// Mitglieder für Dropdown
$mitglieder = $conn->query("SELECT id, name FROM users WHERE status='active' ORDER BY name");
$mitglieder_list = [];
while($m = $mitglieder->fetch_assoc()) {
    $mitglieder_list[] = $m;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaktionen verwalten – PUSHING P</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .transaction-row {
            transition: background 0.2s;
        }
        .transaction-row:hover {
            background: var(--bg-tertiary);
        }
        .transaction-row.storniert {
            opacity: 0.5;
            text-decoration: line-through;
        }
        .filter-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }
        .filter-tab {
            padding: 8px 16px;
            border-radius: 6px;
            background: var(--bg-tertiary);
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s;
        }
        .filter-tab.active {
            background: var(--accent);
            color: white;
        }
        .edit-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .edit-modal.active {
            display: flex;
        }
        .modal-content {
            background: var(--bg-primary);
            padding: 32px;
            border-radius: 12px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
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
                <a href="admin_kasse.php" class="nav-item">Admin</a>
                <a href="admin_transaktionen.php" class="nav-item">Transaktionen</a>
                <a href="logout.php" class="nav-item">Logout</a>
            </nav>
        </div>
    </div>

    <div class="container">
        <div class="welcome">
            <h1>📊 Transaktionen verwalten</h1>
            <p class="text-secondary">Alle Transaktionen bearbeiten, löschen und neu erstellen</p>
        </div>

        <?php if ($edit_transaction): ?>
        <!-- Edit Form (direkt sichtbar wenn ?edit=ID) -->
        <div class="section" style="background: var(--bg-tertiary); padding: 24px; border-radius: 12px; margin-bottom: 24px;">
            <h2><?= $is_new ? '➕ Neue Transaktion erstellen' : '✏️ Transaktion #' . $edit_transaction['id'] . ' bearbeiten' ?></h2>
            <form id="directEditForm" style="display: grid; gap: 16px; margin-top: 24px; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
                <input type="hidden" id="direct_edit_id" value="<?= $edit_transaction['id'] ?>">
                <input type="hidden" id="is_new" value="<?= $is_new ? '1' : '0' ?>">
                
                <div class="form-group">
                    <label>Typ</label>
                    <select id="direct_edit_typ" required>
                        <option value="EINZAHLUNG" <?= $edit_transaction['typ'] === 'EINZAHLUNG' ? 'selected' : '' ?>>EINZAHLUNG</option>
                        <option value="AUSZAHLUNG" <?= $edit_transaction['typ'] === 'AUSZAHLUNG' ? 'selected' : '' ?>>AUSZAHLUNG</option>
                        <option value="GRUPPENAKTION_KASSE" <?= $edit_transaction['typ'] === 'GRUPPENAKTION_KASSE' ? 'selected' : '' ?>>GRUPPENAKTION_KASSE</option>
                        <option value="GRUPPENAKTION_ANTEILIG" <?= $edit_transaction['typ'] === 'GRUPPENAKTION_ANTEILIG' ? 'selected' : '' ?>>GRUPPENAKTION_ANTEILIG</option>
                        <option value="SCHADEN" <?= $edit_transaction['typ'] === 'SCHADEN' ? 'selected' : '' ?>>SCHADEN</option>
                        <option value="AUSGLEICH" <?= $edit_transaction['typ'] === 'AUSGLEICH' ? 'selected' : '' ?>>AUSGLEICH</option>
                        <option value="KORREKTUR" <?= $edit_transaction['typ'] === 'KORREKTUR' ? 'selected' : '' ?>>KORREKTUR</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Mitglied</label>
                    <select id="direct_edit_mitglied_id">
                        <option value="">-- Kein Mitglied (System) --</option>
                        <?php foreach($mitglieder_list as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= $edit_transaction['mitglied_id'] == $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Betrag (€)</label>
                    <input type="number" id="direct_edit_betrag" step="0.01" value="<?= $edit_transaction['betrag'] ?>" required>
                </div>

                <div class="form-group">
                    <label>Beschreibung</label>
                    <input type="text" id="direct_edit_beschreibung" value="<?= htmlspecialchars($edit_transaction['beschreibung'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select id="direct_edit_status">
                        <option value="gebucht" <?= $edit_transaction['status'] === 'gebucht' ? 'selected' : '' ?>>gebucht</option>
                        <option value="storniert" <?= $edit_transaction['status'] === 'storniert' ? 'selected' : '' ?>>storniert</option>
                        <option value="gesperrt" <?= $edit_transaction['status'] === 'gesperrt' ? 'selected' : '' ?>>gesperrt</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Datum</label>
                    <input type="datetime-local" id="direct_edit_datum" value="<?= date('Y-m-d\TH:i', strtotime($edit_transaction['datum'])) ?>">
                </div>

                <div style="grid-column: 1 / -1; display: flex; gap: 8px;">
                    <button type="submit" class="btn" style="flex: 1;">💾 Speichern</button>
                    <a href="admin_transaktionen.php" class="btn" style="flex: 1; background: var(--text-tertiary); text-decoration: none; display: flex; align-items: center; justify-content: center;">✖ Abbrechen</a>
                    <button type="button" onclick="loescheTransaktion(<?= $edit_transaction['id'] ?>)" class="btn" style="background: var(--error);">🗑️ Löschen</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <div class="filter-tabs">
            <a href="?filter=alle" class="filter-tab <?= $filter === 'alle' ? 'active' : '' ?>">Alle</a>
            <a href="?filter=gebucht" class="filter-tab <?= $filter === 'gebucht' ? 'active' : '' ?>">Gebucht</a>
            <a href="?filter=storniert" class="filter-tab <?= $filter === 'storniert' ? 'active' : '' ?>">Storniert</a>
        </div>

        <div class="section">
            <div class="section-header">
                <h2>Transaktionen</h2>
                <a href="?edit=new" class="btn">➕ Neue Transaktion</a>
            </div>

            <table class="balance-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Datum</th>
                        <th>Typ</th>
                        <th>Mitglied</th>
                        <th>Betrag</th>
                        <th>Beschreibung</th>
                        <th>Status</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($t = $transaktionen->fetch_assoc()): ?>
                    <tr class="transaction-row <?= $t['status'] ?>">
                        <td><?= $t['id'] ?></td>
                        <td><?= date('d.m.Y H:i', strtotime($t['datum'])) ?></td>
                        <td><span class="badge"><?= htmlspecialchars($t['typ']) ?></span></td>
                        <td><?= htmlspecialchars($t['mitglied_name'] ?? 'System') ?></td>
                        <td class="<?= $t['betrag'] >= 0 ? 'balance-positive' : 'balance-negative' ?>">
                            <?= number_format($t['betrag'], 2, ',', '.') ?> €
                        </td>
                        <td><?= htmlspecialchars($t['beschreibung'] ?? '') ?></td>
                        <td><?= $t['status'] ?></td>
                        <td>
                            <a href="?edit=<?= $t['id'] ?>" class="btn" style="font-size: 0.75rem; padding: 4px 8px; text-decoration: none;">✏️</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>
