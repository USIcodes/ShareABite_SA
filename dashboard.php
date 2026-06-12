<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_role('buyer');

$buyerId = $_SESSION['userID'];
$stmt = $pdo->prepare('SELECT o.orderID, o.orderDate, o.status, f.title, f.imageURL, o.totalAmount FROM orders o JOIN food_listings f ON o.listingID = f.listingID WHERE o.buyerID = :buyerID ORDER BY o.orderDate DESC LIMIT 5');
$stmt->execute(['buyerID' => $buyerId]);
$recentOrders = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<section class="container card">
    <h2>Buyer Dashboard</h2>
    <p>Welcome back, <?php echo htmlspecialchars($_SESSION['firstName'], ENT_QUOTES, 'UTF-8'); ?>.</p>
    <div class="grid-2">
        <div class="card">
            <h3>Browse food listings</h3>
            <p>Search by location, category and donations to find food in your community.</p>
            <a class="button" href="/Shareabite/listings/browse.php">Browse Listings</a>
        </div>
        <div class="card">
            <h3>Order history</h3>
            <p>View your recent orders and track delivery or pickup updates.</p>
            <a class="button button-secondary" href="/Shareabite/orders/history.php">View History</a>
        </div>
    </div>
    <div class="card">
        <h3>Recent orders</h3>
        <?php if (empty($recentOrders)): ?>
            <p>No orders yet. Start browsing food listings now.</p>
        <?php else: ?>
            <div class="listing-grid">
                <?php foreach ($recentOrders as $order): ?>
                    <div class="listing-card">
                        <img src="<?php echo htmlspecialchars($order['imageURL'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($order['title'], ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="listing-card-body">
                            <h4><?php echo htmlspecialchars($order['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                            <div class="listing-meta">
                                <span><?php echo htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span>R<?php echo number_format((float)$order['totalAmount'], 2); ?></span>
                            </div>
                            <small>Ordered <?php echo date('M j, Y', strtotime($order['orderDate'])); ?></small>
                            <a class="button button-secondary" href="/Shareabite/orders/track.php?orderID=<?php echo (int)$order['orderID']; ?>">Track</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php';
