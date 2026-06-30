<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';

$pfData = $_POST;
$orderId = is_numeric($pfData['m_payment_id'] ?? '') ? (int)$pfData['m_payment_id'] : 0;
$paymentStatus = strtoupper((string)($pfData['payment_status'] ?? ''));

if ($orderId > 0 && $paymentStatus === 'COMPLETE') {
    $amount = (float)($pfData['amount_gross'] ?? 0);
    $platformFee = round($amount * 0.05, 2);
    $sellerPayout = round($amount - $platformFee, 2);
    $transactionRef = (string)($pfData['pf_payment_id'] ?? ('PF-' . date('YmdHis')));

    $stmt = $pdo->prepare('
        INSERT INTO payments (orderID, amount, platformFee, sellerPayout, method, status, transactionRef, paymentDate)
        VALUES (:orderID, :amount, :platformFee, :sellerPayout, "payfast", "successful", :transactionRef, NOW())
        ON DUPLICATE KEY UPDATE
            amount = VALUES(amount),
            platformFee = VALUES(platformFee),
            sellerPayout = VALUES(sellerPayout),
            method = VALUES(method),
            status = VALUES(status),
            transactionRef = VALUES(transactionRef),
            paymentDate = NOW()
    ');
    $stmt->execute([
        'orderID' => $orderId,
        'amount' => $amount,
        'platformFee' => $platformFee,
        'sellerPayout' => $sellerPayout,
        'transactionRef' => $transactionRef,
    ]);

    $stmt = $pdo->prepare('UPDATE orders SET status = "confirmed", updatedAt = NOW() WHERE orderID = :orderID');
    $stmt->execute(['orderID' => $orderId]);
}

http_response_code(200);
echo 'OK';
