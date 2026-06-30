<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('seller');

$listingId = is_numeric($_GET['listingID'] ?? $_POST['listingID'] ?? '') ? (int)($_GET['listingID'] ?? $_POST['listingID']) : 0;
if ($listingId <= 0) {
    redirect_to('seller/my_listings.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM food_listings WHERE listingID = :listingID AND sellerID = :sellerID LIMIT 1');
$stmt->execute(['listingID' => $listingId, 'sellerID' => $_SESSION['userID']]);
$listing = $stmt->fetch();
if (!$listing) {
    set_flash_message('Listing not found or unauthorized.');
    redirect_to('seller/my_listings.php');
    exit;
}

$categoryStmt = $pdo->query('SELECT categoryID, name FROM categories ORDER BY name ASC');
$categories = $categoryStmt->fetchAll();

$errors = [];
$values = [
    'title' => $listing['title'],
    'description' => $listing['description'],
    'categoryID' => $listing['categoryID'],
    'price' => number_format((float)$listing['price'], 2, '.', ''),
    'isDonation' => (int)$listing['isDonation'],
    'quantity' => (int)$listing['quantity'],
    'pickupLocation' => $listing['pickupLocation'],
    'pickupMethod' => $listing['pickupMethod'] ?? 'pickup',
    'expiresAt' => $listing['expiresAt'] ?? '',
    'imageURL' => $listing['imageURL'],
];

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

    $filename = $values['imageURL'];
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $errors['image'] = 'There was a problem uploading the image.';
        } else {
            if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                $errors['image'] = 'Image must be smaller than 5MB.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($_FILES['image']['tmp_name']);
                $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if (!isset($allowed[$mimeType])) {
                    $errors['image'] = 'Only JPG, PNG or WEBP files are allowed.';
                } else {
                    $ext = $allowed[$mimeType];
                    $filename = uniqid('listing_', true) . '.' . $ext;
                    $destination = __DIR__ . '/../uploads/' . $filename;
                    if (!move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                        $errors['image'] = 'Could not save the uploaded image.';
                    } else {
                        $filename = 'uploads/' . $filename;
                    }
                }
            }
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('UPDATE food_listings SET categoryID = :categoryID, title = :title, description = :description, price = :price, isDonation = :isDonation, quantity = :quantity, imageURL = :imageURL, pickupLocation = :pickupLocation, pickupMethod = :pickupMethod, expiresAt = :expiresAt WHERE listingID = :listingID AND sellerID = :sellerID');
        $stmt->execute([
            'categoryID' => $values['categoryID'],
            'title' => $values['title'],
            'description' => $values['description'],
            'price' => $values['price'],
            'isDonation' => $values['isDonation'],
            'quantity' => $values['quantity'],
            'imageURL' => $filename,
            'pickupLocation' => $values['pickupLocation'],
            'pickupMethod' => $values['pickupMethod'],
            'expiresAt' => $values['expiresAt'] !== '' ? $values['expiresAt'] : null,
            'listingID' => $listingId,
            'sellerID' => $_SESSION['userID'],
        ]);

        set_flash_message('Listing updated successfully.');
        redirect_to('seller/my_listings.php');
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<section class="container card">
    <h2>Edit Listing</h2>
    <form method="post" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <input type="hidden" name="listingID" value="<?php echo $listingId; ?>">
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
                    <option value="0">Select category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo (int)$category['categoryID']; ?>" <?php echo $values['categoryID'] === (int)$category['categoryID'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['categoryID'])): ?><div class="error-text"><?php echo $errors['categoryID']; ?></div><?php endif; ?>
            </div>
            <div class="field-group">
                <label><input type="checkbox" id="isDonation" name="isDonation" <?php echo $values['isDonation'] ? 'checked' : ''; ?>> This is a donation</label>
            </div>
            <div class="field-group">
                <label for="price">Price (R)</label>
                <input id="price" name="price" type="number" step="0.01" min="0" value="<?php echo htmlspecialchars($values['price'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $values['isDonation'] ? 'readonly' : ''; ?> required>
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
                <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp">
                <?php if (!empty($errors['image'])): ?><div class="error-text"><?php echo $errors['image']; ?></div><?php endif; ?>
                <p>Current image: <?php echo htmlspecialchars($values['imageURL'], ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
        <div class="form-footer">
            <button type="submit" class="submit-button loader-button">Update Listing</button>
        </div>
    </form>
</section>
<?php require_once __DIR__ . '/../includes/footer.php';
