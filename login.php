<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . login_redirect_url($_SESSION['userType']));
    exit;
}

$errors = [];
$values = ['email' => sanitize_text($_COOKIE['remember_email'] ?? ''), 'remember' => !empty($_COOKIE['remember_email'])];
$pendingTwoFactor = !empty($_SESSION['pending_2fa_userID']);

$clientIp = get_client_ip();
$lockKey = 'login_lock_' . $clientIp;
$attemptsKey = 'login_attempts_' . $clientIp;
$lockedUntil = $_SESSION[$lockKey] ?? 0;
$attempts = $_SESSION[$attemptsKey] ?? 0;

if ($lockedUntil > 0 && time() >= $lockedUntil) {
    unset($_SESSION[$lockKey], $_SESSION[$attemptsKey]);
    $lockedUntil = 0;
    $attempts = 0;
}

$isLocked = $lockedUntil > 0 && time() < $lockedUntil;

if ($isLocked) {
    $minutes = max(1, (int)ceil(($lockedUntil - time()) / 60));
    $errors['lockout'] = 'Too many failed attempts. Please try again in about ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . '.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['csrf'] = 'Invalid form submission. Please try again.';
    }

    if (empty($errors) && ($_POST['auth_step'] ?? '') === 'totp') {
        $code = $_POST['totp_code'] ?? '';
        $stmt = $pdo->prepare('SELECT userID, firstName, userType, twoFactorSecret FROM users WHERE userID = :userID AND twoFactorEnabled = 1 LIMIT 1');
        $stmt->execute(['userID' => (int)$_SESSION['pending_2fa_userID']]);
        $user = $stmt->fetch();

        if ($user && verify_totp($user['twoFactorSecret'], $code)) {
            $rememberEmail = $_SESSION['pending_2fa_email'] ?? '';
            $rememberLogin = !empty($_SESSION['pending_2fa_remember']);
            complete_login($user);
            unset($_SESSION['pending_2fa_userID'], $_SESSION['pending_2fa_remember'], $_SESSION['pending_2fa_email']);
            unset($_SESSION[$attemptsKey], $_SESSION[$lockKey]);

            $stmt = $pdo->prepare('UPDATE users SET lastLogin = NOW() WHERE userID = :userID');
            $stmt->execute(['userID' => $user['userID']]);

            if ($rememberLogin) {
                setcookie('remember_email', $rememberEmail, cookie_options(time() + 604800));
            } else {
                setcookie('remember_email', '', cookie_options(time() - 3600));
            }

            header('Location: ' . login_redirect_url($user['userType']));
            exit;
        }

        $errors['totp'] = 'Invalid authenticator code.';
        $pendingTwoFactor = true;
    } elseif (empty($errors)) {
        $values['email'] = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ? strtolower(trim($_POST['email'])) : '';
        $password = $_POST['password'] ?? '';
        $values['remember'] = isset($_POST['remember']);

        if ($values['email'] === '' || $password === '') {
            $errors['login'] = 'Invalid email or password.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('SELECT userID, firstName, userType, passwordHash, twoFactorEnabled FROM users WHERE LOWER(email) = :email LIMIT 1');
            $stmt->execute(['email' => $values['email']]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['passwordHash'])) {
                if ((int)$user['twoFactorEnabled'] === 1) {
                    $_SESSION['pending_2fa_userID'] = (int)$user['userID'];
                    $_SESSION['pending_2fa_email'] = $values['email'];
                    $_SESSION['pending_2fa_remember'] = $values['remember'];
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $pendingTwoFactor = true;
                } else {
                    complete_login($user);
                    unset($_SESSION[$attemptsKey], $_SESSION[$lockKey]);

                    $stmt = $pdo->prepare('UPDATE users SET lastLogin = NOW() WHERE userID = :userID');
                    $stmt->execute(['userID' => $user['userID']]);

                    if ($values['remember']) {
                        setcookie('remember_email', $values['email'], cookie_options(time() + 604800));
                    } else {
                        setcookie('remember_email', '', cookie_options(time() - 3600));
                    }

                    header('Location: ' . login_redirect_url($user['userType']));
                    exit;
                }
            }
        }

        if (!$pendingTwoFactor) {
            $attempts++;
            $_SESSION[$attemptsKey] = $attempts;
            if ($isLocked) {
                $minutes = max(1, (int)ceil(($lockedUntil - time()) / 60));
                $errors['lockout'] = 'Invalid email or password. You can try again in about ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . '.';
            } elseif ($attempts >= 5) {
                $_SESSION[$lockKey] = time() + 900;
                $errors['lockout'] = 'Too many failed attempts. Please try again after 15 minutes.';
            } else {
                $errors['login'] = 'Invalid email or password.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="container card">
    <h2>Login</h2>
    <p>Sign in to access ShareABite SA and place or manage orders.</p>
    <?php if (!empty($_GET['success'])): ?>
        <div class="success-text"><?php echo htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if (!empty($_GET['message'])): ?>
        <div class="success-text"><?php echo htmlspecialchars($_GET['message'], ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if (!empty($errors['csrf'])): ?><div class="error-text"><?php echo htmlspecialchars($errors['csrf'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($pendingTwoFactor): ?>
    <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <input type="hidden" name="auth_step" value="totp">
        <div class="form-grid">
            <div class="field-group">
                <label for="totp_code">Authenticator Code</label>
                <input id="totp_code" name="totp_code" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" required autofocus>
            </div>
        </div>
        <?php if (!empty($errors['totp'])): ?><div class="error-text"><?php echo htmlspecialchars($errors['totp'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <div class="form-footer">
            <button type="submit" class="submit-button loader-button">Verify Code</button>
        </div>
    </form>
    <?php else: ?>
    <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <div class="form-grid">
            <div class="field-group">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="<?php echo htmlspecialchars($values['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="field-group">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>
            </div>
            <div class="field-group">
                <label><input type="checkbox" name="remember" <?php echo $values['remember'] ? 'checked' : ''; ?>> Remember Me</label>
            </div>
        </div>
        <?php if (!empty($errors['login'])): ?><div class="error-text"><?php echo $errors['login']; ?></div><?php endif; ?>
        <?php if (!empty($errors['lockout'])): ?><div class="error-text"><?php echo $errors['lockout']; ?></div><?php endif; ?>
        <div class="form-footer">
            <button type="submit" class="submit-button loader-button">Sign In</button>
        </div>
    </form>
    <?php endif; ?>
    <p>New to ShareABite SA? <a href="/Shareabite/register.php">Register now</a>.</p>
</section>
<?php require_once __DIR__ . '/includes/footer.php';
