(function() {
/**
 * PUSHING P - Advanced Chat Features
 * Edit, Delete, Reactions, Pin, Search, Typing, Read Receipts, Sounds
 */

// ==================== SOUND EFFECTS ====================
const sendSound = new Audio('/sounds/a0.mp3');
const receiveSound = new Audio('/sounds/e5.mp3');
sendSound.volume = 0.3;
receiveSound.volume = 0.3;

// Play sound with error handling
function playSound(sound) {
    sound.currentTime = 0;
    sound.play().catch(e => console.log('Sound play failed:', e));
}

// ==================== MESSAGE EDITING ====================
let editingMessageId = null;

function showEditMessageModal(messageId, currentText) {
    editingMessageId = messageId;
    
    const modal = document.createElement('div');
    modal.className = 'modal-overlay active';
    modal.id = 'editMessageModal';
    
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-title">✏️ Nachricht bearbeiten</div>
            
            <div class="form-group">
                <textarea id="editMessageInput" class="form-input" style="min-height: 120px; resize: vertical;">${currentText}</textarea>
            </div>
            
            <div class="modal-buttons">
                <button class="btn-secondary" onclick="closeEditMessageModal()">Abbrechen</button>
                <button class="btn-primary" onclick="submitEditMessage()">Speichern</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    document.getElementById('editMessageInput').focus();
}

function closeEditMessageModal() {
    const modal = document.getElementById('editMessageModal');
    if (modal) modal.remove();
    editingMessageId = null;
}

async function submitEditMessage() {
    const newMessage = document.getElementById('editMessageInput').value.trim();
    
    if (!newMessage) {
        alert('Nachricht darf nicht leer sein!');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('message_id', editingMessageId);
        formData.append('new_message', newMessage);
        
        const response = await fetch('/api/v2/chat_edit.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            closeEditMessageModal();
            loadMessages();
        } else {
            alert('❌ Fehler: ' + data.error);
        }
    } catch (error) {
        alert('❌ Fehler: ' + error.message);
    }
}

// ==================== MESSAGE DELETION ====================
async function deleteMessage(messageId) {
    if (!confirm('🗑️ Nachricht wirklich löschen?')) return;
    
    try {
        const formData = new FormData();
        formData.append('message_id', messageId);
        
        const response = await fetch('/api/v2/chat_delete.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            loadMessages();
        } else {
            alert('❌ Fehler beim Löschen');
        }
    } catch (error) {
        alert('❌ Fehler: ' + error.message);
    }
}

// ==================== MESSAGE REACTIONS ====================
const reactionEmojis = ['👍', '❤️', '😂', '😮', '😢', '🔥', '🎉', '👏'];

function showReactionPicker(messageId, event) {
    event.stopPropagation();
    
    // Remove existing pickers
    document.querySelectorAll('.reaction-picker').forEach(p => p.remove());
    
    const picker = document.createElement('div');
    picker.className = 'reaction-picker';
    picker.style.cssText = `
        position: absolute;
        background: var(--bg-tertiary);
        border: 1px solid var(--border);
        border-radius: 24px;
        padding: 8px 12px;
        display: flex;
        gap: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        z-index: 10000;
        animation: scaleIn 0.2s ease-out;
    `;
    
    reactionEmojis.forEach(emoji => {
        const btn = document.createElement('button');
        btn.innerHTML = emoji;
        btn.style.cssText = `
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 4px;
            border-radius: 50%;
            transition: all 0.2s;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
        `;
        btn.onmouseover = () => btn.style.transform = 'scale(1.3)';
        btn.onmouseout = () => btn.style.transform = 'scale(1)';
        btn.onclick = () => toggleReaction(messageId, emoji);
        picker.appendChild(btn);
    });
    
    // Position picker
    const rect = event.target.getBoundingClientRect();
    picker.style.left = rect.left + 'px';
    picker.style.top = (rect.top - 60) + 'px';
    
    document.body.appendChild(picker);
    
    // Close on outside click
    setTimeout(() => {
        document.addEventListener('click', function closePickerHandler(e) {
            if (!picker.contains(e.target)) {
                picker.remove();
                document.removeEventListener('click', closePickerHandler);
            }
        });
    }, 100);
}

async function toggleReaction(messageId, emoji) {
    try {
        const formData = new FormData();
        formData.append('message_id', messageId);
        formData.append('emoji', emoji);
        formData.append('action', 'add');
        
        const response = await fetch('/api/v2/chat_reactions.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            // Remove picker
            document.querySelectorAll('.reaction-picker').forEach(p => p.remove());
            // Reload messages to show updated reactions
            loadMessages();
        }
    } catch (error) {
        console.error('Reaction error:', error);
    }
}

// ==================== MESSAGE PINNING ====================
async function togglePinMessage(messageId, isPinned) {
    try {
        const formData = new FormData();
        formData.append('message_id', messageId);
        formData.append('action', isPinned ? 'unpin' : 'pin');
        
        const response = await fetch('/api/v2/chat_pin.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            loadMessages();
        }
    } catch (error) {
        console.error('Pin error:', error);
    }
}

// ==================== TYPING INDICATOR ====================
let typingTimeout = null;

async function sendTypingIndicator() {
    if (!currentChatId || !currentChatType) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'start_typing');
        formData.append('chat_type', currentChatType);
        formData.append('chat_id', currentChatId);
        
        await fetch('/api/v2/chat_typing.php', {
            method: 'POST',
            body: formData
        });
    } catch (error) {
        console.error('Typing error:', error);
    }
}

async function checkTypingIndicator() {
    if (!currentChatId || !currentChatType) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'check_typing');
        formData.append('chat_type', currentChatType);
        formData.append('chat_id', currentChatId);
        
        const response = await fetch('/api/v2/chat_typing.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.status === 'success' && data.typing_users.length > 0) {
            showTypingIndicator(data.typing_users);
        } else {
            hideTypingIndicator();
        }
    } catch (error) {
        console.error('Check typing error:', error);
    }
}

function showTypingIndicator(users) {
    let indicator = document.getElementById('typingIndicatorInChat');
    
    if (!indicator) {
        const messagesContainer = document.getElementById('chatMessages');
        if (!messagesContainer) return;
        
        indicator = document.createElement('div');
        indicator.id = 'typingIndicatorInChat';
        indicator.style.cssText = `
            padding: 12px 16px;
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-style: italic;
            display: flex;
            align-items: center;
            gap: 8px;
        `;
        messagesContainer.appendChild(indicator);
    }
    
    const text = users.length === 1 
        ? `${users[0]} schreibt...` 
        : `${users.join(', ')} schreiben...`;
    
    indicator.innerHTML = `
        <span style="display: inline-flex; gap: 2px;">
            <span style="animation: typingDot 1.4s infinite;">●</span>
            <span style="animation: typingDot 1.4s infinite 0.2s;">●</span>
            <span style="animation: typingDot 1.4s infinite 0.4s;">●</span>
        </span>
        ${text}
    `;
    
    // Scroll to bottom
    const container = document.getElementById('chatMessages');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

function hideTypingIndicator() {
    const indicator = document.getElementById('typingIndicatorInChat');
    if (indicator) indicator.remove();
}

// ==================== SEARCH IN CHAT ====================
function showSearchModal() {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay active';
    modal.id = 'searchModal';
    
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-title">🔍 In Chat suchen</div>
            
            <div class="form-group">
                <input type="text" id="searchChatInput" class="form-input" placeholder="Suche nach Nachrichten..." autofocus />
            </div>
            
            <div id="searchResults" style="max-height: 400px; overflow-y: auto; margin-top: 16px;">
                <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                    Gib einen Suchbegriff ein
                </div>
            </div>
            
            <div class="modal-buttons" style="margin-top: 16px;">
                <button class="btn-secondary" onclick="closeSearchModal()">Schließen</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    const input = document.getElementById('searchChatInput');
    let searchTimeout;
    
    input.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            performSearch(this.value);
        }, 300);
    });
}

function closeSearchModal() {
    const modal = document.getElementById('searchModal');
    if (modal) modal.remove();
}

async function performSearch(query) {
    if (!query || !currentChatId) return;
    
    try {
        const response = await fetch(`/api/v2/chat_search.php?q=${encodeURIComponent(query)}&chat_id=${currentChatId}`);
        const data = await response.json();
        
        const resultsDiv = document.getElementById('searchResults');
        
        if (data.status === 'success' && data.results.length > 0) {
            resultsDiv.innerHTML = data.results.map(result => `
                <div class="search-result-item" onclick="scrollToMessage(${result.id})" style="
                    padding: 12px 16px;
                    background: var(--bg-secondary);
                    border-radius: 8px;
                    margin-bottom: 8px;
                    cursor: pointer;
                    transition: all 0.2s;
                " onmouseover="this.style.background='var(--accent)'" onmouseout="this.style.background='var(--bg-secondary)'">
                    <div style="font-size: 0.875rem; margin-bottom: 4px;">
                        ${escapeHtml(result.message)}
                    </div>
                    <div style="font-size: 0.75rem; color: var(--text-secondary);">
                        ${formatTime(result.created_at)}
                    </div>
                </div>
            `).join('');
        } else {
            resultsDiv.innerHTML = `
                <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                    Keine Ergebnisse gefunden
                </div>
            `;
        }
    } catch (error) {
        console.error('Search error:', error);
    }
}

function scrollToMessage(messageId) {
    closeSearchModal();
    
    const messageEl = document.querySelector(`[data-msg-id="${messageId}"]`);
    if (messageEl) {
        messageEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Highlight effect
        messageEl.style.backgroundColor = 'rgba(139, 92, 246, 0.3)';
        setTimeout(() => {
            messageEl.style.backgroundColor = '';
        }, 2000);
    }
}

// ==================== READ RECEIPTS ====================
// Create read receipts table on first load
async function initReadReceipts() {
    try {
        await fetch('/api/chat/init_read_receipts.php', { method: 'POST' });
    } catch (error) {
        console.error('Init read receipts error:', error);
    }
}

async function markMessagesAsRead() {
    if (!currentChatId || !currentChatType) return;
    
    try {
        const formData = new FormData();
        formData.append('chat_type', currentChatType);
        formData.append('chat_id', currentChatId);
        
        await fetch('/api/chat/mark_as_read.php', {
            method: 'POST',
            body: formData
        });
    } catch (error) {
        console.error('Mark as read error:', error);
    }
}

// ==================== CONTEXT MENU ====================
function showMessageContextMenu(event, messageId, messageText, senderId, isPinned) {
    event.preventDefault();
    event.stopPropagation();
    
    // Remove existing menus
    document.querySelectorAll('.message-context-menu').forEach(m => m.remove());
    
    const menu = document.createElement('div');
    menu.className = 'message-context-menu';
    menu.style.cssText = `
        position: fixed;
        background: var(--bg-tertiary);
        border: 1px solid var(--border);
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.4);
        z-index: 10000;
        min-width: 200px;
        overflow: hidden;
        animation: scaleIn 0.15s ease-out;
    `;
    
    const isOwn = senderId == userId;
    
    const items = [
        { icon: '😊', text: 'Reaktion', action: () => showReactionPicker(messageId, event) },
        { icon: '📌', text: isPinned ? 'Entpinnen' : 'Anpinnen', action: () => togglePinMessage(messageId, isPinned) },
        { icon: '🔍', text: 'Suchen', action: showSearchModal },
    ];
    
    if (isOwn) {
        items.push(
            { icon: '✏️', text: 'Bearbeiten', action: () => showEditMessageModal(messageId, messageText) },
            { icon: '🗑️', text: 'Löschen', action: () => deleteMessage(messageId), danger: true }
        );
    }
    
    items.forEach(item => {
        const btn = document.createElement('button');
        btn.innerHTML = `
            <span style="font-size: 1.25rem; margin-right: 12px;">${item.icon}</span>
            <span>${item.text}</span>
        `;
        btn.style.cssText = `
            width: 100%;
            padding: 12px 16px;
            background: none;
            border: none;
            color: ${item.danger ? 'var(--error)' : 'var(--text-primary)'};
            cursor: pointer;
            display: flex;
            align-items: center;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s;
        `;
        btn.onmouseover = () => btn.style.background = 'var(--bg-secondary)';
        btn.onmouseout = () => btn.style.background = 'none';
        btn.onclick = () => {
            item.action();
            menu.remove();
        };
        menu.appendChild(btn);
    });
    
    // Position menu
    menu.style.left = event.pageX + 'px';
    menu.style.top = event.pageY + 'px';
    
    document.body.appendChild(menu);
    
    // Close on outside click
    setTimeout(() => {
        document.addEventListener('click', function closeMenuHandler(e) {
            if (!menu.contains(e.target)) {
                menu.remove();
                document.removeEventListener('click', closeMenuHandler);
            }
        });
    }, 100);
}

// ==================== FORWARD MESSAGE ====================
function forwardMessage(messageId, messageText) {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay active';
    modal.id = 'forwardModal';
    
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-title">➡️ Nachricht weiterleiten</div>
            
            <div class="form-group">
                <label class="form-label">Wähle einen Chat:</label>
                <div class="checkbox-list" id="forwardChatList">
                    <!-- Filled by JS -->
                </div>
            </div>
            
            <div class="modal-buttons">
                <button class="btn-secondary" onclick="closeForwardModal()">Abbrechen</button>
                <button class="btn-primary" onclick="submitForward(${messageId}, '${escapeHtml(messageText).replace(/'/g, "\\'")}')">Weiterleiten</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Load available chats (simplified - would need proper API)
    const chatList = document.getElementById('forwardChatList');
    chatList.innerHTML = '<div style="padding: 20px; text-align: center;">Feature in Entwicklung</div>';
}

function closeForwardModal() {
    const modal = document.getElementById('forwardModal');
    if (modal) modal.remove();
}

// ==================== ANIMATIONS ====================
const style = document.createElement('style');
style.textContent = `
    @keyframes scaleIn {
        from { transform: scale(0.9); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }
    
    @keyframes typingDot {
        0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
        30% { transform: translateY(-10px); opacity: 1; }
    }
`;
document.head.appendChild(style);

// Add typing indicator to textarea and init read receipts
document.addEventListener('DOMContentLoaded', function() {
    // Wait for chat to be opened
    const checkInterval = setInterval(() => {
        const textarea = document.getElementById('messageInput');
        if (textarea && !textarea.dataset.typingListenerAdded) {
            textarea.dataset.typingListenerAdded = 'true';
            
            textarea.addEventListener('input', function() {
                clearTimeout(typingTimeout);
                sendTypingIndicator();
                
                typingTimeout = setTimeout(() => {
                    // Stop typing after 3 seconds
                }, 3000);
            });
            
            clearInterval(checkInterval);
        }
    }, 500);
    
    // Init read receipts table
    initReadReceipts();
});

// Add search button to chat header
function addSearchButton() {
    // Wait for chat to open
    setTimeout(function() {
        const chatHeader = document.querySelector('.chat-header');
        if (chatHeader && !document.getElementById('searchChatBtn')) {
            const searchBtn = document.createElement('button');
            searchBtn.id = 'searchChatBtn';
            searchBtn.innerHTML = '🔍';
            searchBtn.title = 'In Chat suchen';
            searchBtn.style.cssText = `
                background: none;
                border: none;
                color: var(--text-primary);
                font-size: 1.5rem;
                cursor: pointer;
                padding: 8px;
                border-radius: 8px;
                transition: all 0.2s;
            `;
            searchBtn.onmouseover = () => searchBtn.style.background = 'var(--bg-secondary)';
            searchBtn.onmouseout = () => searchBtn.style.background = 'none';
            searchBtn.onclick = showSearchModal;
            
            const headerContent = chatHeader.querySelector('div[style*="justify-content"]');
            if (headerContent) {
                // Insert before the hide button
                const hideBtn = headerContent.querySelector('button[onclick*="hideChat"]');
                if (hideBtn) {
                    headerContent.insertBefore(searchBtn, hideBtn);
                } else {
                    headerContent.appendChild(searchBtn);
                }
            }
        }
    }, 500);
}

// Monitor for chat opens
setInterval(function() {
    if (document.querySelector('.chat-header') && !document.getElementById('searchChatBtn')) {
        addSearchButton();
    }
}, 1000);

// ==================== SLASH COMMANDS ====================
async function handleSlashCommand(text) {
    if (!text.startsWith('/')) return false;
    
    const parts = text.split(' ');
    const command = parts[0].toLowerCase();
    const args = parts.slice(1).join(' ');
    
    const validCommands = ['/roll', '/flip', '/8ball', '/me', '/poll', '/spotify', '/music'];
    
    if (validCommands.includes(command)) {
        try {
            const formData = new FormData();
            formData.append('command', command);
            formData.append('args', args);
            formData.append('chat_type', currentChatType);
            formData.append('chat_id', currentChatId);
            
            const response = await fetch('/api/v2/chat_command.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            if (data.status === 'success') {
                document.getElementById('messageInput').value = '';
                loadMessages();
                playSound(sendSound);
                return true;
            }
        } catch (error) {
            console.error('Command error:', error);
        }
    }
    
    return false;
}

// ==================== INTEGRATION ====================
// Store original functions
let previousMessageCount = 0;

// Override functions immediately as they should be defined by now
if (typeof window.sendMessage === 'function') {
    const originalSendMessage = window.sendMessage;
    window.sendMessage = async function() {
        const input = document.getElementById('messageInput');
        const message = input.value.trim();
        
        // Check for slash command
        if (message.startsWith('/')) {
            const handled = await handleSlashCommand(message);
            if (handled) return;
        }
        
        await originalSendMessage();
        playSound(sendSound);
    };
}

// Enhance loadMessages to detect new messages and play sound
if (typeof window.loadMessages === 'function') {
    const originalLoadMessages = window.loadMessages;
    window.loadMessages = async function() {
        const messagesBefore = document.querySelectorAll('.chat-message').length;
        await originalLoadMessages();
        const messagesAfter = document.querySelectorAll('.chat-message').length;
        
        // If new messages appeared and we didn't just send one
        if (messagesAfter > messagesBefore && messagesAfter > previousMessageCount) {
            // Check if newest message is not from current user
            const lastMessage = document.querySelector('.chat-message:last-child');
            if (lastMessage && !lastMessage.classList.contains('own')) {
                playSound(receiveSound);
            }
        }
        
        previousMessageCount = messagesAfter;
        
        // Check typing indicator
        checkTypingIndicator();
        
        // Mark as read
        markMessagesAsRead();
    };
}

// Enhance renderMessage to add context menu and reactions
if (typeof window.renderMessage === 'function') {
    const originalRenderMessage = window.renderMessage;
    window.renderMessage = function(msg) {
        let html = originalRenderMessage(msg);
        
        // Escape message text for context menu
        const escapedMsg = (msg.message || '').replace(/'/g, "\\'").replace(/"/g, '&quot;').replace(/\n/g, ' ');
        
        // Add context menu trigger
        html = html.replace(
            'class="chat-message ',
            `class="chat-message " oncontextmenu="showMessageContextMenu(event, ${msg.id}, '${escapedMsg}', ${msg.sender_id}, false)" `
        );
        
        // Add reactions display if exists
        if (msg.reactions && msg.reactions.length > 0) {
            const reactionsHTML = `
                <div class="message-reactions" style="display: flex; gap: 4px; margin-top: 8px; flex-wrap: wrap;">
                    ${msg.reactions.map(r => `
                        <div class="reaction-badge" style="
                            background: var(--bg-secondary);
                            padding: 4px 8px;
                            border-radius: 12px;
                            font-size: 0.875rem;
                            display: flex;
                            align-items: center;
                            gap: 4px;
                            cursor: pointer;
                            transition: all 0.2s;
                        " onclick="toggleReaction(${msg.id}, '${r.emoji}')" title="${r.users}" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                            <span>${r.emoji}</span>
                            <span style="font-size: 0.75rem; color: var(--text-secondary);">${r.count}</span>
                        </div>
                    `).join('')}
                </div>
            `;
            
            // Insert reactions into placeholder
            const placeholder = `<div class="chat-message-reactions" id="reactions-${msg.id}"></div>`;
            if (html.includes(placeholder)) {
                html = html.replace(placeholder, reactionsHTML);
            } else {
                // Fallback for older cached versions or if placeholder missing
                const closingDivs = '</div>\n                    <div class="chat-message-meta">';
                if (html.includes(closingDivs)) {
                    html = html.replace(closingDivs, reactionsHTML + closingDivs);
                }
            }
        }
        
        // RENDER POLL
        if (msg.poll) {
            const poll = msg.poll;
            const pollHTML = `
                <div class="chat-poll" style="margin-top: 12px; background: rgba(0,0,0,0.2); padding: 16px; border-radius: 12px;">
                    <div style="font-weight: 700; margin-bottom: 12px;">${poll.question}</div>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        ${poll.options.map(opt => {
                            const percent = poll.total_votes > 0 ? Math.round((opt.votes / poll.total_votes) * 100) : 0;
                            const isVoted = opt.user_voted > 0;
                            return `
                                <div class="poll-option" onclick="votePoll(${poll.id}, ${opt.id})" style="cursor: pointer; position: relative; background: var(--bg-secondary); border-radius: 8px; overflow: hidden; border: 1px solid ${isVoted ? 'var(--accent)' : 'transparent'};">
                                    <div style="position: absolute; top: 0; left: 0; bottom: 0; width: ${percent}%; background: var(--accent); opacity: 0.2; transition: width 0.5s;"></div>
                                    <div style="position: relative; padding: 10px 12px; display: flex; justify-content: space-between; align-items: center; z-index: 1;">
                                        <span style="font-weight: 500;">${opt.option_text} ${isVoted ? '✅' : ''}</span>
                                        <span style="font-size: 0.8rem; opacity: 0.8;">${opt.votes} (${percent}%)</span>
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                    <div style="margin-top: 8px; font-size: 0.75rem; opacity: 0.6; text-align: right;">
                        ${poll.total_votes} Stimmen
                    </div>
                </div>
            `;
            
            // Insert poll after message text
            html = html.replace(
                '<div class="chat-message-text">',
                '<div class="chat-message-text">'
            ).replace(
                '</div>\n                         <div class="chat-message-meta">',
                pollHTML + '</div>\n                         <div class="chat-message-meta">'
            );
        }
        
        return html;
    };
}

// Poll Voting Function
async function votePoll(pollId, optionId) {
    try {
        const formData = new FormData();
        formData.append('poll_id', pollId);
        formData.append('option_id', optionId);
        
        const response = await fetch('/api/v2/chat_poll_vote.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        if (data.status === 'success') {
            loadMessages(); // Reload to show updated votes
        } else {
            alert('Fehler: ' + data.error);
        }
    } catch (error) {
        console.error('Vote error:', error);
    }
}

// ==================== VOICE MESSAGES ====================
// Variables expected to be global or managed here
let mediaRecorder = null;
let audioChunks = [];

function startVoiceRecording() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert('Dein Browser unterstützt keine Audio-Aufnahme.');
        return;
    }

    navigator.mediaDevices.getUserMedia({ audio: true })
        .then(stream => {
            mediaRecorder = new MediaRecorder(stream);
            audioChunks = [];
            
            mediaRecorder.ondataavailable = (e) => {
                audioChunks.push(e.data);
            };
            
            mediaRecorder.onstop = () => {
                const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                sendVoiceMessage(audioBlob);
                stream.getTracks().forEach(track => track.stop());
            };
            
            mediaRecorder.start();
            showRecordingIndicator();
        })
        .catch(err => {
            console.error('Microphone error:', err);
            alert('Mikrofon-Zugriff verweigert oder nicht verfügbar: ' + err.message);
        });
}

function stopVoiceRecording() {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
        mediaRecorder.stop();
        hideRecordingIndicator();
    }
}

function sendVoiceMessage(audioBlob) {
    if (!currentChatId) return;
    
    const formData = new FormData();
    formData.append('audio', audioBlob, 'voice.webm');
    formData.append('receiver_id', currentChatId);
    // Add group_id if needed, but chat_voice.php might need update or check
    if (currentChatType === 'group') {
        formData.append('group_id', currentChatId);
    }
    
    fetch('/api/v2/chat_voice.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            loadMessages();
            playSound(sendSound);
        } else {
            alert('Fehler beim Senden der Sprachnachricht: ' + (data.error || 'Unbekannt'));
        }
    })
    .catch(e => console.error('Voice upload error:', e));
}

function showRecordingIndicator() {
    const ind = document.getElementById('recordingIndicator');
    if (ind) ind.style.display = 'flex';
}

function hideRecordingIndicator() {
    const ind = document.getElementById('recordingIndicator');
    if (ind) ind.style.display = 'none';
}

// Make functions global
window.startVoiceRecording = startVoiceRecording;
window.stopVoiceRecording = stopVoiceRecording;
window.votePoll = votePoll;
window.showEditMessageModal = showEditMessageModal;
window.closeEditMessageModal = closeEditMessageModal;
window.submitEditMessage = submitEditMessage;
window.deleteMessage = deleteMessage;
window.showReactionPicker = showReactionPicker;
window.toggleReaction = toggleReaction;
window.togglePinMessage = togglePinMessage;
window.showSearchModal = showSearchModal;
window.closeSearchModal = closeSearchModal;
window.showMessageContextMenu = showMessageContextMenu;
window.forwardMessage = forwardMessage;
window.closeForwardModal = closeForwardModal;
window.addSearchButton = addSearchButton;
window.scrollToMessage = scrollToMessage;

console.log('✅ Advanced Chat Features loaded!');
})();
