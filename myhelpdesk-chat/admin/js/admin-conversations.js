/**
 * MyHelpDesk Admin Conversations
 * Handles the conversations management page. jQuery allowed (WP bundled).
 */
(function ($) {
    'use strict';

    if (typeof $ === 'undefined') return;

    var cfg = window.mhd_admin || {};
    var ajaxUrl = cfg.ajax_url || '';
    var nonce = cfg.nonce || '';

    var activeConversationId = null;
    var lastUpdateTimestamp = 0;
    var lastThreadMessageId = 0;
    var pollTimer = null;
    var typingTimer = null;
    var savedRepliesVisible = false;
    var basePollingInterval = 2000;
    var currentPollingInterval = basePollingInterval;
    var currentFilter = { status: 'open', search: '', department: '' };

    /* ------------------------------------------------------------------ */
    /*  AJAX helper                                                        */
    /* ------------------------------------------------------------------ */
    function doAjax(action, data) {
        var payload = $.extend({ action: action, nonce: nonce }, data || {});
        return $.ajax({ url: ajaxUrl, type: 'POST', data: payload, dataType: 'json' });
    }

    /* ------------------------------------------------------------------ */
    /*  Init                                                               */
    /* ------------------------------------------------------------------ */
    $(document).ready(function () {
        loadConversations();
        startPolling();
        bindEvents();
        handleVisibility();
    });

    /* ------------------------------------------------------------------ */
    /*  Event binding                                                      */
    /* ------------------------------------------------------------------ */
    function bindEvents() {
        // Status tabs
        $(document).on('click', '.mhd-conv-tab', function (e) {
            e.preventDefault();
            $('.mhd-conv-tab').removeClass('active');
            $(this).addClass('active');
            currentFilter.status = $(this).data('status') || 'open';
            loadConversations();
        });

        // Search
        var searchDebounce;
        $(document).on('input', '.mhd-conv-search', function () {
            var val = $(this).val();
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(function () {
                currentFilter.search = val;
                loadConversations();
            }, 300);
        });

        // Department filter
        $(document).on('change', '.mhd-conv-dept-filter', function () {
            currentFilter.department = $(this).val();
            loadConversations();
        });

        // Click conversation
        $(document).on('click', '.mhd-conv-item', function () {
            var id = $(this).data('id');
            if (id) openConversation(id);
        });

        // Send reply
        $(document).on('click', '.mhd-reply-send', function () {
            sendReply(false);
        });

        // Send note
        $(document).on('click', '.mhd-reply-note', function () {
            sendReply(true);
        });

        // Enter to send (Shift+Enter for newline)
        $(document).on('keydown', '.mhd-reply-composer textarea', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendReply(false);
            }
        });

        // Saved replies trigger "/"
        $(document).on('keyup', '.mhd-reply-composer textarea', function (e) {
            var val = $(this).val();
            if (val.charAt(val.length - 1) === '/' && val.length === 1) {
                showSavedReplies('');
            } else if (savedRepliesVisible) {
                var match = val.match(/\/(\S*)$/);
                if (match) {
                    showSavedReplies(match[1]);
                } else {
                    hideSavedReplies();
                }
            }
        });

        // Select saved reply
        $(document).on('click', '.mhd-saved-reply-item', function () {
            var content = $(this).data('content');
            var $textarea = $('.mhd-reply-composer textarea');
            var current = $textarea.val().replace(/\/\S*$/, '');
            $textarea.val(current + content);
            hideSavedReplies();
            $textarea.focus();
        });

        // Assign agent
        $(document).on('change', '.mhd-assign-agent', function () {
            if (!activeConversationId) return;
            doAjax('mhd_assign_agent', {
                conversation_id: activeConversationId,
                agent_id: $(this).val()
            });
        });

        // Assign department
        $(document).on('change', '.mhd-assign-department', function () {
            if (!activeConversationId) return;
            doAjax('mhd_assign_department', {
                conversation_id: activeConversationId,
                department_id: $(this).val()
            });
        });

        // Change status
        $(document).on('change', '.mhd-conv-status-select', function () {
            if (!activeConversationId) return;
            doAjax('mhd_change_conversation_status', {
                conversation_id: activeConversationId,
                status: $(this).val()
            }).done(function () { loadConversations(); });
        });

        // File upload
        $(document).on('click', '.mhd-admin-file-btn', function () {
            $('.mhd-admin-file-input').trigger('click');
        });
        $(document).on('change', '.mhd-admin-file-input', handleFileUpload);

        // Typing indicator
        $(document).on('input', '.mhd-reply-composer textarea', function () {
            clearTimeout(typingTimer);
            if (!activeConversationId) return;
            doAjax('mhd_set_typing', { conversation_id: activeConversationId, typing: 1 });
            typingTimer = setTimeout(function () {
                doAjax('mhd_set_typing', { conversation_id: activeConversationId, typing: 0 });
            }, 3000);
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Load conversations list                                            */
    /* ------------------------------------------------------------------ */
    function loadConversations() {
        doAjax('mhd_get_conversations_updates', {
            status: currentFilter.status,
            search: currentFilter.search,
            department: currentFilter.department,
            since: 0
        }).done(function (resp) {
            if (resp.success && resp.data && resp.data.conversations) {
                renderConversationsList(resp.data.conversations);
                lastUpdateTimestamp = resp.data.timestamp || Math.floor(Date.now() / 1000);
            }
        });
    }

    function renderConversationsList(conversations) {
        var $list = $('.mhd-conversations-list');
        if (!$list.length) return;
        if (!conversations.length) {
            $list.html('<p class="mhd-empty">No conversations found.</p>');
            return;
        }
        var html = '';
        $.each(conversations, function (_, c) {
            var active = c.id == activeConversationId ? ' mhd-conv-active' : '';
            var unread = c.unread ? ' mhd-conv-unread' : '';
            html += '<div class="mhd-conv-item' + active + unread + '" data-id="' + c.id + '">' +
                '<div class="mhd-conv-visitor">' + escapeHtml(c.visitor_name || 'Visitor') + '</div>' +
                '<div class="mhd-conv-preview">' + escapeHtml(c.last_message || '') + '</div>' +
                '<div class="mhd-conv-meta">' +
                    '<span class="mhd-conv-time">' + escapeHtml(c.updated_at || '') + '</span>' +
                    (c.department ? ' <span class="mhd-conv-dept">' + escapeHtml(c.department) + '</span>' : '') +
                '</div>' +
                '</div>';
        });
        $list.html(html);
    }

    /* ------------------------------------------------------------------ */
    /*  Open conversation thread                                           */
    /* ------------------------------------------------------------------ */
    function openConversation(id) {
        activeConversationId = id;
        lastThreadMessageId = 0;
        $('.mhd-conv-item').removeClass('mhd-conv-active');
        $('.mhd-conv-item[data-id="' + id + '"]').addClass('mhd-conv-active').removeClass('mhd-conv-unread');

        doAjax('mhd_get_messages', {
            conversation_id: id,
            last_message_id: 0
        }).done(function (resp) {
            if (resp.success && resp.data) {
                renderThread(resp.data.messages || []);
                renderSidebar(resp.data.conversation || {});
                markAsRead(id);
            }
        });
    }

    function renderThread(messages) {
        var $thread = $('.mhd-thread-messages');
        if (!$thread.length) return;
        var html = '';
        $.each(messages, function (_, m) {
            html += buildMessageHtml(m);
            var mid = parseInt(m.id, 10);
            if (mid > lastThreadMessageId) lastThreadMessageId = mid;
        });
        $thread.html(html);
        $thread.scrollTop($thread[0].scrollHeight);
    }

    function buildMessageHtml(m) {
        var cls = 'mhd-thread-msg mhd-msg-' + (m.sender_type || 'visitor');
        if (m.is_note) cls += ' mhd-msg-note';
        var content = '';
        if (m.file_url) {
            content = '<a href="' + escapeHtml(m.file_url) + '" target="_blank" class="mhd-file-link">' +
                escapeHtml(m.file_name || 'Attachment') + '</a>';
        } else {
            content = escapeHtml(m.content || '').replace(/\n/g, '<br>');
        }
        return '<div class="' + cls + '" data-id="' + m.id + '">' +
            '<div class="mhd-msg-sender">' + escapeHtml(m.sender_name || '') + '</div>' +
            '<div class="mhd-msg-content">' + content + '</div>' +
            '<div class="mhd-msg-time">' + escapeHtml(m.created_at || '') + '</div>' +
            '</div>';
    }

    function renderSidebar(conv) {
        var $sidebar = $('.mhd-conv-sidebar');
        if (!$sidebar.length) return;
        $sidebar.find('.mhd-visitor-name').text(conv.visitor_name || 'Visitor');
        $sidebar.find('.mhd-visitor-email').text(conv.visitor_email || '');
        $sidebar.find('.mhd-assign-agent').val(conv.agent_id || '');
        $sidebar.find('.mhd-assign-department').val(conv.department_id || '');
        $sidebar.find('.mhd-conv-status-select').val(conv.status || 'open');
        $sidebar.show();
    }

    function markAsRead(convId) {
        doAjax('mhd_mark_read', { conversation_id: convId });
    }

    /* ------------------------------------------------------------------ */
    /*  Send reply / note                                                  */
    /* ------------------------------------------------------------------ */
    function sendReply(isNote) {
        if (!activeConversationId) return;
        var $textarea = $('.mhd-reply-composer textarea');
        var text = $textarea.val().trim();
        if (!text) return;

        doAjax('mhd_send_message', {
            conversation_id: activeConversationId,
            message: text,
            is_note: isNote ? 1 : 0
        }).done(function (resp) {
            if (resp.success) {
                $textarea.val('');
                // Append immediately
                var m = resp.data && resp.data.message ? resp.data.message : {
                    id: 'tmp_' + Date.now(),
                    content: text,
                    sender_type: 'agent',
                    sender_name: cfg.agent_name || 'You',
                    is_note: isNote,
                    created_at: new Date().toISOString()
                };
                var $thread = $('.mhd-thread-messages');
                $thread.append(buildMessageHtml(m));
                $thread.scrollTop($thread[0].scrollHeight);
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Saved replies                                                      */
    /* ------------------------------------------------------------------ */
    function showSavedReplies(query) {
        savedRepliesVisible = true;
        doAjax('mhd_get_saved_replies', { search: query }).done(function (resp) {
            if (!resp.success || !resp.data || !resp.data.replies) {
                hideSavedReplies();
                return;
            }
            var $dropdown = $('.mhd-saved-replies-dropdown');
            if (!$dropdown.length) {
                $dropdown = $('<div class="mhd-saved-replies-dropdown"></div>');
                $('.mhd-reply-composer').append($dropdown);
            }
            var html = '';
            $.each(resp.data.replies, function (_, r) {
                html += '<div class="mhd-saved-reply-item" data-content="' + escapeHtml(r.content) + '">' +
                    '<strong>' + escapeHtml(r.title) + '</strong>' +
                    '<span>' + escapeHtml(r.shortcut || '') + '</span>' +
                    '</div>';
            });
            $dropdown.html(html || '<div class="mhd-saved-reply-empty">No saved replies found.</div>').show();
        });
    }

    function hideSavedReplies() {
        savedRepliesVisible = false;
        $('.mhd-saved-replies-dropdown').hide();
    }

    /* ------------------------------------------------------------------ */
    /*  File upload                                                        */
    /* ------------------------------------------------------------------ */
    function handleFileUpload() {
        if (!activeConversationId) return;
        var files = this.files;
        if (!files || !files.length) return;

        var fd = new FormData();
        fd.append('action', 'mhd_upload_file');
        fd.append('nonce', nonce);
        fd.append('conversation_id', activeConversationId);
        fd.append('file', files[0]);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json',
            xhr: function () {
                var xhr = $.ajaxSettings.xhr();
                if (xhr.upload) {
                    xhr.upload.addEventListener('progress', function (e) {
                        if (e.lengthComputable) {
                            var pct = Math.round(e.loaded / e.total * 100);
                            $('.mhd-upload-progress').text('Uploading: ' + pct + '%').show();
                        }
                    });
                }
                return xhr;
            }
        }).done(function (resp) {
            $('.mhd-upload-progress').hide();
            if (resp.success && resp.data) {
                var m = {
                    id: resp.data.message_id || ('file_' + Date.now()),
                    content: '',
                    file_url: resp.data.file_url,
                    file_name: resp.data.file_name || files[0].name,
                    sender_type: 'agent',
                    sender_name: cfg.agent_name || 'You',
                    created_at: new Date().toISOString()
                };
                var $thread = $('.mhd-thread-messages');
                $thread.append(buildMessageHtml(m));
                $thread.scrollTop($thread[0].scrollHeight);
            }
        }).fail(function () {
            $('.mhd-upload-progress').hide();
        });
        $(this).val('');
    }

    /* ------------------------------------------------------------------ */
    /*  Real-time polling                                                  */
    /* ------------------------------------------------------------------ */
    function startPolling() {
        pollUpdates();
    }

    function pollUpdates() {
        doAjax('mhd_get_conversations_updates', {
            status: currentFilter.status,
            search: currentFilter.search,
            department: currentFilter.department,
            since: lastUpdateTimestamp
        }).done(function (resp) {
            if (resp.success && resp.data) {
                if (resp.data.conversations && resp.data.conversations.length) {
                    renderConversationsList(resp.data.conversations);
                }
                lastUpdateTimestamp = resp.data.timestamp || Math.floor(Date.now() / 1000);
            }
        }).always(function () {
            pollTimer = setTimeout(pollUpdates, currentPollingInterval);
        });

        // Also poll active thread
        if (activeConversationId) {
            pollThread();
        }
    }

    function pollThread() {
        doAjax('mhd_get_messages', {
            conversation_id: activeConversationId,
            last_message_id: lastThreadMessageId
        }).done(function (resp) {
            if (resp.success && resp.data && resp.data.messages && resp.data.messages.length) {
                var $thread = $('.mhd-thread-messages');
                $.each(resp.data.messages, function (_, m) {
                    $thread.append(buildMessageHtml(m));
                    var mid = parseInt(m.id, 10);
                    if (mid > lastThreadMessageId) lastThreadMessageId = mid;
                });
                $thread.scrollTop($thread[0].scrollHeight);
            }
            // Typing indicator
            if (resp.data && resp.data.visitor_typing) {
                $('.mhd-visitor-typing').text('Visitor is typing…').show();
            } else {
                $('.mhd-visitor-typing').hide();
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Visibility change                                                  */
    /* ------------------------------------------------------------------ */
    function handleVisibility() {
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                currentPollingInterval = 10000;
            } else {
                currentPollingInterval = basePollingInterval;
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Utility                                                            */
    /* ------------------------------------------------------------------ */
    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

})(jQuery);
