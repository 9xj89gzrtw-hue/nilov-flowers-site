<?php
/* «Случаи» — occasion-лендинги (SEO-критик critical#2). CRUD по образцу promo.php:
   slug, тексты, FAQ, подборка товаров (id через запятую), тумблер «показывать». */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/util.php';
require_once __DIR__ . '/../includes/layout.php';
ensureAdminUser();
requireAdmin();

/* CSRF: админ-POST без валидного токена — отказ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_check()) {
    $back = $_SERVER['HTTP_REFERER'] ?? '/admin/occasions.php';
    header('Location: ' . $back);
    exit;
}

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $slug = slugify((string)($_POST['slug'] ?? ''));
        $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 120);
        if ($slug === '' || $title === '') {
            flash('Заполните название и адрес (slug)', true);
        } else {
            $f = static fn(string $k, int $max): string => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);
            $vals = [
                ':slug' => $slug, ':title' => $title,
                ':mt' => $f('meta_title', 120), ':md' => $f('meta_description', 200),
                ':intro' => $f('intro', 400), ':body' => $f('body', 4000),
                ':q1' => $f('faq_q1', 200), ':a1' => $f('faq_a1', 700),
                ':q2' => $f('faq_q2', 200), ':a2' => $f('faq_a2', 700),
                ':ids' => preg_replace('/[^0-9,]/', '', (string)($_POST['product_ids'] ?? '')),
                ':act' => isset($_POST['active']) ? 1 : 0,
                ':sort' => (int)($_POST['sort'] ?? 99),
            ];
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE occasions SET slug=:slug, title=:title, meta_title=:mt, meta_description=:md, intro=:intro, body=:body, faq_q1=:q1, faq_a1=:a1, faq_q2=:q2, faq_a2=:a2, product_ids=:ids, active=:act, sort=:sort WHERE id=:i')
                        ->execute($vals + [':i' => $id]);
                    flash('Страница случая обновлена');
                } else {
                    $pdo->prepare('INSERT INTO occasions (slug, title, meta_title, meta_description, intro, body, faq_q1, faq_a1, faq_q2, faq_a2, product_ids, active, sort) VALUES (:slug,:title,:mt,:md,:intro,:body,:q1,:a1,:q2,:a2,:ids,:act,:sort)')
                        ->execute($vals);
                    flash('Страница создана — добавьте ссылку в sitemap она уже учитывается');
                }
            } catch (Throwable $e) {
                flash('Такой адрес (slug) уже занят', true);
            }
        }
    } elseif ($action === 'toggle') {
        $pdo->prepare('UPDATE occasions SET active = 1 - active WHERE id = :i')->execute([':i' => (int)($_POST['id'] ?? 0)]);
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM occasions WHERE id = :i')->execute([':i' => (int)($_POST['id'] ?? 0)]);
        flash('Страница удалена');
    }
    header('Location: /admin/occasions.php');
    exit;
}

$editId = (int)($_GET['edit'] ?? 0);
$editing = null;
if ($editId > 0) {
    $st = $pdo->prepare('SELECT * FROM occasions WHERE id = :i');
    $st->execute([':i' => $editId]);
    $editing = $st->fetch() ?: null;
}
$rows = $pdo->query('SELECT * FROM occasions ORDER BY sort, id')->fetchAll();
$liveProducts = $pdo->query("SELECT id, name FROM products WHERE is_active = 1 AND price > 0 ORDER BY sort, id")->fetchAll();

adminHeader('Случаи', 'occasions');
flash();
?>
<h1>Страницы-случаи (для поиска)</h1>
<p class="admin-hint" style="font-size:.85rem;color:var(--ink-soft);margin:-8px 0 14px">Отдельные страницы под вопросы покупателей: «свадебные букеты», «букет на день рождения», «траурные». Каждая — со своей подборкой букетов, FAQ и текстом. Включено = видно в меню на сайте и в sitemap.</p>

<div class="card">
  <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px"><?= $editing ? 'Редактирование: ' . e($editing['title']) : 'Новая страница-случай' ?></h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $editing ? (int)$editing['id'] : 0 ?>">
    <div class="grid2">
      <div>
        <label class="f" for="o-title">Название (заголовок страницы) *</label>
        <input class="input" id="o-title" name="title" required maxlength="120" value="<?= e($editing['title'] ?? '') ?>" placeholder="Свадебные букеты в Санкт-Петербурге">
        <label class="f" for="o-slug">Адрес страницы *</label>
        <input class="input" id="o-slug" name="slug" required maxlength="80" value="<?= e($editing['slug'] ?? '') ?>" placeholder="svadebnye-bukety">
        <label class="f" for="o-intro">Вводный абзац (под заголовком)</label>
        <textarea class="input" id="o-intro" name="intro" rows="2" maxlength="400"><?= e($editing['intro'] ?? '') ?></textarea>
        <label class="f" for="o-body">Основной текст (как всё устроено)</label>
        <textarea class="input" id="o-body" name="body" rows="5" maxlength="4000"><?= e($editing['body'] ?? '') ?></textarea>
      </div>
      <div>
        <label class="f" for="o-md">Описание для поиска (150-160 знаков)</label>
        <textarea class="input" id="o-md" name="meta_description" rows="2" maxlength="200"><?= e($editing['meta_description'] ?? '') ?></textarea>
        <label class="f" for="o-ids">Букеты из подборки (идея: через запятую)</label>
        <input class="input" id="o-ids" name="product_ids" value="<?= e($editing['product_ids'] ?? '') ?>" placeholder="1,7,8">
        <small style="display:block;color:var(--ink-soft);margin:4px 0 0">
          <?php foreach ($liveProducts as $lp): ?><?= (int)$lp['id'] ?> — <?= e($lp['name']) ?><br><?php endforeach; ?>
        </small>
        <label class="f" for="o-q1">Вопрос 1</label>
        <input class="input" id="o-q1" name="faq_q1" maxlength="200" value="<?= e($editing['faq_q1'] ?? '') ?>">
        <label class="f" for="o-a1">Ответ 1</label>
        <textarea class="input" id="o-a1" name="faq_a1" rows="2" maxlength="700"><?= e($editing['faq_a1'] ?? '') ?></textarea>
        <label class="f" for="o-q2">Вопрос 2</label>
        <input class="input" id="o-q2" name="faq_q2" maxlength="200" value="<?= e($editing['faq_q2'] ?? '') ?>">
        <label class="f" for="o-a2">Ответ 2</label>
        <textarea class="input" id="o-a2" name="faq_a2" rows="2" maxlength="700"><?= e($editing['faq_a2'] ?? '') ?></textarea>
      </div>
    </div>
    <div style="display:flex;gap:16px;align-items:center;margin-top:12px;flex-wrap:wrap">
      <label class="f" style="display:flex;gap:8px;align-items:center;margin:0">
        <input type="checkbox" name="active" style="width:auto" <?= !$editing || (int)$editing['active'] === 1 ? 'checked' : '' ?>> Показывать страницу
      </label>
      <label class="f" style="display:flex;gap:8px;align-items:center;margin:0">Порядок
        <input class="input" type="number" name="sort" min="1" max="999" value="<?= (int)($editing['sort'] ?? 99) ?>" style="width:80px">
      </label>
    </div>
    <div style="display:flex;gap:10px;margin-top:14px">
      <button class="btn btn--accent" type="submit"><?= $editing ? 'Сохранить' : 'Создать страницу' ?></button>
      <?php if ($editing): ?><a class="btn btn--ghost" href="/admin/occasions.php">Отмена</a><?php endif; ?>
    </div>
  </form>
</div>

<?php if ($rows !== []): ?>
<div class="card">
  <h2 style="font-family:var(--font-display);font-size:1.15rem;margin-bottom:8px">Созданные страницы</h2>
  <div class="table-scroll"><table>
    <tr><th>Название</th><th>Адрес</th><th>Букетов</th><th>Статус</th><th></th><th></th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><strong><?= e($r['title']) ?></strong></td>
      <td><code style="font-size:.8rem">/occasion/<?= e($r['slug']) ?></code></td>
      <td><?= count(array_filter(array_map('intval', explode(',', (string)$r['product_ids'])))) ?></td>
      <td><?= (int)$r['active'] === 1 ? 'Показана' : 'Скрыта' ?></td>
      <td>
        <div class="row-actions">
          <a href="/admin/occasions.php?edit=<?= (int)$r['id'] ?>">Изменить</a>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button type="submit"><?= (int)$r['active'] === 1 ? 'Скрыть' : 'Показать' ?></button></form>
        </div>
      </td>
      <td>
        <div class="row-actions">
          <form method="post" onsubmit="return confirm('Удалить страницу «<?= e($r['title']) ?>»?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button type="submit" class="danger">Удалить</button></form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php endif; ?>
<?php adminFooter(); ?>
