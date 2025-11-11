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
$page_title = 'Events';

// Custom page styles - Apple iOS Style
ob_start();
?>
<style>
    /* Apple-Inspired Events Page */
    .events-container {
        max-width: 680px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .month-selector {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 16px;
        margin-bottom: 24px;
        padding: 12px;
        background: var(--bg-secondary);
        border-radius: 16px;
    }
    
    .month-btn {
        background: none;
        border: none;
        color: var(--accent);
        font-size: 1.125rem;
        font-weight: 600;
        cursor: pointer;
        padding: 8px 16px;
        border-radius: 8px;
        transition: all 0.2s;
    }
    
    .month-btn:hover {
        background: var(--bg-tertiary);
    }
    
    .month-label {
        font-size: 1.125rem;
        font-weight: 700;
        min-width: 160px;
        text-align: center;
    }
    
    .events-timeline {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .date-group {
        margin-bottom: 20px;
    }
    
    .date-header {
        position: sticky;
        top: 110px;
        z-index: 10;
        background: var(--bg-primary);
        padding: 12px 16px;
        margin: 0 -16px 8px -16px;
        border-bottom: 1px solid var(--border);
        backdrop-filter: blur(10px);
    }
    
    .date-header-day {
        font-size: 0.813rem;
        font-weight: 600;
        text-transform: uppercase;
        color: var(--text-secondary);
        letter-spacing: 0.5px;
    }
    
    .date-header-date {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--text-primary);
        margin-top: 2px;
    }
    
    .date-header.today .date-header-date {
        color: var(--accent);
    }
    
    .event-card {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 16px;
        transition: all 0.2s;
        cursor: pointer;
    }
    
    .event-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        border-color: var(--accent);
    }
    
    .event-card:active {
        transform: scale(0.98);
    }
    
    .event-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
    }
    
    .event-title {
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 4px;
    }
    
    .event-time {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        background: var(--bg-tertiary);
        border-radius: 8px;
        font-size: 0.813rem;
        font-weight: 600;
        color: var(--text-secondary);
    }
    
    .event-meta {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-bottom: 16px;
        font-size: 0.875rem;
        color: var(--text-secondary);
    }
    
    .event-meta-item {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .event-meta-icon {
        font-size: 1rem;
    }
    
    .event-actions {
        display: flex;
        gap: 8px;
        padding-top: 12px;
        border-top: 1px solid var(--border);
    }
    
    .event-btn {
        flex: 1;
        padding: 10px 16px;
        border-radius: 10px;
        font-size: 0.875rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    
    .event-btn.accept {
        background: var(--success);
        color: white;
    }
    
    .event-btn.accept:hover {
        background: #2d8a4d;
        transform: scale(1.02);
    }
    
    .event-btn.decline {
        background: var(--bg-tertiary);
        color: var(--text-secondary);
    }
    
    .event-btn.decline:hover {
        background: var(--error);
        color: white;
    }
    
    .event-btn.accepted {
        background: rgba(34, 197, 94, 0.15);
        color: var(--success);
        border: 1px solid var(--success);
    }
    
    .event-btn.declined {
        background: rgba(239, 68, 68, 0.15);
        color: var(--error);
        border: 1px solid var(--error);
    }
    
    .participants-list {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 12px;
    }
    
    .participant-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        background: var(--bg-tertiary);
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-secondary);
    }
    
    .participant-badge.accepted {
        background: rgba(34, 197, 94, 0.15);
        color: var(--success);
    }
    
    .participant-badge.declined {
        background: rgba(239, 68, 68, 0.15);
        color: var(--error);
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-secondary);
    }
    
    .empty-state-icon {
        font-size: 3rem;
        margin-bottom: 16px;
        opacity: 0.5;
    }
    
    .empty-state-text {
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 8px;
    }
    
    /* Admin Actions */
    .admin-actions {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid var(--border);
        display: flex;
        gap: 8px;
    }
    
    .admin-btn {
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 0.813rem;
        font-weight: 600;
        border: 1px solid var(--border);
        background: var(--bg-tertiary);
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .admin-btn:hover {
        background: var(--accent);
        color: white;
        border-color: var(--accent);
    }
    
    /* Create Event Button */
    .create-event-btn {
        width: 100%;
        padding: 16px 24px;
        background: linear-gradient(135deg, var(--accent), #a855f7);
        color: white;
        border: none;
        border-radius: 16px;
        font-size: 1rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        box-shadow: 0 4px 16px rgba(88, 101, 242, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .create-event-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(88, 101, 242, 0.4);
    }
    
    .create-event-btn:active {
        transform: scale(0.98);
    }
    
    /* Mobile Optimizations */
    @media (max-width: 768px) {
        .events-container {
            padding: 16px 12px;
        }
        
        .date-header {
            top: 60px;
        }
        
        .event-card {
            padding: 14px;
        }
        
        .event-title {
            font-size: 1rem;
        }
        
        .event-actions {
            flex-direction: column;
        }
        
        .event-btn {
            width: 100%;
        }
    }
</style>
<?php
$page_styles = ob_get_clean();

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="events-container">
        <div class="welcome">
            <h1>🎉 Events</h1>
            <p class="text-secondary">Deine kommenden Gruppenevents</p>
        </div>
        
        <div style="margin-bottom: 20px;">
            <button onclick="window.location.href='event_manager.php'" class="create-event-btn">
                ➕ Neues Event erstellen
            </button>
        </div>
        
        <div class="month-selector">
            <button class="month-btn" onclick="changeMonth(-1)">‹</button>
            <div class="month-label" id="monthLabel"></div>
            <button class="month-btn" onclick="changeMonth(1)">›</button>
        </div>
        
        <div class="events-timeline" id="eventsTimeline">
            <div class="empty-state">
                <div class="empty-state-icon">📅</div>
                <div class="empty-state-text">Lädt Events...</div>
            </div>
        </div>
    </div>
</div>

<script>
const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
const userId = <?php echo $user_id; ?>;
const monthLabel = document.getElementById('monthLabel');
const eventsTimeline = document.getElementById('eventsTimeline');

let currentDate = new Date();
let currentMonth = currentDate.getMonth();
let currentYear = currentDate.getFullYear();
let allEvents = [];

const monthNames = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 
                    'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
const weekdayNames = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];

function changeMonth(delta) {
    currentMonth += delta;
    if (currentMonth > 11) { 
        currentMonth = 0; 
        currentYear++; 
    } else if (currentMonth < 0) { 
        currentMonth = 11; 
        currentYear--; 
    }
    loadEvents();
}

async function loadEvents() {
    try {
        monthLabel.textContent = `${monthNames[currentMonth]} ${currentYear}`;
        
        const response = await fetch('/api/events_list.php');
        allEvents = await response.json();
        
        // Filter events for current month
        const monthEvents = allEvents.filter(event => {
            const eventDate = new Date(event.datum);
            return eventDate.getMonth() === currentMonth && eventDate.getFullYear() === currentYear;
        });
        
        renderEvents(monthEvents);
    } catch (error) {
        console.error('Fehler beim Laden:', error);
        eventsTimeline.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">❌</div>
                <div class="empty-state-text">Fehler beim Laden</div>
            </div>
        `;
    }
}

function renderEvents(events) {
    if (events.length === 0) {
        eventsTimeline.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <div class="empty-state-text">Keine Events in diesem Monat</div>
                <p class="text-secondary" style="margin-top: 8px;">Schau in einem anderen Monat nach</p>
            </div>
        `;
        return;
    }
    
    // Group events by date
    const grouped = events.reduce((acc, event) => {
        const date = event.datum;
        if (!acc[date]) acc[date] = [];
        acc[date].push(event);
        return acc;
    }, {});
    
    // Sort dates
    const sortedDates = Object.keys(grouped).sort();
    
    let html = '';
    const today = new Date().toISOString().split('T')[0];
    
    sortedDates.forEach(date => {
        const eventDate = new Date(date);
        const dayName = weekdayNames[eventDate.getDay()];
        const dayNum = eventDate.getDate();
        const isToday = date === today;
        
        html += `
            <div class="date-group">
                <div class="date-header ${isToday ? 'today' : ''}">
                    <div class="date-header-day">${dayName.toUpperCase()}</div>
                    <div class="date-header-date">${dayNum}${isToday ? ' • Heute' : ''}</div>
                </div>
        `;
        
        grouped[date].forEach(event => {
            html += renderEventCard(event);
        });
        
        html += '</div>';
    });
    
    eventsTimeline.innerHTML = html;
}

function renderEventCard(event) {
    const userResponse = event.participants?.find(p => p.mitglied_id == userId);
    const status = userResponse?.status || 'pending';
    
    const accepted = event.participants?.filter(p => p.status === 'accepted').length || 0;
    const declined = event.participants?.filter(p => p.status === 'declined').length || 0;
    const pending = event.participants?.filter(p => p.status === 'pending').length || 0;
    
    // Check if user is owner or admin
    const isOwner = event.created_by == userId;
    const canEdit = isAdmin || isOwner;
    
    return `
        <div class="event-card" onclick="toggleEventDetails(${event.id})">
            <div class="event-header">
                <div>
                    <div class="event-title">${escapeHtml(event.title)}</div>
                    ${event.start_time ? `<div class="event-time">🕐 ${event.start_time} Uhr</div>` : ''}
                </div>
            </div>
            
            <div class="event-meta">
                ${event.location ? `
                    <div class="event-meta-item">
                        <span class="event-meta-icon">📍</span>
                        <span>${escapeHtml(event.location)}</span>
                    </div>
                ` : ''}
                ${event.cost > 0 ? `
                    <div class="event-meta-item">
                        <span class="event-meta-icon">💰</span>
                        <span>${event.cost}€ pro Person</span>
                    </div>
                ` : ''}
                ${event.description ? `
                    <div class="event-meta-item">
                        <span class="event-meta-icon">📝</span>
                        <span>${escapeHtml(event.description)}</span>
                    </div>
                ` : ''}
            </div>
            
            ${status === 'pending' ? `
                <div class="event-actions">
                    <button class="event-btn accept" onclick="respondToEvent(event, ${event.id}, 'accepted')">
                        ✓ Zusagen
                    </button>
                    <button class="event-btn decline" onclick="respondToEvent(event, ${event.id}, 'declined')">
                        ✕ Absagen
                    </button>
                </div>
            ` : `
                <div class="event-actions">
                    <button class="event-btn ${status}" disabled>
                        ${status === 'accepted' ? '✓ Zugesagt' : '✕ Abgesagt'}
                    </button>
                </div>
            `}
            
            ${accepted + declined > 0 ? `
                <div class="participants-list">
                    <div class="participant-badge accepted">✓ ${accepted}</div>
                    <div class="participant-badge declined">✕ ${declined}</div>
                    ${pending > 0 ? `<div class="participant-badge">⏳ ${pending}</div>` : ''}
                </div>
            ` : ''}
            
            ${canEdit ? `
                <div class="admin-actions">
                    <button class="admin-btn" onclick="editEvent(event, ${event.id})">✏️ Bearbeiten</button>
                    ${isAdmin || isOwner ? `<button class="admin-btn" onclick="deleteEvent(event, ${event.id})">🗑️ Löschen</button>` : ''}
                </div>
            ` : ''}
        </div>
    `;
}

async function respondToEvent(e, eventId, status) {
    e.stopPropagation();
    
    try {
        const formData = new FormData();
        formData.append('event_id', eventId);
        formData.append('status', status);
        
        const response = await fetch('/api/event_respond.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.status === 'success') {
            loadEvents(); // Reload
        } else {
            alert('Fehler: ' + (result.error || 'Unbekannter Fehler'));
        }
    } catch (error) {
        console.error('Fehler:', error);
        alert('Netzwerkfehler beim Antworten');
    }
}

function toggleEventDetails(eventId) {
    // Optional: Expand event details
    console.log('Event clicked:', eventId);
}

function editEvent(e, eventId) {
    e.stopPropagation();
    window.location.href = `/event_manager.php?edit=${eventId}`;
}

function deleteEvent(e, eventId) {
    e.stopPropagation();
    if (confirm('Event wirklich löschen?')) {
        // Delete logic
        window.location.href = `/api/event_delete.php?id=${eventId}`;
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initial load
loadEvents();
</script>

</body>
</html>
