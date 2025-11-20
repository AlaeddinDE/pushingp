<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$username = $_SESSION['username'] ?? 'User';
$name = $_SESSION['name'] ?? $username;
$is_admin = is_admin();
$page_title = 'Chat';

// Get all active users
$users_result = $conn->query("SELECT id, name, username FROM users WHERE status = 'active' AND id != $user_id ORDER BY name ASC");
$users = [];
while ($row = $users_result->fetch_assoc()) {
    $users[] = $row;
}

// Get groups (admins see all)
if ($is_admin) {
    $groups_result = $conn->query("
        SELECT g.id, g.name, g.created_by, g.created_at, g.is_protected,
               COUNT(DISTINCT cgm.user_id) as member_count
        FROM chat_groups g
        LEFT JOIN chat_group_members cgm ON cgm.group_id = g.id
        GROUP BY g.id, g.name, g.created_by, g.created_at, g.is_protected
        ORDER BY g.name ASC
    ");
} else {
    $groups_result = $conn->query("
        SELECT g.id, g.name, g.created_by, g.created_at, g.is_protected,
               COUNT(DISTINCT cgm.user_id) as member_count
        FROM chat_groups g
        LEFT JOIN chat_group_members cgm ON cgm.group_id = g.id
        WHERE cgm.user_id = $user_id OR g.created_by = $user_id
        GROUP BY g.id, g.name, g.created_by, g.created_at, g.is_protected
        ORDER BY g.name ASC
    ");
}
$groups = [];
while ($row = $groups_result->fetch_assoc()) {
    $groups[] = $row;
}

require_once __DIR__ . '/includes/header.php';
?>
<style>
    body { overflow: hidden; height: 100vh; }
    .container { max-height: 100vh; overflow: hidden; padding: 20px 0; }
    
    .chat-container {
        display: grid;
        grid-template-columns: 320px 1fr;
        gap: 0;
        height: calc(100vh - 140px);
        max-width: 1600px;
        margin: 0 auto;
        background: var(--bg-secondary);
        border-radius: 12px;
        overflow: hidden;
    }
    
    /* SIDEBAR */
    .chat-sidebar {
        background: var(--bg-tertiary);
        border-right: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    
    .chat-sidebar-header {
        padding: 16px;
        border-bottom: 1px solid var(--border);
    }
    
    .chat-tabs {
        display: flex;
        gap: 8px;
        padding: 12px 16px;
        border-bottom: 1px solid var(--border);
    }
    
    .chat-tab {
        padding: 8px 16px;
        background: transparent;
        border: none;
        color: var(--text-secondary);
        font-weight: 600;
        font-size: 0.875rem;
        cursor: pointer;
        border-radius: 6px;
        transition: all 0.2s;
    }
    
    .chat-tab.active {
        background: var(--accent);
        color: white;
    }
    
    .chat-list {
        flex: 1;
        overflow-y: auto;
        padding: 8px;
    }
    
    .chat-item {
        padding: 12px;
        background: var(--bg-secondary);
        border-radius: 8px;
        margin-bottom: 6px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .chat-item:hover, .chat-item.active {
        background: var(--accent);
        transform: translateX(4px);
    }
    
    .chat-item-name {
        font-weight: 600;
        font-size: 0.875rem;
    }
    
    /* MAIN CHAT */
    .chat-main {
        display: flex;
        flex-direction: column;
        height: 100%;
        position: relative;
    }
    
    .chat-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border);
        background: var(--bg-tertiary);
    }
    
    .chat-header-title {
        font-size: 1.25rem;
        font-weight: 700;
    }
    
    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 24px;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    
    /* MESSAGE BUBBLES - FIXED ALIGNMENT */
    .chat-message {
        display: flex;
        gap: 12px;
        max-width: 70%;
        width: fit-content;
    }
    
    /* OTHER MESSAGES - LEFT */
    .chat-message:not(.own) {
        align-self: flex-start;
        flex-direction: row;
    }
    
    /* OWN MESSAGES - RIGHT */
    .chat-message.own {
        align-self: flex-end;
        flex-direction: row-reverse;
    }
    
    .chat-message-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        color: white;
        font-size: 0.8rem;
        flex-shrink: 0;
    }
    
    .chat-message.own .chat-message-avatar {
        background: linear-gradient(135deg, var(--accent), #a855f7);
    }
    
    .chat-message-content {
        display: flex;
        flex-direction: column;
    }
    
    .chat-message.own .chat-message-content {
        align-items: flex-end;
    }
    
    .chat-message-bubble {
        background: var(--bg-tertiary);
        padding: 12px 16px;
        border-radius: 12px;
        word-wrap: break-word;
    }
    
    .chat-message.own .chat-message-bubble {
        background: var(--accent);
        color: white;
    }
    
    .chat-message-text {
        font-size: 0.9rem;
        line-height: 1.5;
    }
    
    .chat-message-meta {
        font-size: 0.7rem;
        color: var(--text-secondary);
        margin-top: 4px;
        padding: 0 4px;
    }
    
    /* INPUT AREA */
    .chat-input-area {
        padding: 16px 24px;
        border-top: 1px solid var(--border);
        background: var(--bg-tertiary);
    }
    
    .chat-input-wrapper {
        display: flex;
        gap: 12px;
        align-items: flex-end;
    }
    
    .chat-input {
        flex: 1;
        padding: 12px 16px;
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text-primary);
        font-size: 0.9rem;
        resize: none;
        max-height: 120px;
    }
    
    .chat-send-btn {
        padding: 12px 24px;
        background: var(--accent);
        border: none;
        border-radius: 8px;
        color: white;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .chat-send-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
    }
</style>

<div class="container">
    <div class="chat-container">
        <!-- SIDEBAR -->
        <div class="chat-sidebar">
            <div class="chat-sidebar-header">
                <h2 style="font-size: 1.25rem; font-weight: 700;">Chats</h2>
            </div>
            
            <div class="chat-tabs">
                <button class="chat-tab active" onclick="showTab('users')">Personen</button>
                <button class="chat-tab" onclick="showTab('groups')">Gruppen</button>
            </div>
            
            <div class="chat-list" id="usersList">
                <?php foreach ($users as $user): ?>
                    <div class="chat-item" onclick="openChat('user', <?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>')">
                        <div class="chat-item-name"><?= htmlspecialchars($user['name']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="chat-list" id="groupsList" style="display: none;">
                <?php foreach ($groups as $group): ?>
                    <div class="chat-item" onclick="openChat('group', <?= $group['id'] ?>, '<?= htmlspecialchars($group['name']) ?>')">
                        <div class="chat-item-name">👥 <?= htmlspecialchars($group['name']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- MAIN CHAT -->
        <div class="chat-main" id="chatMain" style="display: none;">
            <div class="chat-header">
                <div class="chat-header-title" id="chatTitle">Chat</div>
            </div>
            
            <div class="chat-messages" id="chatMessages"></div>
            
            <div class="chat-input-area">
                <div class="chat-input-wrapper">
                    <textarea id="messageInput" class="chat-input" placeholder="Nachricht schreiben..." rows="1"></textarea>
                    <button class="chat-send-btn" onclick="sendMessage()">Senden</button>
                </div>
            </div>
        </div>
        
        <div style="display: flex; align-items: center; justify-content: center; flex: 1; color: var(--text-secondary);" id="emptyState">
            <div style="text-align: center;">
                <div style="font-size: 3rem; margin-bottom: 16px;">��</div>
                <div style="font-size: 1.125rem; font-weight: 600; margin-bottom: 8px;">Wähle einen Chat</div>
                <div style="font-size: 0.875rem;">Starte eine Unterhaltung</div>
            </div>
        </div>
    </div>
</div>

<script>
    const userId = <?= $user_id ?>;
    let currentChatType = null;
    let currentChatId = null;
    let pollInterval = null;
    
    function showTab(tab) {
        document.querySelectorAll('.chat-tab').forEach(t => t.classList.remove('active'));
        event.target.classList.add('active');
        
        document.getElementById('usersList').style.display = tab === 'users' ? 'block' : 'none';
        document.getElementById('groupsList').style.display = tab === 'groups' ? 'block' : 'none';
    }
    
    function openChat(type, id, name) {
        currentChatType = type;
        currentChatId = id;
        
        document.getElementById('chatTitle').textContent = name;
        document.getElementById('emptyState').style.display = 'none';
        document.getElementById('chatMain').style.display = 'flex';
        
        document.querySelectorAll('.chat-item').forEach(item => item.classList.remove('active'));
        event.currentTarget.classList.add('active');
        
        loadMessages();
        
        if (pollInterval) clearInterval(pollInterval);
        pollInterval = setInterval(loadMessages, 3000);
    }
    
    async function loadMessages() {
        if (!currentChatId || !currentChatType) return;
        
        try {
            const response = await fetch(`/api/chat/get_messages.php?type=${currentChatType}&id=${currentChatId}`);
            const data = await response.json();
            
            if (data.status === 'success') {
                const container = document.getElementById('chatMessages');
                container.innerHTML = data.messages.map(msg => renderMessage(msg)).join('');
                container.scrollTop = container.scrollHeight;
            }
        } catch (error) {
            console.error('Error loading messages:', error);
        }
    }
    
    function renderMessage(msg) {
        const isOwn = msg.sender_id == userId;
        const initials = msg.sender_name ? msg.sender_name.split(' ').map(n => n[0]).join('').toUpperCase().substr(0, 2) : '?';
        const time = new Date(msg.created_at).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
        
        return `
            <div class="chat-message ${isOwn ? 'own' : ''}">
                <div class="chat-message-avatar">${initials}</div>
                <div class="chat-message-content">
                    <div class="chat-message-bubble">
                        ${!isOwn ? `<div style="font-weight: 600; font-size: 0.75rem; margin-bottom: 4px; opacity: 0.8;">${msg.sender_name}</div>` : ''}
                        <div class="chat-message-text">${escapeHtml(msg.message)}</div>
                    </div>
                    <div class="chat-message-meta">${time}</div>
                </div>
            </div>
        `;
    }
    
    async function sendMessage() {
        const input = document.getElementById('messageInput');
        const message = input.value.trim();
        
        if (!message || !currentChatId) return;
        
        try {
            const response = await fetch('/api/chat/send_message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: currentChatType,
                    id: currentChatId,
                    message: message
                })
            });
            
            const data = await response.json();
            if (data.status === 'success') {
                input.value = '';
                input.style.height = 'auto';
                loadMessages();
            }
        } catch (error) {
            console.error('Error sending message:', error);
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Auto-resize textarea
    document.getElementById('messageInput').addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
    
    // Enter to send
    document.getElementById('messageInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
