# 🎮 Leveling System - Implementation Documentation

## [2025-11-10] Complete XP/Level System Implementation

### ✅ **Created Database Tables**

1. **`level_config`** - Defines 11 levels (Rookie → Unantastbar)
2. **`xp_history`** - Tracks all XP gains/losses with full audit trail
3. **`badges`** - Badge definitions (11 badges defined)
4. **`user_badges`** - User badge awards (junction table)
5. **`user_streaks`** - Tracks login, event, and payment streaks
6. **`xp_actions`** - Configurable XP values for all actions

### ✅ **Added to `users` Table**
- `xp_total` (INT) - Total XP accumulated
- `level_id` (INT) - Current level
- `xp_multiplier` (DECIMAL) - Multiplier for special cases
- `badges_json` (JSON) - Badge metadata storage
- `last_xp_update` (TIMESTAMP) - Last XP change

### ✅ **Created Views**
- `v_xp_leaderboard` - Ranked leaderboard with user stats
- `v_user_xp_progress` - Individual XP progress with percentages

---

## 📊 **XP System Configuration**

### **Events**
- Event joined: **+20 XP**
- Event created: **+80 XP**
- Event completed: **+30 XP**
- Large event (≥10 participants): **+25 XP**
- Event streak (5 in a row): **+150 XP**

### **Finance**
- On-time payment: **+30 XP**
- Extra payment (per 10€): **+100 XP**
- Large deposit (100€): **+1000 XP**
- 3 months no debt: **+100 XP**
- 6 months no damage: **+300 XP**
- Positive balance (>100€): **+80 XP**
- Balance cleared: **+20 XP**

### **Community**
- Complete profile: **+100 XP**
- Daily login: **+5 XP** (max 1x/day)
- 7-day login streak: **+50 XP**
- 30-day login streak: **+200 XP**
- Member recruited: **+500 XP**

### **Penalties**
- Inactivity (per day after 30d): **-10 XP**
- Fake activity detected: **-500 XP**
- No event decision: **-15 XP**

---

## 🎖️ **Badges Defined**

1. **1 Jahr Crew** 🎂 (500 XP) - 365 days membership
2. **2 Jahre Crew** 🎉 (2000 XP) - 730 days membership
3. **Event Starter** 🎫 (100 XP) - 5 events attended
4. **Event Enthusiast** 🎪 (500 XP) - 25 events attended
5. **Event Legend** 🌟 (2000 XP) - 100 events attended
6. **Event Creator** 🎬 (300 XP) - 5 events created
7. **Recruiter** 👥 (1000 XP) - 3 members recruited
8. **Financial Hero** 💰 (800 XP) - 6 months no debt
9. **Großzügiger Spender** 💸 (1500 XP) - 500€ extra donations
10. **Daily Warrior** 🔥 (400 XP) - 30-day login streak
11. **Schadensfrei** ✨ (600 XP) - 12 months no damage

---

## 🔄 **Level Progression**

| Level | XP Needed | Title | Emoji |
|-------|-----------|-------|-------|
| 1 | 0 | Rookie | 🌱 |
| 2 | 250 | Staff | 👤 |
| 3 | 750 | Member | 🔥 |
| 4 | 1500 | Crew | 💪 |
| 5 | 3000 | Trusted | ⭐ |
| 6 | 5000 | Inner Circle | 💎 |
| 7 | 8000 | Elite | 👑 |
| 8 | 12000 | Ehrenmember | 🏆 |
| 9 | 18000 | Pushing Veteran | 🔱 |
| 10 | 25000 | Pushing Legend | ⚡ |
| 11 | 40000 | Unantastbar | 🌟 |

---

## 📁 **Files Created**

### **Core System**
- `/includes/xp_system.php` - Core XP functions (11.6 KB)

### **API Endpoints**
- `/api/v2/get_user_xp.php` - Get user XP & level info
- `/api/v2/get_leaderboard.php` - Get top users leaderboard
- `/api/v2/get_xp_history.php` - Get user XP transaction history

### **Pages**
- `/leaderboard.php` - Full leaderboard page with top 3 podium

### **Cron Jobs**
- `/api/cron/daily_xp_maintenance.php` - Daily badge checks & penalties

### **Database**
- `/migrations/auto/MIGRATION_20251110_leveling_system.sql` - Complete DB schema

---

## 🔗 **Integrations**

### **Automatic XP Tracking**
1. **Login** (`login.php`) - Awards daily login XP + streak tracking
2. **Events** (`api/events_join.php`) - Awards XP on event join
3. **Payments** (`api/einzahlung_buchen.php`) - Awards XP on deposits with bonuses

### **Dashboard Integration**
- Dashboard now shows:
  - Current level & XP
  - Progress bar to next level
  - Badge collection (first 5 displayed)
  - Link to leaderboard

---

## 🧪 **Testing**

✅ Database tables created successfully
✅ XP addition tested (5 XP + 200 XP from 10 events = 205 XP total)
✅ Level calculation working (82% to level 2)
✅ User views working correctly
✅ Badge system ready for auto-award

---

## 🚀 **Next Steps / Future Enhancements**

1. **Event Completion Hook** - Auto-award XP when event marked as completed
2. **Admin XP Panel** - Manual XP adjustment tool
3. **XP Notifications** - Real-time toast notifications on XP gain
4. **Monthly Leaderboard** - Reset monthly rankings
5. **Team XP** - Crew-based collective XP pools
6. **Special Events** - Double XP weekends/events
7. **XP Shop** - Redeem XP for perks (profile customization, etc.)

---

## 🔒 **Security & Rules**

- XP **cannot go below 0**
- Levels **cannot decrease** (only XP progress resets)
- Badges are **permanent** once earned
- All XP changes are **logged** in `xp_history`
- Admin actions are **tracked** with source user
- Daily cron job runs at **00:00** for maintenance

---

## 📊 **Usage Examples**

### Award XP
```php
require_once '/includes/xp_system.php';
add_xp($user_id, 'event_attended', 'Event: Summer Party', $event_id, 'events');
```

### Get User Level
```php
$info = get_user_level_info($user_id);
echo $info['current_level']; // "Rookie"
echo $info['xp_total']; // 205
```

### Get Leaderboard
```php
$top10 = get_leaderboard(10);
foreach ($top10 as $user) {
    echo $user['name'] . ': ' . $user['xp_total'] . ' XP';
}
```

---

**Status:** ✅ Fully Functional & Integrated
**Tested:** ✅ Core functions verified
**Migration:** ✅ Applied to production database
**Documentation:** ✅ Complete

---

_This leveling system is now live and tracking all user activity across events, payments, and community engagement._

---

## 🔧 Admin Management System

### Admin Pages Created

1. **`/admin_xp.php`** - Main Admin Dashboard
   - 5 Tabs: Users, History, Actions, Badges, Levels
   - Live statistics dashboard
   - Full XP management interface

2. **`/admin_user_xp.php`** - User Detail View
   - Complete XP history (100 entries)
   - All earned badges with dates
   - Activity statistics
   - Streak information
   - Quick admin actions

### Admin Capabilities

**User Management:**
- ✅ Award/deduct XP manually
- ✅ Reset user XP completely
- ✅ View detailed user statistics
- ✅ Award badges manually

**System Configuration:**
- ✅ Enable/disable XP actions
- ✅ Modify XP values
- ✅ View complete transaction history
- ✅ Monitor badge distribution
- ✅ Track level progression

**Analytics:**
- ✅ Total XP awarded
- ✅ Total transactions
- ✅ Badges distributed
- ✅ Active users per level
- ✅ Recent activity feed

### Admin API Endpoints

```
POST /api/v2/admin_award_xp.php
POST /api/v2/admin_reset_user_xp.php
POST /api/v2/admin_award_badge.php
POST /api/v2/admin_toggle_xp_action.php
POST /api/v2/admin_update_xp_action.php
```

### Access

Admin panel is accessible via:
- Header link: "⚙️ XP Admin" (admin-only)
- Direct URL: `https://pushingp.de/admin_xp.php`
- Requires `role = 'admin'` in session

---

**Last Updated:** 2025-11-10
**Status:** ✅ Production Ready
**Total Files Created:** 14
**Total Lines of Code:** ~2,500

