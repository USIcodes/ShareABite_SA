<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('seller');

$errors = [];
$values = [
    'title' => '',
    'description' => '',
    'categoryID' => '',
    'price' => '0.00',
    'isDonation' => 0,
    'quantity' => 1,
    'pickupLocation' => '',
    'pickupMethod' => 'pickup',
    'expiresAt' => '',
];

$categoryStmt = $pdo->query('SELECT categoryID, name FROM categories ORDER BY name ASC');
$categories = $categoryStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['csrf'] = 'Invalid form submission. Please refresh the page.';
    }

    $values['title'] = sanitize_text($_POST['title'] ?? '');
    $values['description'] = sanitize_text($_POST['description'] ?? '');
    $values['categoryID'] = is_numeric($_POST['categoryID'] ?? '') ? (int)$_POST['categoryID'] : 0;
    $values['isDonation'] = isset($_POST['isDonation']) ? 1 : 0;
    $values['price'] = $values['isDonation'] ? '0.00' : number_format((float)($_POST['price'] ?? '0'), 2, '.', '');
    $values['quantity'] = max(1, (int)($_POST['quantity'] ?? 1));
    $values['pickupLocation'] = sanitize_text($_POST['pickupLocation'] ?? '');
    $values['pickupMethod'] = in_array($_POST['pickupMethod'] ?? 'pickup', ['pickup', 'delivery'], true) ? $_POST['pickupMethod'] : 'pickup';
    $values['expiresAt'] = trim($_POST['expiresAt'] ?? '');

    if ($values['title'] === '') {
        $errors['title'] = 'Title is required.';
    }
    if ($values['description'] === '') {
        $errors['description'] = 'Description is required.';
    }
    if ($values['categoryID'] <= 0) {
        $errors['categoryID'] = 'Please select a category.';
    }
    if ($values['pickupLocation'] === '') {
        $errors['pickupLocation'] = 'Pickup location is required.';
    }
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $errors['image'] = 'Please upload a listing image.';
    }

    if (empty($errors) && isset($_FILES['image'])) {
        $file = $_FILES['image'];
        if ($file['size'] > 5 * 1024 * 1024) {
            $errors['image'] = 'Image must be smaller than 5MB.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($allowed[$mimeType])) {
                $errors['image'] = 'Only JPG, PNG or WEBP files are allowed.';
            }
        }
    }

    if (empty($errors)) {
        $ext = $allowed[$mimeType];
        $filename = uniqid('listing_', true) . '.' . $ext;
        $destination = __DIR__ . '/../uploads/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $errors['image'] = 'Could not save the uploaded image. Please try again.';
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('INSERT INTO food_listings (sellerID, categoryID, title, description, price, isDonation, quantity, imageURL, pickupLocation, pickupMethod, status, createdAt, expiresAt) VALUES (:sellerID, :categoryID, :title, :description, :price, :isDonation, :quantity, :imageURL, :pickupLocation, :pickupMethod, :status, NOW(), :expiresAt)');
        $stmt->execute([
            'sellerID' => $_SESSION['userID'],
            'categoryID' => $values['categoryID'],
            'title' => $values['title'],
            'description' => $values['description'],
            'price' => $values['price'],
            'isDonation' => $values['isDonation'],
            'quantity' => $values['quantity'],
            'imageURL' => 'uploads/' . $filename,
            'pickupLocation' => $values['pickupLocation'],
            'pickupMethod' => $values['pickupMethod'],
            'status' => 'active',
            'expiresAt' => $values['expiresAt'] !== '' ? $values['expiresAt'] : null,
        ]);

        redirect_to('seller/my_listings.php?success=' . urlencode('Listing created successfully.'));
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<section class="container card">
    <h2>Create Listing</h2>
    <p>Add a food listing with a mandatory image so buyers can discover it easily.</p>
    <form method="post" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <div class="form-grid">
            <div class="field-group">
                <label for="title">Title</label>
                <input id="title" name="title" value="<?php echo htmlspecialchars($values['title'], ENT_QUOTES, 'UTF-8'); ?>" required>
                <?php if (!empty($errors['title'])): ?><div class="error-text"><?php echo $errors['title']; ?></div><?php endif; ?>
            </div>
            <div class="field-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" required><?php echo htmlspecialchars($values['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                <?php if (!empty($errors['description'])): ?><div class="error-text"><?php echo $errors['description']; ?></div><?php endif; ?>
            </div>
            <div class="field-group">
                <label for="categoryID">Category</label>
                <select id="categoryID" name="categoryID" required>
                    <option value="">Select category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo (int)$category['categoryID']; ?>" <?php echo $values['categoryID'] == $category['categoryID'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['categoryID'])): ?><div class="error-text"><?php echo $errors['categoryID']; ?></div><?php endif; ?>
            </div>
            <div class="field-group">
                <label><input type="checkbox" id="isDonation" name="isDonation" <?php echo $values['isDonation'] ? 'checked' : ''; ?>> This is a donation</label>
            </div>
            <div class="field-group">
                <label for="price">Price (R)</label>
                <input id="price" name="price" value="<?php echo htmlspecialchars($values['price'], ENT_QUOTES, 'UTF-8'); ?>" type="number" step="0.01" min="0" required>
            </div>
            <div class="field-group">
                <label for="quantity">Quantity</label>
                <input id="quantity" name="quantity" type="number" min="1" value="<?php echo (int)$values['quantity']; ?>" required>
            </div>
            <div class="field-group">
                <label for="pickupLocation">Pickup Location</label>
                <input id="pickupLocation" name="pickupLocation" value="<?php echo htmlspecialchars($values['pickupLocation'], ENT_QUOTES, 'UTF-8'); ?>" required>
                <?php if (!empty($errors['pickupLocation'])): ?><div class="error-text"><?php echo $errors['pickupLocation']; ?></div><?php endif; ?>
            </div>
            <div class="field-group">
                <label>Delivery Options</label>
                <div class="radio-group">
                    <label><input type="radio" name="pickupMethod" value="pickup" <?php echo $values['pickupMethod'] === 'pickup' ? 'checked' : ''; ?>> Pickup</label>
                    <label><input type="radio" name="pickupMethod" value="delivery" <?php echo $values['pickupMethod'] === 'delivery' ? 'checked' : ''; ?>> Delivery</label>
                </div>
            </div>
            <div class="field-group">
                <label for="expiresAt">Expiry Date (optional)</label>
                <input id="expiresAt" name="expiresAt" type="date" value="<?php echo htmlspecialchars($values['expiresAt'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="field-group">
                <label for="image">Listing Image</label>
                <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" required>
                <?php if (!empty($errors['image'])): ?><div class="error-text"><?php echo $errors['image']; ?></div><?php endif; ?>
            </div>
        </div>
        <div class="form-footer">
            <button type="submit" class="submit-button loader-button">Create Listing</button>
        </div>
    </form>
</section>
<?php require_once __DIR__ . '/../includes/footer.php';
