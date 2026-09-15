/* Опрос новых заказов: /admin/poll.php {"count":N} (10 с активная вкладка, 60 с фоновая).
   Новый заказ → chime() (880+1174.7 Hz, gain ramp), flashTitle с счётчиком, setAppBadge.
   Громкость per-device: localStorage 'admin_sound_volume' (0..1, по умолчанию 0.5).
   AudioContext разблокируется по первому клику (требование браузеров). */
(function () {
    'use strict';
    var last = null;
    var baseTitle = document.title;
    var audioCtx = null;

    function volume() {
        var v = parseFloat(localStorage.getItem('admin_sound_volume'));
        return isNaN(v) ? 0.5 : Math.min(1, Math.max(0, v));
    }

    /* Разблокировка звука по первому пользовательскому жесту */
    function unlockAudio() {
        try {
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            if (!audioCtx) audioCtx = new Ctx();
            if (audioCtx.state === 'suspended') audioCtx.resume();
        } catch (e) { /* звук не критичен */ }
    }
    document.addEventListener('click', unlockAudio, { once: true });
    document.addEventListener('keydown', unlockAudio, { once: true });

    /* «Динь-динь»: ля5 (880) → ре6 (1174.7), gain ramp */
    function chime() {
        try {
            unlockAudio();
            if (!audioCtx || audioCtx.state !== 'running') return;
            var t = audioCtx.currentTime;
            var vol = volume();
            [880, 1174.7].forEach(function (freq, i) {
                var o = audioCtx.createOscillator();
                var g = audioCtx.createGain();
                o.type = 'sine';
                o.frequency.value = freq;
                g.gain.setValueAtTime(0.0001, t + i * 0.18);
                g.gain.linearRampToValueAtTime(0.25 * vol, t + i * 0.18 + 0.02);
                g.gain.exponentialRampToValueAtTime(0.0001, t + i * 0.18 + 0.5);
                o.connect(g);
                g.connect(audioCtx.destination);
                o.start(t + i * 0.18);
                o.stop(t + i * 0.18 + 0.55);
            });
        } catch (e) { /* звук не критичен */ }
    }

    /* Счётчик в title + мигание при новых заказах */
    var blinkOn = false;
    setInterval(function () {
        if (last === null || last <= 0) { document.title = baseTitle; return; }
        if (document.hidden) { document.title = '(' + last + ') ' + baseTitle; return; }
        document.title = (blinkOn = !blinkOn)
            ? '🔔 (' + last + ') НОВЫЙ ЗАКАЗ!'
            : '(' + last + ') ' + baseTitle;
    }, 1200);

    /* Badging API (Chrome/Edge desktop, Android PWA, iOS 16.4+ Home-Screen PWA) */
    function updateBadge(count) {
        if ('setAppBadge' in navigator) {
            if (count > 0) navigator.setAppBadge(count).catch(function () {});
            else navigator.clearAppBadge().catch(function () {});
        }
    }

    window.adminToast = function (text) { toast(text); };
    function toast(text) {
        var el = document.createElement('div');
        el.className = 'card toast';
        el.textContent = text;
        document.body.appendChild(el);
        setTimeout(function () { el.remove(); }, 5000);
    }

    function poll() {
        fetch('/admin/poll.php', { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || typeof data.count !== 'number') return;
                if (last !== null && data.count > last) {
                    chime();
                    toast('Новый заказ! Всего новых: ' + data.count);
                }
                last = data.count;
                updateBadge(last);
            })
            .catch(function () { /* сеть моргнула — не страшно */ });
    }

    var timer;
    function start(interval) {
        clearInterval(timer);
        timer = setInterval(poll, interval);
    }
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) { poll(); start(10000); }
        else start(60000);
    });

    poll();
    start(10000);
})();
