/* Опрос новых заказов: раз в 20 с → /admin/poll.php {"count":N}.
   Если количество выросло — короткий звук (WebAudio) + toast.
   Громкость per-device: localStorage 'admin_sound_volume' (0..1, по умолчанию 0.5). */
(function () {
    'use strict';
    var last = null;

    function volume() {
        var v = parseFloat(localStorage.getItem('admin_sound_volume'));
        return isNaN(v) ? 0.5 : Math.min(1, Math.max(0, v));
    }

    function beep() {
        try {
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            var ctx = new Ctx();
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = 880;
            gain.gain.value = volume();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.12);
            osc.onended = function () { ctx.close(); };
        } catch (e) { /* звук не критичен */ }
    }

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
                    beep();
                    toast('Новый заказ! Всего новых: ' + data.count);
                }
                last = data.count;
            })
            .catch(function () { /* сеть моргнула — не страшно */ });
    }

    poll();
    setInterval(poll, 20000);
})();
