<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

$is_admin = is_admin();
$user_id = $_SESSION['user_id'];
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
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--bg-secondary), var(--bg-tertiary));
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
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
        }
        
        .member-list {
            display: grid;
            gap: 12px;
            margin-bottom: 32px;
        }
        
        .member-item {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            display: grid;
            grid-template-columns: auto 1fr auto auto auto;
            gap: 16px;
            align-items: center;
        }
        
        .member-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .member-info h3 {
            margin: 0;
            font-size: 1rem;
            color: var(--text-primary);
        }
        
        .member-info p {
            margin: 4px 0 0 0;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .konto-saldo {
            font-size: 1.25rem;
            font-weight: 700;
        }
        
        .konto-positive { color: var(--success); }
        .konto-negative { color: var(--error); }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-gedeckt {
            background: rgba(59, 165, 93, 0.2);
            color: var(--success);
        }
        
        .status-ueberfaellig {
            background: rgba(239, 68, 68, 0.2);
            color: var(--error);
        }
        
        .status-inactive {
            background: rgba(156, 163, 175, 0.2);
            color: var(--text-secondary);
        }
        
        .tx-list {
            display: grid;
            gap: 8px;
        }
        
        .tx-item {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 12px;
            align-items: center;
        }
        
        .tx-date {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .tx-desc {
            font-size: 0.875rem;
            color: var(--text-primary);
        }
        
        .tx-amount {
            font-size: 1rem;
            font-weight: 700;
        }
        
        @media (max-width: 768px) {
            .member-item {
                grid-template-columns: auto 1fr;
                gap: 12px;
            }
            
            .member-item .konto-saldo,
            .member-item .status-badge {
                grid-column: 2;
                justify-self: end;
            }
        }
    </style>
</head>
<body>
    <div class="grain"></div>
    
    <div class="header">
        <div class="header-content">
            <a href="https://pushingp.de" class="logo">PUSHING P</a>
            <nav class="nav">
                <a href="kasse.php" class="nav-item active">Kasse</a>
                <a href="events.php" class="nav-item">Events</a>
                <a href="schichten.php" class="nav-item">Schichten</a>
                <a href="chat.php" class="nav-item">Chat</a>
                <?php if($is_admin): ?>
                <a href="admin.php" class="nav-item">Admin</a>
                <?php endif; ?>
                <a href="settings.php" class="nav-item">Settings</a>
                <a href="logout.php" class="nav-item">Logout</a>
            </nav>
        </div>
    </div>

    <div class="container">
        <div class="welcome">
            <h1>💰 Kasse</h1>
            <p class="text-secondary">Simpel. Fair. Transparent.</p>
        </div>

        <!-- Stats -->
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <div class="stat-value" id="kassenstand">0,00€</div>
                <div class="stat-label">Kassenstand</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="aktive">0</div>
                <div class="stat-label">Aktive Mitglieder</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="ueberfaellig">0</div>
                <div class="stat-label">Überfällig</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="tx_monat">0</div>
                <div class="stat-label">Transaktionen (Monat)</div>
            </div>
        </div>

        <!-- Mitglieder-Konten -->
        <div class="section">
            <div class="section-header">
                <span>👥</span>
                <h2 class="section-title">Mitglieder-Konten</h2>
            </div>
            <div class="member-list" id="memberList">
                <p class="text-secondary">Lade Daten...</p>
            </div>
        </div>

        <!-- Letzte Transaktionen -->
        <div class="section">
            <div class="section-header">
                <span>📊</span>
                <h2 class="section-title">Letzte Transaktionen</h2>
            </div>
            <div class="tx-list" id="txList">
                <p class="text-secondary">Lade Transaktionen...</p>
            </div>
        </div>

        <?php if($is_admin): ?>
        <div style="margin-top: 32px; text-align: center;">
            <a href="admin_kasse.php" class="btn" style="display: inline-block; padding: 12px 24px; background: var(--accent); color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">
                🛠️ Kassen-Admin
            </a>
        </div>
        <?php endif; ?>
    </div>

    <script>
    // Daten laden
    async function loadData() {
        try {
            // Dashboard Stats
            const dashRes = await fetch('/api/v2/get_kasse_simple.php');
            const dashData = await dashRes.json();
            
            if (dashData.status === 'success') {
                document.getElementById('kassenstand').textContent = dashData.data.kassenstand.toFixed(2) + '€';
                document.getElementById('aktive').textContent = dashData.data.aktive_mitglieder;
                document.getElementById('ueberfaellig').textContent = dashData.data.ueberfaellig_count;
                document.getElementById('tx_monat').textContent = dashData.data.transaktionen_monat;
                
                // Transaktionen
                const txList = document.getElementById('txList');
                if (dashData.data.recent_transactions && dashData.data.recent_transactions.length > 0) {
                    txList.innerHTML = dashData.data.recent_transactions.map(tx => `
                        <div class="tx-item">
                            <div>
                                <div class="tx-date">${new Date(tx.datum).toLocaleDateString('de-DE')}</div>
                                <div class="tx-desc">${tx.typ}</div>
                            </div>
                            <div>
                                <div class="tx-desc">${tx.beschreibung || ''}</div>
                                ${tx.mitglied_name ? `<div class="tx-date">${tx.mitglied_name}</div>` : ''}
                            </div>
                            <div class="tx-amount ${tx.betrag >= 0 ? 'konto-positive' : 'konto-negative'}">
                                ${tx.betrag >= 0 ? '+' : ''}${tx.betrag.toFixed(2)}€
                            </div>
                        </div>
                    `).join('');
                } else {
                    txList.innerHTML = '<p class="text-secondary">Keine Transaktionen vorhanden</p>';
                }
            }
            
            // Mitglieder-Konten
            const memberRes = await fetch('/api/v2/get_member_konto.php');
            const memberData = await memberRes.json();
            
            if (memberData.status === 'success') {
                const memberList = document.getElementById('memberList');
                if (memberData.data && memberData.data.length > 0) {
                    memberList.innerHTML = memberData.data.map(m => {
                        const statusClass = m.zahlungsstatus === 'ueberfaellig' ? 'status-ueberfaellig' : 
                                          m.zahlungsstatus === 'gedeckt' ? 'status-gedeckt' : 'status-inactive';
                        const salDoClass = m.konto_saldo >= 0 ? 'konto-positive' : 'konto-negative';
                        
                        return `
                            <div class="member-item">
                                <img src="${m.avatar || '/assets/default-avatar.png'}" class="member-avatar" alt="${m.name}">
                                <div class="member-info">
                                    <h3>${m.emoji} ${m.name}</h3>
                                    <p>Nächste Zahlung: ${new Date(m.naechste_faelligkeit).toLocaleDateString('de-DE')}</p>
                                    <p>Gedeckt für: ${m.monate_gedeckt} Monat(e)</p>
                                </div>
                                <div class="konto-saldo ${salDoClass}">
                                    ${m.konto_saldo.toFixed(2)}€
                                </div>
                                <div class="status-badge ${statusClass}">
                                    ${m.zahlungsstatus === 'ueberfaellig' ? 'Überfällig' : 
                                      m.zahlungsstatus === 'gedeckt' ? 'Gedeckt' : 'Inaktiv'}
                                </div>
                            </div>
                        `;
                    }).join('');
                } else {
                    memberList.innerHTML = '<p class="text-secondary">Keine Mitglieder gefunden</p>';
                }
            }
            
        } catch (error) {
            console.error('Fehler beim Laden:', error);
        }
    }
    
    // Initial laden
    loadData();
    
    // Alle 30 Sekunden aktualisieren
    setInterval(loadData, 30000);
    </script>
</body>
</html>
