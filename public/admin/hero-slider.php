<?php
ob_start();

$pageTitle = 'Hero Slider';
require_once __DIR__ . '/header.php';

function ensureHeroSliderTable(Database $db): void
{
    $db->query(
        "CREATE TABLE IF NOT EXISTS hero_slider_images (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            image_path VARCHAR(255) NOT NULL,
            title VARCHAR(255) DEFAULT NULL,
            subtitle VARCHAR(500) DEFAULT NULL,
            display_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function uploadHeroSlideImage(array $file): array
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return [false, null, 'Please select an image.'];
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions, true)) {
        return [false, null, 'Allowed formats: JPG, JPEG, PNG, WEBP.'];
    }

    $maxSize = 8 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxSize) {
        return [false, null, 'Image size must be 8MB or less.'];
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return [false, null, 'Uploaded file is not a valid image.'];
    }

    $uploadDir = dirname(__DIR__) . '/uploads/hero-slider/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        return [false, null, 'Failed to create slider upload directory.'];
    }

    $fileName = 'hero_slide_' . date('Ymd_His') . '_' . random_int(1000, 9999) . '.' . $extension;
    $destination = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return [false, null, 'Failed to upload image file.'];
    }

    return [true, 'uploads/hero-slider/' . $fileName, null];
}

ensureHeroSliderTable($db);

$slidesCount = (int) $db->query('SELECT COUNT(*) FROM hero_slider_images')->fetchColumn();
if ($slidesCount === 0) {
    $db->query(
        'INSERT INTO hero_slider_images (image_path, title, subtitle, display_order, is_active) VALUES (:image_path, :title, :subtitle, :display_order, :is_active)',
        [
            'image_path' => 'assets/images/hero-bg.jpg',
            'title' => 'Aleppo Gift',
            'subtitle' => 'Curated tableware and home accessories for everyday hosting and gifting.',
            'display_order' => 1,
            'is_active' => 1,
        ]
    );
}

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_order']) && isset($_POST['slide_order']) && is_array($_POST['slide_order'])) {
        foreach ($_POST['slide_order'] as $position => $slideId) {
            $id = (int) $slideId;
            $displayOrder = $position + 1;
            $db->query(
                'UPDATE hero_slider_images SET display_order = :display_order WHERE id = :id',
                [
                    'display_order' => $displayOrder,
                    'id' => $id,
                ]
            );
        }

        $message = 'Slide order updated successfully.';
        $messageType = 'success';
    }

    if (isset($_POST['add_slide'])) {
        [$ok, $imagePath, $errorMessage] = uploadHeroSlideImage($_FILES['slide_image'] ?? []);

        if (!$ok) {
            $message = (string) $errorMessage;
            $messageType = 'danger';
        } else {
            $title = trim((string) ($_POST['title'] ?? ''));
            $subtitle = trim((string) ($_POST['subtitle'] ?? ''));
            $displayOrder = max(0, (int) ($_POST['display_order'] ?? 0));
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            $db->query(
                'INSERT INTO hero_slider_images (image_path, title, subtitle, display_order, is_active) VALUES (:image_path, :title, :subtitle, :display_order, :is_active)',
                [
                    'image_path' => $imagePath,
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'display_order' => $displayOrder,
                    'is_active' => $isActive,
                ]
            );

            $message = 'Hero slide added successfully.';
            $messageType = 'success';
        }
    }

    if (isset($_POST['update_slide'])) {
        $id = (int) ($_POST['slide_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $subtitle = trim((string) ($_POST['subtitle'] ?? ''));
        $displayOrder = max(0, (int) ($_POST['display_order'] ?? 0));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $db->query(
            'UPDATE hero_slider_images SET title = :title, subtitle = :subtitle, display_order = :display_order, is_active = :is_active WHERE id = :id',
            [
                'title' => $title,
                'subtitle' => $subtitle,
                'display_order' => $displayOrder,
                'is_active' => $isActive,
                'id' => $id,
            ]
        );

        $message = 'Hero slide updated successfully.';
        $messageType = 'success';
    }

    if (isset($_POST['delete_slide'])) {
        $id = (int) ($_POST['slide_id'] ?? 0);
        $slide = $db->query('SELECT image_path FROM hero_slider_images WHERE id = :id', ['id' => $id])->fetch(PDO::FETCH_ASSOC);

        if ($slide) {
            $db->query('DELETE FROM hero_slider_images WHERE id = :id', ['id' => $id]);

            $imagePath = (string) ($slide['image_path'] ?? '');
            $fullImagePath = dirname(__DIR__) . '/' . ltrim($imagePath, '/');
            if ($imagePath !== 'assets/images/hero-bg.jpg' && is_file($fullImagePath)) {
                @unlink($fullImagePath);
            }

            $message = 'Hero slide deleted successfully.';
            $messageType = 'success';
        }
    }
}

$slides = $db->query('SELECT * FROM hero_slider_images ORDER BY display_order ASC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .slide-sort-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 10px;
    }

    .slide-sort-item {
        display: flex;
        align-items: center;
        gap: 12px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 10px 12px;
        cursor: move;
    }

    .slide-sort-item.is-dragging {
        opacity: 0.7;
        border-color: #0d6efd;
        background: #eef4ff;
    }

    .slide-sort-thumb {
        width: 86px;
        height: 48px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        background: #fff;
    }

    .slide-sort-handle {
        color: #64748b;
        font-size: 1.1rem;
    }

    .slide-upload-preview {
        margin-top: 10px;
        display: none;
    }

    .slide-upload-preview img {
        width: 220px;
        height: 124px;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        background: #fff;
    }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Hero Slider Management</h2>
            <p class="text-muted mb-0">Upload and manage homepage hero slider images.</p>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-grip-vertical me-1"></i> Drag & Drop Order</span>
            <small class="text-muted">Drag rows then click Save Order</small>
        </div>
        <div class="card-body">
            <?php if (empty($slides)): ?>
                <div class="alert alert-info mb-0">No slides available for ordering.</div>
            <?php else: ?>
                <form method="post" id="slideOrderForm">
                    <ul class="slide-sort-list" id="slideSortList">
                        <?php foreach ($slides as $slide): ?>
                            <li class="slide-sort-item" draggable="true">
                                <i class="fas fa-grip-lines slide-sort-handle" aria-hidden="true"></i>
                                <img src="../<?php echo htmlspecialchars($slide['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Slide" class="slide-sort-thumb" onerror="this.src='../assets/images/hero-bg.jpg'">
                                <div class="flex-grow-1">
                                    <div class="fw-semibold"><?php echo htmlspecialchars((string) (($slide['title'] ?? '') !== '' ? $slide['title'] : 'Untitled slide'), ENT_QUOTES, 'UTF-8'); ?></div>
                                    <small class="text-muted">ID: <?php echo (int) $slide['id']; ?> | Current order: <?php echo (int) $slide['display_order']; ?></small>
                                </div>
                                <input type="hidden" name="slide_order[]" value="<?php echo (int) $slide['id']; ?>">
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="mt-3">
                        <button type="submit" name="save_order" class="btn btn-outline-primary">
                            <i class="fas fa-save me-1"></i> Save Order
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-image me-1"></i>
            Add New Slide
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <div class="col-lg-4">
                    <label class="form-label">Slide Image *</label>
                    <input type="file" class="form-control" id="slideImageInput" name="slide_image" accept=".jpg,.jpeg,.png,.webp" required>
                    <small class="text-muted">Recommended: 1600x700, max 8MB.</small>
                    <div class="slide-upload-preview" id="slideUploadPreview">
                        <img id="slideUploadPreviewImage" src="" alt="New slide preview">
                    </div>
                </div>
                <div class="col-lg-3">
                    <label class="form-label">Title</label>
                    <input type="text" class="form-control" name="title" maxlength="255" placeholder="Optional slide title">
                </div>
                <div class="col-lg-3">
                    <label class="form-label">Subtitle</label>
                    <input type="text" class="form-control" name="subtitle" maxlength="500" placeholder="Optional subtitle">
                </div>
                <div class="col-lg-1">
                    <label class="form-label">Order</label>
                    <input type="number" class="form-control" name="display_order" value="0" min="0">
                </div>
                <div class="col-lg-1 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="is_active_new" name="is_active" checked>
                        <label class="form-check-label" for="is_active_new">Active</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" name="add_slide" class="btn btn-primary">
                        <i class="fas fa-upload me-1"></i> Add Slide
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <i class="fas fa-list me-1"></i>
            Existing Slides
        </div>
        <div class="card-body">
            <?php if (empty($slides)): ?>
                <div class="alert alert-info mb-0">No hero slides found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 120px;">Preview</th>
                                <th>Content</th>
                                <th style="width: 100px;">Order</th>
                                <th style="width: 90px;">Active</th>
                                <th style="width: 220px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($slides as $slide): ?>
                                <tr>
                                    <td>
                                        <img src="../<?php echo htmlspecialchars($slide['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Slide preview" class="img-thumbnail" style="width: 100px; height: 56px; object-fit: cover;" onerror="this.src='../assets/images/hero-bg.jpg'">
                                    </td>
                                    <td>
                                        <form method="post" class="row g-2">
                                            <input type="hidden" name="slide_id" value="<?php echo (int) $slide['id']; ?>">
                                            <div class="col-md-5">
                                                <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars((string) ($slide['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="255" placeholder="Title">
                                            </div>
                                            <div class="col-md-7">
                                                <input type="text" class="form-control" name="subtitle" value="<?php echo htmlspecialchars((string) ($slide['subtitle'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="500" placeholder="Subtitle">
                                            </div>
                                    </td>
                                    <td>
                                                <input type="number" class="form-control" name="display_order" value="<?php echo (int) $slide['display_order']; ?>" min="0">
                                    </td>
                                    <td class="text-center">
                                                <input type="checkbox" class="form-check-input" name="is_active" <?php echo (int) $slide['is_active'] === 1 ? 'checked' : ''; ?>>
                                    </td>
                                    <td>
                                                <button type="submit" name="update_slide" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-save"></i> Save
                                                </button>
                                        </form>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this slide?');">
                                            <input type="hidden" name="slide_id" value="<?php echo (int) $slide['id']; ?>">
                                            <button type="submit" name="delete_slide" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    (function () {
        const list = document.getElementById('slideSortList');
        if (list) {
            let draggedItem = null;

            list.addEventListener('dragstart', function (event) {
                const target = event.target.closest('.slide-sort-item');
                if (!target) {
                    return;
                }
                draggedItem = target;
                target.classList.add('is-dragging');
            });

            list.addEventListener('dragend', function (event) {
                const target = event.target.closest('.slide-sort-item');
                if (target) {
                    target.classList.remove('is-dragging');
                }
                draggedItem = null;
            });

            list.addEventListener('dragover', function (event) {
                event.preventDefault();
                const afterElement = getDragAfterElement(list, event.clientY);
                if (!draggedItem) {
                    return;
                }

                if (afterElement === null) {
                    list.appendChild(draggedItem);
                } else if (afterElement !== draggedItem) {
                    list.insertBefore(draggedItem, afterElement);
                }
            });
        }

        function getDragAfterElement(container, y) {
            const draggableElements = Array.from(container.querySelectorAll('.slide-sort-item:not(.is-dragging)'));

            return draggableElements.reduce(function (closest, child) {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                }
                return closest;
            }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
        }

        const input = document.getElementById('slideImageInput');
        const preview = document.getElementById('slideUploadPreview');
        const previewImage = document.getElementById('slideUploadPreviewImage');

        if (input && preview && previewImage) {
            input.addEventListener('change', function () {
                const file = input.files && input.files[0] ? input.files[0] : null;
                if (!file) {
                    preview.style.display = 'none';
                    previewImage.removeAttribute('src');
                    return;
                }

                const objectUrl = URL.createObjectURL(file);
                previewImage.src = objectUrl;
                preview.style.display = 'block';
                previewImage.onload = function () {
                    URL.revokeObjectURL(objectUrl);
                };
            });
        }
    }());
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
