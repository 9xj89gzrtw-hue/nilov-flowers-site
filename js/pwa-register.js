/* Регистрация service worker на всех публичных страницах (подключается в partials/head.php). */
(function () {
    'use strict';
    if (!('serviceWorker' in navigator)) return;
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js').catch(function (err) {
            console.warn('SW registration failed:', err);
        });
        /* Авто-обновление без ручного сброса кеша (критерий 23): браузер сверяет sw.js
           только при навигации — для долгоживущих вкладок проверяем каждый час. */
        setInterval(function () {
            navigator.serviceWorker.getRegistrations().then(function (regs) {
                regs.forEach(function (r) { r.update(); });
            });
        }, 3600000);
    });
})();
