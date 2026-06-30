<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
$flash = get_flash_message();
$user = current_user();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ShareABite SA</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('assets/css/style.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="logo" href="<?php echo htmlspecialchars(app_path('index.php'), ENT_QUOTES, 'UTF-8'); ?>">
            <img src="<?php echo htmlspecialchars(asset_url('assets/images/logo.png'), ENT_QUOTES, 'UTF-8'); ?>" alt="ShareABite SA logo">
            <span>ShareABite SA</span>
        </a>
        <nav class="main-nav">
            <a href="<?php echo htmlspecialchars(app_path('listings/browse.php'), ENT_QUOTES, 'UTF-8'); ?>">Browse Food</a>
            <a href="<?php echo htmlspecialchars(app_path('terms.php'), ENT_QUOTES, 'UTF-8'); ?>">Ts and Cs</a>
            <?php if (!$user): ?>
                <a href="<?php echo htmlspecialchars(app_path('login.php'), ENT_QUOTES, 'UTF-8'); ?>">Sign In</a>
                <a href="<?php echo htmlspecialchars(app_path('register.php'), ENT_QUOTES, 'UTF-8'); ?>">Register</a>
            <?php else: ?>
                <?php if ($user['userType'] === 'buyer'): ?>
                    <a href="<?php echo htmlspecialchars(app_path('dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>">Dashboard</a>
                    <a href="<?php echo htmlspecialchars(app_path('orders/history.php'), ENT_QUOTES, 'UTF-8'); ?>">Order History</a>
                <?php elseif ($user['userType'] === 'seller'): ?>
                    <a href="<?php echo htmlspecialchars(app_path('seller/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>">Seller</a>
                    <a href="<?php echo htmlspecialchars(app_path('seller/my_listings.php'), ENT_QUOTES, 'UTF-8'); ?>">My Listings</a>
                <?php elseif ($user['userType'] === 'admin'): ?>
                    <a href="<?php echo htmlspecialchars(app_path('admin/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>">Admin</a>
                <?php endif; ?>
                <a href="<?php echo htmlspecialchars(app_path('account/security.php'), ENT_QUOTES, 'UTF-8'); ?>">Security</a>
                <a href="<?php echo htmlspecialchars(app_path('logout.php'), ENT_QUOTES, 'UTF-8'); ?>">Logout</a>
            <?php endif; ?>
        </nav>
    </div>
    <?php if ($flash): ?>
        <div class="flash-message"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
</header>
<main class="page-content">
