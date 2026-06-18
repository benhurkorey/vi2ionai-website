<?php
require_once __DIR__ . '/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/db.php';

$db    = getDB();
$id    = (int)($_GET['id'] ?? 0);
$flash = null;
$product = [
    'name'=>'','slug'=>'','price'=>'','category'=>'','badge'=>'',
    'badge_class'=>'badge-dark','description'=>'','benefits'=>'',
    'hover_text'=>'','stripe_link'=>'','image_path'=>'',
    'sort_order'=>0,'is_visible'=>1
];

// Load existing product for edit
if ($id > 0) {
    $stmt = $db->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) $product = $found;
    else { header('Location: /admin/dashboard.php'); exit; }
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $flash = ['type'=>'error','msg'=>'Invalid CSRF token.'];
    } else {
        // Sanitize
        $name        = trim(strip_tags($_POST['name'] ?? ''));
        $slug        = trim(preg_replace('/[^a-z0-9-]/', '', strtolower(str_replace(' ', '-', $_POST['slug'] ?? $name))));
        $price       = max(0, (float)preg_replace('/[^0-9.]/', '', $_POST['price'] ?? '0'));
        $category    = trim(strip_tags($_POST['category'] ?? ''));
        $badge       = trim(strip_tags($_POST['badge'] ?? ''));
        $badge_class = in_array($_POST['badge_class']??'', ['badge-dark','badge-blue','badge-outline']) ? $_POST['badge_class'] : 'badge-dark';
        $description = trim($_POST['description'] ?? '');
        $benefits    = trim($_POST['benefits'] ?? '');
        $hover_text  = trim($_POST['hover_text'] ?? '');
        $stripe_link = trim($_POST['stripe_link'] ?? '');
        $sort_order  = max(0, (int)($_POST['sort_order'] ?? 0));
        $is_visible  = isset($_POST['is_visible']) ? 1 : 0;
        $image_path  = $product['image_path'] ?? '';

        // Handle image upload
        if (!empty($_FILES['image']['tmp_name'])) {
            $result = handleImageUpload($_FILES['image']);
            if ($result['ok']) {
                // Delete old image
                if ($image_path && file_exists(UPLOAD_DIR . $image_path)) {
                    @unlink(UPLOAD_DIR . $image_path);
                }
                $image_path = $result['path'];
            } else {
                $flash = ['type'=>'error','msg'=>$result['error']];
            }
        }

        if (!$flash) {
            if (empty($name)) {
                $flash = ['type'=>'error','msg'=>'Product name is required.'];
            } else {
                if ($id > 0) {
                    $stmt = $db->prepare(
                        'UPDATE products SET name=?,slug=?,price=?,category=?,badge=?,badge_class=?,
                         description=?,benefits=?,hover_text=?,stripe_link=?,image_path=?,
                         sort_order=?,is_visible=? WHERE id=?'
                    );
                    $stmt->execute([$name,$slug,$price,$category,$badge,$badge_class,
                        $description,$benefits,$hover_text,$stripe_link,$image_path,
                        $sort_order,$is_visible,$id]);
                    $flash = ['type'=>'success','msg'=>'Product updated successfully.'];
                } else {
                    $stmt = $db->prepare(
                        'INSERT INTO products (name,slug,price,category,badge,badge_class,
                         description,benefits,hover_text,stripe_link,image_path,sort_order,is_visible)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
                    );
                    $stmt->execute([$name,$slug,$price,$category,$badge,$badge_class,
                        $description,$benefits,$hover_text,$stripe_link,$image_path,
                        $sort_order,$is_visible]);
                    $id = (int)$db->lastInsertId();
                    $flash = ['type'=>'success','msg'=>'Product created.'];
                    header("Location: /admin/product-edit.php?id={$id}&saved=1");
                    exit;
                }
                // Reload product
                $stmt = $db->prepare('SELECT * FROM products WHERE id = ?');
                $stmt->execute([$id]);
                $product = $stmt->fetch() ?: $product;
            }
        }
    }
}

function handleImageUpload(array $file): array {
    $maxBytes = UPLOAD_MAX_MB * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        return ['ok'=>false,'error'=>'Image must be under ' . UPLOAD_MAX_MB . 'MB.'];
    }
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, UPLOAD_ALLOWED_TYPES)) {
        return ['ok'=>false,'error'=>'Only JPG, PNG, WebP or GIF allowed.'];
    }
    $ext      = match($mime) {
        'image/jpeg' => 'jpg', 'image/png' => 'png',
        'image/webp' => 'webp', 'image/gif' => 'gif', default => 'jpg'
    };
    $filename = bin2hex(random_bytes(10)) . '_' . time() . '.' . $ext;
    $dest     = UPLOAD_DIR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok'=>false,'error'=>'Upload failed. Check server permissions.'];
    }
    return ['ok'=>true,'path'=>$filename];
}

$csrf = generateCsrf();
$editing = $id > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= $editing ? 'Edit' : 'Add' ?> Product — Vi2ionai Admin</title>
  <meta name="robots" content="noindex, nofollow"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Manrope', sans-serif; background: #f8f9fa; color: #111; }
    a { text-decoration: none; color: inherit; }
    .sidebar {
      position: fixed; top: 0; left: 0; bottom: 0; width: 240px;
      background: #0a0a0a; color: #fff; padding: 28px 20px;
      display: flex; flex-direction: column; z-index: 100;
    }
    .sidebar-logo { margin-bottom: 36px; display: flex; align-items: center; gap: 10px; }
    .sidebar-logo img { height: 28px; width: auto; }
    .sidebar-logo-badge { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #444; background: #1a1a1a; border-radius: 4px; padding: 2px 7px; }
    .nav-item {
      display: flex; align-items: center; gap: 10px; padding: 10px 12px;
      border-radius: 8px; font-size: .85rem; font-weight: 500; color: #888;
      margin-bottom: 4px; transition: .15s;
    }
    .nav-item:hover, .nav-item.active { background: #1a1a1a; color: #fff; }
    .sidebar-footer { margin-top: auto; }

    .main { margin-left: 240px; padding: 40px 48px; }
    .page-header { display: flex; align-items: center; gap: 16px; margin-bottom: 32px; }
    .back-btn { color: #888; font-size: .85rem; }
    .back-btn:hover { color: #000; }
    .page-title { font-size: 1.4rem; font-weight: 800; letter-spacing: -.02em; }

    .form-grid { display: grid; grid-template-columns: 1fr 360px; gap: 24px; }
    .card { background: #fff; border: 1px solid #eee; border-radius: 12px; padding: 28px; }
    .card-title { font-size: .9rem; font-weight: 700; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #f0f0f0; }

    .field { margin-bottom: 20px; }
    label { display: block; font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #888; margin-bottom: 6px; }
    input[type=text], input[type=number], input[type=url], select, textarea {
      width: 100%; background: #fff; border: 1.5px solid #e5e7eb; border-radius: 8px;
      padding: 10px 14px; font-size: .875rem; font-family: inherit; color: #111;
      transition: border-color .15s; resize: vertical;
    }
    input:focus, select:focus, textarea:focus { outline: none; border-color: #000; }
    textarea { min-height: 90px; line-height: 1.6; }
    .hint { font-size: .72rem; color: #aaa; margin-top: 5px; }

    .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

    .toggle-label { display: flex; align-items: center; gap: 10px; cursor: pointer; }
    .toggle-label input { width: auto; }

    .image-preview { margin-bottom: 16px; }
    .image-preview img { width: 100%; max-height: 160px; object-fit: cover; border-radius: 8px; border: 1px solid #eee; }
    .no-image { background: #f8f9fa; border: 2px dashed #ddd; border-radius: 8px; height: 120px; display: flex; align-items: center; justify-content: center; color: #bbb; font-size: .85rem; margin-bottom: 16px; }

    .btn-row { display: flex; gap: 12px; align-items: center; margin-top: 8px; }
    .btn-save {
      background: #000; color: #fff; border: none; border-radius: 8px;
      padding: 12px 28px; font-size: .9rem; font-weight: 700;
      font-family: inherit; cursor: pointer; transition: opacity .15s;
    }
    .btn-save:hover { opacity: .8; }
    .btn-cancel { color: #888; font-size: .875rem; }
    .btn-cancel:hover { color: #000; }

    .flash { padding: 12px 16px; border-radius: 8px; margin-bottom: 24px; font-size: .875rem; font-weight: 600; }
    .flash-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
    .flash-error   { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
  </style>
</head>
<body>
<div class="sidebar">
  <div class="sidebar-logo">
    <img src="/assets/img/logo-dark-bg.png" alt="Vi2ion AI" width="112" height="28" loading="eager">
    <span class="sidebar-logo-badge">Admin</span>
  </div>
  <a class="nav-item" href="/admin/dashboard.php">← Dashboard</a>
  <a class="nav-item active" href="/admin/product-edit.php">
    <?= $editing ? 'Edit Product' : 'Add Product' ?>
  </a>
</div>

<div class="main">
  <div class="page-header">
    <a href="/admin/dashboard.php" class="back-btn">← Back</a>
    <div class="page-title"><?= $editing ? 'Edit Product' : 'New Product' ?></div>
  </div>

  <?php if ($flash): ?>
    <div class="flash flash-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($csrf) ?>"/>
    <div class="form-grid">

      <!-- LEFT: Main details -->
      <div>
        <div class="card" style="margin-bottom:20px">
          <div class="card-title">Product Details</div>

          <div class="field">
            <label>Product Name *</label>
            <input type="text" name="name" required
                   value="<?= htmlspecialchars($product['name']) ?>"
                   oninput="autoSlug(this.value)" placeholder="e.g. Fleet GPS Tracker"/>
          </div>

          <div class="row-2">
            <div class="field">
              <label>Slug (URL)</label>
              <input type="text" name="slug" id="slug-field"
                     value="<?= htmlspecialchars($product['slug']) ?>"
                     placeholder="fleet-gps-tracker"/>
              <div class="hint">Auto-generated from name</div>
            </div>
            <div class="field">
              <label>Price (AUD) — 0 = "Contact Us"</label>
              <input type="number" name="price" min="0" step="0.01"
                     value="<?= htmlspecialchars($product['price']) ?>" placeholder="0.00"/>
            </div>
          </div>

          <div class="field">
            <label>Category</label>
            <input type="text" name="category"
                   value="<?= htmlspecialchars($product['category']) ?>"
                   placeholder="e.g. Fleet GPS Tracking"/>
          </div>

          <div class="field">
            <label>Description</label>
            <textarea name="description" rows="4"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
          </div>

          <div class="field">
            <label>Benefits (one per line)</label>
            <textarea name="benefits" rows="5"><?= htmlspecialchars($product['benefits'] ?? '') ?></textarea>
            <div class="hint">Shown as bullet points. One benefit per line.</div>
          </div>

          <div class="field">
            <label>Hover / Callout Text</label>
            <textarea name="hover_text" rows="3"><?= htmlspecialchars($product['hover_text'] ?? '') ?></textarea>
            <div class="hint">Optional highlighted text shown below description</div>
          </div>

          <div class="field">
            <label>Stripe Payment Link</label>
            <input type="url" name="stripe_link"
                   value="<?= htmlspecialchars($product['stripe_link'] ?? '') ?>"
                   placeholder="https://buy.stripe.com/..."/>
          </div>
        </div>
      </div>

      <!-- RIGHT: Image + Settings -->
      <div>
        <div class="card" style="margin-bottom:20px">
          <div class="card-title">Product Image</div>
          <?php if (!empty($product['image_path'])): ?>
            <div class="image-preview">
              <img src="<?= htmlspecialchars(UPLOAD_URL . $product['image_path']) ?>"
                   alt="<?= htmlspecialchars($product['name']) ?>"/>
            </div>
          <?php else: ?>
            <div class="no-image">No image uploaded</div>
          <?php endif; ?>
          <div class="field">
            <label>Upload Image (JPG/PNG/WebP, max <?= UPLOAD_MAX_MB ?>MB)</label>
            <input type="file" name="image" id="image-input"
                   accept="image/jpeg,image/png,image/webp,image/gif"
                   style="border:none;padding:0;font-size:.85rem"
                   onchange="validateImageFile(this)"/>
            <div id="image-error" style="display:none;margin-top:8px;padding:10px 14px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;font-size:.8rem;font-weight:600;color:#dc2626;"></div>
            <div id="image-info" style="display:none;margin-top:6px;font-size:.75rem;color:#888;"></div>
          </div>
        </div>

        <div class="card">
          <div class="card-title">Display Settings</div>
          <div class="row-2">
            <div class="field">
              <label>Badge Text</label>
              <input type="text" name="badge"
                     value="<?= htmlspecialchars($product['badge'] ?? '') ?>"
                     placeholder="e.g. Best Seller"/>
            </div>
            <div class="field">
              <label>Badge Style</label>
              <select name="badge_class">
                <option value="badge-dark"    <?= ($product['badge_class']??'') === 'badge-dark'    ? 'selected' : '' ?>>Dark</option>
                <option value="badge-blue"    <?= ($product['badge_class']??'') === 'badge-blue'    ? 'selected' : '' ?>>Blue</option>
                <option value="badge-outline" <?= ($product['badge_class']??'') === 'badge-outline' ? 'selected' : '' ?>>Outline</option>
              </select>
            </div>
          </div>

          <div class="field">
            <label>Sort Order</label>
            <input type="number" name="sort_order" min="0" max="999"
                   value="<?= (int)($product['sort_order'] ?? 0) ?>"/>
            <div class="hint">Lower numbers appear first (1 = first)</div>
          </div>

          <div class="field">
            <label class="toggle-label">
              <input type="checkbox" name="is_visible" value="1"
                     <?= !empty($product['is_visible']) ? 'checked' : '' ?>/>
              Visible on website
            </label>
          </div>
        </div>

        <div class="btn-row" style="margin-top:20px">
          <button type="submit" class="btn-save">
            <?= $editing ? 'Save Changes' : 'Create Product' ?> →
          </button>
          <a href="/admin/dashboard.php" class="btn-cancel">Cancel</a>
        </div>
      </div>

    </div>
  </form>
</div>

<script>
// ── Slug auto-generation ──────────────────────────────────────────────────
function autoSlug(name) {
  const s = document.getElementById('slug-field');
  if (!s.dataset.manual) {
    s.value = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  }
}
document.getElementById('slug-field').addEventListener('input', () => {
  document.getElementById('slug-field').dataset.manual = '1';
});

// ── Image upload frontend validation ─────────────────────────────────────
const MAX_UPLOAD_MB  = <?= UPLOAD_MAX_MB ?>;
const MAX_UPLOAD_BYTES = MAX_UPLOAD_MB * 1024 * 1024;
const ALLOWED_TYPES  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
const TYPE_LABELS    = { 'image/jpeg':'JPG', 'image/png':'PNG', 'image/webp':'WebP', 'image/gif':'GIF' };

function validateImageFile(input) {
  const errEl  = document.getElementById('image-error');
  const infoEl = document.getElementById('image-info');
  const saveBtn = document.querySelector('.btn-save');
  errEl.style.display  = 'none';
  infoEl.style.display = 'none';
  errEl.textContent    = '';

  if (!input.files || !input.files[0]) return;
  const file = input.files[0];

  // Type check
  if (!ALLOWED_TYPES.includes(file.type)) {
    showUploadError(input, `Only JPG, PNG, WebP, or GIF files are allowed. Selected file type: ${file.type || 'unknown'}.`);
    saveBtn.disabled = true;
    return;
  }

  // Size check
  if (file.size > MAX_UPLOAD_BYTES) {
    const sizeMB = (file.size / 1024 / 1024).toFixed(1);
    showUploadError(input, `File is too large (${sizeMB} MB). Maximum allowed size is ${MAX_UPLOAD_MB} MB. Please resize the image and try again.`);
    saveBtn.disabled = true;
    return;
  }

  // All good — show friendly info
  saveBtn.disabled = false;
  const sizeMB  = (file.size / 1024 / 1024).toFixed(2);
  const typeLabel = TYPE_LABELS[file.type] || file.type;
  infoEl.textContent = `✓ ${file.name} · ${typeLabel} · ${sizeMB} MB`;
  infoEl.style.display = 'block';
  infoEl.style.color = '#16a34a';

  // Live preview of selected image
  const reader = new FileReader();
  reader.onload = function(e) {
    let preview = document.querySelector('.image-preview img');
    if (!preview) {
      const container = document.querySelector('.image-preview') || document.querySelector('.no-image');
      if (container) {
        container.outerHTML = '<div class="image-preview"><img src="" alt="Preview"/></div>';
        preview = document.querySelector('.image-preview img');
      }
    }
    if (preview) { preview.src = e.target.result; }
  };
  reader.readAsDataURL(file);
}

function showUploadError(input, msg) {
  const errEl = document.getElementById('image-error');
  errEl.textContent    = '⚠ ' + msg;
  errEl.style.display  = 'block';
  input.value          = '';  // Clear the invalid selection
}

// Re-enable Save if user clears the file input
document.getElementById('image-input').addEventListener('change', function() {
  if (!this.files || !this.files[0]) {
    document.querySelector('.btn-save').disabled = false;
    document.getElementById('image-error').style.display  = 'none';
    document.getElementById('image-info').style.display   = 'none';
  }
});
</script>
</body>
</html>
