# 🎮 Automatisches XP & Leveling System

## ✅ Status: VOLLSTÄNDIG IMPLEMENTIERT

Das XP-System läuft jetzt **vollautomatisch**!

---

## 🔄 Automatische XP-Vergabe

### 🎉 Events
| Aktion | XP | Trigger |
|--------|----|---------| 
| Event Teilnahme | +20 | Event zusagen |
| Event organisiert | +80 | Event erstellen |
| Event abgeschlossen | +30 | Event Status → completed |
| Event 10+ Teilnehmer | +25 | Großes Event |
| 5 Events Streak | +150 | 5 Events hintereinander |

### 💰 Kasse
| Aktion | XP | Trigger |
|--------|----|---------| 
| Pünktliche Zahlung | +30 | Monatsbeitrag gezahlt |
| Extra-Zahlung | +100 | je 10€ Einzahlung |
| Große Einzahlung | +1000 | 100€+ auf einmal |
| Ausgeglichen | +20 | Saldo ≥ 0 |
| Hoher Kassenstand | +80 | Saldo ≥ 100€ |

### 👥 Community
| Aktion | XP | Trigger |
|--------|----|---------| 
| Täglicher Login | +5 | Jeden Tag bei Login |
| 7-Tage Streak | +50 | 7 Tage am Stück |
| 30-Tage Streak | +200 | 30 Tage am Stück |
| Profil vollständig | +100 | Avatar + Bio + Telefon |
| Mitglied geworben | +500 | Neues Mitglied |

### 🔕 Strafen
| Aktion | XP | Trigger |
|--------|----|---------| 
| Inaktivität | -10/Tag | 30+ Tage nicht eingeloggt |
| Keine Event-Antwort | -5 | Event-Einladung ignoriert |
| Fake Activity | -500 | Betrugsversuch |

---

## 🏅 Automatische Badges

### 🎂 Membership
- **1 Jahr Crew** (500 XP) - 365 Tage Mitglied
- **2 Jahre Crew** (1000 XP) - 730 Tage Mitglied

### 🎉 Events  
- **Event Enthusiast** (250 XP) - 25 Events besucht
- **Event Master** (500 XP) - 50 Events besucht
- **Event Legend** (1000 XP) - 100 Events besucht
- **Event Organisator** (300 XP) - 10 Events erstellt

### 👥 Community
- **Talent Scout** (750 XP) - 3 Mitglieder geworben
- **Social Butterfly** (200 XP) - 30-Tage Login Streak
- **Treue Seele** (500 XP) - 90-Tage Login Streak

### 💰 Kasse
- **Großzügig** (400 XP) - 500€+ eingezahlt
- **Schuldenfrei** (300 XP) - 6 Monate keine Rückstände

### 🏆 Achievements
- **Level 5 erreicht** (100 XP) - Trusted Status
- **Level 10 erreicht** (500 XP) - Legend Status
- **XP Master** (250 XP) - 10.000+ XP gesammelt

---

## 🔧 Technische Integration

### API-Endpoints mit XP-Hooks
✅ `/api/event_join.php` - Event-Teilnahme
✅ `/api/kasse_add.php` - Transaktionen
✅ `login.php` - Login-Tracking

### Automatische Prozesse
✅ **Login:** XP-Vergabe bei jedem Login
✅ **Events:** XP bei Zusage/Erstellung
✅ **Kasse:** XP bei Zahlungen
✅ **Badges:** Automatische Prüfung & Vergabe

### Cron-Job (täglich 2:00 Uhr)
```bash
0 2 * * * /usr/bin/php /var/www/html/includes/xp_cron.php
```

Prüft täglich:
- Inaktivitäts-Strafen
- Payment-Streaks
- Milestone-Badges
- Level-Badges
- XP-Milestones

---

## 📁 Dateien

### Core System
- `/includes/xp_system.php` - Haupt-XP-Funktionen
- `/includes/event_xp_hooks.php` - Event-Hooks
- `/includes/kasse_xp_hooks.php` - Kassen-Hooks
- `/includes/xp_cron.php` - Cron-Job

### Admin Interface
- `/admin_xp.php` - XP System Admin
- `/admin_user_xp.php` - User XP Details

### Frontend
- `/dashboard.php` - XP-Anzeige
- `/leaderboard.php` - Rankings

---

## 🎯 Wie es funktioniert

### 1. User loggt sich ein
→ `login.php` ruft `track_login_xp()` auf
→ +5 XP für täglichen Login
→ Streak wird aktualisiert
→ Bei 7/30 Tagen: Bonus-XP

### 2. User sagt Event zu
→ `api/event_join.php` ruft `event_rsvp_hook()` auf
→ +20 XP für Teilnahme
→ Event-Streak wird geprüft
→ Bei 5 Events: +150 XP Bonus

### 3. User zahlt Monatsbeitrag
→ `api/kasse_add.php` ruft `transaction_added_hook()` auf
→ +30 XP für pünktliche Zahlung
→ Balance wird geprüft
→ Bei positivem Saldo: +20 XP

### 4. Nachts um 2 Uhr
→ Cron-Job läuft automatisch
→ Prüft Inaktivität
→ Vergibt Milestone-Badges
→ Aktualisiert Streaks

---

## 🧪 Testen

```bash
# Cron-Job manuell testen
php /var/www/html/includes/xp_cron.php

# XP-Funktionen testen
php -r "
require_once '/var/www/html/includes/db.php';
require_once '/var/www/html/includes/xp_system.php';
track_login_xp(USER_ID);
"
```

---

## 📊 Monitoring

### XP-Historie ansehen
Admin → XP System Admin → User auswählen → XP-Historie

### Aktuelle Actions
```sql
SELECT * FROM xp_actions WHERE is_active = 1;
```

### Badge-Status
```sql
SELECT u.name, COUNT(ub.id) as badges
FROM users u
LEFT JOIN user_badges ub ON u.id = ub.user_id
GROUP BY u.id;
```

---

## 🚀 Das System ist LIVE!

- ✅ Automatische XP-Vergabe aktiv
- ✅ Login-Tracking läuft
- ✅ Event-Integration aktiv
- ✅ Kassen-Integration aktiv
- ✅ Badge-System funktioniert
- ✅ Cron-Job eingerichtet
- ✅ Admin-Interface bereit

**User müssen nichts machen - alles läuft automatisch!** 🎉
