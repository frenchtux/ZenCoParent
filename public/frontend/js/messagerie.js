/* ============================================================
   ZenCoParent — Messagerie Page Logic
   Thread list + chat view
   ============================================================ */

(function (global) {
  'use strict';

  let threads       = [];
  let activeThread  = null;
  let messages      = [];
  let currentUser   = null;
  let users         = [];
  let pollTimer     = null;

  const POLL_INTERVAL = 10000; // 10s

  /* ── Init ─────────────────────────────────────────────────── */
  async function init(user) {
    currentUser = user;
    await loadUsers();
    await loadThreads();
    bindNewThreadButton();
    bindThreadForm();
    bindMessageSend();
    buildParticipantsSelect();
  }

  /* ── Data loading ─────────────────────────────────────────── */
  async function loadUsers() {
    try {
      const res = await api.get('/users');
      users = Array.isArray(res) ? res : (res && res.data ? res.data : []);
    } catch {
      users = [];
    }
  }

  async function loadThreads() {
    const listEl = document.getElementById('thread-list');
    listEl.innerHTML = '<div class="loading-state" style="padding:var(--space-6);"><div class="spinner spinner-sm"></div><span>Chargement...</span></div>';
    try {
      const res = await api.get('/threads');
      threads = Array.isArray(res) ? res : (res && res.data ? res.data : []);
      renderThreadList();
    } catch (err) {
      toast(err.message || 'Impossible de charger les conversations.', 'error');
      listEl.innerHTML = '<p style="padding:var(--space-4);font-size:var(--text-sm);color:var(--color-error);">Erreur de chargement.</p>';
    }
  }

  function renderThreadList() {
    const listEl = document.getElementById('thread-list');
    if (threads.length === 0) {
      listEl.innerHTML = `
        <div class="empty-state" style="padding: var(--space-8) var(--space-4);">
          <div class="empty-state-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="24" height="24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" /></svg>
          </div>
          <p class="empty-state-desc">Aucune conversation. Créez un nouveau fil de discussion.</p>
        </div>
      `;
      return;
    }

    listEl.innerHTML = threads.map(thread => {
      const isActive  = activeThread && activeThread.id === thread.id;
      const unread    = thread.unread_count || 0;
      const typeKey   = (thread.thread_type || thread.type || 'general').toLowerCase();
      const timeStr   = thread.last_message_at
        ? formatShortDate(thread.last_message_at)
        : '';
      const preview   = thread.last_message_preview || thread.description || '—';
      // Display: thread has no title in DB — use type label as title
      const displayTitle = thread.title || threadTypeLabel(typeKey);
      return `
        <div class="thread-item ${isActive ? 'active' : ''}" data-thread-id="${thread.id}" onclick="selectThread('${thread.id}')">
          <div class="avatar avatar-md ${typeAvatarColor(typeKey)}" style="flex-shrink:0;">
            ${escapeHtml(typeInitials(typeKey))}
          </div>
          <div class="thread-item-content">
            <div class="thread-item-title">${escapeHtml(displayTitle)}</div>
            <div class="thread-item-preview">${escapeHtml(preview)}</div>
          </div>
          <div class="thread-item-meta">
            ${timeStr ? `<span class="thread-item-time">${escapeHtml(timeStr)}</span>` : ''}
            ${unread > 0 ? `<span class="unread-badge">${unread}</span>` : ''}
          </div>
        </div>
      `;
    }).join('');
  }

  function typeAvatarColor(type) {
    if (type === 'parents') return 'avatar-green';
    if (type === 'family')  return 'avatar-accent';
    return 'avatar-info';
  }

  function typeInitials(type) {
    if (type === 'parents') return 'P';
    if (type === 'family')  return 'F';
    return 'G';
  }

  /* ── Select thread ────────────────────────────────────────── */
  global.selectThread = async function (threadId) {
    clearInterval(pollTimer);
    activeThread = threads.find(t => t.id === threadId) || null;
    renderThreadList();

    const chatEl   = document.getElementById('chat-area');
    const emptyEl  = document.getElementById('chat-empty');
    const headerEl = document.getElementById('chat-header');
    const inputEl  = document.getElementById('chat-input-area');

    emptyEl.style.display  = 'none';
    chatEl.style.display   = 'flex';
    headerEl.style.display = 'flex';
    inputEl.style.display  = 'flex';

    // Set header
    document.getElementById('chat-thread-title').textContent = activeThread
      ? (activeThread.title || 'Conversation')
      : '';
    const typeKey = activeThread
      ? (activeThread.thread_type || activeThread.type || 'general').toLowerCase()
      : '';
    document.getElementById('chat-thread-badge').textContent = threadTypeLabel(typeKey);
    document.getElementById('chat-thread-badge').className = `badge badge-primary`;

    await loadMessages(threadId);

    // Poll for new messages
    pollTimer = setInterval(() => loadMessages(threadId, true), POLL_INTERVAL);
  };

  async function loadMessages(threadId, silent = false) {
    const messagesEl = document.getElementById('messages-container');
    if (!silent) {
      messagesEl.innerHTML = '<div class="loading-state"><div class="spinner spinner-sm"></div><span>Chargement...</span></div>';
    }

    try {
      const res = await api.get(`/threads/${threadId}/messages`);
      messages = Array.isArray(res) ? res : (res && res.data ? res.data : []);
      renderMessages(messagesEl);

      // Mark as read (non-blocking)
      messages
        .filter(m => !m.is_read && m.sender_id !== currentUser.id)
        .forEach(m => {
          api.patch(`/threads/${threadId}/messages/${m.id}/read`, {}).catch(() => {});
        });

      // Update unread count in thread list
      const t = threads.find(t => t.id === threadId);
      if (t) t.unread_count = 0;
      renderThreadList();
    } catch (err) {
      if (!silent) {
        toast(err.message || 'Impossible de charger les messages.', 'error');
      }
    }
  }

  function renderMessages(container) {
    if (messages.length === 0) {
      container.innerHTML = `
        <div class="empty-state">
          <div class="empty-state-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="24" height="24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25" /></svg>
          </div>
          <p class="empty-state-desc">Aucun message dans cette conversation.<br>Soyez le premier à écrire !</p>
        </div>
      `;
      return;
    }

    // Group consecutive messages by sender
    let html = '';
    let lastSenderId = null;

    messages.forEach((msg, idx) => {
      const isMine = msg.sender_id === currentUser.id;
      const isSameSender = lastSenderId === msg.sender_id;
      const timeStr = formatTime(msg.created_at || msg.sent_at || '');

      if (!isSameSender) {
        if (idx > 0) html += '</div>'; // close previous group
        const senderName = isMine ? 'Vous' : (msg.sender_name || `Utilisateur ${msg.sender_id}`);
        html += `<div class="message-group ${isMine ? 'sent' : 'received'}">`;
        if (!isMine) {
          html += `<div class="message-sender">${escapeHtml(senderName)}</div>`;
        }
      }

      html += `
        <div class="message-bubble">${escapeHtml(msg.content || msg.body || '')}</div>
        ${timeStr ? `<div class="message-time">${escapeHtml(timeStr)}</div>` : ''}
      `;
      lastSenderId = msg.sender_id;

      // Close last group
      if (idx === messages.length - 1) {
        html += '</div>';
      }
    });

    container.innerHTML = html;
    container.scrollTop = container.scrollHeight;
  }

  /* ── Send message ─────────────────────────────────────────── */
  function bindMessageSend() {
    const input  = document.getElementById('message-input');
    const sendBtn = document.getElementById('send-btn');

    async function sendMessage() {
      if (!activeThread) return;
      const content = input.value.trim();
      if (!content) return;

      input.value = '';
      input.style.height = 'auto';
      setLoading(sendBtn, true);

      try {
        await api.post(`/threads/${activeThread.id}/messages`, { content });
        await loadMessages(activeThread.id, true);
      } catch (err) {
        toast(err.message || 'Erreur lors de l\'envoi.', 'error');
        input.value = content; // restore
      } finally {
        setLoading(sendBtn, false);
      }
    }

    sendBtn.addEventListener('click', sendMessage);

    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });

    // Auto-resize textarea
    input.addEventListener('input', function () {
      this.style.height = 'auto';
      this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
  }

  /* ── New Thread ───────────────────────────────────────────── */
  function bindNewThreadButton() {
    document.getElementById('btn-new-thread').addEventListener('click', () => {
      resetThreadForm();
      openModal('thread-modal');
    });
  }

  function buildParticipantsSelect() {
    const sel = document.getElementById('thread-participants');
    if (!sel) return;
    sel.innerHTML = users.map(u => {
      const name = `${u.first_name || ''} ${u.last_name || ''}`.trim() || u.email;
      return `<option value="${u.id}">${escapeHtml(name)}</option>`;
    }).join('');
  }

  function bindThreadForm() {
    document.getElementById('thread-form').addEventListener('submit', async function (e) {
      e.preventDefault();
      const btn = document.getElementById('thread-save-btn');
      setLoading(btn, true);

      const participantEls = document.getElementById('thread-participants');
      // participant IDs are UUIDs — do NOT parse as int
      const selectedParticipants = Array.from(participantEls.selectedOptions).map(o => o.value);

      const payload = {
        // API expects 'type', not 'thread_type'
        type: document.getElementById('thread-type').value,
        participant_ids: selectedParticipants,
      };

      if (!payload.type) {
        toast('Le type de conversation est requis.', 'warning');
        setLoading(btn, false);
        return;
      }

      try {
        const res = await api.post('/threads', payload);
        toast('Conversation créée.', 'success');
        closeModal('thread-modal');
        await loadThreads();
        // Select newly created thread
        const newThread = res && res.data ? res.data : res;
        if (newThread && newThread.id) {
          selectThread(newThread.id);
        }
      } catch (err) {
        toast(err.message || 'Erreur lors de la création.', 'error');
      } finally {
        setLoading(btn, false);
      }
    });
  }

  function resetThreadForm() {
    document.getElementById('thread-form').reset();
  }

  global.messageInit = init;
  global.messagePollStop = function () { clearInterval(pollTimer); };

})(window);
