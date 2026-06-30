<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    $sessionPath = session_save_path();
    if ($sessionPath === '' || !is_dir($sessionPath) || !is_writable($sessionPath)) {
        $fallbackSessionPath = dirname(__DIR__) . '/tmp/sessions';
        if (!is_dir($fallbackSessionPath)) {
            mkdir($fallbackSessionPath, 0775, true);
        }
        session_save_path($fallbackSessionPath);
    }
    $secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/db.php';

function generate_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(string $token = null): bool
{
    return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function sanitize_text(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function current_user(): ?array
{
    static $checked = false;
    static $cachedUser = null;

    if ($checked) {
        return $cachedUser;
    }

    $checked = true;
    $userId = filter_var($_SESSION['userID'] ?? null, FILTER_VALIDATE_INT);
    if (!$userId || empty($_SESSION['userType'])) {
        return null;
    }

    global $pdo;
    $stmt = $pdo->prepare('SELECT userID, firstName, userType, twoFactorEnabled FROM users WHERE userID = :userID LIMIT 1');
    $stmt->execute(['userID' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        unset($_SESSION['userID'], $_SESSION['userType'], $_SESSION['firstName']);
        return null;
    }

    $_SESSION['userID'] = (int)$user['userID'];
    $_SESSION['userType'] = $user['userType'];
    $_SESSION['firstName'] = $user['firstName'];

    $cachedUser = [
        'userID' => (int)$user['userID'],
        'userType' => $user['userType'],
        'firstName' => $user['firstName'],
        'twoFactorEnabled' => (bool)$user['twoFactorEnabled'],
    ];

    return $cachedUser;
}

function complete_login(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['userID'] = (int)$user['userID'];
    $_SESSION['userType'] = $user['userType'];
    $_SESSION['firstName'] = $user['firstName'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function login_redirect_url(string $userType): string
{
    $routes = [
        'buyer' => 'dashboard.php',
        'seller' => 'seller/dashboard.php',
        'admin' => 'admin/dashboard.php',
    ];

    return app_path($routes[$userType] ?? 'index.php');
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect_to('login.php?message=' . urlencode('Please log in to continue'));
        exit;
    }
}

function require_role(string $role): void
{
    require_login();
    if (($_SESSION['userType'] ?? '') !== $role) {
        redirect_to('login.php?message=' . urlencode('Unauthorized access'));
        exit;
    }
}

function require_roles(array $roles): void
{
    require_login();
    if (!in_array($_SESSION['userType'] ?? '', $roles, true)) {
        redirect_to('login.php?message=' . urlencode('Unauthorized access'));
        exit;
    }
}

function get_flash_message(): ?string
{
    $message = $_SESSION['flash_message'] ?? null;
    if (isset($_SESSION['flash_message'])) {
        unset($_SESSION['flash_message']);
    }
    return $message;
}

function set_flash_message(string $message): void
{
    $_SESSION['flash_message'] = $message;
}

function get_client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function app_url(string $path = ''): string
{
    $configuredUrl = rtrim((string)(getenv('SHAREABITE_APP_URL') ?: ''), '/');
    if ($configuredUrl !== '') {
        return $configuredUrl . '/' . ltrim($path, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host . app_path($path);
}

function app_base_path(): string
{
    $configuredPath = getenv('SHAREABITE_BASE_PATH');
    if ($configuredPath !== false) {
        $configuredPath = trim((string)$configuredPath);
        if ($configuredPath === '' || $configuredPath === '/') {
            return '';
        }
        return '/' . trim($configuredPath, '/');
    }

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $scriptFile = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';
    $appRoot = realpath(dirname(__DIR__)) ?: '';

    if ($scriptName !== '' && $scriptFile !== '' && $appRoot !== '' && strpos($scriptFile, $appRoot) === 0) {
        $relativeScript = ltrim(str_replace('\\', '/', substr($scriptFile, strlen($appRoot))), '/');
        if ($relativeScript !== '' && substr($scriptName, -strlen($relativeScript)) === $relativeScript) {
            $basePath = rtrim(substr($scriptName, 0, -strlen($relativeScript)), '/');
            return $basePath === '' ? '' : $basePath;
        }
    }

    return '/Shareabite';
}

function app_path(string $path = ''): string
{
    $basePath = app_base_path();
    $path = ltrim($path, '/');

    if ($path === '') {
        return $basePath === '' ? '/' : $basePath . '/';
    }

    return ($basePath === '' ? '' : $basePath) . '/' . $path;
}

function redirect_to(string $path): void
{
    header('Location: ' . app_path($path));
}

function asset_url(string $path): string
{
    return app_path($path);
}

function listing_image_url(?string $imageUrl): string
{
    $imageUrl = trim((string)$imageUrl);
    if ($imageUrl === '') {
        return asset_url('assets/images/placeholder.svg');
    }
    if (preg_match('#^(?:https?:)?//#', $imageUrl) === 1) {
        return $imageUrl;
    }
    if (strpos($imageUrl, '/Shareabite/') === 0) {
        return app_path(substr($imageUrl, strlen('/Shareabite/')));
    }
    if ($imageUrl[0] === '/') {
        return app_path(ltrim($imageUrl, '/'));
    }

    return app_path($imageUrl);
}

function cookie_options(int $expires): array
{
    return [
        'expires' => $expires,
        'path' => '/',
        'domain' => '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function generate_totp_secret(int $length = 20): string
{
    return base32_encode(random_bytes($length));
}

function base32_encode(string $data): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    $encoded = '';

    foreach (str_split($data) as $character) {
        $bits .= str_pad(decbin(ord($character)), 8, '0', STR_PAD_LEFT);
    }

    foreach (str_split($bits, 5) as $chunk) {
        if (strlen($chunk) < 5) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        }
        $encoded .= $alphabet[bindec($chunk)];
    }

    return $encoded;
}

function base32_decode(string $secret): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret));
    $bits = '';
    $decoded = '';

    foreach (str_split($secret) as $character) {
        $position = strpos($alphabet, $character);
        if ($position === false) {
            continue;
        }
        $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
    }

    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) {
            $decoded .= chr(bindec($chunk));
        }
    }

    return $decoded;
}

function hotp(string $secret, int $counter, int $digits = 6): string
{
    $key = base32_decode($secret);
    $counterBytes = pack('N*', 0) . pack('N*', $counter);
    $hash = hash_hmac('sha1', $counterBytes, $key, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $binary = ((ord($hash[$offset]) & 0x7F) << 24)
        | ((ord($hash[$offset + 1]) & 0xFF) << 16)
        | ((ord($hash[$offset + 2]) & 0xFF) << 8)
        | (ord($hash[$offset + 3]) & 0xFF);

    return str_pad((string)($binary % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
}

function verify_totp(string $secret, string $code, int $window = 1): bool
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) {
        return false;
    }

    $counter = (int)floor(time() / 30);
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(hotp($secret, $counter + $i), $code)) {
            return true;
        }
    }

    return false;
}

function generate_email_code(): string
{
    return (string)random_int(100000, 999999);
}

function hash_email_code(string $code): string
{
    return hash('sha256', $code);
}

function email_code_debug_allowed(): bool
{
    $configured = getenv('SHAREABITE_SHOW_EMAIL_CODES');
    if ($configured !== false) {
        return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
    }

    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    return $host === '' || str_starts_with($host, 'localhost') || str_starts_with($host, '127.0.0.1');
}

function send_email_verification_code(string $email, string $firstName, string $code): bool
{
    if (!function_exists('mail')) {
        return false;
    }

    $from = getenv('SHAREABITE_MAIL_FROM') ?: 'no-reply@shareabite.co.za';
    $subject = 'Your ShareABite SA verification code';
    $name = trim($firstName) !== '' ? trim($firstName) : 'there';
    $message = "Hi {$name},\n\nYour ShareABite SA verification code is {$code}.\n\nThis code expires in 10 minutes. If you did not request it, you can ignore this email.";
    $headers = [
        'From: ShareABite SA <' . $from . '>',
        'Reply-To: ' . $from,
        'Content-Type: text/plain; charset=UTF-8',
    ];

    return mail($email, $subject, $message, implode("\r\n", $headers));
}

function create_email_verification_challenge(string $prefix, string $email, string $firstName): array
{
    $code = generate_email_code();
    $_SESSION[$prefix . '_code_hash'] = hash_email_code($code);
    $_SESSION[$prefix . '_code_expires'] = time() + 600;
    $_SESSION[$prefix . '_code_sent_at'] = time();
    unset($_SESSION[$prefix . '_debug_code']);

    $sent = send_email_verification_code($email, $firstName, $code);
    if (email_code_debug_allowed()) {
        $_SESSION[$prefix . '_debug_code'] = $code;
    }

    return ['sent' => $sent, 'code' => email_code_debug_allowed() ? $code : null];
}

function verify_email_code_challenge(string $prefix, string $code): bool
{
    $code = preg_replace('/\D/', '', $code);
    $expectedHash = $_SESSION[$prefix . '_code_hash'] ?? '';
    $expiresAt = (int)($_SESSION[$prefix . '_code_expires'] ?? 0);

    if (strlen($code) !== 6 || $expectedHash === '' || $expiresAt < time()) {
        return false;
    }

    return hash_equals($expectedHash, hash_email_code($code));
}

function clear_email_code_challenge(string $prefix): void
{
    unset(
        $_SESSION[$prefix . '_code_hash'],
        $_SESSION[$prefix . '_code_expires'],
        $_SESSION[$prefix . '_code_sent_at'],
        $_SESSION[$prefix . '_debug_code']
    );
}

function totp_setup_uri(string $email, string $secret): string
{
    $issuer = 'ShareABite SA';
    $label = rawurlencode($issuer . ':' . $email);
    $query = http_build_query([
        'secret' => $secret,
        'issuer' => $issuer,
        'algorithm' => 'SHA1',
        'digits' => 6,
        'period' => 30,
    ]);

    return 'otpauth://totp/' . $label . '?' . $query;
}

function is_valid_sa_phone(string $phone): bool
{
    $normalized = preg_replace('/[^0-9+]/', '', $phone);
    return preg_match('/^(?:0\d{9}|\+27\d{9})$/', $normalized) === 1;
}

function format_sa_phone(string $phone): string
{
    $normalized = preg_replace('/[^0-9+]/', '', $phone);
    if (strpos($normalized, '+27') === 0) {
        return '+27 ' . substr($normalized, 3, 2) . ' ' . substr($normalized, 5, 3) . ' ' . substr($normalized, 8, 4);
    }
    if (strlen($normalized) === 10 && $normalized[0] === '0') {
        return substr($normalized, 0, 3) . ' ' . substr($normalized, 3, 3) . ' ' . substr($normalized, 6, 4);
    }
    return $phone;
}
