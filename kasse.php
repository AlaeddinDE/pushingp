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
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        
        @keyframes shimmer {
            0% { background-position: -1000px 0; }
            100% { background-position: 1000px 0; }
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, rgba(88, 101, 242, 0.05), rgba(124, 137, 255, 0.05));
            backdrop-filter: blur(10px);
            border: 1px solid rgba(88, 101, 242, 0.2);
            border-radius: 20px;
            padding: 28px;
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            animation: fadeIn 0.6s ease;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(88, 101, 242, 0.1) 0%, transparent 70%);
            animation: pulse 3s ease-in-out infinite;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            border-color: rgba(88, 101, 242, 0.4);
            box-shadow: 0 20px 40px rgba(88, 101, 242, 0.15);
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 900;
            background: linear-gradient(135deg, #5865f2, #7c89ff, #a8b3ff);
            background-size: 200% 200%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 12px;
            position: relative;
            animation: shimmer 3s linear infinite;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .member-list {
            display: grid;
            gap: 16px;
            margin-bottom: 40px;
        }
        
        .member-item {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.01));
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 20px;
            display: grid;
            grid-template-columns: auto 1fr auto auto;
            gap: 20px;
            align-items: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.6s ease;
        }
        
        .member-item::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(88, 101, 242, 0.05), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .member-item:hover {
            transform: translateX(5px);
            border-color: rgba(88, 101, 242, 0.3);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .member-item:hover::before {
            opacity: 1;
        }
        
        .member-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(88, 101, 242, 0.3);
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(88, 101, 242, 0.2);
        }
        
        .member-item:hover .member-avatar {
            transform: scale(1.1);
            border-color: rgba(88, 101, 242, 0.6);
        }
        
        .member-info h3 {
            margin: 0;
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .member-info p {
            margin: 6px 0 0 0;
            font-size: 0.8rem;
            color: var(--text-secondary);
            opacity: 0.8;
        }
        
        .konto-saldo {
            font-size: 1.5rem;
            font-weight: 800;
            padding: 8px 16px;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .konto-positive { 
            color: var(--success); 
            background: rgba(59, 165, 93, 0.1);
        }
        
        .konto-negative { 
            color: var(--error);
            background: rgba(239, 68, 68, 0.1);
        }
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 24px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .status-gedeckt {
            background: linear-gradient(135deg, rgba(59, 165, 93, 0.25), rgba(59, 165, 93, 0.15));
            color: var(--success);
            border: 1px solid rgba(59, 165, 93, 0.3);
        }
        
        .status-ueberfaellig {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.25), rgba(239, 68, 68, 0.15));
            color: var(--error);
            border: 1px solid rgba(239, 68, 68, 0.3);
            animation: pulse 2s ease-in-out infinite;
        }
        
        .status-inactive {
            background: rgba(156, 163, 175, 0.15);
            color: var(--text-secondary);
            border: 1px solid rgba(156, 163, 175, 0.2);
        }
        
        .tx-list {
            display: grid;
            gap: 12px;
        }
        
        .tx-item {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.01));
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 16px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 16px;
            align-items: center;
            transition: all 0.3s ease;
            animation: fadeIn 0.6s ease;
        }
        
        .tx-item:hover {
            border-color: rgba(88, 101, 242, 0.3);
            transform: translateX(5px);
            background: linear-gradient(135deg, rgba(88, 101, 242, 0.05), rgba(88, 101, 242, 0.02));
        }
        
        .tx-date {
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .tx-desc {
            font-size: 0.875rem;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .tx-amount {
            font-size: 1.125rem;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 8px;
        }
        
        .section {
            animation: fadeIn 0.8s ease;
        }
        
        .section-header {
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-header span {
            font-size: 1.75rem;
        }
        
        .section-title {
            background: linear-gradient(135deg, var(--text-primary), var(--text-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .admin-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 28px;
            background: linear-gradient(135deg, #5865f2, #7c89ff);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 16px rgba(88, 101, 242, 0.3);
            border: none;
            cursor: pointer;
        }
        
        .admin-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(88, 101, 242, 0.4);
            background: linear-gradient(135deg, #6875f5, #8b97ff);
        }
        
        .admin-btn:active {
            transform: translateY(-1px);
        }
        
        .welcome {
            text-align: center;
            margin-bottom: 48px;
            animation: fadeIn 0.8s ease;
        }
        
        .welcome h1 {
            font-size: 3rem;
            font-weight: 900;
            background: linear-gradient(135deg, #5865f2, #7c89ff, #a8b3ff);
            background-size: 200% 200%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shimmer 3s linear infinite;
            margin-bottom: 12px;
        }
        
        .welcome p {
            font-size: 1.125rem;
            opacity: 0.7;
        }
        
        @media (max-width: 768px) {
            .welcome h1 {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .member-item {
                grid-template-columns: auto 1fr;
                gap: 12px;
            }
            
            .member-item .konto-saldo,
            .member-item .status-badge {
                grid-column: 2;
                justify-self: end;
                font-size: 1rem;
            }
            
            .tx-item {
                grid-template-columns: 1fr;
                gap: 8px;
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
        <div style="margin-top: 48px; text-align: center; animation: fadeIn 1s ease;">
            <a href="admin_kasse.php" class="admin-btn">
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
                    txList.innerHTML = dashData.data.recent_transactions.map((tx, index) => {
                        const isPositive = tx.betrag >= 0;
                        const amountClass = isPositive ? 'konto-positive' : 'konto-negative';
                        const date = new Date(tx.datum);
                        const formattedDate = date.toLocaleDateString('de-DE', { day: '2-digit', month: 'short' });
                        const formattedTime = date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
                        
                        // Emoji basierend auf Typ
                        let icon = '💰';
                        if (tx.typ === 'EINZAHLUNG') icon = '💸';
                        else if (tx.typ === 'AUSZAHLUNG') icon = '💵';
                        else if (tx.typ === 'AUSGLEICH') icon = '✨';
                        else if (tx.typ === 'SCHADEN') icon = '⚠️';
                        else if (tx.typ.includes('GRUPPENAKTION')) icon = '🎬';
                        
                        return `
                            <div class="tx-item" style="animation-delay: ${index * 0.05}s;">
                                <div>
                                    <div class="tx-date">${formattedDate}</div>
                                    <div class="tx-date">${formattedTime}</div>
                                </div>
                                <div>
                                    <div class="tx-desc">${icon} ${tx.typ}</div>
                                    ${tx.beschreibung ? `<div class="tx-date">${tx.beschreibung}</div>` : ''}
                                    ${tx.mitglied_name ? `<div class="tx-date">👤 ${tx.mitglied_name}</div>` : ''}
                                </div>
                                <div class="tx-amount ${amountClass}">
                                    ${isPositive ? '+' : ''}${tx.betrag.toFixed(2)}€
                                </div>
                            </div>
                        `;
                    }).join('');
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
                    memberList.innerHTML = memberData.data.map((m, index) => {
                        const statusClass = m.zahlungsstatus === 'ueberfaellig' ? 'status-ueberfaellig' : 
                                          m.zahlungsstatus === 'gedeckt' ? 'status-gedeckt' : 'status-inactive';
                        const salDoClass = m.konto_saldo >= 0 ? 'konto-positive' : 'konto-negative';
                        const naechsteZahlung = new Date(m.naechste_faelligkeit);
                        const formattedDate = naechsteZahlung.toLocaleDateString('de-DE', { 
                            day: '2-digit', 
                            month: 'long', 
                            year: 'numeric' 
                        });
                        
                        return `
                            <div class="member-item" style="animation-delay: ${index * 0.05}s;">
                                <img src="${m.avatar || '/assets/default-avatar.png'}" class="member-avatar" alt="${m.name}" onerror="this.src='/assets/default-avatar.png'">
                                <div class="member-info">
                                    <h3>${m.emoji} ${m.name}</h3>
                                    <p>📅 Nächste Zahlung: ${formattedDate}</p>
                                    <p>✅ Gedeckt für: ${m.monate_gedeckt} Monat${m.monate_gedeckt !== 1 ? 'e' : ''}</p>
                                </div>
                                <div class="konto-saldo ${salDoClass}">
                                    ${m.konto_saldo >= 0 ? '+' : ''}${m.konto_saldo.toFixed(2)}€
                                </div>
                                <div class="status-badge ${statusClass}">
                                    ${m.zahlungsstatus === 'ueberfaellig' ? '🔴 Überfällig' : 
                                      m.zahlungsstatus === 'gedeckt' ? '🟢 Gedeckt' : '⚪ Inaktiv'}
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
