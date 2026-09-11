<?php
declare(strict_types=1);

function adminHeader(string $title, string $active = ''): void
{
    $nav = [
        'index' => 'Заказы',
        'products' => 'Товары',
        'categories' => 'Категории',
        'zones' => 'Зоны доставки',
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
  --line:rgba(43,45,47,.12); --err:#d64545; --ok:#3e8e5a;
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
.admin-nav a{padding:8px 14px;border-radius:999px;font-size:.9rem;font-weight:500;color:var(--ink-soft)}
.admin-nav a.active{background:var(--rose);color:var(--ink)}
.admin-top .spacer{flex:1}
.btn{display:inline-flex;align-items:center;gap:8px;border:none;border-radius:999px;padding:9px 18px;font:600 .85rem var(--font-ui);cursor:pointer;background:var(--ink);color:#fff}
.btn:hover{background:#000}
.btn--accent{background:var(--rose-deep)}
.btn--ghost{background:transparent;border:1.5px solid var(--line);color:var(--ink)}
main.wrap{padding:28px 20px 60px}
h1{font-family:var(--font-display);font-weight:600;font-size:1.8rem;margin-bottom:18px}
h1 .status-badge{vertical-align:middle;margin-left:10px}
.order-link{font-weight:700;text-decoration:underline}
.filters-bar{display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:14px}
.filters-bar .f{margin:0}
.card{background:#fff;border-radius:var(--radius-lg);padding:22px;box-shadow:0 14px 40px -28px rgba(43,45,47,.35);margin-bottom:20px}
table{width:100%;border-collapse:collapse;font-size:.9rem}
th{text-align:left;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;color:var(--ink-soft);padding:8px 10px;border-bottom:1px solid var(--line)}
td{padding:10px;border-bottom:1px solid var(--line);vertical-align:middle}
tr:last-child td{border-bottom:none}
.input,select,textarea{width:100%;border:1.5px solid var(--line);border-radius:10px;padding:9px 12px;font:400 .9rem var(--font-ui);background:var(--bg)}
.input:focus,select:focus,textarea:focus{outline:none;border-color:var(--rose-deep)}
label.f{display:block;font-size:.8rem;font-weight:600;margin:12px 0 4px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:0 20px}
@media(max-width:700px){.grid2{grid-template-columns:1fr}}
.status-badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:.75rem;font-weight:600;background:var(--bg-alt)}
.status-badge.new{background:var(--rose)}
.status-badge.confirmed{background:var(--blue)}
.status-badge.done{background:var(--mint)}
.status-badge.canceled{background:#eee}
.flash{padding:12px 16px;border-radius:12px;background:var(--mint);margin-bottom:16px;font-size:.9rem}
.flash--err{background:#fbe3e3}
.thumb{width:48px;height:48px;object-fit:cover;border-radius:8px;background:var(--bg-alt)}
.row-actions{display:flex;gap:6px;flex-wrap:wrap}
.row-actions a,.row-actions button{font-size:.78rem;padding:5px 10px;border-radius:8px;border:1px solid var(--line);background:#fff;cursor:pointer;font-family:var(--font-ui)}
.row-actions a.danger,.row-actions button.danger{color:var(--err);border-color:var(--err)}
</style>
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
    <a href="/admin/logout.php" class="btn btn--ghost">Выйти</a>
  </div>
</header>
<main class="wrap">
    <?php
}

function adminFooter(): void
{
    ?></main></body></html><?php
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
