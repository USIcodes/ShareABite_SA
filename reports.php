<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalListings = (int)$pdo->query('SELECT COUNT(*) FROM food_listings')->fetchColumn();
$totalOrders = (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$totalRevenue = (float)$pdo->query('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = "successful"')->fetchColumn();

$topCategoriesStmt = $pdo->query('SELECT c.name, COUNT(*) AS total FROM food_listings l JOIN categories c ON l.categoryID = c.categoryID GROUP BY l.categoryID ORDER BY total DESC LIMIT 5');
$topCategories = $topCategoriesStmt->fetchAll();

$recentPaymentsStmt = $pdo->query('SELECT p.paymentID, p.amount, p.status, p.transactionRef, p.paymentDate, u.firstName, u.lastName FROM payments p JOIN orders o ON p.orderID = o.orderID JOIN users u ON o.buyerID = u.userID WHERE p.status = "successful" ORDER BY p.paymentDate DESC LIMIT 5');
$recentPayments = $recentPaymentsStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<section class="container card">
    <h2>Platform Reports</h2>
    <div class="grid-2">
        <div class="card">
            <h3>Key Metrics</h3>
            <p>Total users: <?php echo $totalUsers; ?></p>
            <p>Total listings: <?php echo $totalListings; ?></p>
            <p>Total orders: <?php echo $totalOrders; ?></p>
            <p>Total revenue: R<?php echo number_format($totalRevenue, 2); ?></p>
        </div>
        <div class="card">
            <h3>Top Categories</h3>
            <?php if (empty($topCategories)): ?>
                <p>No categories to report.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($topCategories as $category): ?>
                        <li><?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?> — <?php echo (int)$category['total']; ?> listings</li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <h3>Recent Payments</h3>
        <?php if (empty($recentPayments)): ?>
            <p>No successful payments recorded yet.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($recentPayments as $payment): ?>
                    <li><?php echo htmlspecialchars($payment['firstName'] . ' ' . $payment['lastName'], ENT_QUOTES, 'UTF-8'); ?> paid R<?php echo number_format((float)$payment['amount'], 2); ?> — Ref <?php echo htmlspecialchars($payment['transactionRef'], ENT_QUOTES, 'UTF-8'); ?> on <?php echo date('M j, Y', strtotime($payment['paymentDate'])); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php';
