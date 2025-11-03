<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/bootstrap.php';
require_auth();
require_admin();

$currentUser = current_user();
$message = '';
$errors = [];

// Handle POST requests (role changes and other actions)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? '')) {
        $errors[] = t('csrf_invalid');
    } else {
        try {
            if (isset($_POST['action']) && $_POST['action'] === 'change_role') {
                $targetUserId = (int)($_POST['user_id'] ?? 0);
                $newRole = trim($_POST['new_role'] ?? '');
                
                change_user_role((int)$currentUser['id'], $targetUserId, $newRole);
                $message = t('role_changed_success');
            } elseif (isset($_POST['action']) && $_POST['action'] === 'lock_account') {
                $targetUserId = (int)($_POST['user_id'] ?? 0);
                $lock = (bool)($_POST['lock'] ?? true);
                $lockUntil = null;
                if ($lock) {
                  $lockDate = trim($_POST['lock_date'] ?? '');
                  $lockTime = trim($_POST['lock_time'] ?? '');
                  if ($lockDate !== '' && $lockTime !== '') {
                    $lockUntil = $lockDate . ' ' . $lockTime . ':00';
                  } elseif ($lockDate !== '') {
                    $lockUntil = $lockDate . ' 00:00:00';
                  }
                }
                admin_lock_user_account((int)$currentUser['id'], $targetUserId, $lock, $lockUntil);
                $message = $lock ? t('account_locked_success') : t('account_unlocked_success');
            } elseif (isset($_POST['action']) && $_POST['action'] === 'change_email') {
                $targetUserId = (int)($_POST['user_id'] ?? 0);
                $newEmail = trim($_POST['new_email'] ?? '');
                
                admin_update_user_email((int)$currentUser['id'], $targetUserId, $newEmail);
                $message = t('email_changed_success');
            } elseif (isset($_POST['action']) && $_POST['action'] === 'change_password') {
                $targetUserId = (int)($_POST['user_id'] ?? 0);
                $newPassword = $_POST['new_password'] ?? '';
                
                admin_update_user_password((int)$currentUser['id'], $targetUserId, $newPassword);
                $message = t('password_changed_success');
            } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_account') {
                $targetUserId = (int)($_POST['user_id'] ?? 0);
                
                admin_delete_user_account((int)$currentUser['id'], $targetUserId);
                $message = t('account_deleted_success');
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

// Get all users
$users = get_all_users();
$currentUserLevel = get_role_level($currentUser['role']);
?><!doctype html>
<html lang="<?= htmlspecialchars(APP_LOCALE) ?>" class="">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('user_management_title')) ?> - <?= e(APP_NAME) ?></title>
  <?php render_common_head_links(); ?>
  <style>
    .user-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 1rem;
    }
    .user-table th,
    .user-table td {
      padding: 0.75rem 1rem;
      text-align: left;
      border-bottom: 1px solid rgba(var(--color-border), 0.3);
    }
    .user-table th {
      font-weight: 600;
      background: var(--bg-secondary);
    }
    .user-table tr:hover {
      background: rgba(var(--color-primary), 0.05);
    }
    .role-badge {
      display: inline-block;
      padding: 0.25rem 0.75rem;
      border-radius: var(--radius-sm);
      font-size: 0.875rem;
      font-weight: 500;
    }
    .role-viewer { background: #6c757d; color: white; }
    .role-user { background: #0d6efd; color: white; }
    .role-admin { background: #fd7e14; color: white; }
    .role-owner { background: #dc3545; color: white; }
    .role-select {
      padding: 0.5rem;
      border: 1px solid rgba(var(--color-border), 0.5);
      border-radius: var(--radius-sm);
      background: var(--bg);
      color: var(--text);
      font-size: 0.875rem;
    }
    .btn-icon {
      background: none;
      border: none;
      cursor: pointer;
      padding: 0;
      width: 36px;
      height: 36px;
      border-radius: 8px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: background-color 0.2s;
    }
    .btn-icon:hover {
      background: rgba(var(--color-primary), 0.1);
    }
    .actions-col { width: 320px; }
    .action-buttons {
      display: grid;
      grid-template-columns: 1fr;
      grid-auto-rows: auto;
      row-gap: 0.5rem;
      justify-items: center;
    }
    .action-buttons .role-select {
      width: 220px;
      max-width: 100%;
      justify-self: center;
    }
    .action-icons {
      display: grid;
      grid-auto-flow: column;
      grid-template-columns: repeat(4, 36px);
      justify-content: center;
      align-items: center;
      gap: 0.5rem;
    }
    @media (max-width: 768px) {
      .btn-icon { width: 32px; height: 32px; }
      .action-icons { grid-template-columns: repeat(4, 32px); gap: 0.4rem; }
      .actions-col { width: 260px; }
      .action-buttons .role-select { width: 200px; }
    }
    @media (max-width: 768px) {
      .user-table {
        font-size: 0.875rem;
      }
      .user-table th,
      .user-table td {
        padding: 0.5rem;
      }
      .hide-mobile {
        display: none;
      }
    }
    /* Modal & Blur Overlay */
    .modal {
      position: fixed;
      inset: 0;
      display: none;
      align-items: center;
      justify-content: center;
      /* Overlay passt sich dem Theme-Hintergrund an (hell im Light Mode, dunkel im Dark Mode) */
      background: rgba(var(--color-bg), 0.65);
      -webkit-backdrop-filter: blur(8px);
      backdrop-filter: blur(8px);
      z-index: 1000;
    }
    .modal.open { display: flex; }
    .modal-content {
      background: rgb(var(--color-bg));
      border-radius: var(--radius-lg);
      width: 90%;
      max-width: 500px;
      padding: 2rem;
      box-shadow: var(--shadow-lg);
      color: var(--text);
    }
    .modal-content h3 {
      font-size: 1.5rem;
      margin-bottom: 1rem;
    }
    .modal-actions {
      display: flex;
      gap: 1rem;
      justify-content: flex-end;
      align-items: center;
    }
    .modal-actions .btn {
      padding: 0.75rem 1.25rem;
      border-radius: var(--radius-lg);
      border: none;
      cursor: pointer;
    }
    .btn-neutral { background: var(--text-muted); color: #fff; }
    .btn-cancel {
      background: #111827; /* dark slate for light mode */
      border-color: #111827;
      color: #fff;
      padding: 0.75rem 1.25rem;
      border-radius: var(--radius-lg);
      border: none;
      cursor: pointer;
    }
    @media (prefers-color-scheme: dark) {
      .btn-cancel { background: #6b7280; border-color: #6b7280; color: #fff; }
    }
    .modal-content input,
    .modal-content label,
    .modal-content p,
    .modal-content span { color: var(--text); }
    @media (prefers-color-scheme: light) {
      .modal { background: rgba(255, 255, 255, 0.65); }
      .btn-neutral { background: #e5e7eb; color: #111827; }
    }
  </style>
</head>
<body class="page">
  <?php render_brand_header([
    'links' => [
      ['label' => t('dashboard_title'), 'href' => '/', 'icon' => 'home'],
      ['label' => t('admin_settings_title'), 'href' => '/admin/', 'icon' => 'settings'],
      ['label' => t('account_link'), 'href' => '/account/', 'icon' => 'user', 'text' => $currentUser['username'] ?? ''],
      ['label' => t('logout'), 'href' => '/logout', 'icon' => 'logout']
    ]
  ]); ?>
  <main class="page-content">
    <div class="container-wide reveal-enter">

      <?php if ($errors): ?>
        <div class="error-notification" id="error-toast">
          <div class="error-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"/>
              <line x1="12" y1="8" x2="12" y2="12"/>
              <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
          </div>
          <div class="error-content">
            <?php foreach ($errors as $err): ?>
              <div class="error-message"><?= e($err) ?></div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="error-close" onclick="this.parentElement.style.display='none'" aria-label="Schließen">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"/>
              <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>
        <script>
          setTimeout(() => {
            const toast = document.getElementById('error-toast');
            if (toast) toast.style.display = 'none';
          }, 5000);
        </script>
      <?php endif; ?>

      <?php if ($message): ?>
        <div class="alert success-message" style="margin-bottom:2rem;">
          <?= e($message) ?>
        </div>
      <?php endif; ?>

      <div class="card">
        <h1 class="card-title" style="font-size:2rem;margin-bottom:1rem;"><?= e(t('user_management_title')) ?></h1>
        <p style="color:var(--text-muted);margin-bottom:1.5rem;"><?= e(t('user_management_intro')) ?></p>

        <table class="user-table">
          <thead>
            <tr>
              <th><?= e(t('username')) ?></th>
              <th><?= e(t('name')) ?></th>
              <th class="hide-mobile"><?= e(t('email')) ?></th>
              <th><?= e(t('role')) ?></th>
              <th class="hide-mobile"><?= e(t('status')) ?></th>
              <th class="hide-mobile"><?= e(t('registered_at')) ?></th>
              <th class="actions-col"><?= e(t('actions')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): 
              $userLevel = get_role_level($u['role']);
              $isLocked = !empty($u['locked_until']) && strtotime($u['locked_until']) > time();
              $canChange = false;
              
              // Bestimme ob die Rolle geändert werden kann
              if ($currentUserLevel === 4) {
                // Owner kann alle ändern (außer andere zu Owner machen, wird in change_user_role geprüft)
                $canChange = true;
              } elseif ($currentUserLevel === 3) {
                // Admin kann User zu Admin befördern, aber nicht degradieren
                $canChange = $userLevel <= 2; // Nur viewer und user
              } elseif ($currentUserLevel === 2) {
                // User kann nur Viewer zu User machen
                $canChange = $userLevel === 1;
              }
              
              $isCurrentUser = (int)$u['id'] === (int)$currentUser['id'];
            ?>
              <tr>
                <td style="font-weight:500;"><?= e($u['username']) ?></td>
                <td><?= e($u['name']) ?></td>
                <td class="hide-mobile"><?= e($u['email']) ?></td>
                <td>
                  <span class="role-badge role-<?= e(strtolower($u['role'])) ?>">
                    <?= e(t('role_' . strtolower($u['role']))) ?>
                  </span>
                </td>
                <td class="hide-mobile">
                  <?php if ($isLocked): ?>
                    <span style="color:#dc3545;font-size:0.875rem;">🔒 <?= e(t('locked')) ?></span>
                  <?php else: ?>
                    <span style="color:#28a745;font-size:0.875rem;">✓ <?= e(t('active')) ?></span>
                  <?php endif; ?>
                </td>
                <td class="hide-mobile" style="color:var(--text-muted);font-size:0.875rem;">
                  <?= e(date('d.m.Y', strtotime($u['created_at']))) ?>
                </td>
                <td>
                  <?php if ($canChange && !$isCurrentUser): ?>
                    <div class="action-buttons">
                      <!-- Role Change -->
                      <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="change_role">
                        <input type="hidden" name="user_id" value="<?= e((string)$u['id']) ?>">
                        <select name="new_role" class="role-select" onchange="this.form.submit()">
                          <option value=""><?= e(t('change_role')) ?></option>
                          <?php
                          // Zeige mögliche Rollen basierend auf Berechtigungen
                          $possibleRoles = [];
                          if ($currentUserLevel === 4) {
                            // Owner kann alle Rollen vergeben
                            $possibleRoles = ['viewer', 'user', 'admin', 'owner'];
                          } elseif ($currentUserLevel === 3) {
                            // Admin kann bis zu Admin befördern
                            if ($userLevel <= 2) {
                              $possibleRoles = ['viewer', 'user', 'admin'];
                            }
                          } elseif ($currentUserLevel === 2) {
                            // User kann nur Viewer zu User machen
                            if ($userLevel === 1) {
                              $possibleRoles = ['user'];
                            }
                          }
                          
                          foreach ($possibleRoles as $role):
                            if (strtolower($role) !== strtolower($u['role'])):
                          ?>
                            <option value="<?= e($role) ?>"><?= e(t('role_' . $role)) ?></option>
                          <?php 
                            endif;
                          endforeach; 
                          ?>
                        </select>
                      </form>
                      <div class="action-icons">
                        <!-- Lock/Unlock Button -->
                        <?php if ($isLocked): ?>
                          <form method="post" style="display:inline;">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="lock_account">
                            <input type="hidden" name="user_id" value="<?= e((string)$u['id']) ?>">
                            <input type="hidden" name="lock" value="0">
                            <button type="submit" class="btn-icon" title="<?= e(t('unlock_account')) ?>" style="color:#28a745;">
                              <?= icon_svg('unlock') ?>
                            </button>
                          </form>
                        <?php else: ?>
                          <button type="button" class="btn-icon" title="<?= e(t('lock_account')) ?>" onclick="openLockModal(<?= e((string)$u['id']) ?>, '<?= e($u['username']) ?>')" style="color:#dc3545;">
                            <?= icon_svg('lock') ?>
                          </button>
                        <?php endif; ?>
                        
                        <!-- Change Email Button -->
                        <button type="button" class="btn-icon" title="<?= e(t('change_email')) ?>" onclick="openEmailModal(<?= e((string)$u['id']) ?>, '<?= e($u['email']) ?>', '<?= e($u['username']) ?>')" style="color:#007bff;">
                          <?= icon_svg('at') ?>
                        </button>
                        
                        <!-- Change Password Button -->
                        <button type="button" class="btn-icon" title="<?= e(t('change_password')) ?>" onclick="openPasswordModal(<?= e((string)$u['id']) ?>, '<?= e($u['username']) ?>')" style="color:#6f42c1;">
                          <?= icon_svg('key') ?>
                        </button>
                        
                        <!-- Delete Button -->
                        <button type="button" class="btn-icon" title="<?= e(t('delete_account')) ?>" onclick="openDeleteModal(<?= e((string)$u['id']) ?>, '<?= e($u['username']) ?>')" style="color:#dc3545;">
                          <?= icon_svg('trash') ?>
                        </button>
                      </div>
                    </div>
                  <?php elseif ($isCurrentUser): ?>
                    <span style="color:var(--text-muted);font-size:0.875rem;"><?= e(t('you')) ?></span>
                  <?php else: ?>
                    <span style="color:var(--text-muted);font-size:0.875rem;">-</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <!-- Modals for actions -->
        <!-- Lock Account Modal -->
        <div id="lockModal" class="modal" aria-hidden="true">
          <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="lockModalTitle">
            <h3 id="lockModalTitle" style="margin-bottom:0.75rem;"><?= e(t('lock_account')) ?></h3>
            <p style="color:var(--text-muted);margin-bottom:1rem;">Benutzer <strong id="lockUsername"></strong> sperren bis:</p>
            <form id="lockForm" method="post">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="lock_account">
              <input type="hidden" name="user_id" id="lockUserId">
              <input type="hidden" name="lock" value="1">
              <div style="display:flex;gap:0.75rem;align-items:end;flex-wrap:wrap;margin-bottom:1rem;">
                <label style="flex:1;min-width:180px;">
                  <span style="display:block;margin-bottom:0.25rem;">Datum</span>
                  <input type="date" id="lockDate" name="lock_date" required style="width:100%;padding:0.5rem;border:1px solid rgba(var(--color-border),0.5);border-radius:var(--radius-sm);">
                </label>
                <label style="width:150px;">
                  <span style="display:block;margin-bottom:0.25rem;">Uhrzeit</span>
                  <input type="time" id="lockTime" name="lock_time" required step="60" style="width:100%;padding:0.5rem;border:1px solid rgba(var(--color-border),0.5);border-radius:var(--radius-sm);">
                </label>
              </div>
              <div style="display:flex;gap:1rem;">
                <button type="submit" class="btn-primary" style="flex:1;background:#dc3545;border-color:#dc3545;">
                  <?= e(t('lock_account')) ?>
                </button>
                <button type="button" onclick="closeLockModal()" class="btn-cancel" style="flex:1;">
                  <?= e(t('cancel')) ?>
                </button>
              </div>
            </form>
          </div>
        </div>
        <!-- Email Change Modal -->
        <div id="emailModal" class="modal">
          <div class="modal-content">
            <h3 style="margin-bottom:1rem;"><?= e(t('change_email')) ?></h3>
            <form id="emailForm" method="post">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="change_email">
              <input type="hidden" name="user_id" id="emailUserId">
              <label style="display:block;margin-bottom:1rem;">
                <span style="display:block;margin-bottom:0.5rem;"><?= e(t('new_email')) ?></span>
                <input type="email" id="newEmail" name="new_email" required style="width:100%;padding:0.5rem;border:1px solid rgba(var(--color-border),0.5);border-radius:var(--radius-sm);">
              </label>
              <div style="display:flex;gap:1rem;">
                <button type="submit" class="btn-primary" style="flex:1;background:#007bff;border-color:#007bff;">
                  Speichern
                </button>
                <button type="button" onclick="closeEmailModal()" class="btn-cancel" style="flex:1;">
                  <?= e(t('cancel')) ?>
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Password Change Modal -->
        <div id="passwordModal" class="modal">
          <div class="modal-content">
            <h3 style="margin-bottom:1rem;"><?= e(t('change_password')) ?></h3>
            <form id="passwordForm" method="post">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="change_password">
              <input type="hidden" name="user_id" id="passwordUserId">
              <label style="display:block;margin-bottom:1rem;">
                <span style="display:block;margin-bottom:0.5rem;"><?= e(t('new_password')) ?></span>
                <input type="password" id="newPassword" name="new_password" required style="width:100%;padding:0.5rem;border:1px solid rgba(var(--color-border),0.5);border-radius:var(--radius-sm);">
              </label>
              <div style="display:flex;gap:1rem;">
                <button type="submit" class="btn-primary" style="flex:1;background:#6f42c1;border-color:#6f42c1;">
                  Speichern
                </button>
                <button type="button" onclick="closePasswordModal()" class="btn-cancel" style="flex:1;">
                  <?= e(t('cancel')) ?>
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="modal">
          <div class="modal-content">
            <h3 style="margin-bottom:1rem;color:#dc3545;"><?= e(t('delete_account')) ?></h3>
            <p style="margin-bottom:1.5rem;"><?= e(t('delete_account_confirm')) ?> <strong id="deleteUsername"></strong>?</p>
            <p style="color:#dc3545;font-size:0.875rem;margin-bottom:1.5rem;"><?= e(t('action_irreversible')) ?></p>
            <form id="deleteForm" method="post">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete_account">
              <input type="hidden" name="user_id" id="deleteUserId">
              <div style="display:flex;gap:1rem;">
                <button type="submit" class="btn-primary" style="flex:1;background:#dc3545;border-color:#dc3545;">
                  <?= e(t('delete_account')) ?>
                </button>
                <button type="button" onclick="closeDeleteModal()" class="btn-cancel" style="flex:1;">
                  <?= e(t('cancel')) ?>
                </button>
              </div>
            </form>
          </div>
        </div>

        <script>
          function openEmailModal(userId, currentEmail, username) {
            document.getElementById('emailUserId').value = userId;
            document.getElementById('newEmail').value = currentEmail;
            document.getElementById('emailModal').classList.add('open');
            document.getElementById('newEmail').focus();
          }
          
          function closeEmailModal() {
            document.getElementById('emailModal').classList.remove('open');
          }
          
          function openPasswordModal(userId, username) {
            document.getElementById('passwordUserId').value = userId;
            document.getElementById('passwordModal').classList.add('open');
            document.getElementById('newPassword').focus();
          }
          
          function closePasswordModal() {
            document.getElementById('passwordModal').classList.remove('open');
          }
          
          function openDeleteModal(userId, username) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUsername').textContent = username;
            document.getElementById('deleteModal').classList.add('open');
          }
          
          function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('open');
          }

          function openLockModal(userId, username) {
            const now = new Date();
            const oneWeek = new Date(now.getTime() + 7*24*60*60*1000);
            const d = oneWeek.toISOString().slice(0,10);
            const hh = String(oneWeek.getHours()).padStart(2,'0');
            const mm = String(oneWeek.getMinutes()).padStart(2,'0');
            document.getElementById('lockUserId').value = userId;
            document.getElementById('lockUsername').textContent = username;
            document.getElementById('lockDate').value = d;
            document.getElementById('lockTime').value = hh + ':' + mm;
            document.getElementById('lockModal').classList.add('open');
          }
          function closeLockModal() {
            document.getElementById('lockModal').classList.remove('open');
          }
          
          // Close modals when clicking outside
          window.onclick = function(event) {
            const modals = ['emailModal', 'passwordModal', 'deleteModal', 'lockModal'];
            modals.forEach(modalId => {
              const modal = document.getElementById(modalId);
              if (event.target === modal) {
                modal.classList.remove('open');
              }
            });
          }
        </script>
      </div>

    </div>
  </main>
</body>
</html>
