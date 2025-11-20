// PREMIUM CHAT FEATURES - PUSHING P

// ===== 1. VOICE MESSAGES =====
// Variables declared in chat.php: mediaRecorder, audioChunks

function startVoiceRecording() {
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
            alert('Mikrofon-Zugriff verweigert: ' + err);
        });
}

function stopVoiceRecording() {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
        mediaRecorder.stop();
        hideRecordingIndicator();
    }
}

function sendVoiceMessage(audioBlob) {
    const formData = new FormData();
    formData.append('audio', audioBlob, 'voice.webm');
    formData.append('receiver_id', currentChatId);
    
    fetch('/api/v2/chat_voice.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            loadMessages();
        }
    });
}

// ===== 2. EMOJI REACTIONS =====
function addReaction(messageId, emoji) {
    fetch('/api/v2/chat_reactions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=add&message_id=${messageId}&emoji=${encodeURIComponent(emoji)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            updateMessageReactions(messageId, data.reactions);
        }
    });
}

function showEmojiPicker(messageId) {
    const emojis = ['👍', '❤️', '😂', '😮', '😢', '🔥', '🎉', '👏'];
    const picker = document.createElement('div');
    picker.className = 'emoji-picker';
    picker.innerHTML = emojis.map(e => 
        `<span onclick="addReaction(${messageId}, '${e}')" style="cursor: pointer; font-size: 1.5rem; padding: 4px;">${e}</span>`
    ).join('');
    return picker;
}

// ===== 3. TYPING INDICATOR =====
let typingTimeout = null;

function onUserTyping() {
    fetch('/api/v2/chat_typing.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=start_typing&chat_type=user&chat_id=${currentChatId}`
    });
    
    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(() => {}, 3000);
}

function checkTypingStatus() {
    fetch('/api/v2/chat_typing.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=check_typing&chat_type=user&chat_id=${currentChatId}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.typing_users && data.typing_users.length > 0) {
            showTypingIndicator(data.typing_users);
        } else {
            hideTypingIndicator();
        }
    });
}

// Check typing every 2 seconds
setInterval(checkTypingStatus, 2000);

// ===== 4. MESSAGE EDITING =====
function editMessage(messageId, newText) {
    fetch('/api/v2/chat_edit.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `message_id=${messageId}&new_message=${encodeURIComponent(newText)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            loadMessages();
        }
    });
}

// ===== 5. MESSAGE SEARCH =====
function searchMessages(query) {
    fetch(`/api/v2/chat_search.php?q=${encodeURIComponent(query)}&chat_id=${currentChatId}`)
        .then(r => r.json())
        .then(data => {
            displaySearchResults(data.results);
        });
}

// ===== 6. GIF SUPPORT (Tenor API) =====
async function searchGIFs(query) {
    const apiKey = 'YOUR_TENOR_API_KEY'; // Get from tenor.com
    const response = await fetch(`https://tenor.googleapis.com/v2/search?q=${query}&key=${apiKey}&limit=20`);
    const data = await response.json();
    return data.results;
}

function insertGIF(gifUrl) {
    const message = `[GIF]${gifUrl}`;
    sendMessage(message);
}

// ===== 7. CODE BLOCKS with Syntax Highlighting =====
function formatCodeBlock(code, language = 'javascript') {
    return `\`\`\`${language}\n${code}\n\`\`\``;
}

function renderCodeBlocks(messageText) {
    return messageText.replace(/```(\w+)?\n([\s\S]+?)```/g, (match, lang, code) => {
        return `<pre><code class="language-${lang || 'text'}">${escapeHtml(code)}</code></pre>`;
    });
}

// ===== 8. @MENTIONS =====
function detectMentions(text) {
    return text.replace(/@(\w+)/g, '<span class="mention">@$1</span>');
}

// ===== 9. REPLY TO MESSAGE =====
let replyToMessageId = null;

function replyToMessage(messageId, messageText) {
    replyToMessageId = messageId;
    document.getElementById('replyPreview').innerHTML = `
        <div style="padding: 8px; background: var(--bg-tertiary); border-left: 3px solid var(--accent); margin-bottom: 8px;">
            <div style="font-size: 0.75rem; color: var(--text-secondary);">Antworten auf:</div>
            <div style="font-size: 0.875rem;">${messageText.substring(0, 50)}...</div>
            <button onclick="cancelReply()" style="position: absolute; right: 8px; top: 8px;">×</button>
        </div>
    `;
}

function cancelReply() {
    replyToMessageId = null;
    document.getElementById('replyPreview').innerHTML = '';
}

// ===== 10. FORWARD MESSAGE =====
function forwardMessage(messageId, toUserId) {
    fetch('/api/v2/chat_forward.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `message_id=${messageId}&to_user_id=${toUserId}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            alert('Nachricht weitergeleitet!');
        }
    });
}

// ===== 11. PIN MESSAGES =====
function pinMessage(messageId) {
    fetch('/api/v2/chat_pin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `message_id=${messageId}&action=pin`
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            showPinnedMessage(messageId);
        }
    });
}

// ===== 12. READ RECEIPTS =====
function markAsRead(messageId) {
    fetch('/api/v2/chat_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `message_id=${messageId}`
    });
}

// ===== 13. DARK MODE TOGGLE =====
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    localStorage.setItem('chatDarkMode', document.body.classList.contains('dark-mode'));
}

// Load dark mode preference
if (localStorage.getItem('chatDarkMode') === 'true') {
    document.body.classList.add('dark-mode');
}

// ===== 14. MESSAGE DELETION =====
function deleteMessage(messageId) {
    if (confirm('Nachricht wirklich löschen?')) {
        fetch('/api/v2/chat_delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `message_id=${messageId}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                document.querySelector(`[data-message-id="${messageId}"]`).remove();
            }
        });
    }
}

// ===== 15. AUTO-SCROLL TO BOTTOM =====
function scrollToBottom() {
    const messages = document.querySelector('.chat-messages');
    if (messages) {
        messages.scrollTop = messages.scrollHeight;
    }
}

// ===== HELPER FUNCTIONS =====
function showRecordingIndicator() {
    document.getElementById('recordingIndicator').style.display = 'flex';
}

function hideRecordingIndicator() {
    document.getElementById('recordingIndicator').style.display = 'none';
}

function showTypingIndicator(users) {
    const indicator = document.getElementById('typingIndicator');
    indicator.textContent = users.join(', ') + ' schreibt...';
    indicator.style.display = 'block';
}

function hideTypingIndicator() {
    document.getElementById('typingIndicator').style.display = 'none';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

console.log('🚀 PREMIUM CHAT FEATURES LOADED - PUSHING P');
