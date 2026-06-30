<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('buyer');

$listingId = is_numeric($_GET['listingID'] ?? $_POST['listingID'] ?? '') ? (int)($_GET['listingID'] ?? $_POST['listingID']) : 0;
if ($listingId <= 0) {
    redirect_to('listings/browse.php');
    exit;
}

$stmt = $pdo->prepare('SELECT l.*, u.firstName AS sellerFirst, u.lastName AS sellerLast, u.phone AS sellerPhone FROM food_listings l JOIN users u ON l.sellerID = u.userID WHERE l.listingID = :listingID LIMIT 1');
$stmt->execute(['listingID' => $listingId]);
$listing = $stmt->fetch();
if (!$listing || $listing['status'] !== 'active') {
    set_flash_message('Listing not available for order.');
    redirect_to('listings/browse.php');
    exit;
}

$userStmt = $pdo->prepare('SELECT phone FROM users WHERE userID = :userID LIMIT 1');
$userStmt->execute(['userID' => $_SESSION['userID']]);
$user = $userStmt->fetch();
if (empty($user['phone'])) {
    set_flash_message('Please add your phone number before ordering.');
    redirect_to('dashboard.php');
    exit;
}

$errors = [];
$quantity = 1;
$pickupMethod = 'pickup';
$deliveryFee = 0.00;
$ticket = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    }
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));
    $pickupMethod = in_array($_POST['pickupMethod'] ?? 'pickup', ['pickup', 'delivery'], true) ? $_POST['pickupMethod'] : 'pickup';

    if ($quantity > $listing['quantity']) {
        $errors[] = 'There is not enough quantity available for your request.';
    }

    $deliveryFee = $pickupMethod === 'delivery' ? 6.00 : 0.00;
    $subtotal = $listing['isDonation'] ? 0.00 : (float)$listing['price'] * $quantity;
    $total = $subtotal + $deliveryFee;

    if (empty($errors)) {
        $insert = $pdo->prepare('INSERT INTO orders (buyerID, listingID, quantity, totalAmount, deliveryFee, pickupMethod, status, orderDate, updatedAt) VALUES (:buyerID, :listingID, :quantity, :totalAmount, :deliveryFee, :pickupMethod, :status, NOW(), NOW())');
        $insert->execute([
            'buyerID' => $_SESSION['userID'],
            'listingID' => $listingId,
            'quantity' => $quantity,
            'totalAmount' => $total,
            'deliveryFee' => $deliveryFee,
            'pickupMethod' => $pickupMethod,
            'status' => 'pending',
        ]);
        $orderId = (int)$pdo->lastInsertId();

        $remainingQuantity = max(0, $listing['quantity'] - $quantity);
        $newStatus = $remainingQuantity === 0 ? 'sold' : $listing['status'];
        $updateListing = $pdo->prepare('UPDATE food_listings SET quantity = :quantity, status = :status WHERE listingID = :listingID');
        $updateListing->execute(['quantity' => $remainingQuantity, 'status' => $newStatus, 'listingID' => $listingId]);

        if ($listing['isDonation']) {
            $ticket = [
                'orderID' => $orderId,
                'buyerName' => $_SESSION['firstName'],
                'buyerPhone' => $user['phone'],
                'sellerName' => $listing['sellerFirst'] . ' ' . $listing['sellerLast'],
                'sellerContact' => $listing['sellerPhone'],
                'itemName' => $listing['title'],
                'quantity' => $quantity,
                'unitPrice' => 0.00,
                'deliveryFee' => $deliveryFee,
                'totalAmount' => $total,
                'paymentMethod' => 'Donation',
                'transactionRef' => 'DONATION-' . date('YmdHis') . rand(100, 999),
                'pickupMethod' => ucfirst($pickupMethod),
                'pickupLocation' => $listing['pickupLocation'],
                'orderDate' => date('Y-m-d H:i:s'),
                'statusText' => 'Awaiting Seller Confirmation',
            ];
        } else {
            redirect_to('payment/checkout.php?orderID=' . $orderId);
            exit;
        }
    }
}

function render_ticket(array $ticket): void
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
            <div class="ticket-row"><span>Order #</span><span>#<?php echo str_pad((string)$ticket['orderID'], 6, '0', STR_PAD_LEFT); ?></span></div>
            <div class="ticket-row"><span>Buyer</span><span><?php echo htmlspecialchars($ticket['buyerName'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ticket-row"><span>Buyer Phone</span><span><?php echo htmlspecialchars($ticket['buyerPhone'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ticket-row"><span>Seller</span><span><?php echo htmlspecialchars($ticket['sellerName'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ticket-row"><span>Seller Contact</span><span><?php echo htmlspecialchars($ticket['sellerContact'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ticket-row"><span>Item</span><span><?php echo htmlspecialchars($ticket['itemName'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ticket-row"><span>Quantity</span><span><?php echo (int)$ticket['quantity']; ?></span></div>
            <div class="ticket-row"><span>Unit Price</span><span>R<?php echo number_format((float)$ticket['unitPrice'], 2); ?></span></div>
            <div class="ticket-row"><span>Delivery Fee</span><span>R<?php echo number_format((float)$ticket['deliveryFee'], 2); ?></span></div>
            <div class="ticket-row"><span>Total</span><span>R<?php echo number_format((float)$ticket['totalAmount'], 2); ?></span></div>
            <div class="ticket-row"><span>Payment</span><span><?php echo htmlspecialchars($ticket['paymentMethod'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ticket-row"><span>Reference</span><span><?php echo htmlspecialchars($ticket['transactionRef'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ticket-row"><span>Pickup Method</span><span><?php echo htmlspecialchars($ticket['pickupMethod'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ticket-row"><span>Pickup Location</span><span><?php echo htmlspecialchars($ticket['pickupLocation'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ticket-row"><span>Date</span><span><?php echo htmlspecialchars($ticket['orderDate'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="ticket-row"><span>Status</span><span><?php echo htmlspecialchars($ticket['statusText'], ENT_QUOTES, 'UTF-8'); ?></span></div>
        </div>
    </div>
    <?php
}

require_once __DIR__ . '/../includes/header.php';
?>
<section class="container card">
    <h2>Place Order</h2>
    <div class="grid-2">
        <div>
            <h3><?php echo htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
            <p><?php echo nl2br(htmlspecialchars($listing['description'], ENT_QUOTES, 'UTF-8')); ?></p>
            <p>Location: <?php echo htmlspecialchars($listing['pickupLocation'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p>Available: <?php echo (int)$listing['quantity']; ?></p>
            <p>Price: <?php echo $listing['isDonation'] ? 'FREE' : 'R' . number_format((float)$listing['price'], 2); ?></p>
        </div>
        <div>
            <?php if (!empty($errors)): ?>
                <div class="error-text"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
            <?php endif; ?>
            <?php if ($ticket): ?>
                <h3>Donation Confirmation</h3>
                <?php render_ticket($ticket); ?>
            <?php else: ?>
                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="listingID" value="<?php echo $listingId; ?>">
                    <div class="field-group">
                        <label for="quantity">Quantity</label>
                        <input id="quantity" name="quantity" type="number" min="1" max="<?php echo (int)$listing['quantity']; ?>" value="<?php echo (int)$quantity; ?>" required>
                    </div>
                    <div class="field-group">
                        <label>Pickup Method</label>
                        <div class="radio-group">
                            <label><input type="radio" name="pickupMethod" value="pickup" <?php echo $pickupMethod === 'pickup' ? 'checked' : ''; ?>> Pickup</label>
                            <label><input type="radio" name="pickupMethod" value="delivery" <?php echo $pickupMethod === 'delivery' ? 'checked' : ''; ?>> Delivery (+R6.00)</label>
                        </div>
                    </div>
                    <div class="card order-summary" style="background:#eef5ed;" data-unit-price="<?php echo $listing['isDonation'] ? '0.00' : htmlspecialchars((string)$listing['price'], ENT_QUOTES, 'UTF-8'); ?>" data-delivery-fee="6.00">
                        <h4>Order Summary</h4>
                        <p>Subtotal: R<span data-summary-subtotal><?php echo number_format($listing['isDonation'] ? 0.00 : (float)$listing['price'] * $quantity, 2); ?></span></p>
                        <p>Delivery Fee: R<span data-summary-delivery><?php echo number_format($deliveryFee, 2); ?></span></p>
                        <p><strong>Total: R<span data-summary-total><?php echo number_format(($listing['isDonation'] ? 0.00 : (float)$listing['price'] * $quantity) + $deliveryFee, 2); ?></span></strong></p>
                    </div>
                    <?php if ($listing['isDonation']): ?>
                        <p>This donation will not require payment.</p>
                    <?php else: ?>
                        <p>After you confirm, you can choose PayFast card/EFT checkout or PayShap manual payment.</p>
                    <?php endif; ?>
                    <div class="form-footer">
                        <button type="submit" class="submit-button loader-button">Confirm Order</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php';
