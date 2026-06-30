<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('buyer');

$orderId = is_numeric($_GET['orderID'] ?? '') ? (int)$_GET['orderID'] : 0;
if ($orderId <= 0) {
    redirect_to('orders/history.php');
    exit;
}

$stmt = $pdo->prepare('
    SELECT o.*, f.title, f.isDonation, u.firstName, u.lastName, u.email
    FROM orders o
    JOIN food_listings f ON f.listingID = o.listingID
    JOIN users u ON u.userID = o.buyerID
    WHERE o.orderID = :orderID AND o.buyerID = :buyerID
    LIMIT 1
');
$stmt->execute(['orderID' => $orderId, 'buyerID' => $_SESSION['userID']]);
$order = $stmt->fetch();

if (!$order || (int)$order['isDonation'] === 1 || (float)$order['totalAmount'] <= 0) {
    redirect_to('orders/history.php');
    exit;
}

$merchantId = getenv('SHAREABITE_PAYFAST_MERCHANT_ID') ?: '10000100';
$merchantKey = getenv('SHAREABITE_PAYFAST_MERCHANT_KEY') ?: '46f0cd694581a';
$passPhrase = getenv('SHAREABITE_PAYFAST_PASSPHRASE') ?: 'jt7NOE43FZPn';
$testMode = filter_var(getenv('SHAREABITE_PAYFAST_TEST_MODE') ?: 'true', FILTER_VALIDATE_BOOLEAN);
$payfastUrl = $testMode ? 'https://sandbox.payfast.co.za/eng/process' : 'https://www.payfast.co.za/eng/process';
$baseUrl = rtrim(app_url(), '/');

$data = [
    'merchant_id' => $merchantId,
    'merchant_key' => $merchantKey,
    'return_url' => $baseUrl . '/payment/success.php',
    'cancel_url' => $baseUrl . '/payment/cancel.php?orderID=' . $orderId,
    'notify_url' => $baseUrl . '/payment/notify.php',
    'name_first' => $order['firstName'],
    'name_last' => $order['lastName'],
    'email_address' => $order['email'],
    'm_payment_id' => (string)$orderId,
    'amount' => number_format((float)$order['totalAmount'], 2, '.', ''),
    'item_name' => $order['title'],
];

$signatureParts = [];
foreach ($data as $key => $value) {
    $signatureParts[] = $key . '=' . urlencode(trim((string)$value));
}
if ($passPhrase !== '') {
    $signatureParts[] = 'passphrase=' . urlencode($passPhrase);
}
$data['signature'] = md5(implode('&', $signatureParts));

require_once __DIR__ . '/../includes/header.php';
?>
<section class="container card">
    <h2>PayFast Checkout</h2>
    <p>You are being redirected to PayFast sandbox checkout for order #<?php echo str_pad((string)$orderId, 6, '0', STR_PAD_LEFT); ?>.</p>
    <form action="<?php echo htmlspecialchars($payfastUrl, ENT_QUOTES, 'UTF-8'); ?>" method="post" id="payfast-form">
        <?php foreach ($data as $key => $value): ?>
            <input type="hidden" name="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); ?>">
        <?php endforeach; ?>
        <button type="submit" class="submit-button">Pay R<?php echo number_format((float)$order['totalAmount'], 2); ?></button>
    </form>
</section>
<script>
    document.getElementById('payfast-form').submit();
</script>
<?php require_once __DIR__ . '/../includes/footer.php';
