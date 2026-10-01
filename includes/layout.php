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
        /* W98-fixD (D3): «Инструкция» была доступна только с дашборда — добавляем в меню
           всем страницам; иконка — эмодзи по образцу подписей настроек (🖼/💾/⚙️) */
        'help' => '📖 Инструкция',
    ];
    /* W100-fixH2 (J6): пункт «Сотрудники» — только владельцу (страница и так 403-ит staff,
       теперь и из меню не соблазняет); все админ-страницы идут после requireAdmin() */
    $navIsOwner = true;
    if (function_exists('currentAdmin')) {
        $navMe = currentAdmin();
        $navIsOwner = $navMe !== null && (string)($navMe['role'] ?? 'owner') === 'owner';
    }
    ?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> — Админ-панель</title>
<link rel="stylesheet" href="/css/fonts.css">
<style>
:root{
  --rose:#F4A9BE; --rose-deep:#E2799C; --rose-cta:#AE4A71; --rose-cta-hover:#9E4062; --blue:#A3C4D9; --mint:#D9E9DF;
  --bg:#F6F1E6; --bg-alt:#EFE7D8; --ink:#2B2D2F; --ink-soft:#6E6A61;
  --line:rgba(43,45,47,.12); --err:#C43A3A; --ok:#2f7a4a;
  --font-display:'Playfair Display','Playfair Fallback',Georgia,serif;
  --font-ui:'Golos Text','Golos Fallback',system-ui,-apple-system,sans-serif;
  --radius:14px; --radius-lg:22px;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font-ui);color:var(--ink);background:var(--bg);line-height:1.55;-webkit-text-size-adjust:100%;text-size-adjust:100%}
a{color:inherit;text-decoration:none}
.wrap{max-width:1100px;margin:0 auto;padding:0 20px}
.admin-top{background:#fff;border-bottom:1px solid var(--line);position:sticky;top:0;z-index:50} /* W68 (obvious NEW-6): sticky на всех брейках — desktop-оглавление top:64 теперь опирается на реальную шапку */
.admin-top .wrap{display:flex;align-items:center;gap:24px;min-height:60px;flex-wrap:wrap;max-width:none}
.admin-top .admin-nav{flex:1;min-width:0;overflow-x:auto;white-space:nowrap;scrollbar-width:thin;scrollbar-color:rgba(174,74,113,.45) transparent;-webkit-overflow-scrolling:touch;mask-image:linear-gradient(90deg,#000 calc(100% - 34px),rgba(0,0,0,.35))} /* W72 (владелец NEW-4): конец ряда виден — не молчаливый оверфлоу */
.admin-top .admin-nav::-webkit-scrollbar{height:6px}
.admin-top .admin-nav::-webkit-scrollbar-thumb{background:rgba(174,74,113,.45);border-radius:3px}
@media(min-width:821px){.admin-top .wrap{flex-wrap:nowrap}}
.admin-logo{font-family:var(--font-display);font-weight:700;font-size:1.05rem}
.admin-nav{display:flex;gap:4px;flex-wrap:nowrap} /* W70 (владелец NEW-4): nowrap+overflow-x:auto выше = один ряд (было 3 ряда, 155px) */
.admin-nav a{padding:8px 14px;border-radius:999px;font-size:.9rem;font-weight:500;color:var(--ink-soft);display:inline-flex;align-items:center;min-height:44px}
.admin-nav a.active{background:var(--rose);color:var(--ink);box-shadow:inset 0 0 0 1.5px rgba(174,74,113,.55)}
.admin-top .spacer{flex:1}
/* W105 (4-b): бургер и иконка «Открыть сайт» — включаются только на ≤700px,
   десктоп их не видит (навигация — прежний единственный ряд) */
.admin-burger{display:none}
.admin-open-site__icon{display:none}
/* Мобильная админка (W4 → W105 4-b): шапка = один ряд (лого + «Открыть сайт» +
   «Выйти» + бургер 44×44), все 10 разделов — в выпадающей панели под шапкой
   (строки 48px, закрывается тапом по ссылке/вне/Escape, фон заблокирован).
   Было: nowrap-полоса со скрытым скроллбаром — 4/10 ссылок @390, 2/10 @360. */
@media(max-width:700px){
  .admin-top{position:sticky;top:0;z-index:50}
  .admin-top .wrap{gap:8px;padding:10px 14px;min-height:0;flex-wrap:nowrap}
  .admin-logo{font-size:1rem;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .admin-top .btn{padding:9px 12px;flex:0 0 auto}
  .admin-burger{display:inline-flex;flex-direction:column;justify-content:center;align-items:center;gap:5px;width:44px;height:44px;min-width:44px;padding:0;border:1.5px solid var(--line);border-radius:12px;background:#fff;cursor:pointer;flex:0 0 auto}
  .admin-burger__bar{display:block;width:20px;height:2px;border-radius:2px;background:var(--ink);transition:transform .25s ease,opacity .2s ease}
  .admin-top.nav-open .admin-burger__bar:nth-child(1){transform:translateY(7px) rotate(45deg)}
  .admin-top.nav-open .admin-burger__bar:nth-child(2){opacity:0}
  .admin-top.nav-open .admin-burger__bar:nth-child(3){transform:translateY(-7px) rotate(-45deg)}
  .admin-top .admin-nav{position:absolute;top:100%;left:0;right:0;flex:none;flex-direction:column;gap:2px;background:#fff;border-bottom:1px solid var(--line);box-shadow:0 24px 44px -24px rgba(43,45,47,.5);max-height:0;visibility:hidden;overflow:hidden;padding:0 12px;white-space:normal;-webkit-mask-image:none;mask-image:none;z-index:60;transition:max-height .28s ease,visibility 0s linear .28s}
  .admin-top.nav-open .admin-nav{max-height:calc(100vh - 76px);max-height:calc(100dvh - 76px);visibility:visible;overflow-y:auto;padding:10px 12px 14px;transition:max-height .28s ease}
  .admin-nav a{display:flex;align-items:center;width:100%;min-height:48px;padding:12px 14px;border-radius:12px;font-size:1rem;white-space:normal}
  html.admin-nav-lock{overflow:hidden} /* фон не скроллится, пока меню открыто */
  main.wrap{padding:16px 16px 80px}
}
/* W105 (4-b): на самом узком «Открыть сайт» не влезает текстом в ряд с «Выйти» и
   бургером — остаётся иконка-стрелка (имя кнопки держит aria-label) */
@media(max-width:460px){
  .admin-open-site__icon{display:inline-flex}
  .admin-top .admin-open-site .admin-open-site__text{display:none}
  .admin-top .admin-open-site{min-width:44px;justify-content:center} /* тач-цель 44×44 и в иконночном виде */
}
.btn{display:inline-flex;align-items:center;gap:8px;min-height:42px;border:none;border-radius:999px;padding:9px 18px;font:600 .85rem var(--font-ui);cursor:pointer;background:var(--ink);color:#fff;transition:background .15s ease,transform .12s ease,box-shadow .15s ease}
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
.filters-bar input,.filters-bar select,.filters-bar button,.filters-bar a.btn{height:42px;min-height:42px}
.filters-bar .input{width:auto;min-width:130px}
.filters-bar select.input{width:auto}
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
.input,select,textarea{width:100%;min-height:42px;border:1.5px solid rgba(43,45,47,.28);border-radius:10px;padding:9px 12px;font:400 .92rem var(--font-ui);background:#fff;color:var(--ink)}
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
/* Дружелюбные статусы: эмодзи + цвет (доступность — не только цвет, паттерн UXPin 2026).
   S3 (v2026.3): этапы курьерского потока — new/photo/florist/courier/done. */
.status-badge.new{background:var(--rose)}
.status-badge.new::before{content:"✨ ";font-size:.7rem}
.status-badge.photo{background:var(--blue)}
.status-badge.photo::before{content:"📷 ";font-size:.7rem}
.status-badge.florist{background:#FFE9C7;color:#7a4a00}
.status-badge.florist::before{content:"🌸 ";font-size:.7rem}
.status-badge.courier{background:#E4EEF8;color:#2c4e7a}
.status-badge.courier::before{content:"🚚 ";font-size:.7rem}
.status-badge.done{background:var(--mint)}
.status-badge.done::before{content:"💐 ";font-size:.7rem}
.status-badge.canceled{background:#eee;opacity:.85}
.status-badge.canceled::before{content:"✖ ";font-size:.7rem}
.status-badge.unredeemed{background:#DCD3F0;color:#3d2e66}
.status-badge.unredeemed::before{content:"🕒 ";font-size:.7rem}
/* W68 (владелец #2): лента дашборда на телефоне — ссылки «№ id»/телефон были 37×17px
   (9 из 19 тап-целей <44). Инлайн-ссылки в ячейке получают min-height и воздух. */
a.order-link{display:inline-block;min-height:28px;line-height:28px}
.card a[href^="tel:"]{display:inline-block;min-height:28px;line-height:28px;padding-right:8px}
.back-link{display:inline-flex;align-items:center;min-height:28px}

/* ================================================================
   S3 (v2026.3): КАНАБН-ДОСКА ЗАКАЗОВ (admin/index.php?view=kanban)
   ================================================================ */
.view-toggle{font:600 .85rem var(--font-ui);color:var(--ink-soft);background:#fff;min-height:44px;display:inline-flex;align-items:center;text-decoration:none;transition:background .15s ease,color .15s ease}
.view-toggle:hover{background:var(--bg-alt)}
.view-toggle.is-active{background:var(--ink);color:#fff}
.kanban{display:grid;grid-template-columns:repeat(5,minmax(232px,1fr)) 200px;gap:12px;overflow-x:auto;padding-bottom:8px;align-items:start}
.kanban__col{background:var(--bg-alt);border-radius:var(--radius-lg);border:1px solid var(--line);min-height:220px;display:flex;flex-direction:column;transition:background .15s ease,border-color .15s ease}
.kanban__col.is-over{background:#fff;border-color:var(--rose-cta);box-shadow:0 0 0 3px rgba(174,74,113,.15)}
.kanban__col--side{grid-column:auto}
.kanban__col-head{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:12px 14px 8px}
.kanban__col-title{font:700 .82rem var(--font-ui);color:var(--ink)}
.kanban__col-count{font:700 .72rem var(--font-ui);background:#fff;border:1px solid var(--line);border-radius:999px;padding:2px 9px;color:var(--ink-soft)}
.kanban__cards{display:flex;flex-direction:column;gap:8px;padding:4px 10px 10px;max-height:62vh;overflow-y:auto}
.kanban__card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:10px 12px;cursor:grab;box-shadow:0 1px 3px rgba(43,45,47,.06);transition:box-shadow .15s ease,transform .12s ease,opacity .15s ease}
.kanban__card:hover{box-shadow:0 6px 18px -8px rgba(43,45,47,.3)}
.kanban__card.is-dragging{opacity:.45;transform:rotate(1.5deg) scale(.98);cursor:grabbing}
.kanban__card--muted{opacity:.75;cursor:default}
.kanban__card-top{display:flex;align-items:baseline;justify-content:space-between;gap:8px}
.kanban__num{font-weight:800;font-size:.92rem;color:var(--ink);text-decoration:underline}
.kanban__sum{font:700 .8rem var(--font-ui);color:var(--ink-soft);white-space:nowrap}
.kanban__client{margin:5px 0 0;font:600 .85rem var(--font-ui);color:var(--ink)}
.kanban__surprise{display:inline-block;background:#F4DEE3;color:#8E3B54;border-radius:999px;padding:1px 7px;font:600 .68rem var(--font-ui)}
.kanban__addr{margin:3px 0 0;font:400 .76rem/1.35 var(--font-ui);color:var(--ink-soft)}
.kanban__items{margin:5px 0 0;font:500 .76rem/1.35 var(--font-ui);color:var(--ink)}
.kanban__card-note{margin:5px 0 0;font:400 .75rem/1.35 var(--font-ui);color:#8E3B54;background:#FDF4F6;border-radius:8px;padding:4px 8px}
.kanban__card-actions{display:flex;gap:6px;margin-top:8px}
.kanban__btn{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;min-width:34px;min-height:34px;border-radius:10px;border:1px solid var(--line);background:#fff;font-size:.95rem;text-decoration:none;transition:background .15s ease,border-color .15s ease}
.kanban__btn:hover{background:var(--bg-alt);border-color:var(--ink-soft)}
.kanban__btn--wa{border-color:rgba(31,175,84,.4);color:#1FAF54}
.kanban__btn--wa:hover{background:#E9F8EF}
.kanban__btn--print{color:var(--ink-soft)}
.kanban__empty{text-align:center;color:var(--ink-soft);font-size:.8rem;padding:14px 0;opacity:.7}
@media(max-width:820px){
  .kanban{grid-template-columns:repeat(5,minmax(262px,78vw)) 190px}
  .kanban__cards{max-height:52vh}
  .kanban__card{cursor:default}
}

/* ================================================================
   S3 (v2026.3): тумблер наличия + инлайн-цена в списке товаров
   ================================================================ */
.stock-switch{display:inline-flex;align-items:center;gap:8px;border:1.5px solid var(--line);background:#fff;border-radius:999px;padding:5px 12px 5px 14px;min-height:38px;cursor:pointer;font:600 .8rem var(--font-ui);color:var(--ink-soft);transition:all .15s ease}
.stock-switch .stock-switch__knob{width:30px;height:18px;border-radius:999px;background:#D8D5CE;position:relative;flex:none;transition:background .18s ease}
.stock-switch .stock-switch__knob::after{content:"";position:absolute;top:2px;left:2px;width:14px;height:14px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(43,45,47,.35);transition:transform .18s ease}
.stock-switch.is-on{border-color:rgba(46,125,79,.45);color:#2E7D4F;background:#F0F7F2}
.stock-switch.is-on .stock-switch__knob{background:#2E7D4F}
.stock-switch.is-on .stock-switch__knob::after{transform:translateX(12px)}
.stock-switch:hover{border-color:var(--ink-soft)}
.stock-switch:focus-visible{outline:2px solid var(--ink);outline-offset:2px}
.price-quick summary{cursor:pointer;list-style:none;font-weight:700;white-space:nowrap}
.price-quick summary::-webkit-details-marker{display:none}
.price-quick summary:hover{text-decoration:underline;text-decoration-style:dotted;text-underline-offset:3px;color:var(--rose-cta)}
.price-quick[open] summary{color:var(--rose-cta)}
@media(hover:none),(pointer:coarse){.stock-switch,.price-quick summary{min-height:44px}}

summary.del-summary{cursor:pointer;color:var(--err,#C43A3A);font-size:.9rem;font-weight:600;display:inline-flex;align-items:center;min-height:32px}
.bulk-hit{display:grid;place-items:center;min-height:44px;min-width:44px;width:calc(100% + 12px);margin:-6px;cursor:pointer} /* W74 (владелец NEW): растяжка на всю ячейку — мёртвых краёв td нет */
.bulk-check{appearance:none;-webkit-appearance:none;width:22px;height:22px;border:1.5px solid rgba(43,45,47,.35);border-radius:6px;background:#fff;cursor:pointer;display:inline-grid;place-content:center;vertical-align:middle}
@media(hover:none),(pointer:coarse){.forgot-link{min-height:44px;display:flex;align-items:center;justify-content:center}} /* W76: тап-цель 16px→44 */
.bulk-check:checked{background-color:var(--rose-cta,#AE4A71);border-color:var(--rose-cta,#AE4A71);background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='white' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round' d='M3 8.5l3.2 3.3L13 4.6'/%3E%3C/svg%3E");background-size:14px 14px;background-position:center;background-repeat:no-repeat} /* W73: галочка SVG-фоном (::before на input не рендерится) */

input[type=range]{height:40px}
@media(hover:none),(pointer:coarse){summary,summary.del-summary{min-height:44px}input[type=range]{height:44px}} /* W77: +del-summary — W76 сам себя обошёл по specificity (32px база (0,1,1) > media (0,0,1)) */ /* W71: door-таргеты + слайдер на тап */
#bulkPriceForm a#bulkSelAll{display:inline-flex;align-items:center;min-height:42px}
@media(hover:none),(pointer:coarse){a.order-link,.card a[href^="tel:"],.back-link{min-height:44px}}
.card a[href^="tel:"]{white-space:nowrap} /* W73 (обvious мелочь-4): телефон не в 3 строки, target 44 соблюдён (W78: закрыт незакрытый коммент — съедал 11 правил) */

.toast{position:fixed;top:16px;right:16px;z-index:1000;background:#fff;box-shadow:0 14px 40px -20px rgba(43,45,47,.5);border-radius:var(--radius);padding:14px 18px;font-size:.9rem;max-width:320px}
.flash{padding:12px 16px;border-radius:12px;background:var(--mint);margin-bottom:16px;font-size:.9rem;border:1px solid rgba(62,142,90,.25);color:#2c5e40}
.flash--err{background:#fbe3e3;border-color:rgba(214,69,69,.3);color:#8c2f2f}
.empty-state{text-align:center;padding:36px 20px;color:var(--ink-soft);font-size:.95rem}
.empty-state strong{display:block;font-family:var(--font-display);font-size:1.15rem;color:var(--ink);margin-bottom:6px;font-weight:600}
.linklike{background:none;border:none;color:var(--rose-cta);font:600 .85rem var(--font-ui);cursor:pointer;padding:0;text-decoration:underline;text-underline-offset:2px}
.linklike:hover{color:var(--rose-cta-hover)}
.thumb{width:48px;height:48px;object-fit:cover;border-radius:10px;background:var(--bg-alt)}
.row-actions{display:flex;gap:6px;flex-wrap:wrap}
.row-actions a,.row-actions button{font-size:.78rem;padding:5px 10px;border-radius:10px;border:1px solid var(--line);background:#fff;cursor:pointer;font-family:var(--font-ui);transition:background .15s ease,color .15s ease,transform .12s ease}
.row-actions a:hover,.row-actions button:hover{transform:translateY(-1px)}
.row-actions a:active,.row-actions button:active{transform:translateY(1px)}
/* W61 (дизайн-критик): уважение prefers-reduced-motion — как на витрине, в админке не было */
@media(prefers-reduced-motion:reduce){*,*::before,*::after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important;scroll-behavior:auto!important}}
.row-actions a.danger,.row-actions button.danger{color:var(--err);border-color:var(--err)}
.card form button.danger[type=submit]{min-height:42px}
#bulkPriceForm select,#bulkPriceForm input[type=number]{min-height:42px;background:#fff}
/* W59 (визит-критик CODE_OPEN#5): единый паттерн иерархии действий — главное
   следующее действие (переход статуса) акцентное, прочие нейтральные */
.card form button[type=submit]:not(.primary-action):not(.btn):not(.danger){font:600 .82rem var(--font-ui);color:var(--ink);background:#fff;border:1px solid var(--line);border-radius:10px;padding:6px 12px;cursor:pointer;transition:background .15s ease,border-color .15s ease,transform .12s ease;min-height:42px}
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
/* W105 (4-b): iOS не зумит фокус на полях/кнопках мельче 16px (инлайн-стили
   компактных bulk-кнопок products.php перекрываем через !important);
   чек-лист дашборда — ссылки 17px → нормальные 44px строки */
@media(max-width:700px){
  button,.btn,.linklike,.row-actions a,.row-actions button,.card form button[type=submit]:not(.primary-action):not(.btn):not(.danger),button.primary-action,a.primary-action,input[type=file],input[type=file]::file-selector-button{font-size:16px !important}
  #launch-check a{display:inline-flex;align-items:center;min-height:44px}
  .filters-bar input,.filters-bar select,.filters-bar button,.filters-bar a.btn{min-height:44px} /* W105 (4-b): 42px из базового правила → 44 на таче */
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
/* W105 (4-b): аффорданс горизонтального скролла — таблицы и чипы настроек:
   правый фейд + чип «↔», гаснущий после первого скролла. Класс .is-scrollable
   вешает JS (фейд только когда контент реально обрезан); десктоп ≥701px не тронут. */
.scroll-hint{display:none}
@media(max-width:700px){
  .table-scroll.is-scrollable,#top-nav.is-scrollable{-webkit-mask-image:linear-gradient(90deg,#000 0,#000 calc(100% - 34px),transparent 100%);mask-image:linear-gradient(90deg,#000 0,#000 calc(100% - 34px),transparent 100%)}
  .hint-anchor{position:relative}
  .scroll-hint{display:inline-flex;align-items:center;justify-content:center;position:absolute;right:8px;width:30px;height:30px;border-radius:999px;background:#fff;border:1px solid var(--line);box-shadow:0 8px 20px -10px rgba(43,45,47,.55);font-size:1rem;line-height:1;color:var(--ink-soft);pointer-events:none;z-index:5;animation:scrollHintNudge 1.6s ease-in-out 2;transition:opacity .35s ease,visibility 0s linear .35s}
  .scroll-hint--off{opacity:0;visibility:hidden}
}
@keyframes scrollHintNudge{0%,100%{transform:translateX(0)}50%{transform:translateX(4px)}}
/* W65 (статич. выверка #3): sticky-оглавление настроек пряталось под двухстрочную
   моб-шапку (inline top:64px < фактические ~90px шапки на ≤700). Класс вместо inline. */
.settings-nav{margin-bottom:16px;position:sticky;top:64px;z-index:30;background:var(--bg,#F6F1E6);padding:8px 0;border-radius:0 0 12px 12px}
@media(max-width:700px){.settings-nav{top:64px}} /* W105 (4-b): шапка стала одним рядом (~64px) — стек шапка+чипы 181px→~124px */
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
    <nav class="admin-nav" id="admin-nav" aria-label="Разделы админ-панели">
      <?php foreach ($nav as $key => $label): ?>
        <?php if ($key === 'users' && !$navIsOwner) { continue; } /* W100-fixH2 (J6) */ ?>
        <a href="/admin/<?= e($key) ?>.php" class="<?= $active === $key ? 'active' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <span class="spacer"></span>
    <a href="/" target="_blank" rel="noopener" class="btn btn--ghost admin-open-site" aria-label="Открыть сайт (в новой вкладке)">
      <span class="admin-open-site__icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2v-2"/><path d="M10 1.5h4.5V6"/><path d="M14.5 1.5 8 8"/></svg></span>
      <span class="admin-open-site__text">Открыть сайт</span>
    </a>
    <a href="/admin/logout.php?t=<?= e(csrf_token()) ?>" class="btn btn--ghost">Выйти</a>
    <button class="admin-burger" type="button" aria-expanded="false" aria-controls="admin-nav" aria-label="Открыть меню разделов">
      <span class="admin-burger__bar" aria-hidden="true"></span>
      <span class="admin-burger__bar" aria-hidden="true"></span>
      <span class="admin-burger__bar" aria-hidden="true"></span>
    </button>
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
/* W105 (4-b): мобильное меню шапки — открытие бургером, закрытие тапом по ссылке,
   по Escape и по тапу вне шапки; фон заблокирован, пока меню открыто */
(function () {
  var top = document.querySelector('.admin-top');
  var burger = top ? top.querySelector('.admin-burger') : null;
  var nav = document.getElementById('admin-nav');
  if (!top || !burger || !nav) return;
  function setOpen(open) {
    top.classList.toggle('nav-open', open);
    burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    burger.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню разделов');
    document.documentElement.classList.toggle('admin-nav-lock', open);
  }
  burger.addEventListener('click', function (e) {
    e.stopPropagation();
    setOpen(!top.classList.contains('nav-open'));
  });
  nav.addEventListener('click', function (e) { if (e.target.closest('a')) setOpen(false); });
  document.addEventListener('click', function (e) {
    if (top.classList.contains('nav-open') && !e.target.closest('.admin-top')) setOpen(false);
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
  var mq = window.matchMedia('(min-width:701px)');
  if (mq.addEventListener) { mq.addEventListener('change', function (m) { if (m.matches) setOpen(false); }); }
})();
/* W105 (4-b): аффорданс скролла — у .table-scroll и чипов настроек #top-nav:
   класс .is-scrollable (правый фейд) + чип «↔», гаснущий после первого скролла.
   Плюс активный чип настроек приезжает в кадр после scrollspy */
(function () {
  var items = [];
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function decorate(sc) {
    var host = sc.parentElement;
    if (!host) return;
    var hint = null, dismissed = false;
    function place() {
      if (!hint) return;
      hint.style.top = Math.round(sc.getBoundingClientRect().top - host.getBoundingClientRect().top + 8) + 'px';
    }
    function sync() {
      var over = sc.scrollWidth - sc.clientWidth > 8;
      sc.classList.toggle('is-scrollable', over);
      if (!hint && over && !dismissed && window.matchMedia('(max-width:700px)').matches) {
        hint = document.createElement('span');
        hint.className = 'scroll-hint';
        hint.setAttribute('aria-hidden', 'true');
        hint.textContent = '↔';
        host.classList.add('hint-anchor');
        host.appendChild(hint);
      }
      if (hint) { hint.classList.toggle('scroll-hint--off', !over || dismissed); place(); }
    }
    sc.addEventListener('scroll', function () {
      dismissed = true;
      if (hint) hint.classList.add('scroll-hint--off');
    }, { passive: true });
    sync();
    items.push(sync);
  }
  Array.prototype.forEach.call(document.querySelectorAll('.table-scroll, #top-nav'), decorate);
  var rt = null;
  function resync() { items.forEach(function (fn) { fn(); }); }
  window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(resync, 150); });
  window.addEventListener('load', resync);
  /* активный чип настроек — в кадр (после того как scrollspy отметит aria-current) */
  setTimeout(function () {
    var a = document.querySelector('#top-nav a[aria-current]');
    if (a && a.scrollIntoView) { try { a.scrollIntoView({ block: 'nearest', inline: 'center', behavior: reduce ? 'auto' : 'smooth' }); } catch (e) {} }
  }, 400);
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
