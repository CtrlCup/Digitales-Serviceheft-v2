<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_auth();

$user = current_user();
$profile_message = '';
$password_message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? '')) {
        $errors[] = t('csrf_invalid');
    } else {
        try {
            if (isset($_POST['action']) && $_POST['action'] === 'profile') {
                $username = trim($_POST['username'] ?? '');
                $email = trim($_POST['email'] ?? '');
                update_profile((int)$user['id'], $username, $email);
                $profile_message = t('profile_saved');
                $user = current_user(); // reload
            } elseif (isset($_POST['action']) && $_POST['action'] === 'password') {
                $current = $_POST['current_password'] ?? '';
                $new = $_POST['new_password'] ?? '';
                $confirm = $_POST['new_password_confirm'] ?? '';
                update_password((int)$user['id'], $current, $new, $confirm);
                $password_message = t('password_saved');
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?><!doctype html>
<html lang="<?= htmlspecialchars(APP_LOCALE) ?>" class="">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('account_title')) ?> - <?= e(APP_NAME) ?></title>
  <?php render_common_head_links(); ?>
</head>
<body class="page">
  <main class="center">
    <div class="container reveal-enter">
      <?php render_brand_header([
        ['label' => '← ' . t('dashboard_title'), 'href' => 'dashboard.php'],
        ['label' => t('logout'), 'href' => 'logout.php'],
      ]); ?>

      <?php if ($errors): ?>
        <div class="alert" style="margin-bottom:1rem;">
          <?php foreach ($errors as $err): ?>
            <div><?= e($err) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" class="card" style="margin-bottom:1rem;">
        <h2 style="margin:0;"><?= e(t('profile_section')) ?></h2>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="profile">
        <label>
          <span><?= e(t('username')) ?></span>
          <input type="text" name="username" value="<?= e($user['username'] ?? '') ?>" required autocomplete="username">
        </label>
        <label>
          <span><?= e(t('email')) ?></span>
          <input type="email" name="email" value="<?= e($user['email'] ?? '') ?>" required autocomplete="email">
        </label>
        <button type="submit" class="btn-primary"><?= e(t('save_profile')) ?></button>
        <?php if ($profile_message): ?>
          <div class="alert" style="margin-top:.5rem;">
            <?= e($profile_message) ?>
          </div>
        <?php endif; ?>
      </form>

      <form method="post" class="card">
        <h2 style="margin:0;"><?= e(t('password_section')) ?></h2>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="password">
        <label>
          <span><?= e(t('current_password')) ?></span>
          <input type="password" name="current_password" required autocomplete="current-password">
        </label>
        <label>
          <span><?= e(t('new_password')) ?></span>
          <input type="password" name="new_password" required autocomplete="new-password">
        </label>
        <label>
          <span><?= e(t('new_password_confirm')) ?></span>
          <input type="password" name="new_password_confirm" required autocomplete="new-password">
        </label>
        <button type="submit" class="btn-primary"><?= e(t('save_password')) ?></button>
        <?php if ($password_message): ?>
          <div class="alert" style="margin-top:.5rem;">
            <?= e($password_message) ?>
          </div>
        <?php endif; ?>
      </form>
    </div>
  </main>
</body>
</html>
