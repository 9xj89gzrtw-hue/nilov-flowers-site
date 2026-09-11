<?php
/* Отложенная загрузка Яндекс.Метрики — только после согласия в cookie-баннере
   (152-ФЗ / ФЗ-156: аналитические cookie требуют явного согласия с 01.09.2025).
   В <head> кода Метрики нет. Номер счётчика — настройка metrika_counter_id;
   пустая настройка → скрипт не печатается вовсе. */
$metrikaId = trim(setting('metrika_counter_id', ''));
if ($metrikaId !== '' && ctype_digit($metrikaId)) : ?>
<script>
window.YM_COUNTER_ID = <?= (int)$metrikaId ?>;
function loadMetrica(){
  if (window.__ymLoaded) return; window.__ymLoaded = true;
  (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
  m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,
  k.src=r,a.parentNode.insertBefore(k,a)})
  (window,document,"script","https://mc.yandex.ru/metrika/tag.js","ym");
  ym(window.YM_COUNTER_ID, "init", { clickmap:true, trackLinks:true, accurateTrackBounce:true, webvisor:true });
}
</script>
<?php endif; ?>
