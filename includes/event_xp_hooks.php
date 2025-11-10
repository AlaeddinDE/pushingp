<?php
/**
 * Event XP Hooks
 * Automatically award XP for event-related actions
 */

require_once __DIR__ . '/xp_system.php';
require_once __DIR__ . '/db.php';

/**
 * Hook: When user RSVP to event (zusagen)
 */
function event_rsvp_hook($event_id, $user_id, $status) {
    if ($status === 'coming') {
        award_event_participation_xp($user_id, $event_id);
    }
}

/**
 * Hook: When event is created
 */
function event_created_hook($event_id, $organizer_id) {
    award_event_organizer_xp($organizer_id, $event_id);
}

/**
 * Hook: When event status changes to completed
 */
function event_completed_hook($event_id) {
    award_event_completion_xp($event_id);
}

/**
 * Hook: When user fails to respond to event
 */
function event_no_response_hook($event_id, $user_id) {
    global $conn;
    
    // Check if user saw the event but didn't respond
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM event_participants 
        WHERE event_id = ? AND mitglied_id = ? AND status = 'pending'
    ");
    $stmt->bind_param('ii', $event_id, $user_id);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    
    if ($count > 0) {
        add_xp($user_id, 'EVENT_NO_RESPONSE', 'Keine Antwort auf Event-Einladung', $event_id, 'events');
    }
}
