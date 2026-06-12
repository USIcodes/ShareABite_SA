<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
if (is_logged_in()) {
    $type = $_SESSION['userType'];
    if ($type === 'buyer') {
        header('Location: /Shareabite/dashboard.php');
        exit;
    }
    if ($type === 'seller') {
        header('Location: /Shareabite/seller/dashboard.php');
        exit;
    }
    if ($type === 'admin') {
        header('Location: /Shareabite/admin/dashboard.php');
        exit;
    }
}
require_once __DIR__ . '/includes/header.php';
?>
<section class="hero hero-image">
    <div class="container hero-copy">
        <h1>ShareABite SA</h1>
        <p>Share more, waste less. Find affordable meals, donations, and surplus food from trusted people in your community.</p>
        <div class="cta-group">
            <a class="cta-button cta-primary" href="/Shareabite/listings/browse.php">Browse Food</a>
            <a class="cta-button cta-secondary" href="/Shareabite/login.php">Sign In / Register</a>
        </div>
    </div>
</section>
<section class="feature-band">
    <div class="container split-section">
        <div>
            <h2>About Us</h2>
            <p>ShareABite SA helps households, small food sellers, and local donors move good food to people nearby before it is wasted.</p>
            <p>Buyers can browse by location, order food for pickup or delivery, pay through PayFast or PayShap, and track each order with a confirmation ticket.</p>
        </div>
        <img src="/Shareabite/about us page.jpeg" alt="ShareABite SA community food sharing">
    </div>
</section>
<section class="container info-strip">
    <div>
        <h3>Payment Gateways</h3>
        <p>PayFast online checkout and PayShap manual confirmation are available for paid listings. Donations stay free.</p>
    </div>
    <div>
        <h3>Safety and Terms</h3>
        <p>Every user should review the platform terms, food safety notes, pickup rules, and payout process before trading.</p>
        <a class="button button-secondary" href="/Shareabite/terms.php">Read Ts and Cs</a>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php';
