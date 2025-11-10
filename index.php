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
        
        .stock-widget {
            max-width: 800px;
            margin: 60px auto;
            padding: 32px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 16px;
            animation: fadeIn 1.8s ease;
        }
        
        .stock-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .stock-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .ticker {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-primary);
        }
        
        .stock-price {
            text-align: right;
        }
        
        .price {
            font-size: 2rem;
            font-weight: 900;
            color: var(--success);
        }
        
        .price-change {
            font-size: 0.875rem;
            color: var(--success);
            font-weight: 600;
        }
        
        .price-change.negative {
            color: var(--error);
        }
        
        #stockChart {
            width: 100%;
            height: 200px;
            margin-top: 20px;
        }
        
        .chart-info {
            display: flex;
            justify-content: space-between;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
            font-size: 0.875rem;
            color: var(--text-secondary);
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
                <h3 class="section-title">Crew</h3>
                <p class="text-secondary">Zentrale Crew-Verwaltung mit Rollen</p>
            </div>
        </div>
        
        <!-- Stock Price Widget -->
        <div class="stock-widget">
            <div class="stock-header">
                <div class="stock-title">
                    <span style="font-size: 1.5rem;">📈</span>
                    <div>
                        <div class="ticker">$PUSHP</div>
                        <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 4px;">PUSHING P CREW</div>
                    </div>
                </div>
                <div class="stock-price">
                    <div class="price" id="currentPrice">€10.00</div>
                    <div class="price-change" id="priceChange">+0.00 (0.00%)</div>
                </div>
            </div>
            
            <canvas id="stockChart"></canvas>
            
            <div class="chart-info">
                <span>24h Hoch: <strong id="high24h">€10.00</strong></span>
                <span>24h Tief: <strong id="low24h">€10.00</strong></span>
                <span>Volumen: <strong id="volume">1.2K</strong></span>
            </div>
        </div>
        
        <div class="footer">
            <p class="text-secondary">🔒 Sichere Platform für registrierte Mitglieder</p>
        </div>
    </div>
    
    <script>
        // Stock Chart Animation
        const canvas = document.getElementById('stockChart');
        const ctx = canvas.getContext('2d');
        
        // Responsive canvas
        canvas.width = canvas.offsetWidth * 2;
        canvas.height = 400;
        
        const basePrice = 10.00;
        let currentPrice = basePrice;
        let priceData = [];
        let animationFrame = 0;
        
        // Generate realistic stock data
        function generateStockData() {
            const points = 60;
            const data = [];
            let price = basePrice;
            
            for (let i = 0; i < points; i++) {
                const volatility = 0.15;
                const trend = Math.sin(i / 10) * 0.3;
                const random = (Math.random() - 0.5) * volatility;
                price = basePrice + trend + random;
                data.push(price);
            }
            
            return data;
        }
        
        priceData = generateStockData();
        currentPrice = priceData[priceData.length - 1];
        
        // Calculate stats
        const high24h = Math.max(...priceData);
        const low24h = Math.min(...priceData);
        const change = currentPrice - basePrice;
        const changePercent = (change / basePrice) * 100;
        
        // Update UI
        document.getElementById('currentPrice').textContent = '€' + currentPrice.toFixed(2);
        document.getElementById('priceChange').textContent = 
            (change >= 0 ? '+' : '') + change.toFixed(2) + ' (' + changePercent.toFixed(2) + '%)';
        document.getElementById('priceChange').classList.toggle('negative', change < 0);
        document.getElementById('high24h').textContent = '€' + high24h.toFixed(2);
        document.getElementById('low24h').textContent = '€' + low24h.toFixed(2);
        
        // Draw chart
        function drawChart() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            const padding = 40;
            const width = canvas.width - padding * 2;
            const height = canvas.height - padding * 2;
            
            const minPrice = Math.min(...priceData);
            const maxPrice = Math.max(...priceData);
            const priceRange = maxPrice - minPrice;
            
            // Draw grid
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.05)';
            ctx.lineWidth = 1;
            for (let i = 0; i <= 4; i++) {
                const y = padding + (height / 4) * i;
                ctx.beginPath();
                ctx.moveTo(padding, y);
                ctx.lineTo(canvas.width - padding, y);
                ctx.stroke();
            }
            
            // Draw gradient fill
            const gradient = ctx.createLinearGradient(0, padding, 0, canvas.height - padding);
            gradient.addColorStop(0, 'rgba(16, 185, 129, 0.2)');
            gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');
            
            ctx.fillStyle = gradient;
            ctx.beginPath();
            ctx.moveTo(padding, canvas.height - padding);
            
            priceData.forEach((price, i) => {
                const x = padding + (width / (priceData.length - 1)) * i;
                const y = padding + height - ((price - minPrice) / priceRange) * height;
                if (i === 0) ctx.lineTo(x, y);
                else ctx.lineTo(x, y);
            });
            
            ctx.lineTo(canvas.width - padding, canvas.height - padding);
            ctx.closePath();
            ctx.fill();
            
            // Draw line
            ctx.strokeStyle = '#10b981';
            ctx.lineWidth = 3;
            ctx.beginPath();
            
            priceData.forEach((price, i) => {
                const x = padding + (width / (priceData.length - 1)) * i;
                const y = padding + height - ((price - minPrice) / priceRange) * height;
                if (i === 0) ctx.moveTo(x, y);
                else ctx.lineTo(x, y);
            });
            
            ctx.stroke();
            
            // Draw current price dot
            const lastX = canvas.width - padding;
            const lastY = padding + height - ((currentPrice - minPrice) / priceRange) * height;
            
            ctx.fillStyle = '#10b981';
            ctx.beginPath();
            ctx.arc(lastX, lastY, 8, 0, Math.PI * 2);
            ctx.fill();
            
            ctx.strokeStyle = 'rgba(16, 185, 129, 0.3)';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(lastX, lastY, 12 + Math.sin(animationFrame / 10) * 3, 0, Math.PI * 2);
            ctx.stroke();
        }
        
        // Animate
        function animate() {
            animationFrame++;
            drawChart();
            
            // Add new data point every 2 seconds
            if (animationFrame % 120 === 0) {
                const volatility = 0.1;
                const change = (Math.random() - 0.5) * volatility;
                currentPrice = Math.max(8, Math.min(12, currentPrice + change));
                priceData.push(currentPrice);
                priceData.shift();
                
                // Update price display
                const priceChange = currentPrice - basePrice;
                const changePercent = (priceChange / basePrice) * 100;
                document.getElementById('currentPrice').textContent = '€' + currentPrice.toFixed(2);
                document.getElementById('priceChange').textContent = 
                    (priceChange >= 0 ? '+' : '') + priceChange.toFixed(2) + ' (' + changePercent.toFixed(2) + '%)';
                document.getElementById('priceChange').classList.toggle('negative', priceChange < 0);
            }
            
            requestAnimationFrame(animate);
        }
        
        animate();
    </script>
</body>
</html>
