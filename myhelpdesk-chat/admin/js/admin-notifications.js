/**
 * MyHelpDesk Admin Notifications
 * Handles notification polling, bell badge, desktop notifications, and sound alerts.
 * jQuery allowed (WP bundled).
 */
(function ($) {
    'use strict';

    if (typeof $ === 'undefined') return;

    var cfg = window.mhd_admin || {};
    var ajaxUrl = cfg.ajax_url || '';
    var nonce = cfg.nonce || '';

    var pollTimer = null;
    var lastNotificationId = 0;
    var audioCtx = null;
    var flashInterval = null;
    var originalTitle = document.title;
    var isFlashing = false;
    var desktopPermission = false;

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
        requestDesktopPermission();
        bindEvents();
        startPolling();
        handleVisibility();
    });

    /* ------------------------------------------------------------------ */
    /*  Event binding                                                      */
    /* ------------------------------------------------------------------ */
    function bindEvents() {
        // Toggle dropdown
        $(document).on('click', '.mhd-notification-bell', function (e) {
            e.stopPropagation();
            $('.mhd-notification-dropdown').toggleClass('mhd-dropdown-open');
        });

        // Close dropdown on outside click
        $(document).on('click', function () {
            $('.mhd-notification-dropdown').removeClass('mhd-dropdown-open');
        });
        $(document).on('click', '.mhd-notification-dropdown', function (e) {
            e.stopPropagation();
        });

        // Click notification item
        $(document).on('click', '.mhd-notification-item', function () {
            var url = $(this).data('url');
            if (url) window.location.href = url;
        });

        // Mark all as read
        $(document).on('click', '.mhd-mark-all-read', function (e) {
            e.preventDefault();
            markAllRead();
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Polling                                                            */
    /* ------------------------------------------------------------------ */
    var currentInterval = 5000;

    function startPolling() {
        pollNotifications();
    }

    function pollNotifications() {
        doAjax('mhd_get_notifications', {
            last_id: lastNotificationId
        }).done(function (resp) {
            if (resp.success && resp.data) {
                updateBadge(resp.data.unread_count || 0);

                if (resp.data.notifications && resp.data.notifications.length) {
                    renderNotifications(resp.data.notifications);

                    // Track last id
                    $.each(resp.data.notifications, function (_, n) {
                        var nid = parseInt(n.id, 10);
                        if (nid > lastNotificationId) lastNotificationId = nid;
                    });

                    // Alert for new items
                    onNewNotifications(resp.data.notifications);
                }
            }
        }).always(function () {
            pollTimer = setTimeout(pollNotifications, currentInterval);
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Badge                                                              */
    /* ------------------------------------------------------------------ */
    function updateBadge(count) {
        var $badge = $('.mhd-notification-badge');
        if (count > 0) {
            $badge.text(count > 99 ? '99+' : count).show();
        } else {
            $badge.hide();
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Render notifications                                               */
    /* ------------------------------------------------------------------ */
    function renderNotifications(notifications) {
        var $list = $('.mhd-notification-list');
        if (!$list.length) return;

        if (!notifications.length) {
            $list.html('<p class="mhd-notif-empty">No notifications.</p>');
            return;
        }

        var html = '';
        $.each(notifications, function (_, n) {
            var unread = n.is_read ? '' : ' mhd-notif-unread';
            var url = '';
            if (n.type === 'conversation') {
                url = (cfg.conversations_url || '#') + '&id=' + n.reference_id;
            } else if (n.type === 'ticket') {
                url = (cfg.tickets_url || '#') + '&id=' + n.reference_id;
            }
            html += '<div class="mhd-notification-item' + unread + '" data-id="' + n.id + '" data-url="' + escapeHtml(url) + '">' +
                '<div class="mhd-notif-icon mhd-notif-' + escapeHtml(n.type || 'info') + '"></div>' +
                '<div class="mhd-notif-body">' +
                    '<div class="mhd-notif-text">' + escapeHtml(n.message || '') + '</div>' +
                    '<div class="mhd-notif-time">' + escapeHtml(n.created_at || '') + '</div>' +
                '</div>' +
                '</div>';
        });
        $list.html(html);
    }

    /* ------------------------------------------------------------------ */
    /*  Mark all as read                                                   */
    /* ------------------------------------------------------------------ */
    function markAllRead() {
        doAjax('mhd_mark_notifications_read', {}).done(function (resp) {
            if (resp.success) {
                updateBadge(0);
                $('.mhd-notification-item').removeClass('mhd-notif-unread');
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /*  New notification alerts                                            */
    /* ------------------------------------------------------------------ */
    function onNewNotifications(notifications) {
        playBeep();

        if (document.hidden) {
            startFlashing('🔔 New notification');
        }

        // Desktop notification for the latest
        if (desktopPermission && notifications.length) {
            var latest = notifications[0];
            showDesktopNotification(latest.message || 'New notification', latest);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Sound (Web Audio API)                                              */
    /* ------------------------------------------------------------------ */
    function getAudioContext() {
        if (!audioCtx) {
            try {
                audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            } catch (_) {
                return null;
            }
        }
        return audioCtx;
    }

    function playBeep() {
        var ctx = getAudioContext();
        if (!ctx) return;

        var osc = ctx.createOscillator();
        var gain = ctx.createGain();

        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, ctx.currentTime);
        gain.gain.setValueAtTime(0.25, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.2);

        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.2);
    }

    /* ------------------------------------------------------------------ */
    /*  Desktop Notifications (Web Notifications API)                      */
    /* ------------------------------------------------------------------ */
    function requestDesktopPermission() {
        if (!('Notification' in window)) return;
        if (Notification.permission === 'granted') {
            desktopPermission = true;
        } else if (Notification.permission !== 'denied') {
            Notification.requestPermission().then(function (perm) {
                desktopPermission = perm === 'granted';
            });
        }
    }

    function showDesktopNotification(body, data) {
        if (!desktopPermission) return;
        try {
            var n = new Notification('MyHelpDesk', {
                body: body,
                icon: cfg.plugin_icon || '',
                tag: 'mhd-notif-' + (data.id || Date.now())
            });
            n.onclick = function () {
                window.focus();
                if (data.type === 'conversation') {
                    window.location.href = (cfg.conversations_url || '#') + '&id=' + data.reference_id;
                } else if (data.type === 'ticket') {
                    window.location.href = (cfg.tickets_url || '#') + '&id=' + data.reference_id;
                }
                n.close();
            };
            setTimeout(function () { n.close(); }, 8000);
        } catch (_) { /* Notification API may throw in some contexts */ }
    }

    /* ------------------------------------------------------------------ */
    /*  Tab title flash                                                    */
    /* ------------------------------------------------------------------ */
    function startFlashing(message) {
        if (isFlashing) return;
        isFlashing = true;
        var show = true;
        flashInterval = setInterval(function () {
            document.title = show ? message : originalTitle;
            show = !show;
        }, 1000);
    }

    function stopFlashing() {
        if (!isFlashing) return;
        isFlashing = false;
        clearInterval(flashInterval);
        flashInterval = null;
        document.title = originalTitle;
    }

    /* ------------------------------------------------------------------ */
    /*  Visibility change                                                  */
    /* ------------------------------------------------------------------ */
    function handleVisibility() {
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                currentInterval = 15000;
            } else {
                currentInterval = 5000;
                stopFlashing();
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
