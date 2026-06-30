<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('buyer');

$orderId = is_numeric($_GET['orderID'] ?? '') ? (int)$_GET['orderID'] : 0;
if ($orderId <= 0) {
    redirect_to('orders/history.php');
    exit;
}

$stmt = $pdo->prepare('SELECT o.*, l.title, l.pickupLocation, l.isDonation, u.firstName AS sellerFirst, u.lastName AS sellerLast, u.phone AS sellerPhone FROM orders o JOIN food_listings l ON o.listingID = l.listingID JOIN users u ON l.sellerID = u.userID WHERE o.orderID = :orderID AND o.buyerID = :buyerID LIMIT 1');
$stmt->execute(['orderID' => $orderId, 'buyerID' => $_SESSION['userID']]);
$order = $stmt->fetch();
if (!$order) {
    set_flash_message('Order not found.');
    redirect_to('orders/history.php');
    exit;
}

$paymentStmt = $pdo->prepare('SELECT * FROM payments WHERE orderID = :orderID ORDER BY paymentDate DESC LIMIT 1');
$paymentStmt->execute(['orderID' => $orderId]);
$payment = $paymentStmt->fetch();

function render_ticket(array $order, ?array $payment): void
{
    $statusText = $order['status'] === 'completed' ? 'Confirmed by Seller ✓' : 'Awaiting Seller Confirmation';
    ?>
    <div class="ticket card">
        <div class="ticket-header">
            <div>
                <h2>ShareABite SA</h2>
                <p>Order Confirmation</p>
            </div>
            <button class="button button-secondary" type="button" onclick="printTicket()">Print / Save</button>
        </div>
        <div class="ticket-body">
            <div class="ticket-row"><span>Order #</span><span>#<?php echo str_pad((string)$order['orderID'], 6, '0', STR_PAD_LEFT); ?></span></div>
            <div class="ticket-row"><span>Buyer</span><span><?php echo htmlspecialchars($_SESSION['firstName'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ticket-row"><span>Seller</span><span><?php echo htmlspecialchars($order['sellerFirst'] . ' ' . $order['sellerLast'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ticket-row"><span>Seller Contact</span><span><?php echo htmlspecialchars($order['sellerPhone'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ticket-row"><span>Item</span><span><?php echo htmlspecialchars($order['title'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ticket-row"><span>Quantity</span><span><?php echo (int)$order['quantity']; ?></span></div>
            <div class="ticket-row"><span>Unit Price</span><span>R<?php echo number_format($order['isDonation'] ? 0.00 : ((float)$order['totalAmount'] - ($order['pickupMethod'] === 'delivery' ? 6.00 : 0.00)) / max(1, (int)$order['quantity']), 2); ?></span></div>
            <div class="ticket-row"><span>Delivery Fee</span><span>R<?php echo $order['pickupMethod'] === 'delivery' ? '6.00' : '0.00'; ?></span></div>
            <div class="ticket-row"><span>Total</span><span>R<?php echo number_format((float)$order['totalAmount'], 2); ?></span></div>
            <div class="ticket-row"><span>Payment</span><span><?php echo htmlspecialchars($payment['method'] ?? ($order['isDonation'] ? 'Donation' : 'Pending'), ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ticket-row"><span>Reference</span><span><?php echo htmlspecialchars($payment['transactionRef'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ticket-row"><span>Pickup Method</span><span><?php echo htmlspecialchars(ucfirst($order['pickupMethod']), ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ticket-row"><span>Pickup Location</span><span><?php echo htmlspecialchars($order['pickupLocation'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ticket-row"><span>Date</span><span><?php echo htmlspecialchars($order['orderDate'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ticket-row"><span>Status</span><span><?php echo $statusText; ?></span></div>
        </div>
    </div>
    <?php
}

require_once __DIR__ . '/../includes/header.php';
?>
<section class="container card">
    <h2>Track Order</h2>
    <p>Order status for #<?php echo str_pad((string)$order['orderID'], 6, '0', STR_PAD_LEFT); ?></p>
    <?php render_ticket($order, $payment); ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php';
