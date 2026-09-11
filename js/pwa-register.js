/* Регистрация service worker на всех публичных страницах (подключается в partials/head.php). */
(function () {
    'use strict';
    if (!('serviceWorker' in navigator)) return;
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js').catch(function (err) {
            console.warn('SW registration failed:', err);
        });
    });
})();
