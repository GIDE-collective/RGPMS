<?php
/**
 * RG PMS - Company site gallery.
 * Admins can upload / delete. Everyone else sees the grid.
 */
require_once __DIR__ . '/includes/layout.php';
require_login();

$user   = current_user();
$isAdmin = ($user['role_name'] === 'admin');
$pdo    = db();
$errors = [];

// ---- Upload handler (admin only) ----
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    csrf_check();

    $title = trim($_POST['title'] ?? '');
    $desc  = trim($_POST['description'] ?? '');

    if ($title === '') $errors[] = 'A title is required.';
    if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please choose an image to upload.';
    }

    if (!$errors) {
        $file = $_FILES['image'];
        $maxSize = 8 * 1024 * 1024; // 8 MB
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

        $mime = mime_content_type($file['tmp_name']);
        if (!isset($allowed[$mime])) {
            $errors[] = 'Only JPG, PNG, WEBP or GIF images are allowed.';
        } elseif ($file['size'] > $maxSize) {
            $errors[] = 'Image is too large (max 8 MB).';
        } else {
            $ext      = $allowed[$mime];
            $safeName = 'site_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dir      = __DIR__ . '/uploads/gallery';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $dest     = $dir . '/' . $safeName;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                $errors[] = 'Could not save the uploaded file.';
            } else {
                $ins = $pdo->prepare(
                    'INSERT INTO gallery (title, description, filename, uploaded_by) VALUES (?, ?, ?, ?)'
                );
                $ins->execute([$title, $desc ?: null, $safeName, $user['id']]);
                flash_set('success', 'Photo added to the gallery.');
                redirect('gallery.php');
            }
        }
    }
}

// ---- Fetch all gallery items ----
$items = $pdo->query(
    'SELECT g.*, u.full_name AS uploaded_by_name
     FROM gallery g
     LEFT JOIN users u ON u.id = g.uploaded_by
     ORDER BY g.created_at DESC'
)->fetchAll();

page_header('Site gallery', 'gallery');
page_heading('Site gallery', 'A visual record of our work on site');
?>

<?php if ($isAdmin): ?>
  <!-- Admin upload card -->
  <div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
      <h6 class="fw-semibold mb-3"><i class="bi bi-cloud-arrow-up me-2 text-brand"></i>Upload a site photo</h6>

      <?php if ($errors): ?>
        <div class="alert alert-danger small">
          <ul class="mb-0 ps-3">
            <?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" class="row g-3 align-items-start">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload">

        <div class="col-md-6 col-lg-5">
          <label class="form-label required">Title</label>
          <input type="text" name="title" class="form-control" required
                 value="<?= e($_POST['title'] ?? '') ?>" maxlength="180"
                 placeholder="e.g. Foundation pour - Phase 2">
        </div>

        <div class="col-md-6 col-lg-4">
          <label class="form-label">Description (optional)</label>
          <input type="text" name="description" class="form-control"
                 value="<?= e($_POST['description'] ?? '') ?>"
                 placeholder="Short caption">
        </div>

        <div class="col-md-12 col-lg-3">
          <label class="form-label required">Image</label>
          <input type="file" name="image" accept="image/*" class="form-control" required
                 onchange="previewImg(this)">
          <div class="form-text">JPG, PNG, WEBP or GIF · max 8 MB</div>
        </div>

        <div class="col-12 text-end">
          <img id="uploadPreview" alt="" style="display:none;max-height:120px;border-radius:10px;margin-right:10px;">
          <button class="btn btn-brand">
            <i class="bi bi-cloud-arrow-up me-2"></i>Upload to gallery
          </button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if (!$items): ?>
  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body text-center py-5">
      <i class="bi bi-images display-4 text-muted"></i>
      <p class="text-muted mt-3 mb-0">No photos have been added yet.</p>
    </div>
  </div>
<?php else: ?>

  <div class="gallery-grid">
    <?php foreach ($items as $it):
        $src = BASE_URL . '/uploads/gallery/' . rawurlencode($it['filename']);
    ?>
      <figure class="gallery-item">
        <a href="<?= $src ?>" class="gallery-link"
           data-caption="<?= e($it['title']) ?><?= $it['description'] ? ' - ' . e($it['description']) : '' ?>">
          <img src="<?= $src ?>" alt="<?= e($it['title']) ?>" loading="lazy">
        </a>
        <figcaption>
          <div class="gallery-title"><?= e($it['title']) ?></div>
          <?php if ($it['description']): ?>
            <div class="gallery-desc small"><?= e($it['description']) ?></div>
          <?php endif; ?>
          <div class="gallery-meta small">
            <?= e($it['uploaded_by_name'] ?? '-') ?> · <?= time_ago($it['created_at']) ?>
          </div>
          <?php if ($isAdmin): ?>
            <div class="gallery-actions">
              <?= delete_button('gallery', (int)$it['id']) ?>
            </div>
          <?php endif; ?>
        </figcaption>
      </figure>
    <?php endforeach; ?>
  </div>

<?php endif; ?>

<!-- Lightbox -->
<div id="lightbox" class="lb-overlay" aria-hidden="true">
  <button class="lb-close" aria-label="Close">&times;</button>
  <img id="lbImg" src="" alt="">
  <div id="lbCaption" class="lb-caption"></div>
</div>

<style>
/* ---------- GALLERY GRID ---------- */
.gallery-grid {
  column-count: 4;
  column-gap: 16px;
}
@media (max-width: 1199.98px) { .gallery-grid { column-count: 3; } }
@media (max-width: 767.98px)  { .gallery-grid { column-count: 2; } }
@media (max-width: 479.98px)  { .gallery-grid { column-count: 1; } }

.gallery-item {
  break-inside: avoid;
  margin: 0 0 16px;
  border-radius: 14px;
  overflow: hidden;
  background: #ffffff;
  border: 1px solid var(--border-color, #e2e8f0);
  box-shadow: 0 1px 3px rgba(15,23,42,.05);
  transition: transform .18s ease, box-shadow .18s ease;
}
.gallery-item:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 28px rgba(15,23,42,.12);
}
.gallery-item .gallery-link {
  display: block;
  overflow: hidden;
  cursor: zoom-in;
}
.gallery-item img {
  width: 100%;
  display: block;
  transition: transform .3s ease;
}
.gallery-item:hover img { transform: scale(1.04); }

.gallery-item figcaption {
  padding: 10px 12px 12px;
}
.gallery-title {
  font-weight: 600;
  font-size: .88rem;
  color: var(--text-color, #0f172a);
  margin-bottom: 2px;
  line-height: 1.3;
}
.gallery-desc {
  color: var(--muted-text, #64748b);
  line-height: 1.35;
  margin-bottom: 4px;
}
.gallery-meta {
  color: var(--muted-text, #64748b);
  font-size: .72rem;
}
.gallery-actions {
  margin-top: 8px;
  display: flex;
  justify-content: flex-end;
}
.gallery-actions .btn { padding: 3px 8px; font-size: .72rem; }

/* ---------- LIGHTBOX ---------- */
.lb-overlay {
  position: fixed;
  inset: 0;
  background: rgba(10, 15, 30, .92);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 30px;
  opacity: 0;
  visibility: hidden;
  transition: opacity .2s ease, visibility .2s ease;
  z-index: 3000;
}
.lb-overlay.open { opacity: 1; visibility: visible; }
.lb-overlay img {
  max-width: 90vw;
  max-height: 80vh;
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0,0,0,.5);
  transform: scale(.96);
  transition: transform .25s ease;
}
.lb-overlay.open img { transform: scale(1); }
.lb-close {
  position: absolute;
  top: 20px; right: 24px;
  background: rgba(255,255,255,.1);
  color: #fff;
  border: none;
  border-radius: 50%;
  width: 44px; height: 44px;
  font-size: 28px;
  line-height: 1;
  cursor: pointer;
  transition: background .15s ease;
}
.lb-close:hover { background: rgba(255,255,255,.2); }
.lb-caption {
  color: #cbd5e1;
  margin-top: 16px;
  font-size: .92rem;
  text-align: center;
  max-width: 90vw;
}

/* Dark mode */
html.theme-dark .gallery-item {
  background: #111a2e;
  border-color: rgba(255,255,255,.08);
}
html.theme-dark .gallery-title { color: #e2e8f0; }
</style>

<script>
// ---- Preview before upload ----
function previewImg(input) {
  var prev = document.getElementById('uploadPreview');
  if (!prev) return;
  if (input.files && input.files[0]) {
    var url = URL.createObjectURL(input.files[0]);
    prev.src = url;
    prev.style.display = 'inline-block';
  } else {
    prev.style.display = 'none';
  }
}

// ---- Lightbox ----
document.addEventListener('DOMContentLoaded', function () {
  var lb      = document.getElementById('lightbox');
  var lbImg   = document.getElementById('lbImg');
  var lbCap   = document.getElementById('lbCaption');
  var lbClose = lb.querySelector('.lb-close');

  document.querySelectorAll('.gallery-link').forEach(function (link) {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      lbImg.src = this.href;
      lbCap.textContent = this.dataset.caption || '';
      lb.classList.add('open');
      document.body.style.overflow = 'hidden';
    });
  });

  function close() {
    lb.classList.remove('open');
    document.body.style.overflow = '';
  }

  lbClose.addEventListener('click', close);
  lb.addEventListener('click', function (e) { if (e.target === lb) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
});
</script>

<?php page_footer(); ?>