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
        .transaction-row.selected {
            background: rgba(124, 58, 237, 0.2) !important;
            border-left: 4px solid var(--accent);
        }
        .bulk-actions-bar {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--bg-primary);
            border: 2px solid var(--accent);
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
            display: none;
            align-items: center;
            gap: 16px;
            z-index: 999;
            animation: slideInBottom 0.3s;
        }
        .checkbox-cell {
            width: 40px;
            text-align: center;
        }
        input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--accent);
        }
    </style>
</head>
<body>
    <div class="grain"></div>
    
    <div class="header">
        <div class="header-content">
            <a href="https://pushingp.de" class="logo" style="text-decoration: none; color: inherit;">PUSHING P <span style="color: #7f1010; margin-left: 12px; font-weight: 700; font-size: 0.9rem; background: rgba(127, 16, 16, 0.1); padding: 4px 12px; border-radius: 6px;">Admin</span></a>
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
                    
                    <?php if ($edit_transaction['status'] === 'storniert'): ?>
                        <button type="button" onclick="hardDeleteTransaction(<?= $edit_transaction['id'] ?>)" class="btn" style="background: #7f1d1d; border: 1px solid #ef4444;">🔥 Endgültig Löschen</button>
                    <?php else: ?>
                        <button type="button" onclick="loescheTransaktion(<?= $edit_transaction['id'] ?>)" class="btn" style="background: var(--error);">🗑️ Stornieren</button>
                    <?php endif; ?>
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
                        <th class="checkbox-cell"><input type="checkbox" id="selectAll" title="Alle auswählen"></th>
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
                <tbody id="transactionTableBody">
                    <?php while($t = $transaktionen->fetch_assoc()): ?>
                    <tr class="transaction-row <?= $t['status'] ?>" data-transaction-id="<?= $t['id'] ?>">
                        <td class="checkbox-cell"><input type="checkbox" class="transaction-checkbox" value="<?= $t['id'] ?>"></td>
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

        <!-- Bulk Actions Bar -->
        <div id="bulkActionsBar" class="bulk-actions-bar">
            <span style="font-weight: 600; color: var(--text-primary);">
                <span id="selectedCount">0</span> ausgewählt
            </span>
            <button onclick="bulkDelete()" class="btn" style="background: var(--error); padding: 8px 16px;">
                🗑️ Löschen
            </button>
            <button onclick="clearSelection()" class="btn" style="background: var(--text-tertiary); padding: 8px 16px;">
                ✖ Abbrechen
            </button>
        </div>
    </div>

    <script>
    // Bulk Selection System with Ctrl/Shift Support
    let selectedTransactions = new Set();
    let lastSelectedIndex = -1;
    
    function updateBulkBar() {
        const count = selectedTransactions.size;
        const bar = document.getElementById('bulkActionsBar');
        const countDisplay = document.getElementById('selectedCount');
        
        if (count > 0) {
            bar.style.display = 'flex';
            countDisplay.textContent = count;
        } else {
            bar.style.display = 'none';
        }
        
        // Update row highlighting
        document.querySelectorAll('.transaction-row').forEach(row => {
            const id = parseInt(row.dataset.transactionId);
            if (selectedTransactions.has(id)) {
                row.classList.add('selected');
            } else {
                row.classList.remove('selected');
            }
        });
    }
    
    // Select All checkbox
    document.getElementById('selectAll')?.addEventListener('change', (e) => {
        const checkboxes = document.querySelectorAll('.transaction-checkbox');
        selectedTransactions.clear();
        
        checkboxes.forEach(cb => {
            cb.checked = e.target.checked;
            if (e.target.checked) {
                selectedTransactions.add(parseInt(cb.value));
            }
        });
        updateBulkBar();
    });
    
    // Individual checkbox with Ctrl/Shift support
    document.querySelectorAll('.transaction-checkbox').forEach((cb, index) => {
        cb.addEventListener('click', (e) => {
            const currentId = parseInt(e.target.value);
            const checkboxes = Array.from(document.querySelectorAll('.transaction-checkbox'));
            
            if (e.shiftKey && lastSelectedIndex !== -1) {
                // Shift-Click: Select range
                e.preventDefault();
                const start = Math.min(lastSelectedIndex, index);
                const end = Math.max(lastSelectedIndex, index);
                
                for (let i = start; i <= end; i++) {
                    checkboxes[i].checked = true;
                    selectedTransactions.add(parseInt(checkboxes[i].value));
                }
            } else if (e.ctrlKey || e.metaKey) {
                // Ctrl-Click: Toggle individual
                if (e.target.checked) {
                    selectedTransactions.add(currentId);
                } else {
                    selectedTransactions.delete(currentId);
                }
            } else {
                // Normal click: Clear others and select this one
                if (!e.target.checked) {
                    selectedTransactions.delete(currentId);
                } else {
                    selectedTransactions.add(currentId);
                }
            }
            
            lastSelectedIndex = index;
            updateBulkBar();
            
            // Update selectAll checkbox state
            const allChecked = checkboxes.every(c => c.checked);
            document.getElementById('selectAll').checked = allChecked;
        });
    });
    
    // Row click support (with Ctrl/Shift)
    document.querySelectorAll('.transaction-row').forEach((row, index) => {
        row.addEventListener('click', (e) => {
            // Skip if clicking on checkbox, button, or link
            if (e.target.type === 'checkbox' || e.target.tagName === 'A' || e.target.tagName === 'BUTTON' || e.target.closest('a, button')) {
                return;
            }
            
            const checkbox = row.querySelector('.transaction-checkbox');
            const checkboxes = Array.from(document.querySelectorAll('.transaction-checkbox'));
            
            if (e.shiftKey && lastSelectedIndex !== -1) {
                // Shift-Click: Select range
                e.preventDefault();
                const start = Math.min(lastSelectedIndex, index);
                const end = Math.max(lastSelectedIndex, index);
                
                for (let i = start; i <= end; i++) {
                    checkboxes[i].checked = true;
                    selectedTransactions.add(parseInt(checkboxes[i].value));
                }
            } else if (e.ctrlKey || e.metaKey) {
                // Ctrl-Click: Toggle
                checkbox.checked = !checkbox.checked;
                const id = parseInt(checkbox.value);
                if (checkbox.checked) {
                    selectedTransactions.add(id);
                } else {
                    selectedTransactions.delete(id);
                }
            } else {
                // Normal click: Toggle only this one
                checkbox.checked = !checkbox.checked;
                const id = parseInt(checkbox.value);
                if (checkbox.checked) {
                    selectedTransactions.add(id);
                } else {
                    selectedTransactions.delete(id);
                }
            }
            
            lastSelectedIndex = index;
            updateBulkBar();
        });
    });
    
    function clearSelection() {
        selectedTransactions.clear();
        document.querySelectorAll('.transaction-checkbox').forEach(cb => cb.checked = false);
        document.getElementById('selectAll').checked = false;
        lastSelectedIndex = -1;
        updateBulkBar();
    }
    
    function bulkDelete() {
        const count = selectedTransactions.size;
        
        if (count === 0) {
            showError('Keine Transaktionen ausgewählt');
            return;
        }
        
        showModal(
            '🗑️ ACHTUNG: Massen-Stornierung',
            `${count} Transaktionen werden ENDGÜLTIG auf "storniert" gesetzt. Diese Aktion kann nicht rückgängig gemacht werden!`,
            async () => {
                const ids = Array.from(selectedTransactions);
                
                try {
                    const response = await fetch('/api/bulk_delete_transactions.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ids })
                    });
                    
                    const result = await response.json();
                    if (result.status === 'success') {
                        showSuccess(`${count} Transaktionen storniert!`);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showError(result.error || 'Fehler beim Stornieren');
                    }
                } catch (error) {
                    showError(error.message);
                }
            },
            'STORNIEREN',
            'Abbrechen'
        );
    }
    
    // Custom Modal System
    function showModal(title, message, onConfirm, confirmText = 'Bestätigen', cancelText = 'Abbrechen') {
        const modal = document.createElement('div');
        modal.style.cssText = 'position: fixed; inset: 0; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; z-index: 9999; animation: fadeIn 0.2s;';
        
        modal.innerHTML = `
            <div style="background: var(--bg-primary); padding: 32px; border-radius: 16px; max-width: 500px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.5); animation: slideUp 0.3s;">
                <h3 style="margin: 0 0 16px 0; font-size: 1.5rem; color: var(--text-primary);">${title}</h3>
                <p style="margin: 0 0 24px 0; color: var(--text-secondary); line-height: 1.6;">${message}</p>
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button class="btn-cancel" style="padding: 10px 24px; background: var(--bg-tertiary); color: var(--text-primary); border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">${cancelText}</button>
                    <button class="btn-confirm" style="padding: 10px 24px; background: var(--accent); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">${confirmText}</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        modal.querySelector('.btn-cancel').onclick = () => {
            modal.style.animation = 'fadeOut 0.2s';
            setTimeout(() => modal.remove(), 200);
        };
        
        modal.querySelector('.btn-confirm').onclick = () => {
            modal.style.animation = 'fadeOut 0.2s';
            setTimeout(() => modal.remove(), 200);
            onConfirm();
        };
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.style.animation = 'fadeOut 0.2s';
                setTimeout(() => modal.remove(), 200);
            }
        };
    }
    
    function showSuccess(message) {
        const toast = document.createElement('div');
        toast.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #10b981; color: white; padding: 16px 24px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 10000; animation: slideInRight 0.3s;';
        toast.innerHTML = `<strong>✅ ${message}</strong>`;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.animation = 'fadeOut 0.3s';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    function showError(message) {
        const toast = document.createElement('div');
        toast.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #ef4444; color: white; padding: 16px 24px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 10000; animation: slideInRight 0.3s;';
        toast.innerHTML = `<strong>❌ ${message}</strong>`;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.animation = 'fadeOut 0.3s';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // Direct Edit Form Handler
    <?php if ($edit_transaction): ?>
    document.getElementById('directEditForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const isNew = document.getElementById('is_new').value === '1';
        const data = {
            id: isNew ? null : parseInt(document.getElementById('direct_edit_id').value),
            typ: document.getElementById('direct_edit_typ').value,
            mitglied_id: document.getElementById('direct_edit_mitglied_id').value || null,
            betrag: parseFloat(document.getElementById('direct_edit_betrag').value),
            beschreibung: document.getElementById('direct_edit_beschreibung').value,
            status: document.getElementById('direct_edit_status').value,
            datum: document.getElementById('direct_edit_datum').value
        };

        const endpoint = isNew ? '/api/transaktion_erstellen.php' : '/api/transaktion_vollstaendig_bearbeiten.php';

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            if (result.status === 'success') {
                showSuccess('Transaktion ' + (isNew ? 'erstellt' : 'gespeichert') + '!');
                setTimeout(() => window.location.href = 'admin_transaktionen.php', 1000);
            } else {
                showError(result.error || 'Fehler beim Speichern');
            }
        } catch (error) {
            showError(error.message);
        }
    });
    <?php endif; ?>

    async function loescheTransaktion(id) {
        showModal(
            '🗑️ Transaktion stornieren',
            'Diese Transaktion wird auf "storniert" gesetzt und aus der Berechnung entfernt. XP werden ebenfalls entfernt. Fortfahren?',
            async () => {
                try {
                    const response = await fetch('/api/transaktion_loeschen.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id })
                    });

                    const result = await response.json();
                    if (result.status === 'success') {
                        showSuccess('Transaktion storniert!');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showError(result.error || 'Fehler beim Stornieren');
                    }
                } catch (error) {
                    showError(error.message);
                }
            },
            'Stornieren',
            'Abbrechen'
        );
    }

    async function hardDeleteTransaction(id) {
        showModal(
            '🔥 Endgültig Löschen',
            'Diese Transaktion wird KOMPLETT aus der Datenbank entfernt. Dies kann NICHT rückgängig gemacht werden! XP werden ebenfalls entfernt.',
            async () => {
                try {
                    const response = await fetch('/api/transaktion_hard_delete.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id })
                    });

                    const result = await response.json();
                    if (result.status === 'success') {
                        showSuccess('Transaktion endgültig gelöscht!');
                        setTimeout(() => window.location.href = 'admin_transaktionen.php', 1000);
                    } else {
                        showError(result.error || 'Fehler beim Löschen');
                    }
                } catch (error) {
                    showError(error.message);
                }
            },
            'LÖSCHEN',
            'Abbrechen'
        );
    }
    </script>

    <style>
    .status-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .status-gebucht {
        background: #10b981;
        color: white;
    }
    
    .status-storniert {
        background: #ef4444;
        color: white;
    }
    
    .transaction-row.storniert {
        opacity: 0.5;
        text-decoration: line-through;
    }
    
    @keyframes slideInBottom {
        from { transform: translateY(100%); }
        to { transform: translateY(0); }
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }
    
    @keyframes slideUp {
        from { transform: translateY(30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    
    @keyframes slideInRight {
        from { transform: translateX(400px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    </style>

</body>
</html>
