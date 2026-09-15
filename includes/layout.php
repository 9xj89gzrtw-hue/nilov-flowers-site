<?php
declare(strict_types=1);

function adminHeader(string $title, string $active = ''): void
{
    $nav = [
        'index' => 'Заказы',
        'products' => 'Товары',
        'categories' => 'Категории',
        'promo' => 'Промокоды',
        'occasions' => 'Случаи',
        'zones' => 'Зоны доставки',
        'users' => 'Сотрудники',
        'settings' => 'Настройки',
        'profile' => 'Профиль',
    ];
    ?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> — Админ-панель</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Golos+Text:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --rose:#F4A9BE; --rose-deep:#E2799C; --blue:#A3C4D9; --mint:#D9E9DF;
  --bg:#F6F1E6; --bg-alt:#EFE7D8; --ink:#2B2D2F; --ink-soft:#6E6A61;
  --line:rgba(43,45,47,.12); --err:#C43A3A; --ok:#3e8e5a;
  --font-display:'Playfair Display',Georgia,serif;
  --font-ui:'Golos Text',system-ui,-apple-system,sans-serif;
  --radius:14px; --radius-lg:22px;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font-ui);color:var(--ink);background:var(--bg);line-height:1.55}
a{color:inherit;text-decoration:none}
.wrap{max-width:1100px;margin:0 auto;padding:0 20px}
.admin-top{background:#fff;border-bottom:1px solid var(--line)}
.admin-top .wrap{display:flex;align-items:center;gap:24px;min-height:60px;flex-wrap:wrap}
.admin-logo{font-family:var(--font-display);font-weight:700;font-size:1.05rem}
.admin-nav{display:flex;gap:4px;flex-wrap:wrap}
.admin-nav a{padding:8px 14px;border-radius:999px;font-size:.9rem;font-weight:500;color:var(--ink-soft);display:inline-flex;align-items:center;min-height:44px}
.admin-nav a.active{background:var(--rose);color:var(--ink);box-shadow:inset 0 0 0 1.5px rgba(174,74,113,.55)}
.admin-top .spacer{flex:1}
/* Мобильная админка (W4): шапка не рвётся, nav скроллится горизонтально внутри себя */
@media(max-width:700px){
  .admin-top .wrap{gap:10px;padding:10px 16px;min-height:0}
  .admin-logo{font-size:1rem}
  .admin-nav{order:3;width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;flex-wrap:nowrap;padding-bottom:2px}
  .admin-nav::-webkit-scrollbar{display:none}
  .admin-nav a{white-space:nowrap;padding:8px 12px;font-size:.88rem}
  .admin-top{position:sticky;top:0;z-index:50} /* единственный sticky: выше карточек и таблиц */
  main.wrap{padding:16px 16px 80px}
}
.btn{display:inline-flex;align-items:center;gap:8px;border:none;border-radius:999px;padding:9px 18px;font:600 .85rem var(--font-ui);cursor:pointer;background:var(--ink);color:#fff;transition:background .15s ease,transform .12s ease,box-shadow .15s ease}
.btn:hover{transform:translateY(-1px)}
.btn:active{transform:translateY(1px)}
.btn:hover{background:#1A1B1D}
.btn--accent{background:var(--rose-cta,#AE4A71);color:#fff}
.btn--accent:hover{background:var(--rose-cta-hover,#9E4062)}
.btn--ghost{background:transparent;border:1.5px solid var(--line);color:var(--ink);transition:border-color .15s ease,color .15s ease}
.btn--ghost:hover{background:var(--bg-alt);border-color:var(--ink-soft);color:var(--ink)}
.btn--outline{background:#fff;border:1.5px solid var(--line);color:var(--ink);transition:background .15s ease,border-color .15s ease}
.btn--outline:hover{background:var(--bg-alt);border-color:var(--ink-soft);color:var(--ink)}
.btn--outline:disabled{opacity:.45;cursor:not-allowed;transform:none}
.btn--danger{background:#fff;border:1.5px solid var(--err);color:var(--err);transition:background .15s ease,color .15s ease}
.btn--danger:hover{background:var(--err);color:#fff;border-color:var(--err)}
main.wrap{padding:28px 20px 60px}
h1{font-family:var(--font-display);font-weight:600;font-size:1.8rem;margin-bottom:18px}
/* Дружелюбная админка (критерий 15, паттерны 2026): приветствие + подсказка «что делать сейчас» */
.admin-hello{
  background:linear-gradient(120deg,#fff 0%,rgba(244,169,190,.14) 100%);
  border:1px solid rgba(226,121,156,.25);
  border-radius:var(--radius-lg);
  padding:16px 20px;margin-bottom:20px;
  display:flex;gap:14px;align-items:center;flex-wrap:wrap;
}
.admin-hello__emoji{font-size:1.7rem;line-height:1}
.admin-hello b{font-family:var(--font-display);font-size:1.05rem}
.admin-hello p{font-size:.85rem;color:var(--ink-soft);margin-top:2px}
h1 .status-badge{vertical-align:middle;margin-left:10px}
.order-link{font-weight:700;text-decoration:underline}
.filters-bar{display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:14px}
.filters-bar .f{margin:0}
.card{background:#fff;border-radius:var(--radius-lg);padding:22px;box-shadow:0 14px 40px -28px rgba(43,45,47,.35);margin-bottom:20px;transition:box-shadow .2s ease}
table{width:100%;border-collapse:collapse;font-size:.9rem}
/* Мобильные таблицы: горизонтальный скролл внутри карточки, документ не рвётся */
.table-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
.table-scroll table{min-width:560px}
.table-scroll table.products-table{min-width:760px}
th{text-align:left;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;color:var(--ink-soft);padding:8px 10px;border-bottom:1px solid var(--line);white-space:nowrap}
td{padding:10px;border-bottom:1px solid var(--line);vertical-align:middle}
tr:last-child td{border-bottom:none}
tbody tr,table tr{transition:background .12s ease}
table tr:hover td{background:rgba(244,169,190,.08)}
table tr:hover td:has(.row-actions form),table tr:hover th{background:transparent}
/* Visual W52: бежевый фон+бежевая рамка = границ не видно; микро-текст мелок */
.input,select,textarea{width:100%;border:1.5px solid rgba(43,45,47,.28);border-radius:10px;padding:9px 12px;font:400 .92rem var(--font-ui);background:#fff;color:var(--ink)}
.input:focus,select:focus,textarea:focus{outline:none;border-color:var(--rose-deep)}
.input:focus-visible,select:focus-visible,textarea:focus-visible,button:focus-visible,a:focus-visible,input[type=checkbox]:focus-visible{outline:3px solid rgba(174,74,113,.55);outline-offset:2px;border-radius:6px}
/* Visual-критик W52: нативный синий чекбокс кричит на розово-бежевой палитре */
input[type=checkbox],input[type=radio]{width:22px;height:22px;min-width:22px;accent-color:var(--rose-cta,#AE4A71);cursor:pointer}
input[type=number]::-webkit-inner-spin-button,input[type=number]::-webkit-outer-spin-button{-webkit-appearance:none;margin:0}
input[type=number]{-moz-appearance:textfield;appearance:textfield}
select{-webkit-appearance:none;appearance:none;background-image:url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' fill='none' stroke='%232B2D2F' stroke-width='2' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 11px center;padding-right:34px}
/* Visual W54: чекбокс — hit-area ≥44px (визуально компакт); td-ячейка шире колонки */
td:first-child:has(input[type=checkbox]){width:44px;min-width:44px;text-align:center}
td input[type=checkbox]{margin:12px auto;display:block}
/* file-input не липнет к label */
input[type=file]{margin-top:6px}
/* нативный Choose File приобщаем к системе */
input[type=file]{font:400 .85rem var(--font-ui);color:var(--ink);padding:8px;border:1.5px dashed var(--line);border-radius:10px;background:var(--bg);width:100%}
input[type=file]::file-selector-button{font:600 .82rem var(--font-ui);border:1px solid rgba(174,74,113,.45);border-radius:999px;background:rgba(174,74,113,.08);color:var(--rose-cta,#AE4A71);padding:7px 14px;margin-right:10px;cursor:pointer;transition:background .15s ease}
label.f{display:block;font-size:.8rem;font-weight:600;margin:12px 0 4px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:0 28px;align-items:start}
@media(max-width:700px){.grid2{grid-template-columns:1fr}}
.status-badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:.75rem;font-weight:600;background:var(--bg-alt)}
/* Дружелюбные статусы: эмодзи + цвет (доступность — не только цвет, паттерн UXPin 2026) */
.status-badge.new{background:var(--rose)}
.status-badge.new::before{content:"✨ ";font-size:.7rem}
.status-badge.confirmed{background:var(--blue)}
.status-badge.confirmed::before{content:"📞 ";font-size:.7rem}
.status-badge.done{background:var(--mint)}
.status-badge.done::before{content:"💐 ";font-size:.7rem}
.status-badge.canceled{background:#eee;opacity:.85}
.status-badge.canceled::before{content:"✖ ";font-size:.7rem}
.status-badge.unredeemed{background:#f3d9a4}
.status-badge.unredeemed::before{content:"🕒 ";font-size:.7rem}
.toast{position:fixed;top:16px;right:16px;z-index:1000;background:#fff;box-shadow:0 14px 40px -20px rgba(43,45,47,.5);border-radius:var(--radius);padding:14px 18px;font-size:.9rem;max-width:320px}
.flash{padding:12px 16px;border-radius:12px;background:var(--mint);margin-bottom:16px;font-size:.9rem;border:1px solid rgba(62,142,90,.25);color:#2c5e40}
.flash--err{background:#fbe3e3;border-color:rgba(214,69,69,.3);color:#8c2f2f}
.empty-state{text-align:center;padding:36px 20px;color:var(--ink-soft);font-size:.95rem}
.empty-state strong{display:block;font-family:var(--font-display);font-size:1.15rem;color:var(--ink);margin-bottom:6px;font-weight:600}
.linklike{background:none;border:none;color:var(--rose-deep);font:600 .85rem var(--font-ui);cursor:pointer;padding:0;text-decoration:underline;text-underline-offset:2px}
.linklike:hover{color:#d4638a}
.thumb{width:48px;height:48px;object-fit:cover;border-radius:10px;background:var(--bg-alt)}
.row-actions{display:flex;gap:6px;flex-wrap:wrap}
.row-actions a,.row-actions button{font-size:.78rem;padding:5px 10px;border-radius:10px;border:1px solid var(--line);background:#fff;cursor:pointer;font-family:var(--font-ui);transition:background .15s ease,color .15s ease,transform .12s ease}
.row-actions a:hover,.row-actions button:hover{transform:translateY(-1px)}
.row-actions a:active,.row-actions button:active{transform:translateY(1px)}
/* W61 (дизайн-критик): уважение prefers-reduced-motion — как на витрине, в админке не было */
@media(prefers-reduced-motion:reduce){*,*::before,*::after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important;scroll-behavior:auto!important}}
.row-actions a.danger,.row-actions button.danger{color:var(--err);border-color:var(--err)}
/* W59 (визит-критик CODE_OPEN#5): единый паттерн иерархии действий — главное
   следующее действие (переход статуса) акцентное, прочие нейтральные */
.card form button[type=submit]:not(.primary-action):not(.btn):not(.danger){font:600 .82rem var(--font-ui);color:var(--ink);background:#fff;border:1px solid var(--line);border-radius:10px;padding:6px 12px;cursor:pointer;transition:background .15s ease,border-color .15s ease,transform .12s ease;min-height:36px}
.card form button[type=submit]:not(.primary-action):not(.btn):not(.danger):hover{background:var(--bg-alt);border-color:var(--ink-soft)}
.card form button[type=submit]:not(.primary-action):not(.btn):not(.danger):active{transform:translateY(1px)}
button.primary-action,a.primary-action{background:var(--rose-cta,#AE4A71);color:#fff;border:1.5px solid var(--rose-cta,#AE4A71);border-radius:10px;padding:7px 13px;font:600 .85rem var(--font-ui);cursor:pointer;text-decoration:none;display:inline-block}
button.primary-action:hover,a.primary-action:hover{background:var(--rose-cta-hover,#9E4062);border-color:var(--rose-cta-hover,#9E4062);color:#fff}
.row-actions a.primary-action,.row-actions button.primary-action{font-weight:700}
/* W38: подсветка строки после сохранения (возврат на #row-ID) */
table tr:target{background:#fdf2f6}
/* Владелец-критик W48: :target-подсветка гаснет при перерисовке — делаем её
   самостоятельной JS-анимацией на 2.5с (не зависит от удержания якоря). */
@keyframes rowFlash{0%{background:#fbdbe7}80%{background:#fbdbe7}100%{background:transparent}}
table tr.row-flash{animation:rowFlash 2.5s ease-out 1}
/* Критик-владелец W36 B3: на телефоне админка «мелкая» — все поля/кнопки/чекбоксы ≥44px на тач */
@media(hover:none),(max-width:820px){
  .input,select,textarea,input[type=text],input[type=email],input[type=password],input[type=tel],input[type=number]{min-height:44px;font-size:16px}
  textarea{font-size:16px}
  input[type=checkbox],input[type=radio]{width:24px;height:24px;min-height:24px}
  .btn,.row-actions a,.row-actions button,.dash-ranges a{min-height:44px;display:inline-flex;align-items:center;justify-content:center}
  .card form button[type=submit]:not(.primary-action):not(.btn):not(.danger){min-height:44px}
  .chip,label.f input[type=checkbox]{min-height:40px}
  .f,.field-hint,p[style*=".78rem"],p[style*=".8rem"]{font-size:.9rem !important}
}
/* --- Дашборд «Статистика» --- */
/* Статистика — контент, а не тулбар: sticky убран (перекрывал таблицы на мобиле).
   Шапка .admin-top — единственный sticky на странице. */
.dash-section{margin-bottom:20px}
.dash-card{background:linear-gradient(180deg,#fff 0%,var(--mint) 220%);border:1px solid rgba(163,196,217,.35)}
.dash-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.dash-ranges{display:flex;gap:6px;flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;-webkit-overflow-scrolling:touch}
/* W59 (визит-критик): чипы разделов настроек на узких — горизонтальный скролл-ряд,
   не «лестница» с рваными отступами */
#top-nav{overflow-x:auto;scrollbar-width:none;flex-wrap:nowrap;-webkit-overflow-scrolling:touch}
#top-nav::-webkit-scrollbar{display:none}
#top-nav a{white-space:nowrap;flex:0 0 auto}
.dash-ranges{-webkit-mask-image:linear-gradient(90deg,#000 92%,transparent);mask-image:linear-gradient(90deg,#000 92%,transparent)}
.dash-ranges a{padding:5px 12px;border-radius:999px;font-size:.8rem;font-weight:600;color:var(--ink-soft);background:var(--bg);transition:background .15s ease,color .15s ease}
.dash-ranges a:hover{color:var(--ink)}
.dash-ranges a.active{background:var(--rose);color:var(--ink)}
.dash-metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px}
@media(max-width:700px){.dash-metrics{grid-template-columns:repeat(2,1fr)}}
.dash-metric{border-radius:var(--radius);padding:12px 16px;background:var(--bg)}
.dash-metric--rose{background:rgba(244,169,190,.25);border:1px solid rgba(226,121,156,.3)}
.dash-metric--rose-deep{background:rgba(226,121,156,.15);border:1px solid rgba(226,121,156,.45)}
.dash-metric--blue{background:rgba(163,196,217,.25);border:1px solid rgba(163,196,217,.5)}
.dash-metric--mint{background:rgba(217,233,223,.7);border:1px solid rgba(62,142,90,.25)}
.dash-metric__label{display:block;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--ink-soft);font-weight:600}
.dash-metric__value{display:block;font-family:var(--font-display);font-size:1.45rem;font-weight:700;margin-top:2px}
.dash-spark{margin-bottom:16px}
.sparkline{display:block;width:100%;height:64px;margin-top:6px;border-bottom:1px solid var(--line)}
.sparkline rect{transition:opacity .15s ease}
.sparkline rect:hover{opacity:.75}
.dash-row{display:grid;grid-template-columns:1fr 1fr;gap:0 24px}
@media(max-width:700px){.dash-row{grid-template-columns:1fr}}
.dash-chips{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}
.dash-chips .status-badge{font-size:.72rem}
.dash-top-list{list-style:none;counter-reset:top;margin-top:6px}
.dash-top-list li{display:flex;align-items:center;gap:10px;padding:6px 0;border-bottom:1px solid var(--line);counter-increment:top}
.dash-top-list li:last-child{border-bottom:none}
/* Мобильный дашборд: компактные метрики, sparkline ниже, порядок после базовых правил */
@media(max-width:700px){
  .dash-metric{padding:10px 12px;border-radius:12px}
  .dash-metric__value{font-size:1.2rem}
  .dash-card{padding:14px}
  .dash-head{margin-bottom:10px}
  .dash-spark{margin-bottom:10px}
  .sparkline{height:48px}
}
.dash-top-list li::before{content:counter(top);font-family:var(--font-display);font-weight:700;color:var(--rose-deep);min-width:18px}
.dash-top-name{flex:1;font-size:.88rem}
.dash-thumb{width:36px;height:36px}
</style>
<script src="/js/pwa-register.js" defer></script>
</head>
<body>
<header class="admin-top">
  <div class="wrap">
    <span class="admin-logo">Админ-панель</span>
    <nav class="admin-nav">
      <?php foreach ($nav as $key => $label): ?>
        <a href="/admin/<?= e($key) ?>.php" class="<?= $active === $key ? 'active' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <span class="spacer"></span>
    <a href="/" target="_blank" class="btn btn--ghost">Открыть сайт</a>
    <a href="/admin/logout.php?t=<?= e(csrf_token()) ?>" class="btn btn--ghost">Выйти</a>
  </div>
</header>
<main class="wrap">
    <?php
}

function adminFooter(): void
{
    ?></main><script src="/js/admin-notify.js" defer></script><script src="/js/admin-guard.js" defer></script>
<script>
/* Владелец-критик W48: подсветка строки после save — анимацией, а не хрупким :target */
(function () {
  try {
    var el = location.hash ? document.getElementById(location.hash.slice(1)) : null;
    if (el) { el.classList.add('row-flash'); el.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
  } catch (e) {}
})();
</script></body></html><?php
}

function flash(?string $msg = null, bool $err = false): ?string
{
    if ($msg === null) {
        $m = $_SESSION['flash'] ?? null;
        $e = $_SESSION['flash_err'] ?? false;
        unset($_SESSION['flash'], $_SESSION['flash_err']);
        if ($m !== null) {
            echo '<div class="flash' . ($e ? ' flash--err' : '') . '">' . e($m) . '</div>';
        }
        return null;
    }
    $_SESSION['flash'] = $msg;
    $_SESSION['flash_err'] = $err;
    return null;
}
