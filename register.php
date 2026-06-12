<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: /Shareabite/index.php');
    exit;
}

$errors = [];
$values = [
    'firstName' => '',
    'lastName' => '',
    'email' => '',
    'phone' => '',
    'location' => '',
    'userType' => 'buyer',
    'businessName' => '',
    'payshapNumber' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['csrf'] = 'Invalid form submission. Please try again.';
    }

    $values['firstName'] = sanitize_text($_POST['firstName'] ?? '');
    $values['lastName'] = sanitize_text($_POST['lastName'] ?? '');
    $values['email'] = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ? strtolower(trim($_POST['email'])) : '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $values['phone'] = sanitize_text($_POST['phone'] ?? '');
    $values['location'] = sanitize_text($_POST['location'] ?? '');
    $values['userType'] = in_array($_POST['userType'] ?? 'buyer', ['buyer', 'seller'], true) ? $_POST['userType'] : 'buyer';
    $values['businessName'] = sanitize_text($_POST['businessName'] ?? '');
    $values['payshapNumber'] = sanitize_text($_POST['payshapNumber'] ?? '');

    if ($values['firstName'] === '') {
        $errors['firstName'] = 'First name is required.';
    }
    if ($values['lastName'] === '') {
        $errors['lastName'] = 'Last name is required.';
    }
    if ($values['email'] === '') {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if ($password === '' || strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password)) {
        $errors['password'] = 'Password must contain 1 uppercase letter and 1 number.';
    }
    if ($confirmPassword !== $password) {
        $errors['confirmPassword'] = 'Passwords must match.';
    }
    if ($values['phone'] === '' || !is_valid_sa_phone($values['phone'])) {
        $errors['phone'] = 'Please enter a valid South African phone number.';
    }
    if ($values['location'] === '') {
        $errors['location'] = 'Location is required.';
    }
    if ($values['userType'] === 'seller' && $values['businessName'] === '') {
        $errors['businessName'] = 'Business name is required for sellers.';
    }
    if ($values['userType'] === 'seller' && ($values['payshapNumber'] === '' || !is_valid_sa_phone($values['payshapNumber']))) {
        $errors['payshapNumber'] = 'Please enter a valid PayShap-linked South African phone number.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT userID FROM users WHERE LOWER(email) = :email LIMIT 1');
        $stmt->execute(['email' => $values['email']]);
        if ($stmt->fetch()) {
            $errors['email'] = 'This email is already registered.';
        }
    }

    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO users (firstName, lastName, email, passwordHash, phone, location, userType, businessName, sellerRating, preferredArea, adminLevel, payshapNumber, lastLogin, createdAt) VALUES (:firstName, :lastName, :email, :passwordHash, :phone, :location, :userType, :businessName, 0, :preferredArea, NULL, :payshapNumber, NULL, NOW())');
        $stmt->execute([
            'firstName' => $values['firstName'],
            'lastName' => $values['lastName'],
            'email' => $values['email'],
            'passwordHash' => $passwordHash,
            'phone' => $values['phone'],
            'location' => $values['location'],
            'userType' => $values['userType'],
            'businessName' => $values['userType'] === 'seller' ? $values['businessName'] : null,
            'preferredArea' => $values['location'],
            'payshapNumber' => $values['userType'] === 'seller' ? $values['payshapNumber'] : null,
        ]);
        $clientIp = get_client_ip();
        unset($_SESSION['login_attempts_' . $clientIp], $_SESSION['login_lock_' . $clientIp]);
        setcookie('remember_email', $values['email'], cookie_options(time() + 604800));
        header('Location: /Shareabite/login.php?success=' . urlencode('Registration complete. Please sign in.'));
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="container card">
    <h2>Register</h2>
    <p>Create a buyer or seller account to start sharing food on ShareABite SA.</p>
    <?php if (!empty($errors['csrf'])): ?><div class="error-text"><?php echo htmlspecialchars($errors['csrf'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <div class="form-grid">
            <div class="field-group">
                <label for="firstName">First Name</label>
                <input id="firstName" name="firstName" value="<?php echo htmlspecialchars($values['firstName'], ENT_QUOTES, 'UTF-8'); ?>" required>
                <?php if (!empty($errors['firstName'])): ?><div class="error-text"><?php echo $errors['firstName']; ?></div><?php endif; ?>
            </div>
            <div class="field-group">
                <label for="lastName">Last Name</label>
                <input id="lastName" name="lastName" value="<?php echo htmlspecialchars($values['lastName'], ENT_QUOTES, 'UTF-8'); ?>" required>
                <?php if (!empty($errors['lastName'])): ?><div class="error-text"><?php echo $errors['lastName']; ?></div><?php endif; ?>
            </div>
            <div class="field-group">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="<?php echo htmlspecialchars($values['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                <?php if (!empty($errors['email'])): ?><div class="error-text"><?php echo $errors['email']; ?></div><?php endif; ?>
            </div>
            <div class="field-group">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>
                <?php if (!empty($errors['password'])): ?><div class="error-text"><?php echo $errors['password']; ?></div><?php endif; ?>
            </div>
            <div class="field-group">
                <label for="confirmPassword">Confirm Password</label>
                <input id="confirmPassword" type="password" name="confirmPassword" required>
                <?php if (!empty($errors['confirmPassword'])): ?><div class="error-text"><?php echo $errors['confirmPassword']; ?></div><?php endif; ?>
            </div>
            <div class="field-group">
                <label for="phone">Phone Number</label>
                <input id="phone" name="phone" value="<?php echo htmlspecialchars($values['phone'], ENT_QUOTES, 'UTF-8'); ?>" required>
                <?php if (!empty($errors['phone'])): ?><div class="error-text"><?php echo $errors['phone']; ?></div><?php endif; ?>
            </div>
            <div class="field-group">
                <label for="location">Location (city / suburb)</label>
                <input id="location" name="location" value="<?php echo htmlspecialchars($values['location'], ENT_QUOTES, 'UTF-8'); ?>" required>
                <?php if (!empty($errors['location'])): ?><div class="error-text"><?php echo $errors['location']; ?></div><?php endif; ?>
            </div>
            <div class="field-group">
                <label>Account Type</label>
                <div class="radio-group">
                    <label><input type="radio" name="userType" value="buyer" <?php echo $values['userType'] === 'buyer' ? 'checked' : ''; ?>> Buyer</label>
                    <label><input type="radio" name="userType" value="seller" <?php echo $values['userType'] === 'seller' ? 'checked' : ''; ?>> Seller</label>
                </div>
                <?php if (!empty($errors['userType'])): ?><div class="error-text"><?php echo $errors['userType']; ?></div><?php endif; ?>
            </div>
            <div class="field-group business-group" style="display: none;">
                <label for="businessName">Business Name</label>
                <input id="businessName" name="businessName" value="<?php echo htmlspecialchars($values['businessName'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php if (!empty($errors['businessName'])): ?><div class="error-text"><?php echo $errors['businessName']; ?></div><?php endif; ?>
            </div>
            <div class="field-group seller-pay-group" style="display: none;">
                <label for="payshapNumber">Seller PayShap Phone Number</label>
                <input id="payshapNumber" name="payshapNumber" value="<?php echo htmlspecialchars($values['payshapNumber'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="082 123 4567">
                <?php if (!empty($errors['payshapNumber'])): ?><div class="error-text"><?php echo $errors['payshapNumber']; ?></div><?php endif; ?>
            </div>
        </div>
        <div class="form-footer">
            <button type="submit" class="submit-button loader-button">Create Account</button>
        </div>
    </form>
</section>
<?php require_once __DIR__ . '/includes/footer.php';
