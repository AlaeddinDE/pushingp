<!-- Apple Wallet Widget -->
<?php
// Check if certificate exists
$cert_exists = file_exists(__DIR__ . '/../wallet/certs/pass_cert.p12');
?>

<?php if (!$cert_exists): ?>
<div style="background: linear-gradient(135deg, #fbbf24, #f59e0b); border-radius: 12px; padding: 16px 20px; margin: 24px 0; color: #000;">
    <div style="display: flex; align-items: start; gap: 12px;">
        <div style="font-size: 1.5rem;">⚠️</div>
        <div style="flex: 1;">
            <div style="font-weight: 700; margin-bottom: 4px;">Apple Wallet Setup erforderlich</div>
            <div style="font-size: 0.875rem; opacity: 0.9;">
                Das Apple Developer Certificate fehlt noch. Der Download-Button erstellt einen Demo-Pass, der aber nicht mit Apple Wallet funktioniert.
                <a href="/wallet/SETUP.md" style="color: #000; text-decoration: underline; font-weight: 600;">Setup-Anleitung ansehen</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="wallet-widget" style="background: linear-gradient(135deg, #000, #1a1a1a); border-radius: 16px; padding: 24px; margin: 24px 0; border: 1px solid #333;">
    <div style="display: flex; align-items: center; gap: 20px;">
        <!-- Wallet Icon -->
        <div style="flex-shrink: 0;">
            <svg width="60" height="60" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="60" height="60" rx="13" fill="url(#gradient)"/>
                <path d="M15 20C15 17.7909 16.7909 16 19 16H41C43.2091 16 45 17.7909 45 20V40C45 42.2091 43.2091 44 41 44H19C16.7909 44 15 42.2091 15 40V20Z" fill="white"/>
                <rect x="35" y="27" width="6" height="6" rx="3" fill="#000"/>
                <defs>
                    <linearGradient id="gradient" x1="0" y1="0" x2="60" y2="60">
                        <stop stop-color="#8B5CF6"/>
                        <stop offset="1" stop-color="#7C3AED"/>
                    </linearGradient>
                </defs>
            </svg>
        </div>
        
        <!-- Content -->
        <div style="flex: 1;">
            <h3 style="margin: 0 0 8px 0; font-size: 1.25rem; font-weight: 700; color: white;">
                Zur Apple Wallet hinzufügen
            </h3>
            <p style="margin: 0 0 16px 0; color: rgba(255,255,255,0.7); font-size: 0.9375rem; line-height: 1.5;">
                Deine Crew-Mitgliedskarte immer dabei. Mit Live-Updates zu XP, Level und Kassenstand.
            </p>
            
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <!-- Add to Apple Wallet Button -->
                <a href="/api/wallet/create" id="addToWalletBtn" style="display: inline-flex; align-items: center; gap: 8px; background: #000; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; border: 2px solid white; transition: all 0.2s;">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10 0C4.477 0 0 4.477 0 10s4.477 10 10 10 10-4.477 10-10S15.523 0 10 0zm5 11h-4v4H9v-4H5V9h4V5h2v4h4v2z"/>
                    </svg>
                    <span>Zur Wallet hinzufügen</span>
                </a>
                
                <!-- Info Button -->
                <button onclick="toggleWalletInfo()" style="background: rgba(255,255,255,0.1); color: white; padding: 10px 20px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2); cursor: pointer; font-weight: 600;">
                    ℹ️ Mehr erfahren
                </button>
            </div>
        </div>
    </div>
    
    <!-- Info Section (collapsed by default) -->
    <div id="walletInfo" style="display: none; margin-top: 24px; padding-top: 24px; border-top: 1px solid rgba(255,255,255,0.1);">
        <h4 style="color: white; margin: 0 0 12px 0; font-size: 1rem;">Was ist auf der Karte?</h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; color: rgba(255,255,255,0.8); font-size: 0.875rem;">
            <div>
                <strong style="color: white;">👤 Dein Name</strong><br>
                Crew-Mitglied Status
            </div>
            <div>
                <strong style="color: white;">🎮 Level & XP</strong><br>
                Aktueller Stand & Fortschritt
            </div>
            <div>
                <strong style="color: white;">💰 Kassenstand</strong><br>
                Dein aktueller Saldo
            </div>
            <div>
                <strong style="color: white;">🎉 Events</strong><br>
                Teilnahme-Counter
            </div>
            <div>
                <strong style="color: white;">📱 QR-Code</strong><br>
                Direktlink zu deinem Profil
            </div>
            <div>
                <strong style="color: white;">🔄 Live-Updates</strong><br>
                Automatische Aktualisierung
            </div>
        </div>
        
        <div style="margin-top: 20px; padding: 16px; background: rgba(139, 92, 246, 0.1); border-radius: 8px; border-left: 4px solid #8b5cf6;">
            <p style="margin: 0; color: rgba(255,255,255,0.9); font-size: 0.875rem;">
                <strong>💡 Tipp:</strong> Die Karte wird automatisch aktualisiert, wenn sich dein Level, XP oder Kassenstand ändert.
                Du musst nichts manuell machen!
            </p>
        </div>
    </div>
</div>

<script>
function toggleWalletInfo() {
    const info = document.getElementById('walletInfo');
    info.style.display = info.style.display === 'none' ? 'block' : 'none';
}

// Add to Wallet Handler
document.getElementById('addToWalletBtn').addEventListener('click', function(e) {
    e.preventDefault();
    
    // Show loading
    const btn = this;
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 20 20" style="animation: spin 1s linear infinite;"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="50" /></svg> <span>Erstelle Pass...</span>';
    btn.style.pointerEvents = 'none';
    
    // Download pass
    fetch('/api/wallet/create')
        .then(response => {
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.error || 'Failed to create pass');
                });
            }
            return response.blob();
        })
        .then(blob => {
            // Create download link
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'PushingP_Crew.pkpass';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            
            // Show success with warning about certificate
            btn.innerHTML = '✅ <span>Pass heruntergeladen!</span>';
            btn.style.background = '#10b981';
            
            // Show info about real certificate
            setTimeout(() => {
                alert('ℹ️ Demo-Pass heruntergeladen!\n\nHinweis: Dieser Pass funktioniert noch nicht mit Apple Wallet, da das Apple Developer Certificate fehlt.\n\nSiehe /wallet/SETUP.md für Anleitung.');
            }, 500);
            
            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.style.background = '#000';
                btn.style.pointerEvents = 'auto';
            }, 3000);
        })
        .catch(error => {
            console.error('Wallet error:', error);
            
            // Detailed error message
            let errorMsg = '❌ Fehler beim Erstellen des Passes.\n\n';
            if (error.message.includes('Certificate')) {
                errorMsg += 'Das Apple Developer Certificate fehlt.\nSiehe /wallet/SETUP.md für Setup-Anleitung.';
            } else if (error.message.includes('Not logged in')) {
                errorMsg += 'Bitte logge dich ein.';
            } else {
                errorMsg += 'Details: ' + error.message;
            }
            
            alert(errorMsg);
            btn.innerHTML = originalHTML;
            btn.style.pointerEvents = 'auto';
        });
});
</script>

<style>
@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>
