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

// PayPal Pool Amount
$paypal_result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'paypal_pool_amount'");
$paypal_amount = 109.05;
if ($paypal_result && $row = $paypal_result->fetch_assoc()) {
    $paypal_amount = floatval($row['setting_value']);
}

require_once __DIR__ . '/includes/header.php';
?>
<style>
    body {
        background: #000;
    }
    
    .kasse-wrapper {
        min-height: 100vh;
        padding: 20px;
        max-width: 600px;
        margin: 0 auto;
    }
    
    /* Hero mit Pool-Betrag */
    .pool-hero {
        text-align: center;
        padding: 60px 20px 40px;
        background: linear-gradient(180deg, rgba(88, 101, 242, 0.15) 0%, transparent 100%);
        border-radius: 32px;
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
    }
    
    .pool-hero::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(88, 101, 242, 0.2) 0%, transparent 50%);
        animation: rotate 20s linear infinite;
    }
    
    @keyframes rotate {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .pool-label {
        font-size: 0.875rem;
        font-weight: 600;
        color: #8e8e93;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-bottom: 16px;
        position: relative;
        z-index: 1;
    }
    
    .pool-amount {
        font-size: 5rem;
        font-weight: 800;
        background: linear-gradient(135deg, #fff, #5865f2);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        line-height: 1;
        margin-bottom: 32px;
        position: relative;
        z-index: 1;
        font-variant-numeric: tabular-nums;
    }
    
    .pool-button {
        display: inline-block;
        padding: 16px 48px;
        background: linear-gradient(135deg, #5865f2, #7c89ff);
        color: #fff;
        text-decoration: none;
        border-radius: 50px;
        font-weight: 700;
        font-size: 1.125rem;
        box-shadow: 0 8px 32px rgba(88, 101, 242, 0.4);
        transition: all 0.3s ease;
        position: relative;
        z-index: 1;
    }
    
    .pool-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 40px rgba(88, 101, 242, 0.6);
    }
    
    .pool-button:active {
        transform: translateY(0);
    }
    
    /* Quick Stats */
    .quick-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 32px;
    }
    
    .stat-box {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 16px;
        padding: 20px 16px;
        text-align: center;
    }
    
    .stat-number {
        font-size: 2rem;
        font-weight: 800;
        color: #fff;
        margin-bottom: 4px;
    }
    
    .stat-text {
        font-size: 0.75rem;
        color: #8e8e93;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    /* Section Header */
    .section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
    }
    
    .section-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #fff;
    }
    
    /* Members & Transactions Cards */
    .card-list {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 20px;
        overflow: hidden;
        margin-bottom: 32px;
    }
    
    .card-item {
        padding: 16px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        display: flex;
        align-items: center;
        gap: 16px;
        transition: background 0.2s ease;
    }
    
    .card-item:last-child {
        border-bottom: none;
    }
    
    .card-item:active {
        background: rgba(255, 255, 255, 0.05);
    }
    
    /* Member Item */
    .member-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
        background: linear-gradient(135deg, #5865f2, #7c89ff);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-weight: 700;
        font-size: 1.25rem;
    }
    
    .member-avatar img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
    }
    
    .member-info {
        flex: 1;
        min-width: 0;
    }
    
    .member-name {
        font-size: 1rem;
        font-weight: 600;
        color: #fff;
        margin-bottom: 4px;
    }
    
    .member-status {
        font-size: 0.875rem;
        color: #8e8e93;
    }
    
    .member-amount {
        font-size: 1.25rem;
        font-weight: 700;
        flex-shrink: 0;
    }
    
    .amount-positive { color: #34c759; }
    .amount-negative { color: #ff3b30; }
    
    .status-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    
    .status-ok { background: #34c759; box-shadow: 0 0 8px #34c759; }
    .status-warning { background: #ff9500; box-shadow: 0 0 8px #ff9500; }
    .status-error { background: #ff3b30; box-shadow: 0 0 8px #ff3b30; }
    
    /* Transaction Item */
    .tx-icon {
        width: 40px;
        height: 40px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }
    
    .tx-info {
        flex: 1;
        min-width: 0;
    }
    
    .tx-title {
        font-size: 0.9375rem;
        font-weight: 600;
        color: #fff;
        margin-bottom: 4px;
    }
    
    .tx-subtitle {
        font-size: 0.8125rem;
        color: #8e8e93;
    }
    
    .tx-amount {
        font-size: 1.125rem;
        font-weight: 700;
        flex-shrink: 0;
    }
    
    /* Admin FAB */
    .admin-fab {
        position: fixed;
        bottom: 24px;
        right: 24px;
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #5865f2, #7c89ff);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        box-shadow: 0 8px 24px rgba(88, 101, 242, 0.5);
        transition: all 0.3s ease;
        z-index: 999;
        text-decoration: none;
    }
    
    .admin-fab:hover {
        transform: scale(1.1);
    }
    
    .admin-fab:active {
        transform: scale(0.95);
    }
    
    /* Mobile Anpassungen */
    @media (max-width: 600px) {
        .pool-amount {
            font-size: 3.5rem;
        }
        
        .pool-button {
            padding: 14px 36px;
            font-size: 1rem;
        }
        
        .quick-stats {
            grid-template-columns: 1fr;
        }
        
        .stat-box {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
        }
        
        .stat-text {
            font-size: 0.875rem;
            order: -1;
        }
        
        .stat-number {
            font-size: 1.5rem;
        }
        
        .admin-fab {
            bottom: 90px;
        }
    }
</style>

<div class="kasse-wrapper">
    <!-- Pool Hero -->
    <div class="pool-hero">
        <div class="pool-label">PayPal Pool</div>
        <div class="pool-amount" id="poolAmount"><?= number_format($paypal_amount, 2, ',', '.') ?>€</div>
        <a href="https://paypal.me/pools/c/95FEcHKK8L" target="_blank" class="pool-button">
            💳 Zum Pool
        </a>
    </div>

    <!-- Quick Stats -->
    <div class="quick-stats">
        <div class="stat-box">
            <div class="stat-number" id="aktiveStat">0</div>
            <div class="stat-text">Aktive</div>
        </div>
        <div class="stat-box">
            <div class="stat-number" id="ueberfaelligStat">0</div>
            <div class="stat-text">Überfällig</div>
        </div>
        <div class="stat-box">
            <div class="stat-number" id="txStat">0</div>
            <div class="stat-text">Transaktionen</div>
        </div>
    </div>

    <!-- Mitglieder -->
    <div class="section-header">
        <div class="section-title">Mitglieder</div>
    </div>
    <div class="card-list" id="memberList">
        <div class="card-item">
            <div class="member-info">
                <div class="member-status">Lade Daten...</div>
            </div>
        </div>
    </div>

    <!-- Transaktionen -->
    <div class="section-header">
        <div class="section-title">Transaktionen</div>
    </div>
    <div class="card-list" id="txList">
        <div class="card-item">
            <div class="tx-info">
                <div class="tx-subtitle">Lade Transaktionen...</div>
            </div>
        </div>
    </div>
</div>

<?php if($is_admin): ?>
<a href="admin_kasse.php" class="admin-fab" title="Admin">
    ⚙️
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
                txList.innerHTML = dashData.data.recent_transactions.slice(0, 8).map(tx => {
                    const isPositive = tx.betrag >= 0;
                    const amountClass = isPositive ? 'amount-positive' : 'amount-negative';
                    const date = new Date(tx.datum);
                    const formattedDate = date.toLocaleDateString('de-DE', { 
                        day: '2-digit', 
                        month: 'short',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    let icon = '💰';
                    if (tx.typ === 'EINZAHLUNG') icon = '💸';
                    else if (tx.typ === 'AUSZAHLUNG') icon = '💵';
                    else if (tx.typ === 'AUSGLEICH') icon = '✨';
                    else if (tx.typ === 'SCHADEN') icon = '⚠️';
                    else if (tx.typ.includes('GRUPPENAKTION')) icon = '🎬';
                    
                    return `
                        <div class="card-item">
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
                const statusClass = m.zahlungsstatus === 'ueberfaellig' ? 'status-error' : 
                                  m.zahlungsstatus === 'gedeckt' ? 'status-ok' : 'status-warning';
                const salDoClass = m.konto_saldo >= 0 ? 'amount-positive' : 'amount-negative';
                
                const initial = m.name.charAt(0).toUpperCase();
                const avatarHTML = m.avatar && m.avatar.trim() 
                    ? `<img src="${m.avatar}" alt="${m.name}" onerror="this.style.display='none'; this.parentElement.textContent='${initial}'">` 
                    : initial;
                
                return `
                    <div class="card-item">
                        <div class="member-avatar">${avatarHTML}</div>
                        <div class="member-info">
                            <div class="member-name">${m.name}</div>
                            <div class="member-status">${m.monate_gedeckt} Monat${m.monate_gedeckt !== 1 ? 'e' : ''} gedeckt</div>
                        </div>
                        <div class="member-amount ${salDoClass}">
                            ${m.konto_saldo >= 0 ? '+' : ''}${m.konto_saldo.toFixed(2)}€
                        </div>
                        <div class="status-indicator ${statusClass}"></div>
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
