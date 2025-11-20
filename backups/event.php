<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();

$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($event_id <= 0) {
    header('Location: /events.php');
    exit;
}

// Get event details
$stmt = $conn->prepare("SELECT id, title, datum, start_time, end_time, location, description, cost, created_by, event_status, created_at FROM events WHERE id = ?");
$stmt->bind_param('i', $event_id);
$stmt->execute();
$stmt->bind_result($id, $title, $datum, $start_time, $end_time, $location, $description, $cost, $created_by, $event_status, $created_at);

if (!$stmt->fetch()) {
    $stmt->close();
    header('Location: /events.php');
    exit;
}
$stmt->close();

// No image in table, use default
$image = null;

// Format date
try {
    $event_date = new DateTime($datum . ' ' . ($start_time ?? '00:00:00'));
    $formatted_date = $event_date->format('d.m.Y');
    $formatted_time = $event_date->format('H:i');
    $day_name = $event_date->format('l');
    $day_names = [
        'Monday' => 'Montag',
        'Tuesday' => 'Dienstag',
        'Wednesday' => 'Mittwoch',
        'Thursday' => 'Donnerstag',
        'Friday' => 'Freitag',
        'Saturday' => 'Samstag',
        'Sunday' => 'Sonntag'
    ];
    $day_name_de = $day_names[$day_name] ?? $day_name;
} catch (Exception $e) {
    $formatted_date = $datum;
    $formatted_time = '00:00';
    $day_name_de = '';
}

// Get participant count
$result = $conn->query("SELECT COUNT(*) as cnt FROM event_participants WHERE event_id = $event_id AND status = 'coming'");
$participant_count = 0;
if ($row = $result->fetch_assoc()) {
    $participant_count = $row['cnt'];
}

// Event image for Open Graph
$event_image_url = $image ? 'https://pushingp.de' . $image : 'https://pushingp.de/assets/event-default.jpg';
$share_url = 'https://pushingp.de/event.php?id=' . $event_id;
$page_title = htmlspecialchars($title) . ' – PUSHING P';
$page_description = ($day_name_de ? $day_name_de . ', ' : '') . $formatted_date . ' um ' . $formatted_time . ' Uhr' . ($location ? ' in ' . htmlspecialchars($location) : '');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
    <!-- Open Graph / Facebook / WhatsApp -->
    <meta property="og:type" content="event">
    <meta property="og:url" content="<?= $share_url ?>">
    <meta property="og:title" content="<?= htmlspecialchars($title) ?>">
    <meta property="og:description" content="<?= $page_description ?>">
    <meta property="og:image" content="<?= $event_image_url ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="PUSHING P">
    <meta property="og:locale" content="de_DE">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= $share_url ?>">
    <meta name="twitter:title" content="<?= htmlspecialchars($title) ?>">
    <meta name="twitter:description" content="<?= $page_description ?>">
    <meta name="twitter:image" content="<?= $event_image_url ?>">
    
    <!-- Discord Embed -->
    <meta name="theme-color" content="#8b5cf6">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .event-detail-hero {
            position: relative;
            height: 400px;
            background: linear-gradient(135deg, var(--bg-tertiary), var(--bg-secondary));
            border-radius: 20px;
            overflow: hidden;
            margin-bottom: 32px;
        }
        
        .event-detail-hero img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.7;
        }
        
        .event-detail-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(180deg, transparent, rgba(0,0,0,0.9));
            padding: 40px;
            color: white;
        }
        
        .share-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .share-btn {
            padding: 12px 24px;
            border-radius: 12px;
            border: none;
            font-weight: 700;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .share-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
        }
        
        .share-whatsapp {
            background: #25D366;
            color: white;
        }
        
        .share-telegram {
            background: #0088cc;
            color: white;
        }
        
        .share-discord {
            background: #5865F2;
            color: white;
        }
        
        .share-copy {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }
    </style>
</head>
<body>
    <div class="grain"></div>
    
    <div class="container" style="max-width: 900px;">
        <!-- Hero Section -->
        <div class="event-detail-hero">
            <?php if ($image): ?>
                <img src="<?= $image ?>" alt="<?= htmlspecialchars($title) ?>">
            <?php endif; ?>
            <div class="event-detail-overlay">
                <h1 style="font-size: 2.5rem; font-weight: 900; margin-bottom: 16px;">
                    <?= htmlspecialchars($title) ?>
                </h1>
                <div style="font-size: 1.25rem; opacity: 0.9;">
                    📅 <?= $day_name_de ?>, <?= $formatted_date ?> · 🕐 <?= $formatted_time ?> Uhr
                </div>
            </div>
        </div>
        
        <!-- Event Details -->
        <div class="section" style="margin-bottom: 32px;">
            <div style="display: grid; gap: 24px;">
                <?php if ($location): ?>
                <div>
                    <div style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 8px;">📍 Location</div>
                    <div style="font-size: 1.25rem; font-weight: 700;"><?= htmlspecialchars($location) ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($description): ?>
                <div>
                    <div style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 8px;">📝 Beschreibung</div>
                    <div style="font-size: 1rem; line-height: 1.6;"><?= nl2br(htmlspecialchars($description)) ?></div>
                </div>
                <?php endif; ?>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    <?php if ($cost > 0): ?>
                    <div style="background: var(--bg-tertiary); padding: 20px; border-radius: 12px; text-align: center;">
                        <div style="font-size: 2rem; font-weight: 800; color: #10b981;">
                            <?= number_format($cost, 2, ',', '.') ?> €
                        </div>
                        <div style="color: var(--text-secondary); font-size: 0.875rem; margin-top: 4px;">Kosten</div>
                    </div>
                    <?php endif; ?>
                    
                    <div style="background: var(--bg-tertiary); padding: 20px; border-radius: 12px; text-align: center;">
                        <div style="font-size: 2rem; font-weight: 800; color: var(--accent);">
                            <?= $participant_count ?>
                        </div>
                        <div style="color: var(--text-secondary); font-size: 0.875rem; margin-top: 4px;">Teilnehmer</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Share Section -->
        <div class="section">
            <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 20px;">📤 Event teilen</h2>
            
            <div class="share-buttons">
                <button class="share-btn share-whatsapp" onclick="shareWhatsApp()">
                    <span style="font-size: 1.5rem;">📱</span>
                    WhatsApp
                </button>
                
                <button class="share-btn share-telegram" onclick="shareTelegram()">
                    <span style="font-size: 1.5rem;">✈️</span>
                    Telegram
                </button>
                
                <button class="share-btn share-discord" onclick="shareDiscord()">
                    <span style="font-size: 1.5rem;">💬</span>
                    Discord
                </button>
                
                <button class="share-btn share-copy" onclick="copyLink()">
                    <span style="font-size: 1.5rem;">🔗</span>
                    Link kopieren
                </button>
            </div>
            
            <div id="copySuccess" style="display: none; margin-top: 16px; padding: 12px; background: #10b981; color: white; border-radius: 8px; font-weight: 600;">
                ✓ Link kopiert!
            </div>
        </div>
        
        <!-- CTA Button -->
        <a href="/events.php" style="display: block; margin-top: 32px; padding: 16px; background: var(--accent); color: white; text-align: center; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 1.125rem; transition: all 0.3s;"
           onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 24px rgba(139,92,246,0.4)';"
           onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
            🎉 Jetzt teilnehmen
        </a>
    </div>
    
    <script>
    const shareUrl = <?= json_encode($share_url) ?>;
    const eventTitle = <?= json_encode($title) ?>;
    const eventText = <?= json_encode($page_description) ?>;
    
    function shareWhatsApp() {
        const text = `🎉 *${eventTitle}*\n\n${eventText}\n\n🔗 ${shareUrl}`;
        window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank');
    }
    
    function shareTelegram() {
        const text = `🎉 ${eventTitle}\n\n${eventText}`;
        window.open(`https://t.me/share/url?url=${encodeURIComponent(shareUrl)}&text=${encodeURIComponent(text)}`, '_blank');
    }
    
    function shareDiscord() {
        // Discord doesn't have direct share API, so copy link and show instructions
        navigator.clipboard.writeText(shareUrl).then(() => {
            alert('🔗 Link kopiert!\n\nFüge den Link in Discord ein - die Vorschau wird automatisch angezeigt! 💬');
        });
    }
    
    function copyLink() {
        navigator.clipboard.writeText(shareUrl).then(() => {
            const success = document.getElementById('copySuccess');
            success.style.display = 'block';
            setTimeout(() => {
                success.style.display = 'none';
            }, 3000);
        });
    }
    </script>
</body>
</html>
