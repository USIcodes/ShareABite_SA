<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_login();

$errors = [];
$userId = (int)$_SESSION['userID'];

$stmt = $pdo->prepare('SELECT userID, firstName, email, passwordHash, twoFactorEnabled FROM users WHERE userID = :userID LIMIT 1');
$stmt->execute(['userID' => $userId]);
$account = $stmt->fetch();

if (!$account) {
    redirect_to('logout.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'enable_2fa') {
            $password = $_POST['password'] ?? '';

            if (!password_verify($password, $account['passwordHash'])) {
                $errors[] = 'Enter your password to send a verification code.';
            } else {
                $challenge = create_email_verification_challenge('setup_2fa', $account['email'], $account['firstName']);
                $_SESSION['setup_2fa_pending'] = true;
                if (!$challenge['sent'] && !email_code_debug_allowed()) {
                    unset($_SESSION['setup_2fa_pending']);
                    clear_email_code_challenge('setup_2fa');
                    $errors[] = 'Could not send your verification email. Please contact support.';
                }
            }
        }

        if ($action === 'confirm_enable_2fa') {
            $code = $_POST['email_code'] ?? '';

            if (!verify_email_code_challenge('setup_2fa', $code) || empty($_SESSION['setup_2fa_pending'])) {
                $errors[] = 'Invalid or expired email code.';
            } else {
                $stmt = $pdo->prepare('UPDATE users SET twoFactorSecret = NULL, twoFactorEnabled = 1 WHERE userID = :userID');
                $stmt->execute(['userID' => $userId]);
                unset($_SESSION['setup_2fa_pending']);
                clear_email_code_challenge('setup_2fa');
                set_flash_message('Email verification is now enabled.');
                redirect_to('account/security.php');
                exit;
            }
        }

        if ($action === 'disable_2fa') {
            $password = $_POST['password'] ?? '';

            if (!password_verify($password, $account['passwordHash'])) {
                $errors[] = 'Enter your password to disable email verification.';
            } else {
                $stmt = $pdo->prepare('UPDATE users SET twoFactorSecret = NULL, twoFactorEnabled = 0 WHERE userID = :userID');
                $stmt->execute(['userID' => $userId]);
                unset($_SESSION['setup_2fa_pending']);
                clear_email_code_challenge('setup_2fa');
                set_flash_message('Email verification has been disabled.');
                redirect_to('account/security.php');
                exit;
            }
        }

        if ($action === 'resend_setup_code') {
            if (!empty($_SESSION['setup_2fa_pending'])) {
                create_email_verification_challenge('setup_2fa', $account['email'], $account['firstName']);
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<section class="container card">
    <h2>Security</h2>
    <p>Manage email code verification for your ShareABite SA account.</p>

    <?php if (!empty($errors)): ?>
        <div class="error-text"><?php echo implode('<br>', array_map(function ($error) {
            return htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
        }, $errors)); ?></div>
    <?php endif; ?>

    <?php if ((int)$account['twoFactorEnabled'] === 1): ?>
        <div class="card">
            <h3>Email verification is enabled</h3>
            <p>Your account requires a 6-digit code sent to <?php echo htmlspecialchars($account['email'], ENT_QUOTES, 'UTF-8'); ?> after your password.</p>
            <form method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="disable_2fa">
                <div class="field-group">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" required>
                </div>
                <div class="form-footer">
                    <button type="submit" class="submit-button">Disable Email Verification</button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="card">
            <h3>Set up email verification</h3>
            <p>Send a 6-digit code to <?php echo htmlspecialchars($account['email'], ENT_QUOTES, 'UTF-8'); ?> and enter it here to enable two-step login.</p>

            <?php if (!empty($_SESSION['setup_2fa_debug_code'])): ?>
                <div class="success-text">Local test code: <?php echo htmlspecialchars($_SESSION['setup_2fa_debug_code'], ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if (!empty($_SESSION['setup_2fa_pending'])): ?>
                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="confirm_enable_2fa">
                    <div class="field-group">
                        <label for="email_code">Email Verification Code</label>
                        <input id="email_code" name="email_code" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" required autofocus>
                    </div>
                    <div class="form-footer">
                        <button type="submit" class="submit-button">Enable Email Verification</button>
                    </div>
                </form>
                <form method="post" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="resend_setup_code">
                    <button type="submit" class="button button-secondary">Resend Code</button>
                </form>
            <?php else: ?>
                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="enable_2fa">
                    <div class="field-group">
                        <label for="password">Password</label>
                        <input id="password" type="password" name="password" required>
                    </div>
                    <div class="form-footer">
                        <button type="submit" class="submit-button">Send Verification Code</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php';
