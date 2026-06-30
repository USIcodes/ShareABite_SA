<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('buyer');

$orderId = is_numeric($_GET['orderID'] ?? '') ? (int)$_GET['orderID'] : 0;

require_once __DIR__ . '/../includes/header.php';
?>
<section class="container card">
    <h2>Payment Cancelled</h2>
    <p>Your payment was not completed. The order is still pending, so you can try another payment option.</p>
    <?php if ($orderId > 0): ?>
        <a class="button" href="<?php echo htmlspecialchars(app_path('payment/checkout.php?orderID=' . $orderId), ENT_QUOTES, 'UTF-8'); ?>">Try Again</a>
    <?php else: ?>
        <a class="button" href="<?php echo htmlspecialchars(app_path('orders/history.php'), ENT_QUOTES, 'UTF-8'); ?>">View Order History</a>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php';
