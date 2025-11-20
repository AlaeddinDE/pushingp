<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

if (!is_admin()) {
    header('Location: dashboard.php');
    exit;
}

$mitglieder=$conn->query("SELECT id,name FROM users WHERE status='active' ORDER BY name ASC");
$mitglieder_list = [];
while($m=$mitglieder->fetch_assoc()) {
    $mitglieder_list[] = $m;
}

// Stats for admin dashboard
$stats = [];
$result = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE status='active'");
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
<body class="admin-page">
    <div class="grain"></div>
    
    <div class="header">
        <div class="header-content">
            <a href="https://pushingp.de" class="logo" style="text-decoration: none; color: inherit; cursor: pointer;">PUSHING P <span style="color: #7f1010; margin-left: 12px; font-weight: 700; font-size: 0.9rem; background: rgba(127, 16, 16, 0.1); padding: 4px 12px; border-radius: 6px;">Admin</span></a>
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
            <h1>💰 Kassen-Admin (vereinfacht)</h1>
            <p class="text-secondary">Simpel, fair, übersichtlich</p>
        </div>

        <div class="admin-stats">
            <div class="admin-stat">
                <div class="stat-value"><?= $stats['members'] ?? 0 ?></div>
                <div class="stat-label">👥 Aktive Mitglieder</div>
            </div>
            <div class="admin-stat">
                <div class="stat-value"><?= $stats['transactions'] ?? 0 ?></div>
                <div class="stat-label">📊 Transaktionen (Monat)</div>
            </div>
            <div class="admin-stat">
                <div class="stat-value"><?= number_format($stats['total'] ?? 0, 2) ?>€</div>
                <div class="stat-label">💰 Kassenstand</div>
            </div>
        </div>

        <div class="admin-grid">
            <!-- Gutschrift "Nicht dabei gewesen" -->
            <div class="section" style="grid-column: 1 / -1;">
                <div class="section-header">
                    <span>💝</span>
                    <h2 class="section-title">Gutschrift: Nicht dabei gewesen</h2>
                </div>
                
                <form id="gutschriftForm" class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
                    <div class="form-group">
                        <label>Mitglied auswählen</label>
                        <select id="gutschrift_mitglied" required>
                            <option value="">-- Mitglied wählen --</option>
                            <?php foreach($mitglieder_list as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Betrag (€)</label>
                        <input type="number" id="gutschrift_betrag" step="0.01" value="10.00" required>
                    </div>
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Grund / Beschreibung</label>
                        <input type="text" id="gutschrift_beschreibung" value="Nicht dabei gewesen" required>
                    </div>
                    
                    <button type="submit" class="btn" style="background: var(--success); grid-column: 1 / -1;">✅ Gutschrift buchen</button>
                </form>
                
                <div id="gutschrift_result" style="margin-top: 16px; padding: 12px; border-radius: 8px; display: none;"></div>
            </div>
        
            <!-- Gruppenaktion buchen -->
            <div class="section" style="grid-column: 1 / -1;">
                <div class="section-header">
                    <span>🎬</span>
                    <h2 class="section-title">Gruppenaktion (Kasse zahlt oder anteilig)</h2>
                </div>
                
                <form id="gruppenaktionForm" class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
                    <div class="form-group">
                        <label>Art der Transaktion</label>
                        <select id="gruppenaktion_typ" style="width: 100%; padding: 12px; background: var(--bg-tertiary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary);">
                            <option value="ausgabe">Ausgabe (aus Kasse nehmen)</option>
                            <option value="einzahlung">Einzahlung (in Kasse einzahlen)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label id="betrag_label">Betrag (€) aus Kasse</label>
                        <input type="number" id="gruppenaktion_betrag" step="0.01" placeholder="60.00" required>
                    </div>
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Beschreibung</label>
                        <input type="text" id="gruppenaktion_beschreibung" placeholder="z.B. Kino - The Batman" required>
                    </div>
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Wer war dabei? (Mehrfachauswahl)</label>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px; margin-top: 8px;">
                            <?php foreach($mitglieder_list as $m): ?>
                                <label style="display: flex; align-items: center; gap: 8px; padding: 8px; background: var(--bg-tertiary); border-radius: 6px; cursor: pointer;">
                                    <input type="checkbox" name="teilnehmer[]" value="<?= $m['id'] ?>" style="width: 18px; height: 18px;">
                                    <span><?= htmlspecialchars($m['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn" id="gruppenaktion_submit_btn" style="background: var(--accent); grid-column: 1 / -1;">🎯 Gruppenaktion buchen</button>
                </form>
                
                <div id="gruppenaktion_result" style="margin-top: 16px; padding: 12px; border-radius: 8px; display: none;"></div>
            </div>
        
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
        
        <!-- Letzte Transaktionen verwalten -->
        <div class="section" style="margin-top: 32px; grid-column: 1 / -1;">
            <div class="section-header">
                <span>📋</span>
                <h2 class="section-title">Letzte Transaktionen verwalten</h2>
            </div>
            
            <div id="transactionsList" style="display: grid; gap: 8px; max-height: 500px; overflow-y: auto;">
                <p style="color: var(--text-secondary); text-align: center; padding: 20px;">Lade Transaktionen...</p>
            </div>
        </div>
    </div>

    <script>
    // Transaktionen laden und anzeigen
    async function loadTransactions() {
        try {
            const res = await fetch('/api/v2/get_kasse_simple.php');
            const data = await res.json();
            
            if (data.status === 'success' && data.data.recent_transactions) {
                const list = document.getElementById('transactionsList');
                list.innerHTML = data.data.recent_transactions.map(tx => {
                    const isPositive = tx.betrag >= 0;
                    const amountClass = isPositive ? 'konto-positive' : 'konto-negative';
                    const date = new Date(tx.datum);
                    const formattedDate = date.toLocaleDateString('de-DE', { 
                        day: '2-digit', 
                        month: 'short',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    return `
                        <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 8px; padding: 16px; display: grid; grid-template-columns: auto 1fr auto auto; gap: 16px; align-items: center;">
                            <div style="font-size: 0.75rem; color: var(--text-secondary);">
                                <div>${formattedDate}</div>
                                <div style="margin-top: 4px; font-weight: 600; color: var(--text-primary);">${tx.typ}</div>
                            </div>
                            <div>
                                <div style="font-size: 0.875rem; color: var(--text-primary);">${tx.beschreibung || '-'}</div>
                                ${tx.mitglied_name ? `<div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 4px;">👤 ${tx.mitglied_name}</div>` : ''}
                            </div>
                            <div style="font-size: 1.25rem; font-weight: 700; color: ${isPositive ? 'var(--success)' : 'var(--error)'};">
                                ${isPositive ? '+' : ''}${tx.betrag.toFixed(2)}€
                            </div>
                            <button onclick="deleteTransaction(${tx.id})" 
                                    style="padding: 8px 12px; background: var(--error); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.875rem;"
                                    title="Transaktion löschen">
                                🗑️
                            </button>
                        </div>
                    `;
                }).join('');
            }
        } catch (error) {
            console.error('Fehler beim Laden:', error);
        }
    }
    
    async function deleteTransaction(id) {
        if (!confirm('Transaktion wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden!')) {
            return;
        }
        
        try {
            const res = await fetch('/api/v2/delete_transaction.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ transaction_id: id })
            });
            
            const data = await res.json();
            
            if (data.status === 'success') {
                alert('✅ Transaktion gelöscht!');
                loadTransactions();
            } else {
                alert('❌ Fehler: ' + data.error);
            }
        } catch (error) {
            alert('❌ Fehler beim Löschen: ' + error.message);
        }
    }
    
    // Initial laden
    loadTransactions();
    
    // Gutschrift buchen
    document.getElementById('gutschriftForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const mitglied_id = parseInt(document.getElementById('gutschrift_mitglied').value);
        const betrag = parseFloat(document.getElementById('gutschrift_betrag').value);
        const beschreibung = document.getElementById('gutschrift_beschreibung').value.trim();
        
        const resultDiv = document.getElementById('gutschrift_result');
        resultDiv.style.display = 'block';
        resultDiv.style.background = 'var(--bg-tertiary)';
        resultDiv.innerHTML = '⏳ Wird gebucht...';
        
        try {
            const response = await fetch('/api/v2/gutschrift_nicht_dabei.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mitglied_id, betrag, beschreibung })
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                resultDiv.style.background = '#10b981';
                resultDiv.style.color = 'white';
                resultDiv.innerHTML = `✅ Gutschrift von ${betrag.toFixed(2)}€ erfolgreich gebucht!`;
                e.target.reset();
                setTimeout(() => location.reload(), 2000);
            } else {
                resultDiv.style.background = '#ef4444';
                resultDiv.style.color = 'white';
                resultDiv.innerHTML = `❌ Fehler: ${data.error}`;
            }
        } catch (error) {
            resultDiv.style.background = '#ef4444';
            resultDiv.style.color = 'white';
            resultDiv.innerHTML = `❌ Fehler: ${error.message}`;
        }
    });
    
    // Gruppenaktion Typ-Wechsel
    document.getElementById('gruppenaktion_typ').addEventListener('change', function() {
        const typ = this.value;
        const label = document.getElementById('betrag_label');
        const btn = document.getElementById('gruppenaktion_submit_btn');
        
        if (typ === 'einzahlung') {
            label.textContent = 'Betrag (€) in Kasse';
            btn.innerHTML = '💰 Einzahlung buchen';
            btn.style.background = 'var(--success)';
        } else {
            label.textContent = 'Betrag (€) aus Kasse';
            btn.innerHTML = '🎯 Gruppenaktion buchen';
            btn.style.background = 'var(--accent)';
        }
    });
    
    // Gruppenaktion buchen
    document.getElementById('gruppenaktionForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const typ = document.getElementById('gruppenaktion_typ').value;
        const betrag = parseFloat(document.getElementById('gruppenaktion_betrag').value);
        const beschreibung = document.getElementById('gruppenaktion_beschreibung').value.trim();
        const checkboxes = document.querySelectorAll('input[name="teilnehmer[]"]:checked');
        const teilnehmer_ids = Array.from(checkboxes).map(cb => parseInt(cb.value));
        
        if (teilnehmer_ids.length === 0) {
            alert('Bitte mindestens 1 Teilnehmer auswählen!');
            return;
        }
        
        const resultDiv = document.getElementById('gruppenaktion_result');
        resultDiv.style.display = 'block';
        resultDiv.style.background = 'var(--bg-tertiary)';
        resultDiv.innerHTML = '⏳ Wird gebucht...';
        
        try {
            const response = await fetch('/api/gruppenaktion_buchen.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ betrag, beschreibung, teilnehmer_ids, typ })
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                resultDiv.style.background = '#10b981';
                resultDiv.style.color = 'white';
                
                if (data.data && data.data.betrag !== undefined) {
                    resultDiv.innerHTML = `
                        ✅ <strong>${typ === 'einzahlung' ? 'Einzahlung' : 'Gruppenaktion'} gebucht!</strong><br>
                        💰 Betrag: ${data.data.betrag.toFixed(2)}€<br>
                        👥 Teilnehmer: ${data.data.anzahl_teilnehmer}<br>
                        🎁 Fair-Share: ${data.data.fair_share.toFixed(2)}€ pro Person<br>
                        ✨ Gutgeschrieben an: ${data.data.nicht_teilnehmer.join(', ')}
                    `;
                } else {
                    resultDiv.innerHTML = `✅ <strong>Erfolgreich gebucht!</strong>`;
                }
                
                e.target.reset();
                setTimeout(() => location.reload(), 3000);
            } else {
                resultDiv.style.background = '#ef4444';
                resultDiv.style.color = 'white';
                resultDiv.innerHTML = `❌ Fehler: ${data.error}`;
            }
        } catch (error) {
            resultDiv.style.background = '#ef4444';
            resultDiv.style.color = 'white';
            resultDiv.innerHTML = `❌ Fehler: ${error.message}`;
        }
    });
    </script>
</body>
</html>
