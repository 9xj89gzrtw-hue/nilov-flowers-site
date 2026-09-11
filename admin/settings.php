<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/util.php';
require_once __DIR__ . '/../includes/layout.php';
ensureAdminUser();
requireAdmin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = ['shop_name','shop_phone','shop_address','hero_title','hero_subtitle',
        'hero_button_text','hero_button_link','steps_title','step_1','step_2','step_3',
        'guarantees_title','guarantee_1','guarantee_2','guarantee_3'];
    $values = [];
    foreach ($keys as $k) {
        $values[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $hero = saveUpload($_FILES['hero_image'] ?? [], IMG_UPLOADS_DIR);
    if ($hero !== '') {
        deleteImage(setting('hero_image'), IMG_UPLOADS_DIR);
        $values['hero_image'] = $hero;
    }
    $logo = saveUpload($_FILES['logo_image'] ?? [], IMG_UPLOADS_DIR);
    if ($logo !== '') {
        deleteImage(setting('logo_image'), IMG_UPLOADS_DIR);
        $values['logo_image'] = $logo;
    }
    saveSettings($values);
    flash('Настройки сохранены');
    header('Location: /admin/settings.php');
    exit;
}

$s = allSettings();
function sv(string $k, array $s): string { return e($s[$k] ?? ''); }

adminHeader('Настройки', 'settings');
flash();
?>
<h1>Настройки магазина</h1>

<form method="post" enctype="multipart/form-data">
  <div class="card">
    <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Общие</h2>
    <div class="grid2">
      <div>
        <label class="f" for="s-name">Название магазина *</label>
        <input class="input" id="s-name" name="shop_name" required value="<?= sv('shop_name', $s) ?>">
        <label class="f" for="s-phone">Телефон</label>
        <input class="input" id="s-phone" name="shop_phone" value="<?= sv('shop_phone', $s) ?>">
        <label class="f" for="s-addr">Адрес</label>
        <input class="input" id="s-addr" name="shop_address" value="<?= sv('shop_address', $s) ?>">
      </div>
      <div>
        <label class="f" for="s-logo">Логотип (заменить)</label>
        <input class="input" id="s-logo" name="logo_image" type="file" accept="image/*">
        <?php if (($s['logo_image'] ?? '') !== ''): ?>
          <img class="thumb" style="margin-top:8px" src="/img/uploads/<?= e($s['logo_image']) ?>" alt="">
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Главная страница</h2>
    <div class="grid2">
      <div>
        <label class="f" for="h-title">Заголовок (hero)</label>
        <input class="input" id="h-title" name="hero_title" value="<?= sv('hero_title', $s) ?>">
        <label class="f" for="h-sub">Подзаголовок</label>
        <textarea class="input" id="h-sub" name="hero_subtitle" rows="2"><?= sv('hero_subtitle', $s) ?></textarea>
        <div style="display:flex;gap:12px">
          <div style="flex:1">
            <label class="f" for="h-btn">Текст кнопки</label>
            <input class="input" id="h-btn" name="hero_button_text" value="<?= sv('hero_button_text', $s) ?>">
          </div>
          <div style="flex:1">
            <label class="f" for="h-link">Ссылка кнопки</label>
            <input class="input" id="h-link" name="hero_button_link" value="<?= sv('hero_button_link', $s) ?>">
          </div>
        </div>
      </div>
      <div>
        <label class="f" for="h-img">Фото для главной (заменить)</label>
        <input class="input" id="h-img" name="hero_image" type="file" accept="image/*">
        <?php if (($s['hero_image'] ?? '') !== ''): ?>
          <img class="thumb" style="margin-top:8px;width:120px;height:80px" src="/img/uploads/<?= e($s['hero_image']) ?>" alt="">
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px"><?= sv('steps_title', $s) !== '' ? 'Этапы работы' : 'Этапы работы' ?></h2>
    <label class="f" for="st-t">Заголовок блока</label>
    <input class="input" id="st-t" name="steps_title" value="<?= sv('steps_title', $s) ?>">
    <div class="grid2">
      <div>
        <label class="f" for="st-1">Шаг 1</label>
        <input class="input" id="st-1" name="step_1" value="<?= sv('step_1', $s) ?>">
        <label class="f" for="st-2">Шаг 2</label>
        <input class="input" id="st-2" name="step_2" value="<?= sv('step_2', $s) ?>">
      </div>
      <div>
        <label class="f" for="st-3">Шаг 3</label>
        <input class="input" id="st-3" name="step_3" value="<?= sv('step_3', $s) ?>">
      </div>
    </div>
  </div>

  <div class="card">
    <h2 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:8px">Гарантии</h2>
    <label class="f" for="g-t">Заголовок блока</label>
    <input class="input" id="g-t" name="guarantees_title" value="<?= sv('guarantees_title', $s) ?>">
    <div class="grid2">
      <div>
        <label class="f" for="g-1">Гарантия 1</label>
        <input class="input" id="g-1" name="guarantee_1" value="<?= sv('guarantee_1', $s) ?>">
        <label class="f" for="g-2">Гарантия 2</label>
        <input class="input" id="g-2" name="guarantee_2" value="<?= sv('guarantee_2', $s) ?>">
      </div>
      <div>
        <label class="f" for="g-3">Гарантия 3</label>
        <input class="input" id="g-3" name="guarantee_3" value="<?= sv('guarantee_3', $s) ?>">
      </div>
    </div>
  </div>

  <button class="btn btn--accent" type="submit" style="padding:14px 32px;font-size:.95rem">Сохранить настройки</button>
</form>
<?php adminFooter(); ?>
