<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$usersStmt = $pdo->query('SELECT userID, firstName, lastName, email, phone, location, userType, businessName, createdAt FROM users ORDER BY createdAt DESC');
$users = $usersStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<section class="container card">
    <h2>Manage Users</h2>
    <?php if (empty($users)): ?>
        <p>No registered users found.</p>
    <?php else: ?>
        <div class="card">
            <table style="width:100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="text-align:left; padding: 10px;">Name</th>
                        <th style="text-align:left; padding: 10px;">Email</th>
                        <th style="text-align:left; padding: 10px;">Phone</th>
                        <th style="text-align:left; padding: 10px;">Type</th>
                        <th style="text-align:left; padding: 10px;">Business</th>
                        <th style="text-align:left; padding: 10px;">Joined</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td style="padding: 10px; border-top: 1px solid #e8e6e1;"><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 10px; border-top: 1px solid #e8e6e1;"><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 10px; border-top: 1px solid #e8e6e1;"><?php echo htmlspecialchars($user['phone'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 10px; border-top: 1px solid #e8e6e1;"><?php echo htmlspecialchars($user['userType'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 10px; border-top: 1px solid #e8e6e1;"><?php echo htmlspecialchars($user['businessName'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 10px; border-top: 1px solid #e8e6e1;"><?php echo date('M j, Y', strtotime($user['createdAt'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php';
