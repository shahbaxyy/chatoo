/**
 * MyHelpDesk Chat Widget
 * Frontend chat widget - pure vanilla JS, no jQuery.
 * Relies on `mhd_chat` localized object from WordPress.
 */
(function () {
    'use strict';

    /* ------------------------------------------------------------------ */
    /*  Config & State                                                     */
    /* ------------------------------------------------------------------ */
    const cfg = window.mhd_chat || {};
    const ajaxUrl = cfg.ajax_url || '';
    const nonce = cfg.nonce || '';
    const pollingBase = (cfg.polling_interval || 3) * 1000;
    const proactiveDelay = (cfg.proactive_delay || 30) * 1000;
    const soundEnabled = cfg.sound_enabled === '1' || cfg.sound_enabled === true;
    const proactiveEnabled = cfg.proactive_enabled === '1' || cfg.proactive_enabled === true;
    const proactiveMessage = cfg.proactive_message || '';
    const popupMessage = cfg.popup_message || '';

    let conversationId = localStorage.getItem('mhd_conversation_id') || null;
    let lastMessageId = 0;
    let pollingTimer = null;
    let typingTimer = null;
    let agentStatusTimer = null;
    let typingPollTimer = null;
    let currentPollingInterval = pollingBase;
    let unreadCount = 0;
    let isChatOpen = false;
    let emojiPickerVisible = false;
    let debounceTimer = null;

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    function $(sel, ctx) {
        return (ctx || document).querySelector(sel);
    }

    function $$(sel, ctx) {
        return (ctx || document).querySelectorAll(sel);
    }

    function setCookie(name, value, days) {
        const d = new Date();
        d.setTime(d.getTime() + days * 86400000);
        document.cookie = name + '=' + encodeURIComponent(value) + ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax';
    }

    function getCookie(name) {
        const v = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
        return v ? decodeURIComponent(v.pop()) : null;
    }

    function ensureGuestId() {
        let id = getCookie('mhd_guest_id');
        if (!id) {
            id = 'guest_' + Date.now() + '_' + Math.random().toString(36).slice(2, 10);
            setCookie('mhd_guest_id', id, 365);
        }
        return id;
    }

    async function ajax(action, data) {
        const body = new URLSearchParams();
        body.append('action', action);
        body.append('nonce', nonce);
        if (data) {
            Object.keys(data).forEach(function (k) {
                body.append(k, data[k]);
            });
        }
        try {
            const res = await fetch(ajaxUrl, { method: 'POST', body: body });
            return await res.json();
        } catch (e) {
            console.error('MHD AJAX error (' + action + '):', e);
            return { success: false, data: { message: 'Network error' } };
        }
    }

    async function ajaxFormData(action, formData) {
        formData.append('action', action);
        formData.append('nonce', nonce);
        try {
            const res = await fetch(ajaxUrl, { method: 'POST', body: formData });
            return await res.json();
        } catch (e) {
            console.error('MHD AJAX error (' + action + '):', e);
            return { success: false, data: { message: 'Network error' } };
        }
    }

    function scrollToBottom(el) {
        if (el) {
            el.scrollTop = el.scrollHeight;
        }
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /* ------------------------------------------------------------------ */
    /*  DOM references (resolved after DOMContentLoaded)                   */
    /* ------------------------------------------------------------------ */
    let widgetEl, bubbleEl, windowEl, closeBtn, preChatForm, chatBody,
        messagesContainer, composerEl, composerInput, sendBtn, searchInput,
        kbResults, kbSection, needHelpLink, emojiBtn, emojiPicker, fileBtn,
        fileInput, ratingWidget, offlineForm, typingIndicator, headerStatus,
        unreadBadge, popupBubble;

    /* ------------------------------------------------------------------ */
    /*  Initialise                                                         */
    /* ------------------------------------------------------------------ */
    document.addEventListener('DOMContentLoaded', init);

    function init() {
        widgetEl = $('.mhd-chat-widget');
        if (!widgetEl) return;

        bubbleEl = $('.mhd-chat-bubble', widgetEl);
        windowEl = $('.mhd-chat-window', widgetEl);
        closeBtn = $('.mhd-chat-close', widgetEl);
        preChatForm = $('.mhd-prechat-form', widgetEl);
        chatBody = $('.mhd-chat-body', widgetEl);
        messagesContainer = $('.mhd-chat-messages', widgetEl);
        composerEl = $('.mhd-chat-composer', widgetEl);
        composerInput = $('.mhd-chat-composer textarea', widgetEl);
        sendBtn = $('.mhd-chat-send', widgetEl);
        searchInput = $('.mhd-kb-search', widgetEl);
        kbResults = $('.mhd-kb-results', widgetEl);
        kbSection = $('.mhd-kb-section', widgetEl);
        needHelpLink = $('.mhd-need-help', widgetEl);
        emojiBtn = $('.mhd-emoji-btn', widgetEl);
        emojiPicker = $('.mhd-emoji-picker', widgetEl);
        fileBtn = $('.mhd-file-btn', widgetEl);
        fileInput = $('.mhd-file-input', widgetEl);
        ratingWidget = $('.mhd-rating-widget', widgetEl);
        offlineForm = $('.mhd-offline-form', widgetEl);
        typingIndicator = $('.mhd-typing-indicator', widgetEl);
        headerStatus = $('.mhd-agent-status', widgetEl);
        unreadBadge = $('.mhd-unread-badge', widgetEl);
        popupBubble = $('.mhd-popup-message', widgetEl);

        ensureGuestId();
        bindEvents();
        restoreSession();
        startAgentStatusPolling();

        if (proactiveEnabled && !conversationId) {
            setTimeout(showProactiveChat, proactiveDelay);
        }
        if (popupMessage && popupBubble) {
            setTimeout(function () {
                popupBubble.textContent = popupMessage;
                popupBubble.classList.add('mhd-popup-visible');
            }, 5000);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Event binding                                                      */
    /* ------------------------------------------------------------------ */
    function bindEvents() {
        if (bubbleEl) bubbleEl.addEventListener('click', toggleChat);
        if (closeBtn) closeBtn.addEventListener('click', toggleChat);

        if (preChatForm) preChatForm.addEventListener('submit', handlePreChatSubmit);

        if (sendBtn) sendBtn.addEventListener('click', sendMessage);
        if (composerInput) {
            composerInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });
            composerInput.addEventListener('input', handleTypingInput);
        }

        if (searchInput) searchInput.addEventListener('input', handleKbSearch);
        if (needHelpLink) needHelpLink.addEventListener('click', function (e) {
            e.preventDefault();
            if (kbSection) kbSection.style.display = 'none';
            showPreChatOrChat();
        });

        if (emojiBtn) emojiBtn.addEventListener('click', toggleEmojiPicker);
        if (fileBtn && fileInput) {
            fileBtn.addEventListener('click', function () { fileInput.click(); });
            fileInput.addEventListener('change', handleFileUpload);
        }

        if (offlineForm) offlineForm.addEventListener('submit', handleOfflineSubmit);

        document.addEventListener('visibilitychange', handleVisibilityChange);

        // Close emoji picker on outside click
        document.addEventListener('click', function (e) {
            if (emojiPickerVisible && emojiPicker && !emojiPicker.contains(e.target) && e.target !== emojiBtn) {
                emojiPicker.classList.remove('mhd-picker-open');
                emojiPickerVisible = false;
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Open / Close                                                       */
    /* ------------------------------------------------------------------ */
    function toggleChat() {
        isChatOpen = !isChatOpen;
        if (widgetEl) widgetEl.classList.toggle('mhd-chat-open', isChatOpen);
        if (popupBubble) popupBubble.classList.remove('mhd-popup-visible');

        if (isChatOpen) {
            unreadCount = 0;
            updateUnreadBadge();
            if (conversationId) {
                startPolling();
            }
        } else {
            stopPolling();
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Session restore                                                    */
    /* ------------------------------------------------------------------ */
    function restoreSession() {
        if (conversationId) {
            showChatView();
            loadExistingMessages();
            if (isChatOpen) startPolling();
        }
    }

    async function loadExistingMessages() {
        var resp = await ajax('mhd_get_messages', {
            conversation_id: conversationId,
            last_message_id: 0
        });
        if (resp.success && resp.data && resp.data.messages) {
            resp.data.messages.forEach(function (m) { appendMessage(m); });
            scrollToBottom(messagesContainer);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Pre-chat form                                                      */
    /* ------------------------------------------------------------------ */
    async function handlePreChatSubmit(e) {
        e.preventDefault();
        var name = ($('.mhd-prechat-name', preChatForm) || {}).value || '';
        var email = ($('.mhd-prechat-email', preChatForm) || {}).value || '';
        var dept = ($('.mhd-prechat-department', preChatForm) || {}).value || '';

        if (!name.trim() || !email.trim()) {
            showFormError(preChatForm, 'Name and email are required.');
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showFormError(preChatForm, 'Please enter a valid email.');
            return;
        }

        var resp = await ajax('mhd_start_conversation', {
            name: name.trim(),
            email: email.trim(),
            department: dept,
            guest_id: ensureGuestId()
        });

        if (resp.success && resp.data && resp.data.conversation_id) {
            conversationId = resp.data.conversation_id;
            localStorage.setItem('mhd_conversation_id', conversationId);
            showChatView();
            startPolling();
        } else {
            showFormError(preChatForm, (resp.data && resp.data.message) || 'Could not start conversation.');
        }
    }

    function showFormError(form, msg) {
        var el = $('.mhd-form-error', form);
        if (el) {
            el.textContent = msg;
            el.style.display = 'block';
        }
    }

    function showChatView() {
        if (preChatForm) preChatForm.style.display = 'none';
        if (kbSection) kbSection.style.display = 'none';
        if (offlineForm) offlineForm.style.display = 'none';
        if (chatBody) chatBody.style.display = 'flex';
        if (composerEl) composerEl.style.display = 'flex';
    }

    function showPreChatOrChat() {
        if (conversationId) {
            showChatView();
        } else if (preChatForm) {
            preChatForm.style.display = 'block';
            if (chatBody) chatBody.style.display = 'none';
            if (composerEl) composerEl.style.display = 'none';
        }
    }

    /* ------------------------------------------------------------------ */
    /*  KB Search (debounced)                                              */
    /* ------------------------------------------------------------------ */
    function handleKbSearch() {
        var query = (searchInput || {}).value || '';
        clearTimeout(debounceTimer);
        if (query.trim().length < 2) {
            if (kbResults) kbResults.innerHTML = '';
            return;
        }
        debounceTimer = setTimeout(async function () {
            var resp = await ajax('mhd_get_kb_articles', { query: query.trim() });
            if (resp.success && resp.data && resp.data.articles) {
                renderKbResults(resp.data.articles);
            }
        }, 300);
    }

    function renderKbResults(articles) {
        if (!kbResults) return;
        if (!articles.length) {
            kbResults.innerHTML = '<p class="mhd-kb-empty">No articles found.</p>';
            return;
        }
        var html = '';
        articles.forEach(function (a) {
            html += '<a class="mhd-kb-article" href="' + escapeHtml(a.url || '#') + '" target="_blank">' +
                '<span class="mhd-kb-title">' + escapeHtml(a.title) + '</span>' +
                '<span class="mhd-kb-excerpt">' + escapeHtml(a.excerpt || '') + '</span>' +
                '</a>';
        });
        kbResults.innerHTML = html;
    }

    /* ------------------------------------------------------------------ */
    /*  Send message                                                       */
    /* ------------------------------------------------------------------ */
    async function sendMessage() {
        if (!composerInput || !conversationId) return;
        var text = composerInput.value.trim();
        if (!text) return;

        // Optimistically append
        appendMessage({
            id: 'tmp_' + Date.now(),
            content: text,
            sender_type: 'visitor',
            created_at: new Date().toISOString()
        });
        composerInput.value = '';
        scrollToBottom(messagesContainer);

        var resp = await ajax('mhd_send_message', {
            conversation_id: conversationId,
            message: text,
            guest_id: ensureGuestId()
        });

        if (!resp.success) {
            appendSystemMessage('Failed to send message. Please try again.');
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Message rendering                                                  */
    /* ------------------------------------------------------------------ */
    function appendMessage(msg) {
        if (!messagesContainer) return;
        var div = document.createElement('div');
        div.className = 'mhd-message mhd-message-' + (msg.sender_type || 'visitor');
        div.setAttribute('data-id', msg.id);

        var bubble = document.createElement('div');
        bubble.className = 'mhd-message-bubble';

        if (msg.file_url) {
            var link = document.createElement('a');
            link.href = msg.file_url;
            link.target = '_blank';
            link.textContent = msg.file_name || 'Attachment';
            link.className = 'mhd-file-link';
            bubble.appendChild(link);
        } else {
            bubble.innerHTML = escapeHtml(msg.content || '').replace(/\n/g, '<br>');
        }

        var time = document.createElement('span');
        time.className = 'mhd-message-time';
        time.textContent = formatTime(msg.created_at);

        div.appendChild(bubble);
        div.appendChild(time);
        messagesContainer.appendChild(div);
    }

    function appendSystemMessage(text) {
        if (!messagesContainer) return;
        var div = document.createElement('div');
        div.className = 'mhd-message mhd-message-system';
        div.textContent = text;
        messagesContainer.appendChild(div);
        scrollToBottom(messagesContainer);
    }

    function formatTime(iso) {
        if (!iso) return '';
        try {
            var d = new Date(iso);
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } catch (_) {
            return '';
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Message polling                                                    */
    /* ------------------------------------------------------------------ */
    function startPolling() {
        stopPolling();
        poll();
    }

    function stopPolling() {
        clearTimeout(pollingTimer);
        clearTimeout(typingPollTimer);
    }

    async function poll() {
        if (!conversationId) return;
        try {
            var resp = await ajax('mhd_get_messages', {
                conversation_id: conversationId,
                last_message_id: lastMessageId
            });
            if (resp.success && resp.data && resp.data.messages && resp.data.messages.length) {
                resp.data.messages.forEach(function (m) {
                    if (typeof m.id === 'number' || (typeof m.id === 'string' && !m.id.startsWith('tmp_'))) {
                        var numId = parseInt(m.id, 10);
                        if (numId > lastMessageId) lastMessageId = numId;
                    }
                    appendMessage(m);
                });
                scrollToBottom(messagesContainer);

                if (!isChatOpen) {
                    unreadCount += resp.data.messages.length;
                    updateUnreadBadge();
                }

                // Notifications
                if (typeof window.MHDNotifications !== 'undefined') {
                    window.MHDNotifications.onNewMessage();
                }
            }
            // Check for ended conversation
            if (resp.data && resp.data.status === 'ended') {
                showRatingWidget();
                stopPolling();
                return;
            }
        } catch (e) {
            // Silently continue polling
        }
        pollingTimer = setTimeout(poll, currentPollingInterval);
        pollTypingIndicator();
    }

    async function pollTypingIndicator() {
        if (!conversationId) return;
        try {
            var resp = await ajax('mhd_get_typing', { conversation_id: conversationId });
            if (resp.success && resp.data && resp.data.typing && typingIndicator) {
                typingIndicator.textContent = 'Agent is typing…';
                typingIndicator.style.display = 'block';
            } else if (typingIndicator) {
                typingIndicator.style.display = 'none';
            }
        } catch (_) { /* ignore */ }
    }

    /* ------------------------------------------------------------------ */
    /*  Visibility change                                                  */
    /* ------------------------------------------------------------------ */
    function handleVisibilityChange() {
        if (document.hidden) {
            currentPollingInterval = 10000;
        } else {
            currentPollingInterval = pollingBase;
            if (typeof window.MHDNotifications !== 'undefined') {
                window.MHDNotifications.stopFlashing();
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Typing indicator (visitor)                                         */
    /* ------------------------------------------------------------------ */
    function handleTypingInput() {
        clearTimeout(typingTimer);
        if (!conversationId) return;
        ajax('mhd_set_typing', {
            conversation_id: conversationId,
            guest_id: ensureGuestId(),
            typing: 1
        });
        typingTimer = setTimeout(function () {
            ajax('mhd_set_typing', {
                conversation_id: conversationId,
                guest_id: ensureGuestId(),
                typing: 0
            });
        }, 3000);
    }

    /* ------------------------------------------------------------------ */
    /*  Agent status                                                       */
    /* ------------------------------------------------------------------ */
    function startAgentStatusPolling() {
        pollAgentStatus();
    }

    async function pollAgentStatus() {
        try {
            var resp = await ajax('mhd_get_agent_status', {});
            if (resp.success && resp.data) {
                var online = resp.data.online;
                if (headerStatus) {
                    headerStatus.textContent = online ? 'Online' : 'Offline';
                    headerStatus.className = 'mhd-agent-status ' + (online ? 'mhd-status-online' : 'mhd-status-offline');
                }
                if (!online && !conversationId && offlineForm) {
                    if (preChatForm) preChatForm.style.display = 'none';
                    offlineForm.style.display = 'block';
                }
            }
        } catch (_) { /* ignore */ }
        agentStatusTimer = setTimeout(pollAgentStatus, 5000);
    }

    /* ------------------------------------------------------------------ */
    /*  Emoji picker                                                       */
    /* ------------------------------------------------------------------ */
    var commonEmojis = [
        '😀','😃','😄','😁','😆','😅','😂','🤣','😊','😇',
        '🙂','🙃','😉','😌','😍','🥰','😘','😗','😙','😚',
        '😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔',
        '😐','😑','😶','😏','😒','🙄','😬','😮','😯','😲',
        '😳','🥺','😢','😭','😤','😠','😡','🤬','👍','👎',
        '👏','🙌','🤝','💪','❤️','🔥','⭐','✅','🎉','🙏'
    ];

    function toggleEmojiPicker() {
        if (!emojiPicker) return;
        emojiPickerVisible = !emojiPickerVisible;
        emojiPicker.classList.toggle('mhd-picker-open', emojiPickerVisible);

        if (emojiPickerVisible && !emojiPicker.dataset.built) {
            emojiPicker.dataset.built = '1';
            var grid = document.createElement('div');
            grid.className = 'mhd-emoji-grid';
            commonEmojis.forEach(function (em) {
                var span = document.createElement('span');
                span.className = 'mhd-emoji-item';
                span.textContent = em;
                span.addEventListener('click', function () {
                    insertEmoji(em);
                });
                grid.appendChild(span);
            });
            emojiPicker.appendChild(grid);
        }
    }

    function insertEmoji(emoji) {
        if (!composerInput) return;
        var start = composerInput.selectionStart;
        var end = composerInput.selectionEnd;
        var val = composerInput.value;
        composerInput.value = val.substring(0, start) + emoji + val.substring(end);
        composerInput.selectionStart = composerInput.selectionEnd = start + emoji.length;
        composerInput.focus();
    }

    /* ------------------------------------------------------------------ */
    /*  File upload                                                        */
    /* ------------------------------------------------------------------ */
    async function handleFileUpload() {
        if (!fileInput || !fileInput.files.length || !conversationId) return;
        var file = fileInput.files[0];
        var fd = new FormData();
        fd.append('file', file);
        fd.append('conversation_id', conversationId);
        fd.append('guest_id', ensureGuestId());

        appendSystemMessage('Uploading ' + file.name + '…');

        var resp = await ajaxFormData('mhd_upload_file', fd);
        if (resp.success && resp.data) {
            appendMessage({
                id: resp.data.message_id || ('file_' + Date.now()),
                content: '',
                file_url: resp.data.file_url,
                file_name: resp.data.file_name || file.name,
                sender_type: 'visitor',
                created_at: new Date().toISOString()
            });
            scrollToBottom(messagesContainer);
        } else {
            appendSystemMessage('File upload failed: ' + ((resp.data && resp.data.message) || 'Unknown error'));
        }
        fileInput.value = '';
    }

    /* ------------------------------------------------------------------ */
    /*  Rating widget                                                      */
    /* ------------------------------------------------------------------ */
    function showRatingWidget() {
        if (!ratingWidget) return;
        ratingWidget.style.display = 'block';
        var stars = $$('.mhd-rating-star', ratingWidget);
        stars.forEach(function (star) {
            star.addEventListener('click', function () {
                submitRating(parseInt(star.dataset.value, 10));
            });
        });
    }

    async function submitRating(value) {
        var comment = ($('.mhd-rating-comment', ratingWidget) || {}).value || '';
        var resp = await ajax('mhd_submit_rating', {
            conversation_id: conversationId,
            rating: value,
            comment: comment
        });
        if (resp.success && ratingWidget) {
            ratingWidget.innerHTML = '<p class="mhd-rating-thanks">Thank you for your feedback!</p>';
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Offline form                                                       */
    /* ------------------------------------------------------------------ */
    async function handleOfflineSubmit(e) {
        e.preventDefault();
        var name = ($('.mhd-offline-name', offlineForm) || {}).value || '';
        var email = ($('.mhd-offline-email', offlineForm) || {}).value || '';
        var message = ($('.mhd-offline-message', offlineForm) || {}).value || '';

        if (!name.trim() || !email.trim() || !message.trim()) {
            showFormError(offlineForm, 'All fields are required.');
            return;
        }

        var resp = await ajax('mhd_offline_form', {
            name: name.trim(),
            email: email.trim(),
            message: message.trim()
        });

        if (resp.success) {
            offlineForm.innerHTML = '<p class="mhd-offline-thanks">Your message has been sent. We will get back to you soon!</p>';
        } else {
            showFormError(offlineForm, (resp.data && resp.data.message) || 'Could not send message.');
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Unread badge                                                       */
    /* ------------------------------------------------------------------ */
    function updateUnreadBadge() {
        if (!unreadBadge) return;
        if (unreadCount > 0) {
            unreadBadge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            unreadBadge.style.display = 'flex';
        } else {
            unreadBadge.style.display = 'none';
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Proactive chat                                                     */
    /* ------------------------------------------------------------------ */
    function showProactiveChat() {
        if (isChatOpen || conversationId) return;
        if (popupBubble && proactiveMessage) {
            popupBubble.textContent = proactiveMessage;
            popupBubble.classList.add('mhd-popup-visible');
        }
        toggleChat();
    }

    /* ------------------------------------------------------------------ */
    /*  Public API (for notifications module)                              */
    /* ------------------------------------------------------------------ */
    window.MHDChat = {
        isOpen: function () { return isChatOpen; },
        getUnreadCount: function () { return unreadCount; }
    };
})();
