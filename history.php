<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('buyer');

$stmt = $pdo->prepare('SELECT o.orderID, o.orderDate, o.totalAmount, o.status, l.title, l.pickupLocation FROM orders o JOIN food_listings l ON o.listingID = l.listingID WHERE o.buyerID = :buyerID ORDER BY o.orderDate DESC');
$stmt->execute(['buyerID' => $_SESSION['userID']]);
$orders = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<section class="container card">
    <h2>Order History</h2>
    <?php if (empty($orders)): ?>
        <p>You have not placed any orders yet. Browse food listings to get started.</p>
    <?php else: ?>
        <div class="listing-grid">
            <?php foreach ($orders as $order): ?>
                <div class="listing-card">
                    <div class="listing-card-body">
                        <h3><?php echo htmlspecialchars($order['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <div class="listing-meta">
                            <span><?php echo htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span>R<?php echo number_format((float)$order['totalAmount'], 2); ?></span>
                        </div>
                        <p>Pickup location: <?php echo htmlspecialchars($order['pickupLocation'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p>Ordered on <?php echo date('M j, Y', strtotime($order['orderDate'])); ?></p>
                        <a class="button button-secondary" href="<?php echo htmlspecialchars(app_path('orders/track.php?orderID=' . (int)$order['orderID']), ENT_QUOTES, 'UTF-8'); ?>">Track Order</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php';
