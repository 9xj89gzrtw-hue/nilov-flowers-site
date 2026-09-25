<?php
/* Профиль администратора: пароль, запасной email, мессенджер-чаты, email уведомлений. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/util.php';
require_once __DIR__ . '/../includes/layout.php';
ensureAdminUser();
requireAdmin();

$pdo = db();

/* W100-fixH2 (J1): глобальные каналы уведомлений (токен бота, ID чатов, тихие часы)
   настраивает только владелец — сотруднику в профиле доступна смена своего пароля,
   запасного email и локального звука. */
$me = currentAdmin();
$isOwner = $me !== null && (string)($me['role'] ?? 'owner') === 'owner';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        /* W90 (stress P3): отказ = HTTP 400, exit ДО PRG-редиректа */
        http_response_code(400);
        exit('Неверный CSRF-токен. Обновите страницу.');
    }
    $admin = currentAdmin();
    if ($admin === null) {
        header('Location: /admin/login.php');
        exit;
    }
    $section = (string)($_POST['section'] ?? '');

    if ($section === 'backup_email') {
        /* Запасной email восстановления — требует текущий пароль */
        $current = (string)($_POST['current'] ?? '');
        $backup = trim((string)($_POST['backup_email'] ?? ''));
        if ($backup !== '' && !filter_var($backup, FILTER_VALIDATE_EMAIL)) {
            flash('Некорректный запасной email', true);
        } elseif (!password_verify($current, (string)$admin['password_hash'])) {
            flash('Текущий пароль указан неверно', true);
        } else {
            $pdo->prepare('UPDATE admin_users SET backup_email = :b WHERE id = :i')
                ->execute([':b' => $backup, ':i' => (int)$admin['id']]);
            flash('Запасной email сохранён');
        }
        header('Location: /admin/profile.php');
        exit;
    }

    if ($section === 'notifications') {
        /* W100-fixH2 (J1): серверный гард — сотруднику глобальные каналы не сохраняются
           (даже собранным руками POST), форма у staff и не рендерится */
        if (!$isOwner) {
            flash('Уведомления магазина настраивает владелец', true);
            header('Location: /admin/profile.php');
            exit;
        }
        /* Личные чаты и email уведомлений (пусто → на email логина) */
        $tg = trim((string)($_POST['tg_chat_id'] ?? ''));
        $mx = trim((string)($_POST['max_chat_id'] ?? ''));
        /* W98-fixD (D4): токен бота Telegram — его читает includes/notify.php
           (setting('telegram_bot_token')), раньше поле в админке отсутствовало вовсе.
           W100-fixH2 (J2-паттерн): токен в DOM не отдаётся — поле без value;
           пусто = не трогать, непусто = записать. */
        $tgToken = mb_substr(trim((string)($_POST['telegram_bot_token'] ?? '')), 0, 200);
        $notifyEmail = trim((string)($_POST['notify_email'] ?? ''));
        $notifyEnabled = isset($_POST['notify_enabled']) ? 1 : 0;
        if ($notifyEmail !== '' && !filter_var($notifyEmail, FILTER_VALIDATE_EMAIL)) {
            flash('Некорректный email для уведомлений', true);
        } else {
            /* tg_chat_id/max_chat_id — глобальные ключи settings (db.php seed), а не колонки admin_users;
               токен пишем только если пришёл непустым — пустая отправка не затирает секрет */
            $saveGlobals = ['tg_chat_id' => $tg, 'max_chat_id' => $mx];
            if ($tgToken !== '') {
                $saveGlobals['telegram_bot_token'] = $tgToken;
            }
            saveSettings($saveGlobals);
            $pdo->prepare('UPDATE admin_users SET notify_email = :n, notify_enabled = :en WHERE id = :i')
                ->execute([':n' => $notifyEmail, ':en' => $notifyEnabled, ':i' => (int)$admin['id']]);
            flash('Настройки уведомлений сохранены');
        }
        header('Location: /admin/profile.php');
        exit;
    }

    if ($section === 'quiet_hours') {
        /* Тихие часы магазина (глобальные настройки, UI в профиле) — только владельцу (W100-fixH2 J1) */
        if (!$isOwner) {
            flash('Уведомления магазина настраивает владелец', true);
            header('Location: /admin/profile.php');
            exit;
        }
        $qf = trim((string)($_POST['quiet_from'] ?? ''));
        $qt = trim((string)($_POST['quiet_to'] ?? ''));
        $valid = static fn(string $t): bool => $t === '' || preg_match('/^\d{2}:\d{2}$/', $t);
        if (!$valid($qf) || !$valid($qt)) {
            flash('Некорректный формат тихих часов', true);
        } else {
            saveSettings(['quiet_from' => $qf, 'quiet_to' => $qt]);
            flash('Тихие часы сохранены');
        }
        header('Location: /admin/profile.php');
        exit;
    }

    if ($section === 'password') {
        /* Смена пароля (логика из password.php) */
        $current = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['new'] ?? '');
        $new2 = (string)($_POST['new2'] ?? '');
        if (!password_verify($current, (string)$admin['password_hash'])) {
            flash('Текущий пароль указан неверно', true);
        } elseif (mb_strlen($new) < 8) {
            flash('Новый пароль должен быть не короче 8 символов', true);
        } elseif ($new !== $new2) {
            flash('Новые пароли не совпадают', true);
        } else {
            $pdo->prepare('UPDATE admin_users SET password_hash = :h WHERE id = :i')
                ->execute([':h' => password_hash($new, PASSWORD_DEFAULT), ':i' => (int)$admin['id']]);
            flash('Пароль изменён');
        }
        header('Location: /admin/profile.php');
        exit;
    }
}

$admin = currentAdmin();
$notifyEmailOut = (string)($admin['notify_email'] ?? '');
$loginEmail = (string)($admin['email'] ?? '') !== '' ? (string)$admin['email'] : (string)$admin['login'];
adminHeader('Профиль', 'profile');
flash();
?>
<h1>Профиль</h1>

<div class="card" style="max-width:560px">
  <h2 style="font-size:1.05rem;margin-bottom:8px">Смена пароля</h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="password">
    <label class="f" for="cur1">Текущий пароль *</label>
    <input class="input" id="cur1" name="current" type="password" autocomplete="current-password" required>
    <label class="f" for="n1">Новый пароль * <small style="font-weight:400;color:var(--ink-soft)">(минимум 8 символов)</small></label>
    <input class="input" id="n1" name="new" type="password" autocomplete="new-password" minlength="8" required>
    <label class="f" for="n2">Новый пароль ещё раз *</label>
    <input class="input" id="n2" name="new2" type="password" autocomplete="new-password" minlength="8" required>
    <button class="btn btn--accent" type="submit" style="margin-top:16px">Сменить пароль</button>
  </form>
</div>

<div class="card" style="max-width:560px">
  <h2 style="font-size:1.05rem;margin-bottom:8px">Запасной email восстановления</h2>
  <p style="font-size:.85rem;color:var(--ink-soft);margin-bottom:4px">Используется для восстановления доступа, если основной email недоступен. Сохранение требует текущий пароль.</p>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="backup_email">
    <label class="f" for="bk">Запасной email</label>
    <input class="input" id="bk" name="backup_email" type="email" value="<?= e((string)($admin['backup_email'] ?? '')) ?>" placeholder="backup@example.com">
    <label class="f" for="cur2">Текущий пароль *</label>
    <input class="input" id="cur2" name="current" type="password" autocomplete="current-password" required>
    <button class="btn btn--accent" type="submit" style="margin-top:16px">💾 Сохранить запасной email</button>
  </form>
</div>

<?php if ($isOwner): /* W100-fixH2 (J1): каналы уведомлений магазина — только владельцу */ ?>
<div class="card" style="max-width:560px">
  <h2 style="font-size:1.05rem;margin-bottom:8px">Уведомления о заказах</h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="notifications">
    <label class="f" for="ne">Email для уведомлений <small style="font-weight:400;color:var(--ink-soft)">(пусто — на <?= e($loginEmail) ?>)</small></label>
    <input class="input" id="ne" name="notify_email" type="email" value="<?= e($notifyEmailOut) ?>">
    <label class="f" style="display:flex;gap:8px;align-items:center;margin-top:12px">
      <input type="checkbox" name="notify_enabled" style="width:auto" <?= (int)($admin['notify_enabled'] ?? 1) === 1 ? 'checked' : '' ?>> Включить уведомления
    </label>
    <div class="grid2" style="margin-top:12px">
      <div>
        <label class="f" for="tgt">Токен бота Telegram</label>
        <?php /* W100-fixH2 (J2-паттерн): значение НЕ отдаётся в DOM — пусто = секрет не изменится */ ?>
        <input class="input" id="tgt" name="telegram_bot_token" type="password" autocomplete="new-password" value="" placeholder="оставьте пустым — токен не изменится"<?= trim(setting('telegram_bot_token', '')) !== '' ? ' data-secret-set="1"' : '' ?>>
        <p style="font-size:.78rem;color:var(--ink-soft);margin:4px 0 0">Получите у <a href="https://t.me/BotFather" target="_blank" rel="noopener">@BotFather</a> (/newbot → скопируйте токен). Вместе с ID чата включает уведомления о заказах в Telegram.<?= trim(setting('telegram_bot_token', '')) !== '' ? ' Токен задан — поле пустое, чтобы его не показывать.' : '' ?></p>
      </div>
      <div>
        <label class="f" for="tg">ID чата: напишите боту @userinfobot — он ответит вашим ID</label>
        <input class="input" id="tg" name="tg_chat_id" value="<?= e(setting('tg_chat_id', '')) ?>" placeholder="123456789">
      </div>
    </div>
    <label class="f" for="mx">ID чата в MAX <small style="font-weight:400;color:var(--ink-soft)">(для MAX — пока не используется)</small></label>
    <input class="input" id="mx" name="max_chat_id" value="<?= e(setting('max_chat_id', '')) ?>" placeholder="-100123456">
    <p style="font-size:.78rem;color:var(--ink-soft);margin:4px 0 0">Мессенджер MAX ещё не подключён к отправке уведомлений — поле сохраняется на будущее.</p>
    <button class="btn btn--accent" type="submit" style="margin-top:16px">💾 Сохранить настройки уведомлений</button>
  </form>
</div>
<?php else: /* W100-fixH2 (J1): паттерн D5 — заглушка для сотрудника */ ?>
<div class="card" style="max-width:560px">
  <h2 style="font-size:1.05rem;margin-bottom:8px">Уведомления о заказах</h2>
  <p style="font-size:.85rem;color:var(--ink-soft);margin:0;padding:10px 12px;background:var(--bg-alt,#F1EAD9);border:1px dashed var(--ink-soft);border-radius:10px">🔒 Уведомления магазина (токен Telegram-бота, ID чатов, тихие часы) — <strong>доступно владельцу</strong>. Здесь вы можете сменить свой пароль и запасной email.</p>
</div>
<?php endif; ?>
<div class="card" style="max-width:560px">
  <h2 style="font-size:1.05rem;margin-bottom:8px">Звук при новом заказе</h2>
  <p style="font-size:.85rem;color:var(--ink-soft);margin-bottom:8px">Пока открыта панель, новые заказы проверяются каждые 10 секунд, пока вкладка активна (в фоне — раз в минуту), и подаётся короткий сигнал. Настройка действует только на этом устройстве и в этом браузере.</p>
  <label class="f" for="vol">Громкость</label>
  <input type="range" id="vol" min="0" max="100" step="10" style="width:100%;accent-color:var(--rose-deep,#E2799C)">
  <div style="display:flex;gap:10px;margin-top:10px">
    <button class="btn" type="button" id="volTest">Проверить звук</button>
    <span id="volHint" style="font-size:.82rem;color:var(--ink-soft);align-self:center"></span>
  </div>
</div>
<script>
/* Громкость звука уведомлений — per-device (localStorage), общий с admin-notify.js */
(function () {
  var range = document.getElementById('vol');
  var hint = document.getElementById('volHint');
  if (!range) return;
  var saved = parseFloat(localStorage.getItem('admin_sound_volume'));
  range.value = isNaN(saved) ? 50 : Math.round(saved * 100);
  function show() { hint.textContent = range.value + '%'; }
  range.addEventListener('input', function () {
    localStorage.setItem('admin_sound_volume', (range.value / 100).toFixed(2));
    show();
  });
  show();
  document.getElementById('volTest').addEventListener('click', function () {
    try {
      var ctx = new (window.AudioContext || window.webkitAudioContext)();
      var osc = ctx.createOscillator();
      var gain = ctx.createGain();
      osc.frequency.value = 880;
      gain.gain.value = parseFloat(localStorage.getItem('admin_sound_volume') || '0.5');
      osc.connect(gain).connect(ctx.destination);
      osc.start();
      setTimeout(function () { osc.stop(); ctx.close(); }, 150);
    } catch (e) { hint.textContent = 'Звук недоступен в этом браузере'; }
  });
})();
</script>

<?php if ($isOwner): /* W100-fixH2 (J1): тихие часы — глобальная настройка, только владельцу */ ?>
<div class="card" style="max-width:560px">
  <h2 style="font-size:1.05rem;margin-bottom:8px">Тихие часы магазина</h2>
  <p style="font-size:.85rem;color:var(--ink-soft);margin-bottom:8px">В это время письма о новых заказах не отправляются. Оба поля пустые — тихие часы выключены. Часовой пояс магазина: <?= e(setting('shop_timezone', 'Europe/Moscow')) ?>.</p>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="quiet_hours">
    <div class="grid2">
      <div>
        <label class="f" for="qf">С (не отправлять с)</label>
        <input class="input" id="qf" name="quiet_from" type="time" value="<?= e(setting('quiet_from', '')) ?>">
      </div>
      <div>
        <label class="f" for="qt">До (возобновить в)</label>
        <input class="input" id="qt" name="quiet_to" type="time" value="<?= e(setting('quiet_to', '')) ?>">
      </div>
    </div>
    <button class="btn" type="submit" style="margin-top:16px">Сохранить тихие часы</button>
  </form>
</div>
<?php endif; ?>
<?php adminFooter(); ?>
