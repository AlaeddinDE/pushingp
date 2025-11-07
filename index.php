<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
if (is_logged_in()) { header('Location: dashboard.php'); exit; }
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PUSHING P</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .hero {
            text-align: center;
            margin-bottom: 80px;
            animation: fadeIn 0.8s ease;
        }
        
        .hero-logo {
            font-size: clamp(3rem, 8vw, 5rem);
            font-weight: 900;
            letter-spacing: -0.03em;
            margin-bottom: 16px;
            background: linear-gradient(135deg, #ffffff 0%, #b4b4b4 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: fadeIn 1s ease;
        }
        
        .tagline {
            font-size: 1.125rem;
            color: var(--text-secondary);
            font-weight: 400;
            margin-bottom: 48px;
            animation: fadeIn 1.2s ease;
        }
        
        .cta-button {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 16px 32px;
            background: var(--accent);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px var(--glow);
            animation: fadeIn 1.4s ease;
        }
        
        .cta-button:hover {
            background: var(--accent-hover);
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 12px 32px var(--glow);
        }
        
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .feature {
            animation: fadeIn 0.6s ease forwards;
            opacity: 0;
        }
        
        .feature:nth-child(1) { animation-delay: 0.2s; }
        .feature:nth-child(2) { animation-delay: 0.4s; }
        .feature:nth-child(3) { animation-delay: 0.6s; }
        .feature:nth-child(4) { animation-delay: 0.8s; }
        
        .footer {
            text-align: center;
            margin-top: 80px;
            padding-top: 40px;
            border-top: 1px solid var(--border);
            animation: fadeIn 1.6s ease;
        }
    </style>
</head>
<body>
    <div class="grain"></div>
    
    <div class="container">
        <div class="hero">
            <h1 class="hero-logo">PUSHING P</h1>
            <p class="tagline">Crew Management. Simplified.</p>
            <a href="login.php" class="cta-button">
                <span>Zum Dashboard</span>
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path d="M7.5 15L12.5 10L7.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </a>
        </div>
        
        <div class="features">
            <div class="card feature">
                <span class="stat-icon">💰</span>
                <h3 class="section-title">Kasse</h3>
                <p class="text-secondary">Transparente Finanzverwaltung mit Live-Tracking</p>
            </div>
            
            <div class="card feature">
                <span class="stat-icon">📅</span>
                <h3 class="section-title">Schichten</h3>
                <p class="text-secondary">Intelligente Schichtplanung und Management</p>
            </div>
            
            <div class="card feature">
                <span class="stat-icon">🎉</span>
                <h3 class="section-title">Events</h3>
                <p class="text-secondary">Event-Organisation mit Crew-Verfügbarkeit</p>
            </div>
            
            <div class="card feature">
                <span class="stat-icon">👥</span>
                <h3 class="section-title">Team</h3>
                <p class="text-secondary">Zentrale Crew-Verwaltung mit Rollen</p>
            </div>
        </div>
        
        <div class="footer">
            <p class="text-secondary">🔒 Sichere Platform für registrierte Mitglieder</p>
        </div>
    </div>
</body>
</html>
