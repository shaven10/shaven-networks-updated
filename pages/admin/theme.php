<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
shaven_web_boot(true);

if (!shaven_theme_can_manage()) {
    shaven_redirect('/index.php?denied=1');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!shaven_csrf_ok()) {
        shaven_flash_set('The theme form expired. Submit it again.', 'err');
        shaven_redirect('/pages/admin/theme.php');
    }
    $preset = (string)($_POST['preset'] ?? '');
    if (($_POST['action'] ?? '') === 'reset') {
        $preset = 'classic';
    }
    $result = shaven_theme_save($conn, $preset, (array)($_POST['colors'] ?? []), (int)($_SESSION['usr_id'] ?? 0));
    shaven_flash_set($result['error'], $result['ok'] ? 'ok' : 'err');
    shaven_redirect('/pages/admin/theme.php');
}

$theme = shaven_theme_current($conn);
$presets = shaven_theme_presets();
$keys = shaven_theme_color_keys();
$active = isset($presets[$theme['preset']]) ? $theme['preset'] : 'custom';

shaven_page_open('Theme Manager', 'Theme Manager');
?>
<style>
.shaven-theme-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(13rem, 1fr)); gap: 1rem; }
.shaven-theme-option { position: relative; display: block; margin: 0; cursor: pointer; }
.shaven-theme-option input { position: absolute; opacity: 0; }
.shaven-theme-card { border: 2px solid var(--shaven-line); border-radius: 12px; overflow: hidden; background: #fff; transition: border-color .15s, box-shadow .15s; }
.shaven-theme-option input:checked + .shaven-theme-card { border-color: var(--shaven-teal); box-shadow: 0 0 0 .2rem var(--shaven-focus); }
.shaven-theme-option input:focus-visible + .shaven-theme-card { outline: 2px solid var(--shaven-teal); outline-offset: 2px; }
.shaven-theme-mini { display: flex; height: 5.5rem; }
.shaven-theme-mini-side { width: 28%; padding: .5rem .4rem; }
.shaven-theme-mini-side span { display: block; height: .4rem; border-radius: 3px; margin-bottom: .35rem; background: rgba(255, 255, 255, .25); }
.shaven-theme-mini-side span.is-active { height: .55rem; }
.shaven-theme-mini-main { flex: 1; padding: .5rem; }
.shaven-theme-mini-bar { height: .9rem; border-radius: 4px; margin-bottom: .45rem; }
.shaven-theme-mini-line { height: .35rem; border-radius: 3px; background: rgba(0, 0, 0, .08); margin-bottom: .3rem; }
.shaven-theme-mini-btn { display: inline-block; width: 2.6rem; height: .8rem; border-radius: 4px; margin-top: .2rem; }
.shaven-theme-name { display: flex; justify-content: space-between; align-items: center; padding: .55rem .75rem; font-weight: 700; color: var(--shaven-navy); }
.shaven-theme-name .badge { font-size: .7rem; }
.shaven-theme-swatches { display: flex; gap: .25rem; }
.shaven-theme-swatches i { display: inline-block; width: .9rem; height: .9rem; border-radius: 50%; border: 1px solid rgba(0, 0, 0, .12); }
.shaven-color-field { display: flex; align-items: center; gap: .6rem; }
.shaven-color-field input[type=color] { width: 3rem; height: 2.6rem; padding: .15rem; border: 1px solid var(--shaven-line); border-radius: 8px; background: #fff; cursor: pointer; }
.shaven-color-field code { min-width: 4.5rem; }
</style>

<p class="text-muted">Choose the color scheme for the whole web application. The choice applies to every account, including subscribers, technicians, and the login page. Clicking a scheme previews it on this page right away. Nothing changes for other accounts until you save. Paid, warning, and overdue colors stay green, amber, and red in every theme so their meaning never changes.</p>

<form method="post" id="theme-form">
  <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
  <div class="card card-outline card-primary">
    <div class="card-header"><h3 class="card-title">Color schemes</h3></div>
    <div class="card-body">
      <div class="shaven-theme-grid">
        <?php foreach ($presets + ['custom' => ['label' => 'Custom colors', 'colors' => $theme['colors']]] as $key => $preset): ?>
          <?php $c = $key === 'custom' && $active !== 'custom' ? $theme['colors'] : $preset['colors']; ?>
          <label class="shaven-theme-option">
            <input type="radio" name="preset" value="<?php echo h($key); ?>" <?php echo $active === $key ? 'checked' : ''; ?>
                   <?php foreach ($c as $ck => $cv): ?>data-<?php echo h($ck); ?>="<?php echo h($cv); ?>" <?php endforeach; ?>>
            <div class="shaven-theme-card" data-preview="<?php echo h($key); ?>">
              <div class="shaven-theme-mini" style="background:<?php echo h($c['sand']); ?>">
                <div class="shaven-theme-mini-side" style="background:<?php echo h($c['navy']); ?>">
                  <span></span><span class="is-active" style="background:<?php echo h($c['teal']); ?>"></span><span></span><span style="background:<?php echo h($c['gold']); ?>"></span>
                </div>
                <div class="shaven-theme-mini-main">
                  <div class="shaven-theme-mini-bar" style="background:<?php echo h($c['navy']); ?>"></div>
                  <div class="shaven-theme-mini-line"></div>
                  <div class="shaven-theme-mini-line" style="width:70%"></div>
                  <span class="shaven-theme-mini-btn" style="background:<?php echo h($c['teal']); ?>"></span>
                  <span class="shaven-theme-mini-btn" style="background:<?php echo h($c['gold']); ?>;width:1.6rem"></span>
                </div>
              </div>
              <div class="shaven-theme-name">
                <span><?php echo h($preset['label']); ?></span>
                <?php if ($active === $key): ?>
                  <span class="badge badge-success">In use</span>
                <?php else: ?>
                  <span class="shaven-theme-swatches"><?php foreach (['navy', 'teal', 'gold'] as $sw): ?><i style="background:<?php echo h($c[$sw]); ?>"></i><?php endforeach; ?></span>
                <?php endif; ?>
              </div>
            </div>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="card card-outline card-info">
    <div class="card-header"><h3 class="card-title">Custom colors</h3></div>
    <div class="card-body">
      <p class="text-muted mb-3">Changing any color switches to Custom colors. Hover, border, and light background shades are worked out from these four colors automatically.</p>
      <div class="row">
        <?php foreach ($keys as $key => $label): ?>
          <div class="col-md-6 col-lg-3 form-group">
            <label for="theme-<?php echo h($key); ?>"><?php echo h($label); ?></label>
            <div class="shaven-color-field">
              <input type="color" id="theme-<?php echo h($key); ?>" name="colors[<?php echo h($key); ?>]" data-key="<?php echo h($key); ?>" value="<?php echo h($theme['colors'][$key]); ?>">
              <code id="theme-<?php echo h($key); ?>-hex"><?php echo h($theme['colors'][$key]); ?></code>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card-footer">
      <button class="btn btn-primary" type="submit" name="action" value="save"><i class="fas fa-save mr-1"></i> Save theme for everyone</button>
      <button class="btn btn-default" type="button" id="theme-revert">Undo preview</button>
      <button class="btn btn-default float-right" type="submit" name="action" value="reset" onclick="return confirm('Switch every account back to SHAVEN Classic?');">Reset to SHAVEN Classic</button>
    </div>
  </div>
</form>

<div class="card">
  <div class="card-header"><h3 class="card-title">Preview</h3></div>
  <div class="card-body">
    <p class="mb-3">This page, the sidebar, and the header already show the scheme you picked. These are common pieces for a quick check.</p>
    <div class="mb-3">
      <button class="btn btn-primary" type="button">Primary</button>
      <button class="btn btn-info" type="button">Info</button>
      <button class="btn btn-warning" type="button">Warning</button>
      <button class="btn btn-success" type="button">Paid</button>
      <button class="btn btn-danger" type="button">Delete</button>
      <button class="btn btn-default" type="button">Default</button>
    </div>
    <div class="mb-3">
      <span class="badge badge-primary">Primary</span>
      <span class="badge badge-info">Info</span>
      <span class="badge badge-success">Paid</span>
      <span class="badge badge-warning">Pending</span>
      <span class="badge badge-danger">Unpaid</span>
      <span class="shaven-account-no ml-2">2024-0001</span>
      <span class="shaven-due-date ml-2">December 5, 2026</span>
    </div>
    <div class="alert alert-info mb-3">Information messages use the accent color.</div>
    <table class="table table-sm mb-0">
      <tbody>
        <tr class="shaven-bill-group"><td colspan="3"><span class="shaven-group-name">Grouped subscriber row</span></td></tr>
        <tr><td>November 2026</td><td>October 5 – November 4, 2026</td><td class="text-right">&#8369;1,000.00</td></tr>
      </tbody>
    </table>
  </div>
</div>

<script>
(function () {
  var form = document.getElementById('theme-form');
  var root = document.documentElement;
  var keys = <?php echo json_encode(array_keys($keys)); ?>;
  var saved = <?php echo json_encode($theme['colors']); ?>;
  var savedPreset = <?php echo json_encode($active); ?>;
  var radios = form.querySelectorAll('input[name="preset"]');
  var customRadio = form.querySelector('input[name="preset"][value="custom"]');

  function apply(colors) {
    keys.forEach(function (key) {
      root.style.setProperty('--shaven-' + key, colors[key]);
      var input = document.getElementById('theme-' + key);
      var hex = document.getElementById('theme-' + key + '-hex');
      if (input) { input.value = colors[key]; }
      if (hex) { hex.textContent = colors[key]; }
    });
  }

  function fromRadio(radio) {
    var colors = {};
    keys.forEach(function (key) { colors[key] = radio.getAttribute('data-' + key); });
    return colors;
  }

  function paintCustomCard(colors) {
    var card = form.querySelector('[data-preview="custom"]');
    if (!card) { return; }
    card.querySelector('.shaven-theme-mini').style.background = colors.sand;
    card.querySelector('.shaven-theme-mini-side').style.background = colors.navy;
    card.querySelector('.shaven-theme-mini-side .is-active').style.background = colors.teal;
    card.querySelector('.shaven-theme-mini-side span:last-child').style.background = colors.gold;
    card.querySelector('.shaven-theme-mini-bar').style.background = colors.navy;
    var btns = card.querySelectorAll('.shaven-theme-mini-btn');
    btns[0].style.background = colors.teal;
    btns[1].style.background = colors.gold;
  }

  Array.prototype.forEach.call(radios, function (radio) {
    radio.addEventListener('change', function () {
      if (radio.value === 'custom') {
        var current = {};
        keys.forEach(function (key) { current[key] = document.getElementById('theme-' + key).value; });
        apply(current);
        return;
      }
      apply(fromRadio(radio));
    });
  });

  keys.forEach(function (key) {
    var input = document.getElementById('theme-' + key);
    input.addEventListener('input', function () {
      var colors = {};
      keys.forEach(function (k) { colors[k] = document.getElementById('theme-' + k).value; });
      customRadio.checked = true;
      apply(colors);
      paintCustomCard(colors);
    });
  });

  document.getElementById('theme-revert').addEventListener('click', function () {
    keys.forEach(function (key) { root.style.removeProperty('--shaven-' + key); });
    var radio = form.querySelector('input[name="preset"][value="' + savedPreset + '"]');
    if (radio) { radio.checked = true; }
    apply(saved);
    keys.forEach(function (key) { root.style.removeProperty('--shaven-' + key); });
  });
})();
</script>
<?php shaven_page_close(); ?>
