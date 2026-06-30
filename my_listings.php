<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('seller');

$sellerId = $_SESSION['userID'];
$stmt = $pdo->prepare('SELECT l.listingID, l.title, l.price, l.isDonation, l.quantity, l.imageURL, l.pickupLocation, l.status, l.createdAt, c.name AS category FROM food_listings l LEFT JOIN categories c ON l.categoryID = c.categoryID WHERE l.sellerID = :sellerID ORDER BY l.createdAt DESC');
$stmt->execute(['sellerID' => $sellerId]);
$listings = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<section class="container card">
    <h2>My Listings</h2>
    <p>Manage your active food listings, edit details, and keep your community offerings up to date.</p>
    <a class="button" href="<?php echo htmlspecialchars(app_path('seller/create_listing.php'), ENT_QUOTES, 'UTF-8'); ?>">Create New Listing</a>
    <?php if (empty($listings)): ?>
        <p>You don't have any listings yet.</p>
    <?php else: ?>
        <div class="listing-grid">
            <?php foreach ($listings as $listing): ?>
                <div class="listing-card">
                    <img src="<?php echo htmlspecialchars(listing_image_url($listing['imageURL'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="listing-card-body">
                        <h4><?php echo htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                        <div class="listing-meta">
                            <span><?php echo htmlspecialchars($listing['category'] ?? 'Unlisted', ENT_QUOTES, 'UTF-8'); ?></span>
                            <span><?php echo $listing['isDonation'] ? 'FREE' : 'R' . number_format((float)$listing['price'], 2); ?></span>
                        </div>
                        <p><?php echo (int)$listing['quantity']; ?> available • <?php echo htmlspecialchars($listing['pickupLocation'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p>Status: <?php echo htmlspecialchars($listing['status'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <a class="button button-secondary" href="<?php echo htmlspecialchars(app_path('seller/edit_listing.php?listingID=' . (int)$listing['listingID']), ENT_QUOTES, 'UTF-8'); ?>">Edit Listing</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php';
