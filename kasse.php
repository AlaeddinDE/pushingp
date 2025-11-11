<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$username = $_SESSION['username'] ?? 'User';
$name = $_SESSION['name'] ?? $username;
$is_admin = is_admin();
$is_admin_user = $is_admin;
$page_title = 'Kasse';

// PayPal Pool Amount holen
$paypal_result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'paypal_pool_amount'");
$paypal_amount = 109.05;
if ($paypal_result && $row = $paypal_result->fetch_assoc()) {
    $paypal_amount = floatval($row['setting_value']);
}

require_once __DIR__ . '/includes/header.php';
?>
    <style>
        /* Apple-Style Design System */
        :root {
            --apple-blue: #007aff;
            --apple-green: #34c759;
            --apple-red: #ff3b30;
            --apple-orange: #ff9500;
            --apple-gray: #8e8e93;
            --apple-light-gray: #f2f2f7;
            --apple-card: rgba(255, 255, 255, 0.05);
            --apple-border: rgba(255, 255, 255, 0.1);
            --apple-shadow: 0 2px 16px rgba(0, 0, 0, 0.12);
        }
        
        body {
            background: linear-gradient(180deg, #000000 0%, #1a1a1a 100%);
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        
        .kasse-hero {
            text-align: center;
            padding: 40px 20px;
            animation: slideUp 0.6s ease;
        }
        
        .kasse-hero h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        
        .kasse-hero p {
            font-size: 1.125rem;
            color: var(--apple-gray);
            font-weight: 400;
        }
        
        /* Pool Card - Hauptanzeige */
        .pool-card {
            background: linear-gradient(135deg, rgba(0, 122, 255, 0.1), rgba(52, 199, 89, 0.1));
            border: 1px solid var(--apple-border);
            border-radius: 24px;
            padding: 32px 24px;
            margin: 0 auto 32px;
            max-width: 500px;
            text-align: center;
            animation: scaleIn 0.6s ease 0.2s backwards;
            backdrop-filter: blur(20px);
            box-shadow: 0 8px 32px rgba(0, 122, 255, 0.15);
        }
        
        .pool-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--apple-gray);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 12px;
        }
        
        .pool-amount {
            font-size: 4rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: -0.03em;
            margin-bottom: 24px;
            font-variant-numeric: tabular-nums;
        }
        
        .pool-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 16px 32px;
            background: var(--apple-blue);
            color: #fff;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.2s ease;
            box-shadow: 0 4px 16px rgba(0, 122, 255, 0.3);
        }
        
        .pool-btn:hover {
            background: #0051d5;
            transform: scale(1.02);
            box-shadow: 0 6px 24px rgba(0, 122, 255, 0.4);
        }
        
        .pool-btn:active {
            transform: scale(0.98);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 32px;
            animation: slideUp 0.6s ease 0.3s backwards;
        }
        
        .stat-card {
            background: var(--apple-card);
            border: 1px solid var(--apple-border);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            backdrop-filter: blur(20px);
        }
        
        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 4px;
            font-variant-numeric: tabular-nums;
        }
        
        .stat-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--apple-gray);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        /* Member List - iOS Style */
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 16px;
            letter-spacing: -0.02em;
        }
        
        .member-list {
            display: grid;
            gap: 1px;
            background: var(--apple-border);
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 32px;
            animation: slideUp 0.6s ease 0.4s backwards;
        }
        
        .member-item {
            background: var(--apple-card);
            backdrop-filter: blur(20px);
            padding: 16px 20px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 12px;
            align-items: center;
            transition: background 0.2s ease;
        }
        
        .member-item:hover {
            background: rgba(255, 255, 255, 0.08);
        }
        
        .member-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .member-info {
            flex: 1;
        }
        
        .member-name {
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 2px;
        }
        
        .member-detail {
            font-size: 0.8125rem;
            color: var(--apple-gray);
        }
        
        .member-badge {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
        }
        
        .konto-amount {
            font-size: 1.125rem;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }
        
        .konto-positive { color: var(--apple-green); }
        .konto-negative { color: var(--apple-red); }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        
        .status-gedeckt { background: var(--apple-green); box-shadow: 0 0 8px var(--apple-green); }
        .status-ueberfaellig { background: var(--apple-red); box-shadow: 0 0 8px var(--apple-red); }
        .status-inactive { background: var(--apple-gray); }
        
        /* Transactions - iOS List Style */
        .tx-list {
            display: grid;
            gap: 1px;
            background: var(--apple-border);
            border-radius: 16px;
            overflow: hidden;
            animation: slideUp 0.6s ease 0.5s backwards;
        }
        
        .tx-item {
            background: var(--apple-card);
            backdrop-filter: blur(20px);
            padding: 14px 20px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 12px;
            align-items: center;
            transition: background 0.2s ease;
        }
        
        .tx-item:hover {
            background: rgba(255, 255, 255, 0.08);
        }
        
        .tx-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        
        .tx-info {
            flex: 1;
        }
        
        .tx-title {
            font-size: 0.9375rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 2px;
        }
        
        .tx-subtitle {
            font-size: 0.8125rem;
            color: var(--apple-gray);
        }
        
        .tx-amount {
            font-size: 1rem;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
        }
        
        /* Admin Button */
        .admin-button {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 56px;
            height: 56px;
            background: var(--apple-blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #fff;
            text-decoration: none;
            box-shadow: 0 8px 24px rgba(0, 122, 255, 0.4);
            transition: all 0.3s ease;
            z-index: 100;
        }
        
        .admin-button:hover {
            transform: scale(1.1);
            box-shadow: 0 12px 32px rgba(0, 122, 255, 0.5);
        }
        
        .admin-button:active {
            transform: scale(0.95);
        }
        
        /* Mobile Optimierung */
        @media (max-width: 768px) {
            .kasse-hero h1 {
                font-size: 2rem;
            }
            
            .pool-amount {
                font-size: 3rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .stat-card {
                display: flex;
                justify-content: space-between;
                align-items: center;
                text-align: left;
            }
            
            .stat-value {
                order: 2;
                font-size: 1.5rem;
            }
            
            .stat-label {
                order: 1;
                font-size: 0.875rem;
            }
            
            .member-item {
                grid-template-columns: auto 1fr;
            }
            
            .member-badge {
                grid-column: 2;
                align-items: flex-end;
            }
            
            .admin-button {
                bottom: 80px;
            }
        }
    </style>

    <div class="container" style="max-width: 768px;">
        <!-- Hero -->
        <div class="kasse-hero">
            <h1>💰 Kasse</h1>
            <p>Simpel. Fair. Transparent.</p>
        </div>

        <!-- Pool Card -->
        <div class="pool-card">
            <div class="pool-label">PayPal Pool</div>
            <div class="pool-amount" id="poolAmount"><?= number_format($paypal_amount, 2, ',', '.') ?>€</div>
            <a href="https://paypal.me/pools/c/95FEcHKK8L" target="_blank" class="pool-btn">
                <span>💳</span>
                <span>Zum Pool</span>
            </a>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Aktive</div>
                <div class="stat-value" id="aktiveStat">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Überfällig</div>
                <div class="stat-value" id="ueberfaelligStat">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Transaktionen</div>
                <div class="stat-value" id="txStat">0</div>
            </div>
        </div>

        <!-- Mitglieder -->
        <h2 class="section-title">Mitglieder</h2>
        <div class="member-list" id="memberList">
            <div class="member-item">
                <div class="member-info">
                    <div class="member-detail">Lade Daten...</div>
                </div>
            </div>
        </div>

        <!-- Transaktionen -->
        <h2 class="section-title">Letzte Transaktionen</h2>
        <div class="tx-list" id="txList">
            <div class="tx-item">
                <div class="tx-info">
                    <div class="tx-subtitle">Lade Transaktionen...</div>
                </div>
            </div>
        </div>
    </div>

    <?php if($is_admin): ?>
    <a href="admin_kasse.php" class="admin-button" title="Kassen-Admin">
        🛠️
    </a>
    <?php endif; ?>

    <script>
    async function loadData() {
        try {
            // Stats
            const dashRes = await fetch('/api/v2/get_kasse_simple.php');
            const dashData = await dashRes.json();
            
            if (dashData.status === 'success') {
                document.getElementById('aktiveStat').textContent = dashData.data.aktive_mitglieder;
                document.getElementById('ueberfaelligStat').textContent = dashData.data.ueberfaellig_count;
                document.getElementById('txStat').textContent = dashData.data.transaktionen_monat;
                
                // Transaktionen
                const txList = document.getElementById('txList');
                if (dashData.data.recent_transactions && dashData.data.recent_transactions.length > 0) {
                    txList.innerHTML = dashData.data.recent_transactions.slice(0, 10).map(tx => {
                        const isPositive = tx.betrag >= 0;
                        const amountClass = isPositive ? 'konto-positive' : 'konto-negative';
                        const date = new Date(tx.datum);
                        const formattedDate = date.toLocaleDateString('de-DE', { 
                            day: '2-digit', 
                            month: 'short' 
                        });
                        
                        let icon = '💰';
                        if (tx.typ === 'EINZAHLUNG') icon = '💸';
                        else if (tx.typ === 'AUSZAHLUNG') icon = '💵';
                        else if (tx.typ === 'AUSGLEICH') icon = '✨';
                        else if (tx.typ === 'SCHADEN') icon = '⚠️';
                        else if (tx.typ.includes('GRUPPENAKTION')) icon = '🎬';
                        
                        return `
                            <div class="tx-item">
                                <div class="tx-icon">${icon}</div>
                                <div class="tx-info">
                                    <div class="tx-title">${tx.typ}</div>
                                    <div class="tx-subtitle">${formattedDate}${tx.mitglied_name ? ' • ' + tx.mitglied_name : ''}</div>
                                </div>
                                <div class="tx-amount ${amountClass}">
                                    ${isPositive ? '+' : ''}${tx.betrag.toFixed(2)}€
                                </div>
                            </div>
                        `;
                    }).join('');
                }
            }
            
            // Mitglieder
            const memberRes = await fetch('/api/v2/get_member_konto.php');
            const memberData = await memberRes.json();
            
            if (memberData.status === 'success' && memberData.data && memberData.data.length > 0) {
                const memberList = document.getElementById('memberList');
                memberList.innerHTML = memberData.data.map(m => {
                    const statusClass = m.zahlungsstatus === 'ueberfaellig' ? 'status-ueberfaellig' : 
                                      m.zahlungsstatus === 'gedeckt' ? 'status-gedeckt' : 'status-inactive';
                    const salDoClass = m.konto_saldo >= 0 ? 'konto-positive' : 'konto-negative';
                    const statusText = m.zahlungsstatus === 'ueberfaellig' ? 'Überfällig' : 
                                     m.zahlungsstatus === 'gedeckt' ? 'Gedeckt' : 'Inaktiv';
                    
                    return `
                        <div class="member-item">
                            <img src="${m.avatar || '/assets/default-avatar.png'}" 
                                 class="member-avatar" 
                                 alt="${m.name}" 
                                 onerror="this.src='/assets/default-avatar.png'">
                            <div class="member-info">
                                <div class="member-name">${m.name}</div>
                                <div class="member-detail">${m.monate_gedeckt} Monat${m.monate_gedeckt !== 1 ? 'e' : ''} gedeckt</div>
                            </div>
                            <div class="member-badge">
                                <div class="konto-amount ${salDoClass}">
                                    ${m.konto_saldo >= 0 ? '+' : ''}${m.konto_saldo.toFixed(2)}€
                                </div>
                                <div class="status-dot ${statusClass}" title="${statusText}"></div>
                            </div>
                        </div>
                    `;
                }).join('');
            }
            
        } catch (error) {
            console.error('Fehler beim Laden:', error);
        }
    }
    
    loadData();
    setInterval(loadData, 30000);
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
