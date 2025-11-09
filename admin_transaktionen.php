<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

if (!is_admin()) {
    header('Location: dashboard.php');
    exit;
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

        <div class="filter-tabs">
            <a href="?filter=alle" class="filter-tab <?= $filter === 'alle' ? 'active' : '' ?>">Alle</a>
            <a href="?filter=gebucht" class="filter-tab <?= $filter === 'gebucht' ? 'active' : '' ?>">Gebucht</a>
            <a href="?filter=storniert" class="filter-tab <?= $filter === 'storniert' ? 'active' : '' ?>">Storniert</a>
        </div>

        <div class="section">
            <div class="section-header">
                <h2>Transaktionen</h2>
                <button onclick="openCreateModal()" class="btn">➕ Neue Transaktion</button>
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
                            <button onclick='openEditModal(<?= json_encode($t) ?>)' class="btn" style="font-size: 0.75rem; padding: 4px 8px;">✏️</button>
                            <?php if ($t['status'] === 'gebucht'): ?>
                            <button onclick="storniereTransaktion(<?= $t['id'] ?>)" class="btn" style="font-size: 0.75rem; padding: 4px 8px; background: var(--warning);">🚫</button>
                            <?php endif; ?>
                            <button onclick="loescheTransaktion(<?= $t['id'] ?>)" class="btn" style="font-size: 0.75rem; padding: 4px 8px; background: var(--error);">🗑️</button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="edit-modal">
        <div class="modal-content">
            <h2 id="modalTitle">Transaktion bearbeiten</h2>
            <form id="editForm" style="display: grid; gap: 16px; margin-top: 24px;">
                <input type="hidden" id="edit_id">
                
                <div class="form-group">
                    <label>Typ</label>
                    <select id="edit_typ" required>
                        <option value="EINZAHLUNG">EINZAHLUNG</option>
                        <option value="AUSZAHLUNG">AUSZAHLUNG</option>
                        <option value="GRUPPENAKTION_KASSE">GRUPPENAKTION_KASSE</option>
                        <option value="GRUPPENAKTION_ANTEILIG">GRUPPENAKTION_ANTEILIG</option>
                        <option value="SCHADEN">SCHADEN</option>
                        <option value="AUSGLEICH">AUSGLEICH</option>
                        <option value="KORREKTUR">KORREKTUR</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Mitglied</label>
                    <select id="edit_mitglied_id">
                        <option value="">-- Kein Mitglied (System) --</option>
                        <?php foreach($mitglieder_list as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Betrag (€)</label>
                    <input type="number" id="edit_betrag" step="0.01" required>
                </div>

                <div class="form-group">
                    <label>Beschreibung</label>
                    <input type="text" id="edit_beschreibung">
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select id="edit_status">
                        <option value="gebucht">gebucht</option>
                        <option value="storniert">storniert</option>
                        <option value="gesperrt">gesperrt</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Datum</label>
                    <input type="datetime-local" id="edit_datum">
                </div>

                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn" style="flex: 1;">💾 Speichern</button>
                    <button type="button" onclick="closeEditModal()" class="btn" style="flex: 1; background: var(--text-tertiary);">✖ Abbrechen</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    let isCreateMode = false;

    function openCreateModal() {
        isCreateMode = true;
        document.getElementById('modalTitle').textContent = 'Neue Transaktion erstellen';
        document.getElementById('edit_id').value = '';
        document.getElementById('edit_typ').value = 'EINZAHLUNG';
        document.getElementById('edit_mitglied_id').value = '';
        document.getElementById('edit_betrag').value = '';
        document.getElementById('edit_beschreibung').value = '';
        document.getElementById('edit_status').value = 'gebucht';
        document.getElementById('edit_datum').value = new Date().toISOString().slice(0, 16);
        document.getElementById('editModal').classList.add('active');
    }

    function openEditModal(transaction) {
        isCreateMode = false;
        document.getElementById('modalTitle').textContent = 'Transaktion #' + transaction.id + ' bearbeiten';
        document.getElementById('edit_id').value = transaction.id;
        document.getElementById('edit_typ').value = transaction.typ;
        document.getElementById('edit_mitglied_id').value = transaction.mitglied_id || '';
        document.getElementById('edit_betrag').value = transaction.betrag;
        document.getElementById('edit_beschreibung').value = transaction.beschreibung || '';
        document.getElementById('edit_status').value = transaction.status;
        document.getElementById('edit_datum').value = new Date(transaction.datum).toISOString().slice(0, 16);
        document.getElementById('editModal').classList.add('active');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.remove('active');
    }

    document.getElementById('editForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const data = {
            id: document.getElementById('edit_id').value || null,
            typ: document.getElementById('edit_typ').value,
            mitglied_id: document.getElementById('edit_mitglied_id').value || null,
            betrag: parseFloat(document.getElementById('edit_betrag').value),
            beschreibung: document.getElementById('edit_beschreibung').value,
            status: document.getElementById('edit_status').value,
            datum: document.getElementById('edit_datum').value
        };

        const endpoint = isCreateMode ? '/api/transaktion_erstellen.php' : '/api/transaktion_vollstaendig_bearbeiten.php';

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            if (result.status === 'success') {
                alert('✅ Transaktion ' + (isCreateMode ? 'erstellt' : 'gespeichert') + '!');
                location.reload();
            } else {
                alert('❌ Fehler: ' + result.error);
            }
        } catch (error) {
            alert('❌ Fehler: ' + error.message);
        }
    });

    async function storniereTransaktion(id) {
        if (!confirm('Transaktion wirklich stornieren?')) return;
        
        try {
            const response = await fetch('/api/transaktion_loeschen.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });

            const result = await response.json();
            if (result.status === 'success') {
                alert('✅ Transaktion storniert!');
                location.reload();
            } else {
                alert('❌ Fehler: ' + result.error);
            }
        } catch (error) {
            alert('❌ Fehler: ' + error.message);
        }
    }

    async function loescheTransaktion(id) {
        if (!confirm('Transaktion ENDGÜLTIG löschen? (Kann nicht rückgängig gemacht werden!)')) return;
        
        try {
            const response = await fetch('/api/transaktion_vollstaendig_loeschen.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });

            const result = await response.json();
            if (result.status === 'success') {
                alert('✅ Transaktion gelöscht!');
                location.reload();
            } else {
                alert('❌ Fehler: ' + result.error);
            }
        } catch (error) {
            alert('❌ Fehler: ' + error.message);
        }
    }

    // ESC to close modal
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeEditModal();
    });
    </script>
</body>
</html>
