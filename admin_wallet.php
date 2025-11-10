<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

if (!is_admin()) {
    header('Location: dashboard.php');
    exit;
}

// Check system status
$status = [];

// Certificate
$cert_path = __DIR__ . '/wallet/certs/pass_cert.p12';
$status['cert_exists'] = file_exists($cert_path);
$status['cert_path'] = $cert_path;

// Directories
$status['passes_dir'] = is_dir(__DIR__ . '/wallet/passes') && is_writable(__DIR__ . '/wallet/passes');
$status['templates_dir'] = is_dir(__DIR__ . '/wallet/templates') && is_writable(__DIR__ . '/wallet/templates');

// Database
$result = $conn->query("SELECT COUNT(*) as cnt FROM wallet_registrations");
$status['registrations'] = $result->fetch_assoc()['cnt'];

$result = $conn->query("SELECT COUNT(*) as cnt FROM wallet_tokens");
$status['tokens'] = $result->fetch_assoc()['cnt'];

$result = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE wallet_serial IS NOT NULL");
$status['users_with_passes'] = $result->fetch_assoc()['cnt'];

// Recent activity
$recent_activity = [];
$result = $conn->query("SELECT u.name, wpu.reason, wpu.last_modified FROM wallet_pass_updates wpu JOIN users u ON wpu.user_id = u.id ORDER BY wpu.last_modified DESC LIMIT 10");
while ($row = $result->fetch_assoc()) {
    $recent_activity[] = $row;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet System Status – PUSHING P</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <div class="container" style="max-width: 1200px;">
        <div class="welcome">
            <h1>🍎 Apple Wallet System Status</h1>
            <p style="color: var(--text-secondary);">Übersicht des Apple Wallet Integration</p>
        </div>
        
        <!-- Status Overview -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">System Status</h2>
            </div>
            
            <div style="display: grid; gap: 16px;">
                <!-- Certificate Status -->
                <div style="background: var(--bg-secondary); padding: 20px; border-radius: 12px; border-left: 4px solid <?= $status['cert_exists'] ? '#10b981' : '#ef4444' ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                            <div style="font-weight: 700; font-size: 1.125rem; margin-bottom: 8px;">
                                <?= $status['cert_exists'] ? '✅' : '❌' ?> Apple Developer Certificate
                            </div>
                            <div style="color: var(--text-secondary); font-size: 0.875rem;">
                                <?php if ($status['cert_exists']): ?>
                                    Certificate gefunden: <?= $status['cert_path'] ?>
                                <?php else: ?>
                                    Certificate NICHT gefunden: <?= $status['cert_path'] ?>
                                    <br><strong>Demo-Modus aktiv</strong> - Passes werden nicht in Apple Wallet funktionieren
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!$status['cert_exists']): ?>
                        <a href="/wallet/SETUP.md" target="_blank" style="background: var(--accent); color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.875rem;">
                            Setup-Anleitung
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Directories -->
                <div style="background: var(--bg-secondary); padding: 20px; border-radius: 12px;">
                    <div style="font-weight: 700; margin-bottom: 12px;">📁 Verzeichnisse</div>
                    <div style="display: grid; gap: 8px; font-size: 0.875rem;">
                        <div><?= $status['passes_dir'] ? '✅' : '❌' ?> Passes Directory: /wallet/passes</div>
                        <div><?= $status['templates_dir'] ? '✅' : '❌' ?> Templates Directory: /wallet/templates</div>
                    </div>
                </div>
                
                <!-- Database Stats -->
                <div style="background: var(--bg-secondary); padding: 20px; border-radius: 12px;">
                    <div style="font-weight: 700; margin-bottom: 12px;">📊 Statistiken</div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                        <div>
                            <div style="font-size: 2rem; font-weight: 800; color: var(--accent);"><?= $status['users_with_passes'] ?></div>
                            <div style="color: var(--text-secondary); font-size: 0.875rem;">Passes erstellt</div>
                        </div>
                        <div>
                            <div style="font-size: 2rem; font-weight: 800; color: var(--accent);"><?= $status['registrations'] ?></div>
                            <div style="color: var(--text-secondary); font-size: 0.875rem;">Device Registrierungen</div>
                        </div>
                        <div>
                            <div style="font-size: 2rem; font-weight: 800; color: var(--accent);"><?= $status['tokens'] ?></div>
                            <div style="color: var(--text-secondary); font-size: 0.875rem;">Auth Tokens</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <?php if (count($recent_activity) > 0): ?>
        <div class="section" style="margin-top: 32px;">
            <div class="section-header">
                <h2 class="section-title">Letzte Aktivität</h2>
            </div>
            
            <div style="background: var(--bg-secondary); border-radius: 12px; overflow: hidden;">
                <?php foreach ($recent_activity as $activity): ?>
                <div style="padding: 16px 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-weight: 600;"><?= escape($activity['name']) ?></div>
                        <div style="font-size: 0.875rem; color: var(--text-secondary);"><?= escape($activity['reason']) ?></div>
                    </div>
                    <div style="font-size: 0.875rem; color: var(--text-secondary);">
                        <?= date('d.m.Y H:i', strtotime($activity['last_modified'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Setup Instructions -->
        <div class="section" style="margin-top: 32px;">
            <div class="section-header">
                <h2 class="section-title">📖 Setup Anweisungen</h2>
            </div>
            
            <div style="background: var(--bg-secondary); padding: 24px; border-radius: 12px;">
                <h3 style="margin: 0 0 16px 0; font-size: 1.125rem;">Um Apple Wallet zu aktivieren:</h3>
                <ol style="margin: 0; padding-left: 24px; line-height: 2;">
                    <li><strong>Apple Developer Account</strong> erstellen ($99/Jahr)</li>
                    <li><strong>Pass Type ID</strong> erstellen: <code>pass.com.pushingp.crew</code></li>
                    <li><strong>Certificate</strong> generieren und konvertieren</li>
                    <li>Certificate nach <code><?= $status['cert_path'] ?></code> kopieren</li>
                    <li><strong>Team ID</strong> in <code>/includes/apple_wallet.php</code> eintragen</li>
                    <li><strong>Bilder</strong> erstellen (icon, logo, strip)</li>
                    <li><strong>Testen!</strong></li>
                </ol>
                
                <div style="margin-top: 20px; padding: 16px; background: var(--bg-tertiary); border-radius: 8px;">
                    <div style="font-weight: 700; margin-bottom: 8px;">📚 Ressourcen</div>
                    <div style="display: grid; gap: 8px; font-size: 0.875rem;">
                        <a href="/wallet/SETUP.md" target="_blank" style="color: var(--accent);">→ Vollständige Setup-Anleitung</a>
                        <a href="https://developer.apple.com/wallet/" target="_blank" style="color: var(--accent);">→ Apple Wallet Developer Docs</a>
                        <a href="/wallet/certs/README.md" target="_blank" style="color: var(--accent);">→ Certificate Anleitung</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
