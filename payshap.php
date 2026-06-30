<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('buyer');

$orderId = is_numeric($_GET['orderID'] ?? $_POST['orderID'] ?? '') ? (int)($_GET['orderID'] ?? $_POST['orderID']) : 0;
if ($orderId <= 0) {
    redirect_to('orders/history.php');
    exit;
}

$stmt = $pdo->prepare('SELECT o.*, l.title, l.isDonation, l.pickupLocation, u.firstName AS sellerFirst, u.lastName AS sellerLast, COALESCE(u.payshapNumber, u.phone) AS sellerPayShap FROM orders o JOIN food_listings l ON o.listingID = l.listingID JOIN users u ON l.sellerID = u.userID WHERE o.orderID = :orderID AND o.buyerID = :buyerID LIMIT 1');
$stmt->execute(['orderID' => $orderId, 'buyerID' => $_SESSION['userID']]);
$order = $stmt->fetch();
if (!$order) {
    set_flash_message('Order not found.');
    redirect_to('orders/history.php');
    exit;
}

if ($order['isDonation']) {
    set_flash_message('This order is a donation and does not require PayShap payment.');
    redirect_to('orders/track.php?orderID=' . $orderId);
    exit;
}

$errors = [];
$success = false;
$payment = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please refresh the page.';
    }
    $payShapId = sanitize_text($_POST['payShapId'] ?? '');
    if ($payShapId === '') {
        $errors[] = 'Please enter your PayShap number or ID.';
    }
    if (empty($order['sellerPayShap'])) {
        $errors[] = 'Seller has not set up PayShap yet.';
    }

    if (empty($errors)) {
        $transactionRef = 'PS-' . date('YmdHis') . rand(100, 999);
        $platformFee = round((float)$order['totalAmount'] * 0.05, 2);
        $sellerPayout = round((float)$order['totalAmount'] - $platformFee, 2);
        $stmt = $pdo->prepare('INSERT INTO payments (orderID, amount, platformFee, sellerPayout, method, status, transactionRef, buyerPayshap, sellerPayshap, paymentDate) VALUES (:orderID, :amount, :platformFee, :sellerPayout, :method, :status, :transactionRef, :buyerPayshap, :sellerPayshap, NOW()) ON DUPLICATE KEY UPDATE amount = VALUES(amount), platformFee = VALUES(platformFee), sellerPayout = VALUES(sellerPayout), method = VALUES(method), status = VALUES(status), transactionRef = VALUES(transactionRef), buyerPayshap = VALUES(buyerPayshap), sellerPayshap = VALUES(sellerPayshap), paymentDate = NOW()');
        $stmt->execute([
            'orderID' => $orderId,
            'amount' => $order['totalAmount'],
            'platformFee' => $platformFee,
            'sellerPayout' => $sellerPayout,
            'method' => 'payshap',
            'status' => 'successful',
            'transactionRef' => $transactionRef,
            'buyerPayshap' => $payShapId,
            'sellerPayshap' => $order['sellerPayShap'],
        ]);
        $pdo->prepare('UPDATE orders SET status = "confirmed", updatedAt = NOW() WHERE orderID = :orderID')->execute(['orderID' => $orderId]);
        $success = true;
        $payment = ['method' => 'PayShap', 'transactionRef' => $transactionRef];
    }
}

function render_ticket(array $order, array $payment): void
{
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
            <div class="ticket-row"><span>Seller PayShap</span><span><?php echo htmlspecialchars($order['sellerPayShap'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ticket-row"><span>Item</span><span><?php echo htmlspecialchars($order['title'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ticket-row"><span>Quantity</span><span><?php echo (int)$order['quantity']; ?></span></div>
            <div class="ticket-row"><span>Unit Price</span><span>R<?php echo number_format(((float)$order['totalAmount'] - ($order['pickupMethod'] === 'delivery' ? 6.00 : 0.00)) / max(1, (int)$order['quantity']), 2); ?></span></div>
            <div class="ticket-row"><span>Delivery Fee</span><span>R<?php echo $order['pickupMethod'] === 'delivery' ? '6.00' : '0.00'; ?></span></div>
            <div class="ticket-row"><span>Total</span><span>R<?php echo number_format((float)$order['totalAmount'], 2); ?></span></div>
            <div class="ticket-row"><span>Payment</span><span><?php echo htmlspecialchars($payment['method'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ticket-row"><span>Reference</span><span><?php echo htmlspecialchars($payment['transactionRef'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ticket-row"><span>Pickup Method</span><span><?php echo htmlspecialchars(ucfirst($order['pickupMethod']), ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ticket-row"><span>Pickup Location</span><span><?php echo htmlspecialchars($order['pickupLocation'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ticket-row"><span>Date</span><span><?php echo htmlspecialchars(date('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ticket-row"><span>Status</span><span>Awaiting Seller Confirmation</span></div>
        </div>
    </div>
    <?php
}

require_once __DIR__ . '/../includes/header.php';
?>
<section class="container card">
    <h2>PayShap Payment</h2>
    <p>Complete your payment for order #<?php echo str_pad((string)$orderId, 6, '0', STR_PAD_LEFT); ?>.</p>
    <?php if (!empty($errors)): ?>
        <div class="error-text"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
    <?php endif; ?>
    <?php if ($success && $payment): ?>
        <?php render_ticket($order, $payment); ?>
    <?php else: ?>
        <div class="card">
            <p>Amount to pay: <strong>R<?php echo number_format((float)$order['totalAmount'], 2); ?></strong></p>
            <p>Seller PayShap ID: <?php echo htmlspecialchars($order['sellerPayShap'] ?: 'Not set', ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="orderID" value="<?php echo $orderId; ?>">
            <div class="field-group">
                <label for="payShapId">Your PayShap phone number or ID</label>
                <input id="payShapId" name="payShapId" value="<?php echo htmlspecialchars($_POST['payShapId'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="form-footer">
                <button type="submit" class="submit-button loader-button">Confirm Payment</button>
            </div>
        </form>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php';
