<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

$listingId = is_numeric($_GET['listingID'] ?? '') ? (int)$_GET['listingID'] : 0;
if ($listingId <= 0) {
    redirect_to('listings/browse.php');
    exit;
}

$stmt = $pdo->prepare('SELECT l.*, c.name AS categoryName, u.firstName AS sellerFirst, u.lastName AS sellerLast, u.sellerRating FROM food_listings l LEFT JOIN categories c ON l.categoryID = c.categoryID LEFT JOIN users u ON l.sellerID = u.userID WHERE l.listingID = :listingID LIMIT 1');
$stmt->execute(['listingID' => $listingId]);
$listing = $stmt->fetch();
if (!$listing) {
    redirect_to('listings/browse.php');
    exit;
}

$reviewErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_logged_in()) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $reviewErrors[] = 'Invalid submission. Please try again.';
    }
    $rating = min(5, max(1, (int)($_POST['rating'] ?? 0)));
    $comment = sanitize_text($_POST['comment'] ?? '');

    $orderStmt = $pdo->prepare('SELECT orderID FROM orders WHERE buyerID = :buyerID AND listingID = :listingID AND status = "completed" LIMIT 1');
    $orderStmt->execute(['buyerID' => $_SESSION['userID'], 'listingID' => $listingId]);
    $completedOrder = $orderStmt->fetch();

    $existingReviewStmt = $pdo->prepare('SELECT reviewID FROM reviews WHERE reviewerID = :reviewerID AND listingID = :listingID LIMIT 1');
    $existingReviewStmt->execute(['reviewerID' => $_SESSION['userID'], 'listingID' => $listingId]);
    $existingReview = $existingReviewStmt->fetch();

    if (!$completedOrder) {
        $reviewErrors[] = 'You can only leave a review after a completed order.';
    }
    if ($existingReview) {
        $reviewErrors[] = 'You have already reviewed this listing.';
    }
    if ($comment === '') {
        $reviewErrors[] = 'Please leave a comment with your rating.';
    }

    if (empty($reviewErrors)) {
        $insert = $pdo->prepare('INSERT INTO reviews (reviewerID, listingID, rating, comment, createdAt) VALUES (:reviewerID, :listingID, :rating, :comment, NOW())');
        $insert->execute(['reviewerID' => $_SESSION['userID'], 'listingID' => $listingId, 'rating' => $rating, 'comment' => $comment]);
        set_flash_message('Thank you. Your review has been submitted.');
        redirect_to('listings/view.php?listingID=' . $listingId);
        exit;
    }
}

$reviewsStmt = $pdo->prepare('SELECT r.rating, r.comment, r.createdAt, u.firstName, u.lastName FROM reviews r JOIN users u ON r.reviewerID = u.userID WHERE r.listingID = :listingID ORDER BY r.createdAt DESC');
$reviewsStmt->execute(['listingID' => $listingId]);
$reviews = $reviewsStmt->fetchAll();

$canReview = false;
if (is_logged_in()) {
    $orderStmt = $pdo->prepare('SELECT orderID FROM orders WHERE buyerID = :buyerID AND listingID = :listingID AND status = "completed" LIMIT 1');
    $orderStmt->execute(['buyerID' => $_SESSION['userID'], 'listingID' => $listingId]);
    $completedOrder = $orderStmt->fetch();

    $existingReviewStmt = $pdo->prepare('SELECT reviewID FROM reviews WHERE reviewerID = :reviewerID AND listingID = :listingID LIMIT 1');
    $existingReviewStmt->execute(['reviewerID' => $_SESSION['userID'], 'listingID' => $listingId]);
    $existingReview = $existingReviewStmt->fetch();
    $canReview = $completedOrder && !$existingReview;
}

require_once __DIR__ . '/../includes/header.php';
?>
<section class="container card">
    <div class="grid-2">
        <div>
            <img src="<?php echo htmlspecialchars(listing_image_url($listing['imageURL'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8'); ?>" style="width:100%; border-radius:18px;">
        </div>
        <div>
            <h2><?php echo htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
            <p><?php echo nl2br(htmlspecialchars($listing['description'], ENT_QUOTES, 'UTF-8')); ?></p>
            <div class="listing-meta">
                <span><?php echo $listing['isDonation'] ? 'FREE' : 'R' . number_format((float)$listing['price'], 2); ?></span>
                <span><?php echo htmlspecialchars($listing['categoryName'] ?? 'General', ENT_QUOTES, 'UTF-8'); ?></span>
                <span><?php echo htmlspecialchars($listing['pickupLocation'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <p>Quantity available: <?php echo (int)$listing['quantity']; ?></p>
            <p>Seller: <?php echo htmlspecialchars($listing['sellerFirst'] . ' ' . $listing['sellerLast'], ENT_QUOTES, 'UTF-8'); ?> • Rating: <?php echo htmlspecialchars($listing['sellerRating'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>/5</p>
            <p>Posted: <?php echo date('M j, Y', strtotime($listing['createdAt'])); ?></p>
            <?php if ($listing['status'] !== 'active'): ?>
                <div class="error-text">This listing is not available.</div>
            <?php else: ?>
                <?php if (!is_logged_in()): ?>
                    <a class="button" href="<?php echo htmlspecialchars(app_path('login.php?message=' . urlencode('Please log in to place an order')), ENT_QUOTES, 'UTF-8'); ?>">Log in to Order</a>
                <?php else: ?>
                    <a class="button" href="<?php echo htmlspecialchars(app_path('orders/place.php?listingID=' . $listingId), ENT_QUOTES, 'UTF-8'); ?>">Place Order</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <h3>Reviews</h3>
        <?php if (empty($reviews)): ?>
            <p>No reviews yet for this listing.</p>
        <?php else: ?>
            <?php foreach ($reviews as $review): ?>
                <div class="card" style="padding:16px; margin-bottom:12px;">
                    <p><strong><?php echo htmlspecialchars($review['firstName'] . ' ' . $review['lastName'], ENT_QUOTES, 'UTF-8'); ?></strong> • Rating: <?php echo (int)$review['rating']; ?>/5</p>
                    <p><?php echo nl2br(htmlspecialchars($review['comment'], ENT_QUOTES, 'UTF-8')); ?></p>
                    <small><?php echo date('M j, Y', strtotime($review['createdAt'])); ?></small>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php if ($canReview): ?>
        <div class="card">
            <h3>Leave a Review</h3>
            <?php if (!empty($reviewErrors)): ?>
                <div class="error-text"><?php echo implode('<br>', array_map('htmlspecialchars', $reviewErrors)); ?></div>
            <?php endif; ?>
            <form method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="field-group">
                    <label for="rating">Rating</label>
                    <select id="rating" name="rating" required>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?> stars</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="field-group">
                    <label for="comment">Comment</label>
                    <textarea id="comment" name="comment" required></textarea>
                </div>
                <div class="form-footer">
                    <button type="submit" class="submit-button loader-button">Submit Review</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php';
