<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('seller');

$sellerId = $_SESSION['userID'];
$stmt = $pdo->prepare('SELECT COUNT(*) AS totalListings FROM food_listings WHERE sellerID = :sellerID');
$stmt->execute(['sellerID' => $sellerId]);
$totalListings = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) AS pendingOrders FROM orders o JOIN food_listings f ON o.listingID = f.listingID WHERE f.sellerID = :sellerID AND o.status = "pending"');
$stmt->execute(['sellerID' => $sellerId]);
$pendingOrders = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT o.orderID, o.orderDate, o.quantity, o.status, u.firstName, u.lastName, f.title FROM orders o JOIN food_listings f ON o.listingID = f.listingID JOIN users u ON o.buyerID = u.userID WHERE f.sellerID = :sellerID ORDER BY o.orderDate DESC LIMIT 5');
$stmt->execute(['sellerID' => $sellerId]);
$recentOrders = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<section class="container card">
    <h2>Seller Dashboard</h2>
    <p>Welcome back, <?php echo htmlspecialchars($_SESSION['firstName'], ENT_QUOTES, 'UTF-8'); ?>.</p>
    <div class="grid-2">
        <div class="card">
            <h3>My listings</h3>
            <p>Manage your food listings and keep your inventory up to date.</p>
            <a class="button" href="<?php echo htmlspecialchars(app_path('seller/my_listings.php'), ENT_QUOTES, 'UTF-8'); ?>">My Listings</a>
        </div>
        <div class="card">
            <h3>Create a new listing</h3>
            <p>Add fresh food or donation listings for your community.</p>
            <a class="button button-secondary" href="<?php echo htmlspecialchars(app_path('seller/create_listing.php'), ENT_QUOTES, 'UTF-8'); ?>">Create Listing</a>
        </div>
    </div>
    <div class="card">
        <h3>Quick stats</h3>
        <p>Total listings: <?php echo (int)$totalListings; ?></p>
        <p>Pending orders: <?php echo (int)$pendingOrders; ?></p>
    </div>
    <div class="card">
        <h3>Recent orders</h3>
        <?php if (empty($recentOrders)): ?>
            <p>No orders yet. Your first order appears here once a buyer places it.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($recentOrders as $order): ?>
                    <li><?php echo htmlspecialchars($order['firstName'] . ' ' . $order['lastName'], ENT_QUOTES, 'UTF-8'); ?> ordered <?php echo (int)$order['quantity']; ?> x <?php echo htmlspecialchars($order['title'], ENT_QUOTES, 'UTF-8'); ?> on <?php echo date('M j, Y', strtotime($order['orderDate'])); ?> — <?php echo htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php';
