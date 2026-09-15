<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/util.php';
require_once __DIR__ . '/../includes/layout.php';
ensureAdminUser();
requireAdmin();

/* CSRF: админ-POST без валидного токена — отказ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_check()) {
    $back = $_SERVER['HTTP_REFERER'] ?? '/admin/index.php';
    header('Location: ' . $back);
    exit;
}

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $anchor = ''; /* владелец-критик W50: якорь #pr-code только при отказе формы save */
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $code = mb_strtoupper(trim((string)($_POST['code'] ?? '')), 'UTF-8');
        $codeRaw = $code;
        $code = preg_replace('/[^A-Z0-9\-_]/u', '', $code);
        $kind = ($_POST['kind'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
        $value = max(0, (int)($_POST['value'] ?? 0));
        if ($kind === 'percent') { $value = min(90, $value); }
        $minOrder = max(0, (int)($_POST['min_order'] ?? 0));
        $maxUses = max(0, (int)($_POST['max_uses'] ?? 0));
        $active = isset($_POST['active']) ? 1 : 0;
        if ($codeRaw !== '' && $code !== preg_replace('/\s+/u', '', $codeRaw)) {
            /* Владелец-критик W45-финал: любой потерянный символ = стоп.
               W48: честная транслитерация фактического ввода + черновик в поле.
               W49: редирект с якорем #pr-code — поле не уезжает вверх после отказа. */
            $suggest = preg_replace('/[^A-Z0-9\-_]/', '', mb_strtoupper(trim(slugify($codeRaw)), 'UTF-8'));
            flash('В коде можно использовать только латинские буквы и цифры (можно дефис).'
                . ($suggest !== '' ? ' Ваш вариант можно записать так: ' . $suggest : ''), true);
            $_SESSION['promo_code_draft'] = $codeRaw;
            header('Location: /admin/promo.php#pr-code');
            exit;
        }
        if ($code === '' && $codeRaw !== '') {
            /* Владелец-критик W45 P1: кириллический код не должен вырезаться молча. */
            $suggest = preg_replace('/[^A-Z0-9\-_]/', '', mb_strtoupper(trim(slugify($codeRaw)), 'UTF-8'));
            flash('Код можно писать только латинскими буквами и цифрами'
                . ($suggest !== '' ? ' — например, ' . $suggest : ' — например CVETY10'), true);
            $_SESSION['promo_code_draft'] = $codeRaw;
            $anchor = '#pr-code';
        } elseif ($code !== '' && ctype_digit($code)) {
            /* Владелец-критик W49 п.3: чисто цифровой код неотличим от скидки/опечатки. */
            flash('Код состоит только из цифр — его неудобно диктовать, «10» путается со скидкой. Добавьте буквы, например CVETY10', true);
            $_SESSION['promo_code_draft'] = $codeRaw;
            $anchor = '#pr-code';
        } elseif ($code === '' || $value <= 0) {
            flash('Заполните код и скидку (больше нуля)', true);
            $anchor = '#pr-code';
        } else {
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE promo_codes SET code=:c, kind=:k, value=:v, min_order=:m, max_uses=:x, active=:a WHERE id=:i')
                        ->execute([':c' => $code, ':k' => $kind, ':v' => $value, ':m' => $minOrder, ':x' => $maxUses, ':a' => $active, ':i' => $id]);
                    flash('Промокод обновлён');
                } else {
                    $pdo->prepare('INSERT INTO promo_codes (code, kind, value, min_order, max_uses, active) VALUES (:c, :k, :v, :m, :x, :a)')
                        ->execute([':c' => $code, ':k' => $kind, ':v' => $value, ':m' => $minOrder, ':x' => $maxUses, ':a' => $active]);
                    unset($_SESSION['promo_code_draft']); /* черновик больше не нужен — код создан */
                    flash('Промокод создан — скажите его покупателю или разместите на витрине');
                }
            } catch (Throwable $e) {
                flash('Такой код уже есть', true);
                $anchor = '#pr-code';
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE promo_codes SET active = 1 - active WHERE id = :i')->execute([':i' => $id]);
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM promo_codes WHERE id = :i')->execute([':i' => $id]);
        flash('Промокод удалён');
    }
    /* Владелец-критик W49-финал/W50: якорь ставится явно в ветках отказа save;
       toggle/delete/успех — чистый Location (E2E поймал протечку draft-флага). */
    header('Location: /admin/promo.php' . $anchor);
    exit;
}

$editId = (int)($_GET['edit'] ?? 0);
$editing = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM promo_codes WHERE id = :i');
    $stmt->execute([':i' => $editId]);
    $editing = $stmt->fetch();
}
$promos = $pdo->query('SELECT * FROM promo_codes ORDER BY active DESC, id DESC')->fetchAll();

adminHeader('Промокоды', 'promo');
flash();
?>
<h1>Промокоды</h1>
<p style="font-size:.85rem;color:var(--ink-soft)">Скидка проверяется и считается на сервере при оформлении заказа. Покупатель вводит код в корзине.</p>

<div class="card">
  <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px"><?= $editing ? 'Редактирование: ' . e($editing['code']) : 'Новый промокод' ?></h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $editing ? (int)$editing['id'] : 0 ?>">
    <div class="grid2">
      <div>
        <label class="f" for="pr-code">Код *</label>
        <input class="input" id="pr-code" name="code" required maxlength="32" style="text-transform:uppercase" value="<?= $editing ? e($editing['code']) : e((string)($_SESSION['promo_code_draft'] ?? '')) ?>" placeholder="Латиница и цифры, напр. CVETY10">
        <?php /* W49: черновик живёт до успешного создания (не исчезает от F5 — владелец п.1) */ ?>
        <label class="f" for="pr-kind">Тип скидки</label>
        <select id="pr-kind" name="kind">
          <option value="percent" <?= $editing && $editing['kind'] === 'percent' ? 'selected' : '' ?>>Процент от заказа (%)</option>
          <option value="fixed" <?= $editing && $editing['kind'] === 'fixed' ? 'selected' : '' ?>>Фикс (₽)</option>
        </select>
        <label class="f" for="pr-value">Размер скидки *</label>
        <input class="input" id="pr-value" name="value" type="number" min="1" required value="<?= $editing ? (int)$editing['value'] : '' ?>" placeholder="10">
      </div>
      <div>
        <label class="f" for="pr-min">Минимальная сумма заказа, ₽ (0 — без условия)</label>
        <input class="input" id="pr-min" name="min_order" type="number" min="0" value="<?= $editing ? (int)$editing['min_order'] : 0 ?>">
        <label class="f" for="pr-max">Максимум использований (0 — безлимит)</label>
        <input class="input" id="pr-max" name="max_uses" type="number" min="0" value="<?= $editing ? (int)$editing['max_uses'] : 0 ?>">
        <label class="f" style="display:flex;gap:8px;align-items:center;font-weight:500;margin-top:16px">
          <input type="checkbox" name="active" style="width:auto" <?= $editing ? ((int)$editing['active'] === 1 ? 'checked' : '') : 'checked' ?>>
          Включён (действует на витрине)
        </label>
      </div>
    </div>
    <div style="display:flex;gap:10px;margin-top:18px">
      <button class="btn btn--accent" type="submit"><?= $editing ? 'Сохранить' : 'Создать промокод' ?></button>
      <?php if ($editing): ?><a class="btn btn--ghost" href="/admin/promo.php">Отмена</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="card">
  <h2 style="font-family:var(--font-display);font-size:1.2rem">Все промокоды</h2>
  <?php if ($promos === []): ?>
    <p style="color:var(--ink-soft)">Пока нет ни одного. Создайте первый — например «ЦВЕТЫ10» на 10% от заказа от 3000 ₽.</p>
  <?php else: ?>
  <div class="table-scroll"><table>
    <tr><th>Код</th><th>Скидка</th><th>От суммы</th><th>Использован</th><th>Статус</th><th></th><th></th></tr>
    <?php foreach ($promos as $pr): ?>
    <tr>
      <td><strong><?= e($pr['code']) ?></strong></td>
      <td><?= $pr['kind'] === 'fixed' ? formatPrice((int)$pr['value']) /* W72: formatPrice уже добавляет ₽ */ : (int)$pr['value'] . '%' ?></td>
      <td><?= (int)$pr['min_order'] > 0 ? formatPrice((int)$pr['min_order']) . ' ₽' : '—' ?></td>
      <td><?= (int)$pr['used'] ?><?= (int)$pr['max_uses'] > 0 ? ' из ' . (int)$pr['max_uses'] : '' ?></td>
      <td><?= (int)$pr['active'] === 1 ? 'Действует' : 'Выключен' ?></td>
      <td>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$pr['id'] ?>">
          <button type="submit"><?= (int)$pr['active'] === 1 ? 'Выключить' : 'Включить' ?></button>
        </form>
      </td>
      <td>
        <a href="/admin/promo.php?edit=<?= (int)$pr['id'] ?>">Изменить</a>
        <form method="post" style="display:inline" onsubmit="return confirm('Удалить промокод <?= e($pr['code']) ?>?')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$pr['id'] ?>">
          <button type="submit">Удалить</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</div>
<?php adminFooter(); ?>
