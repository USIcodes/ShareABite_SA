<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$messages = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $messages[] = 'Invalid action. Please try again.';
    } else {
        $listingId = is_numeric($_POST['listingID'] ?? '') ? (int)$_POST['listingID'] : 0;
        $action = $_POST['action'] ?? '';
        if ($listingId > 0 && $action === 'remove') {
            $stmt = $pdo->prepare('UPDATE food_listings SET status = "removed" WHERE listingID = :listingID');
            $stmt->execute(['listingID' => $listingId]);
            $messages[] = 'Listing removed.';
        }
    }
}

$stmt = $pdo->query('SELECT l.listingID, l.title, l.price, l.isDonation, l.quantity, l.status, l.createdAt, u.firstName, u.lastName FROM food_listings l JOIN users u ON l.sellerID = u.userID ORDER BY l.createdAt DESC');
$listings = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<section class="container card">
    <h2>Moderate Listings</h2>
    <?php if (!empty($messages)): ?>
        <div class="card">
            <?php foreach ($messages as $message): ?>
                <p class="success-text"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if (empty($listings)): ?>
        <p>No listings found.</p>
    <?php else: ?>
        <div class="listing-grid">
            <?php foreach ($listings as $listing): ?>
                <div class="listing-card">
                    <div class="listing-card-body">
                        <h3><?php echo htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <div class="listing-meta">
                            <span><?php echo $listing['isDonation'] ? 'FREE' : 'R' . number_format((float)$listing['price'], 2); ?></span>
                            <span><?php echo htmlspecialchars($listing['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <p>Seller: <?php echo htmlspecialchars($listing['firstName'] . ' ' . $listing['lastName'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p>Quantity: <?php echo (int)$listing['quantity']; ?> • Posted <?php echo date('M j, Y', strtotime($listing['createdAt'])); ?></p>
                        <form method="post" style="margin-top:12px;">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="listingID" value="<?php echo (int)$listing['listingID']; ?>">
                            <button type="submit" name="action" value="remove" class="button button-secondary">Remove Listing</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php';
