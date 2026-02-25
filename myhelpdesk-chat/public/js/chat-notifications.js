/**
 * MyHelpDesk Chat Notifications
 * Sound notifications and tab title flashing — pure vanilla JS.
 */
(function () {
    'use strict';

    var audioCtx = null;
    var flashInterval = null;
    var originalTitle = document.title;
    var isFlashing = false;

    /* ------------------------------------------------------------------ */
    /*  Web Audio API beep                                                 */
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

    function playBeep(frequency, duration) {
        var ctx = getAudioContext();
        if (!ctx) return;

        var oscillator = ctx.createOscillator();
        var gain = ctx.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.setValueAtTime(frequency || 660, ctx.currentTime);
        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + (duration || 0.15));

        oscillator.connect(gain);
        gain.connect(ctx.destination);

        oscillator.start(ctx.currentTime);
        oscillator.stop(ctx.currentTime + (duration || 0.15));
    }

    /* ------------------------------------------------------------------ */
    /*  Sound notification                                                 */
    /* ------------------------------------------------------------------ */
    function playSoundNotification() {
        var cfg = window.mhd_chat || {};
        if (cfg.sound_enabled === '1' || cfg.sound_enabled === true) {
            playBeep(660, 0.15);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Tab title flash                                                    */
    /* ------------------------------------------------------------------ */
    function startFlashing(message) {
        if (isFlashing) return;
        isFlashing = true;
        var show = true;
        flashInterval = setInterval(function () {
            document.title = show ? (message || '💬 New message!') : originalTitle;
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
    /*  Visibility handler                                                 */
    /* ------------------------------------------------------------------ */
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            stopFlashing();
        }
    });

    /* ------------------------------------------------------------------ */
    /*  Public API                                                         */
    /* ------------------------------------------------------------------ */
    window.MHDNotifications = {
        playBeep: playBeep,
        playSound: playSoundNotification,
        startFlashing: startFlashing,
        stopFlashing: stopFlashing,
        onNewMessage: function () {
            playSoundNotification();
            if (document.hidden) {
                startFlashing('💬 New message!');
            }
        }
    };
})();
