<?php
/**
 * RG PMS — Admin: Create / Edit client (creates linked user account on create)
 */
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');

$pdo     = db();
$editId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit  = $editId > 0;
$errors  = [];

$client = [
    'company_name'   => '',
    'contact_person' => '',
    'email'          => '',
    'phone'          => '',
    'address'        => '',
    'user_id'        => 0,
];

// ---- Detect optional columns ----
$cCols    = $pdo->query("SHOW COLUMNS FROM clients")->fetchAll(PDO::FETCH_COLUMN);
$hasUser  = in_array('user_id', $cCols);
$hasAddr  = in_array('address', $cCols);
$uCols    = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
$hasUPhone = in_array('phone', $uCols);

// ---- Load existing for edit ----
if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
    $stmt->execute([$editId]);
    $row = $stmt->fetch();
    if (!$row) {
        flash_set('error', 'Client not found.');
        redirect('admin/clients.php');
    }
    $client = array_merge($client, $row);
}

// ---- Freshly created client (show the generated password once) ----
$newCredentials = $_SESSION['client_new_credentials'] ?? null;
if ($newCredentials) unset($_SESSION['client_new_credentials']);

// ---- Handle submit ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    foreach (['company_name','contact_person','email','phone','address'] as $k) {
        $client[$k] = trim($_POST[$k] ?? '');
    }
    $manualPassword = trim($_POST['password'] ?? '');

    if ($client['company_name'] === '')   $errors[] = 'Company name is required.';
    if ($client['contact_person'] === '') $errors[] = 'Contact person is required.';
    if ($client['email'] === '')          $errors[] = 'Email is required (used for portal sign-in).';
    if (!filter_var($client['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email is not valid.';

    // Look up the 'client' role
    $clientRoleId = null;
    if (!$errors) {
        $rq = $pdo->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
        $rq->execute(['client']);
        $clientRoleId = $rq->fetchColumn();
        if (!$clientRoleId) $errors[] = 'The "client" role is missing from the database.';
    }

    // Email uniqueness in users table
    if (!$errors) {
        $sql = 'SELECT id FROM users WHERE email = ?';
        $params = [$client['email']];
        if ($isEdit && $client['user_id']) {
            $sql .= ' AND id <> ?';
            $params[] = $client['user_id'];
        }
        $ex = $pdo->prepare($sql);
        $ex->execute($params);
        if ($ex->fetchColumn()) $errors[] = 'Another user already uses that email.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $plainPassword = null;

            if ($isEdit && $client['user_id']) {
                // ---- UPDATE existing user ----
                if ($hasUPhone) {
                    $pdo->prepare('UPDATE users SET full_name = ?, email = ?, phone = ?, role_id = ? WHERE id = ?')
                        ->execute([$client['contact_person'], $client['email'], $client['phone'] ?: null, $clientRoleId, $client['user_id']]);
                } else {
                    $pdo->prepare('UPDATE users SET full_name = ?, email = ?, role_id = ? WHERE id = ?')
                        ->execute([$client['contact_person'], $client['email'], $clientRoleId, $client['user_id']]);
                }

                // Optional password reset
                if ($manualPassword !== '') {
                    if (strlen($manualPassword) < 8) throw new Exception('Password must be at least 8 characters.');
                    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                        ->execute([password_hash($manualPassword, PASSWORD_DEFAULT), $client['user_id']]);
                    $plainPassword = $manualPassword;
                }

                // ---- UPDATE client row ----
                $fields = [
                    'company_name'   => $client['company_name'],
                    'contact_person' => $client['contact_person'],
                    'email'          => $client['email']    ?: null,
                    'phone'          => $client['phone']    ?: null,
                ];
                if ($hasAddr) $fields['address'] = $client['address'] ?: null;

                $set = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($fields)));
                $pdo->prepare("UPDATE clients SET $set WHERE id = ?")
                    ->execute([...array_values($fields), $editId]);

                flash_set('success', 'Client updated.');

            } else {
                // ---- CREATE new user ----
                // Generate a random password if none supplied
                if ($manualPassword === '') {
                    $plainPassword = 'RG-' . bin2hex(random_bytes(4)); // e.g. RG-a1b2c3d4
                } else {
                    if (strlen($manualPassword) < 8) throw new Exception('Password must be at least 8 characters.');
                    $plainPassword = $manualPassword;
                }

                $hash = password_hash($plainPassword, PASSWORD_DEFAULT);

                if ($hasUPhone) {
                    $pdo->prepare('INSERT INTO users (full_name, email, phone, password_hash, role_id) VALUES (?, ?, ?, ?, ?)')
                        ->execute([$client['contact_person'], $client['email'], $client['phone'] ?: null, $hash, $clientRoleId]);
                } else {
                    $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role_id) VALUES (?, ?, ?, ?)')
                        ->execute([$client['contact_person'], $client['email'], $hash, $clientRoleId]);
                }
                $newUserId = (int)$pdo->lastInsertId();

                // ---- CREATE client row ----
                $fields = [
                    'company_name'   => $client['company_name'],
                    'contact_person' => $client['contact_person'],
                    'email'          => $client['email']    ?: null,
                    'phone'          => $client['phone']    ?: null,
                ];
                if ($hasAddr) $fields['address'] = $client['address'] ?: null;
                if ($hasUser) $fields['user_id'] = $newUserId;

                $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($fields)));
                $ph   = implode(', ', array_fill(0, count($fields), '?'));
                $pdo->prepare("INSERT INTO clients ($cols) VALUES ($ph)")
                    ->execute(array_values($fields));

                // Stash credentials so we can show them once after redirect
                $_SESSION['client_new_credentials'] = [
                    'name'     => $client['contact_person'],
                    'email'    => $client['email'],
                    'password' => $plainPassword,
                ];

                flash_set('success', 'Client created and portal account set up.');
            }

            $pdo->commit();
            redirect('admin/client_new.php' . ($isEdit ? '?id=' . $editId : ''));
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Save failed: ' . $e->getMessage();
        }
    }
}

page_header($isEdit ? 'Edit client' : 'New client', 'clients');
page_heading(
    $isEdit ? 'Edit client' : 'New client',
    $isEdit ? 'Update the client and portal account' : 'Add a new client and create their portal login'
);
?>
<div class="row justify-content-center">
  <div class="col-lg-7">

    <?php if ($newCredentials): ?>
      <!-- ===== Password shown ONCE after creation ===== -->
      <div class="card border-0 shadow-sm rounded-4 mb-4" style="border-left:4px solid #22c55e !important;">
        <div class="card-body p-4">
          <div class="d-flex align-items-start gap-3 mb-3">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 text-success"
                  style="width:44px;height:44px;font-size:1.3rem;flex-shrink:0;">
              <i class="bi bi-check2-circle"></i>
            </span>
            <div>
              <h6 class="fw-semibold mb-1">Client account created</h6>
              <p class="text-muted small mb-0">Save these credentials now — the password will not be shown again.</p>
            </div>
          </div>

          <div class="bg-light rounded-3 p-3">
            <div class="row g-2 small">
              <div class="col-sm-3 text-muted">Name</div>
              <div class="col-sm-9 fw-semibold"><?= e($newCredentials['name']) ?></div>
              <div class="col-sm-3 text-muted">Email</div>
              <div class="col-sm-9 fw-semibold"><?= e($newCredentials['email']) ?></div>
              <div class="col-sm-3 text-muted">Password</div>
              <div class="col-sm-9">
                <code class="fs-6 fw-bold" style="letter-spacing:1px;"><?= e($newCredentials['password']) ?></code>
              </div>
            </div>
          </div>

          <div class="d-flex gap-2 mt-3">
            <button class="btn btn-sm btn-outline-secondary" type="button"
                    onclick="copyCreds('<?= e($newCredentials['email']) ?>','<?= e($newCredentials['password']) ?>', this)">
              <i class="bi bi-clipboard me-1"></i>Copy credentials
            </button>
            <a href="<?= BASE_URL ?>/admin/clients.php" class="btn btn-sm btn-outline-secondary">
              Back to clients
            </a>
          </div>
        </div>
      </div>

      <script>
      function copyCreds(email, pwd, btn) {
        var text = 'Email: ' + email + '\nPassword: ' + pwd;
        navigator.clipboard.writeText(text).then(function () {
          var orig = btn.innerHTML;
          btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Copied';
          setTimeout(function () { btn.innerHTML = orig; }, 1500);
        });
      }
      </script>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4 p-lg-5">

        <?php if ($errors): ?>
          <div class="alert alert-danger small">
            <ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
          <?= csrf_field() ?>

          <h6 class="fw-semibold mb-3">Client details</h6>

          <div class="mb-3">
            <label class="form-label required">Company name</label>
            <input type="text" name="company_name" class="form-control" required
                   value="<?= e($client['company_name']) ?>">
          </div>

          <div class="mb-3">
            <label class="form-label required">Contact person</label>
            <input type="text" name="contact_person" class="form-control" required
                   value="<?= e($client['contact_person']) ?>">
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label required">Email (used to sign in)</label>
              <input type="email" name="email" class="form-control" required
                     value="<?= e($client['email']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" class="form-control"
                     value="<?= e($client['phone']) ?>">
            </div>
          </div>

          <div class="mb-3 mt-3">
            <label class="form-label">Address</label>
            <textarea name="address" class="form-control" rows="3"><?= e($client['address']) ?></textarea>
          </div>

          <hr class="my-4">

          <h6 class="fw-semibold mb-3">Portal sign-in</h6>
          <div class="mb-3">
            <label class="form-label <?= $isEdit ? '' : '' ?>">
              <?= $isEdit ? 'Set a new password (leave blank to keep current)' : 'Password (leave blank to auto-generate)' ?>
            </label>
            <input type="text" name="password" class="form-control"
                   minlength="8"
                   placeholder="<?= $isEdit ? 'New password' : 'Leave blank for a strong auto-generated password' ?>">
            <div class="form-text">
              <?php if ($isEdit): ?>
                Only fill this in if you want to change their password.
              <?php else: ?>
                If left blank, a random password like <code>RG-a1b2c3d4</code> will be generated and shown after save.
              <?php endif; ?>
            </div>
          </div>

          <div class="d-flex justify-content-between mt-4">
            <a href="<?= BASE_URL ?>/admin/clients.php" class="btn btn-outline-secondary">
              <i class="bi bi-arrow-left me-1"></i>Cancel
            </a>
            <button class="btn btn-brand">
              <i class="bi bi-check2-circle me-2"></i><?= $isEdit ? 'Save changes' : 'Create client' ?>
            </button>
          </div>
        </form>

      </div>
    </div>
  </div>
</div>
<?php page_footer(); ?>