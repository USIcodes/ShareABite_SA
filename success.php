<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('buyer');

$orderId = is_numeric($_GET['m_payment_id'] ?? $_GET['orderID'] ?? '') ? (int)($_GET['m_payment_id'] ?? $_GET['orderID']) : 0;
if ($orderId > 0) {
    set_flash_message('Payment successful. Your order has been confirmed.');
    redirect_to('orders/track.php?orderID=' . $orderId);
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>
<section class="container card">
    <h2>Payment Successful</h2>
    <p>Your payment was completed. You can view your latest order from your order history.</p>
    <a class="button" href="<?php echo htmlspecialchars(app_path('orders/history.php'), ENT_QUOTES, 'UTF-8'); ?>">View Order History</a>
</section>
<?php require_once __DIR__ . '/../includes/footer.php';
