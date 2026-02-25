/**
 * MyHelpDesk Admin Tickets
 * Handles the tickets management page. jQuery allowed (WP bundled).
 */
(function ($) {
    'use strict';

    if (typeof $ === 'undefined') return;

    var cfg = window.mhd_admin || {};
    var ajaxUrl = cfg.ajax_url || '';
    var nonce = cfg.nonce || '';

    var activeTicketId = null;
    var currentFilter = { status: '', priority: '', agent: '', department: '', search: '' };

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
        loadTickets();
        bindEvents();
    });

    /* ------------------------------------------------------------------ */
    /*  Event binding                                                      */
    /* ------------------------------------------------------------------ */
    function bindEvents() {
        // Status filter
        $(document).on('change', '.mhd-ticket-status-filter', function () {
            currentFilter.status = $(this).val();
            loadTickets();
        });

        // Priority filter
        $(document).on('change', '.mhd-ticket-priority-filter', function () {
            currentFilter.priority = $(this).val();
            loadTickets();
        });

        // Agent filter
        $(document).on('change', '.mhd-ticket-agent-filter', function () {
            currentFilter.agent = $(this).val();
            loadTickets();
        });

        // Department filter
        $(document).on('change', '.mhd-ticket-dept-filter', function () {
            currentFilter.department = $(this).val();
            loadTickets();
        });

        // Search
        var searchDebounce;
        $(document).on('input', '.mhd-ticket-search', function () {
            var val = $(this).val();
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(function () {
                currentFilter.search = val;
                loadTickets();
            }, 300);
        });

        // Click ticket
        $(document).on('click', '.mhd-ticket-item', function () {
            var id = $(this).data('id');
            if (id) openTicket(id);
        });

        // Send reply
        $(document).on('click', '.mhd-ticket-reply-send', function () {
            sendTicketReply(false);
        });

        // Send note
        $(document).on('click', '.mhd-ticket-reply-note', function () {
            sendTicketReply(true);
        });

        // Enter to send
        $(document).on('keydown', '.mhd-ticket-composer textarea', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendTicketReply(false);
            }
        });

        // File upload
        $(document).on('click', '.mhd-ticket-file-btn', function () {
            $('.mhd-ticket-file-input').trigger('click');
        });
        $(document).on('change', '.mhd-ticket-file-input', handleTicketFileUpload);

        // Change status
        $(document).on('change', '.mhd-ticket-status-select', function () {
            if (!activeTicketId) return;
            doAjax('mhd_update_ticket', {
                ticket_id: activeTicketId,
                status: $(this).val()
            }).done(function () { loadTickets(); });
        });

        // Change priority
        $(document).on('change', '.mhd-ticket-priority-select', function () {
            if (!activeTicketId) return;
            doAjax('mhd_update_ticket', {
                ticket_id: activeTicketId,
                priority: $(this).val()
            });
        });

        // Assign agent
        $(document).on('change', '.mhd-ticket-assign-agent', function () {
            if (!activeTicketId) return;
            doAjax('mhd_update_ticket', {
                ticket_id: activeTicketId,
                agent_id: $(this).val()
            });
        });

        // Assign department
        $(document).on('change', '.mhd-ticket-assign-dept', function () {
            if (!activeTicketId) return;
            doAjax('mhd_update_ticket', {
                ticket_id: activeTicketId,
                department_id: $(this).val()
            });
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Load tickets list                                                  */
    /* ------------------------------------------------------------------ */
    function loadTickets() {
        doAjax('mhd_get_tickets', currentFilter).done(function (resp) {
            if (resp.success && resp.data && resp.data.tickets) {
                renderTicketsList(resp.data.tickets);
            }
        });
    }

    function renderTicketsList(tickets) {
        var $list = $('.mhd-tickets-list');
        if (!$list.length) return;
        if (!tickets.length) {
            $list.html('<p class="mhd-empty">No tickets found.</p>');
            return;
        }
        var html = '';
        $.each(tickets, function (_, t) {
            var active = t.id == activeTicketId ? ' mhd-ticket-active' : '';
            html += '<div class="mhd-ticket-item' + active + '" data-id="' + t.id + '">' +
                '<div class="mhd-ticket-subject">' +
                    '<span class="mhd-ticket-id">#' + t.id + '</span> ' +
                    escapeHtml(t.subject || 'No subject') +
                '</div>' +
                '<div class="mhd-ticket-meta">' +
                    '<span class="mhd-ticket-status mhd-status-' + escapeHtml(t.status || '') + '">' + escapeHtml(t.status || '') + '</span>' +
                    '<span class="mhd-ticket-priority mhd-priority-' + escapeHtml(t.priority || '') + '">' + escapeHtml(t.priority || '') + '</span>' +
                    '<span class="mhd-ticket-time">' + escapeHtml(t.updated_at || '') + '</span>' +
                '</div>' +
                '<div class="mhd-ticket-requester">' + escapeHtml(t.requester_name || '') + '</div>' +
                '</div>';
        });
        $list.html(html);
    }

    /* ------------------------------------------------------------------ */
    /*  Open ticket thread                                                 */
    /* ------------------------------------------------------------------ */
    function openTicket(id) {
        activeTicketId = id;
        $('.mhd-ticket-item').removeClass('mhd-ticket-active');
        $('.mhd-ticket-item[data-id="' + id + '"]').addClass('mhd-ticket-active');

        doAjax('mhd_get_ticket', { ticket_id: id }).done(function (resp) {
            if (resp.success && resp.data) {
                renderTicketThread(resp.data.messages || []);
                renderTicketSidebar(resp.data.ticket || {});
            }
        });
    }

    function renderTicketThread(messages) {
        var $thread = $('.mhd-ticket-thread');
        if (!$thread.length) return;
        var html = '';
        $.each(messages, function (_, m) {
            html += buildTicketMessageHtml(m);
        });
        $thread.html(html);
        $thread.scrollTop($thread[0].scrollHeight);
    }

    function buildTicketMessageHtml(m) {
        var cls = 'mhd-ticket-msg mhd-msg-' + (m.sender_type || 'visitor');
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

    function renderTicketSidebar(ticket) {
        var $sidebar = $('.mhd-ticket-sidebar');
        if (!$sidebar.length) return;
        $sidebar.find('.mhd-ticket-requester-name').text(ticket.requester_name || '');
        $sidebar.find('.mhd-ticket-requester-email').text(ticket.requester_email || '');
        $sidebar.find('.mhd-ticket-status-select').val(ticket.status || 'open');
        $sidebar.find('.mhd-ticket-priority-select').val(ticket.priority || 'normal');
        $sidebar.find('.mhd-ticket-assign-agent').val(ticket.agent_id || '');
        $sidebar.find('.mhd-ticket-assign-dept').val(ticket.department_id || '');
        $sidebar.show();
    }

    /* ------------------------------------------------------------------ */
    /*  Send reply / note                                                  */
    /* ------------------------------------------------------------------ */
    function sendTicketReply(isNote) {
        if (!activeTicketId) return;
        var $textarea = $('.mhd-ticket-composer textarea');
        var text = $textarea.val().trim();
        if (!text) return;

        doAjax('mhd_reply_ticket', {
            ticket_id: activeTicketId,
            message: text,
            is_note: isNote ? 1 : 0
        }).done(function (resp) {
            if (resp.success) {
                $textarea.val('');
                var m = resp.data && resp.data.message ? resp.data.message : {
                    id: 'tmp_' + Date.now(),
                    content: text,
                    sender_type: 'agent',
                    sender_name: cfg.agent_name || 'You',
                    is_note: isNote,
                    created_at: new Date().toISOString()
                };
                var $thread = $('.mhd-ticket-thread');
                $thread.append(buildTicketMessageHtml(m));
                $thread.scrollTop($thread[0].scrollHeight);
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /*  File upload                                                        */
    /* ------------------------------------------------------------------ */
    function handleTicketFileUpload() {
        if (!activeTicketId) return;
        var files = this.files;
        if (!files || !files.length) return;

        var fd = new FormData();
        fd.append('action', 'mhd_upload_ticket_file');
        fd.append('nonce', nonce);
        fd.append('ticket_id', activeTicketId);
        fd.append('file', files[0]);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (resp) {
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
                var $thread = $('.mhd-ticket-thread');
                $thread.append(buildTicketMessageHtml(m));
                $thread.scrollTop($thread[0].scrollHeight);
            }
        });
        $(this).val('');
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
