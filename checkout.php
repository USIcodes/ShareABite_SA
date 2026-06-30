<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('buyer');

$orderId = is_numeric($_GET['orderID'] ?? '') ? (int)$_GET['orderID'] : 0;
if ($orderId <= 0) {
    redirect_to('orders/history.php');
    exit;
}

$stmt = $pdo->prepare('SELECT o.*, l.title, l.isDonation FROM orders o JOIN food_listings l ON o.listingID = l.listingID WHERE o.orderID = :orderID AND o.buyerID = :buyerID LIMIT 1');
$stmt->execute(['orderID' => $orderId, 'buyerID' => $_SESSION['userID']]);
$order = $stmt->fetch();
if (!$order) {
    set_flash_message('Order not found.');
    redirect_to('orders/history.php');
    exit;
}

if ((int)$order['isDonation'] === 1 || (float)$order['totalAmount'] <= 0) {
    redirect_to('orders/track.php?orderID=' . $orderId);
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>
<section class="container card">
    <h2>Choose Payment Gateway</h2>
    <p>Order #<?php echo str_pad((string)$orderId, 6, '0', STR_PAD_LEFT); ?> for <?php echo htmlspecialchars($order['title'], ENT_QUOTES, 'UTF-8'); ?> totals R<?php echo number_format((float)$order['totalAmount'], 2); ?>.</p>
    <div class="grid-2">
        <div class="card">
            <h3>PayFast</h3>
            <p>Pay online using card, instant EFT, Capitec Pay, or supported PayFast options. ShareABite records a 5% platform fee and seller payout after confirmation.</p>
            <a class="button" href="<?php echo htmlspecialchars(app_path('payment/payfast.php?orderID=' . $orderId), ENT_QUOTES, 'UTF-8'); ?>">Continue with PayFast</a>
        </div>
        <div class="card">
            <h3>PayShap</h3>
            <p>Use manual PayShap confirmation when the buyer and seller are coordinating directly.</p>
            <a class="button button-secondary" href="<?php echo htmlspecialchars(app_path('payment/payshap.php?orderID=' . $orderId), ENT_QUOTES, 'UTF-8'); ?>">Continue with PayShap</a>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php';
