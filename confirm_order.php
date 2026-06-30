<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('seller');

$sellerId = $_SESSION['userID'];
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $messages[] = 'Invalid action. Please try again.';
    } else {
        $orderId = is_numeric($_POST['orderID'] ?? '') ? (int)$_POST['orderID'] : 0;
        $action = $_POST['action'] ?? '';

        $stmt = $pdo->prepare('SELECT o.orderID FROM orders o JOIN food_listings l ON o.listingID = l.listingID WHERE o.orderID = :orderID AND l.sellerID = :sellerID AND o.status = "pending" LIMIT 1');
        $stmt->execute(['orderID' => $orderId, 'sellerID' => $sellerId]);
        $order = $stmt->fetch();

        if (!$order) {
            $messages[] = 'Order not found or not pending.';
        } elseif ($action === 'confirm') {
            $update = $pdo->prepare('UPDATE orders SET status = "completed", updatedAt = NOW() WHERE orderID = :orderID');
            $update->execute(['orderID' => $orderId]);
            $messages[] = 'Order confirmed successfully.';
        } elseif ($action === 'cancel') {
            $update = $pdo->prepare('UPDATE orders SET status = "cancelled", updatedAt = NOW() WHERE orderID = :orderID');
            $update->execute(['orderID' => $orderId]);
            $messages[] = 'Order cancelled and buyer notified.';
        }
    }
}

$stmt = $pdo->prepare('SELECT o.orderID, o.orderDate, o.quantity, o.pickupMethod, o.status, o.totalAmount, o.updatedAt, u.firstName, u.lastName, f.title FROM orders o JOIN food_listings f ON o.listingID = f.listingID JOIN users u ON o.buyerID = u.userID WHERE f.sellerID = :sellerID AND o.status = "pending" ORDER BY o.orderDate ASC');
$stmt->execute(['sellerID' => $sellerId]);
$orders = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<section class="container card">
    <h2>Confirm Orders</h2>
    <p>Review pending orders for your listings and confirm pickup or delivery.</p>
    <?php if (!empty($messages)): ?>
        <div class="card">
            <?php foreach ($messages as $message): ?>
                <p class="success-text"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if (empty($orders)): ?>
        <p>No pending orders at this time.</p>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
            <div class="card">
                <p><strong><?php echo htmlspecialchars($order['firstName'] . ' ' . $order['lastName'], ENT_QUOTES, 'UTF-8'); ?></strong> ordered <strong><?php echo (int)$order['quantity']; ?></strong> x <?php echo htmlspecialchars($order['title'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p>Pickup method: <?php echo htmlspecialchars($order['pickupMethod'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p>Placed: <?php echo date('M j, Y H:i', strtotime($order['orderDate'])); ?></p>
                <form method="post" style="display:flex; gap:12px; flex-wrap:wrap;">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="orderID" value="<?php echo (int)$order['orderID']; ?>">
                    <button class="button" type="submit" name="action" value="confirm">Confirm Pickup/Delivery</button>
                    <button class="button button-secondary" type="submit" name="action" value="cancel">Cancel Order</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php';
