<?php
/**
 * Loaded first by every page: config, database, session, helpers.
 * Nothing in here prints output, so pages can still redirect afterwards.
 */

define('APP_ROOT', dirname(__DIR__));
if (!is_file(APP_ROOT . '/config/config.php')) {
    // Not installed yet: send the visitor to the installer.
    $mtdDoc = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $mtdApp = realpath(APP_ROOT);
    $mtdBase = ($mtdDoc && $mtdApp && strpos($mtdApp, $mtdDoc) === 0) ? rtrim(str_replace('\\', '/', substr($mtdApp, strlen($mtdDoc))), '/') : '';
    if (is_file(APP_ROOT . '/install.php')) {
        header('Location: ' . $mtdBase . '/install.php');
    } else {
        http_response_code(503);
        echo 'The forum is not installed (config/config.php is missing) and install.php was removed.';
    }
    exit;
}
require_once APP_ROOT . '/config/config.php';

define('THREADS_PER_PAGE', 20);
define('POSTS_PER_PAGE', 15);
define('ONLINE_WINDOW_MINUTES', 5);
define('MAX_POST_LENGTH', 20000);

error_reporting(E_ALL);
if (APP_DEBUG) {
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

mb_internal_encoding('UTF-8');
date_default_timezone_set('UTC');

set_exception_handler(function ($e) {
    error_log('[montadak] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>خطأ</title></head>';
    echo '<body style="font-family:Tahoma,Arial,sans-serif;max-width:40rem;margin:4rem auto;padding:0 1rem">';
    echo '<h1>حدث خطأ غير متوقع</h1><p>حاول مرة أخرى بعد قليل. إذا استمرت المشكلة فتحقق من إعدادات قاعدة البيانات.</p>';
    if ($e instanceof mysqli_sql_exception) {
        $c = (int) $e->getCode();
        if (in_array($c, [1054, 1146], true)) {
            echo '<p><strong>قاعدة البيانات ينقصها جدول أو عمود:</strong> شغّل ملفات install/upgrade_v*.sql التي لم تشغّلها بعد (بالترتيب)، أو استورد install/schema.sql في قاعدة فارغة.</p>';
        } elseif (in_array($c, [1044, 1045, 1049, 2002, 2003, 2006], true)) {
            echo '<p><strong>تعذّر الاتصال بقاعدة البيانات:</strong> راجع الاسم والمستخدم وكلمة السر والخادم في config/config.php.</p>';
        }
    }
    // Short diagnostic code to quote when asking for help (no passwords, no query text).
    echo '<p dir="ltr" style="color:#666;font-size:.8rem">' . htmlspecialchars(get_class($e) . ($e instanceof mysqli_sql_exception ? ' #' . $e->getCode() : '') . ' @ ' . basename($e->getFile()) . ':' . $e->getLine(), ENT_QUOTES, 'UTF-8') . '</p>';
    if (APP_DEBUG) {
        echo '<pre dir="ltr" style="white-space:pre-wrap">' . htmlspecialchars($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine(), ENT_QUOTES, 'UTF-8') . '</pre>';
    }
    echo '</body></html>';
});

/** Folder the forum lives in, as a URL path ('' when installed at the domain root). */
function detect_base_url()
{
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $appRoot = realpath(APP_ROOT);
    if ($docRoot && $appRoot && strpos($appRoot, $docRoot) === 0) {
        return rtrim(str_replace('\\', '/', substr($appRoot, strlen($docRoot))), '/');
    }
    return '';
}
define('BASE', rtrim(BASE_URL !== null ? (string) BASE_URL : detect_base_url(), '/'));

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/notify.php';
require_once __DIR__ . '/extras.php';

if (!headers_sent()) {
    // Defense-in-depth headers. Avoid a restrictive CSP here because this legacy
    // template includes inline scripts/styles; add nonces before enabling one.
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header("Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; object-src 'none'; form-action 'self'");
    header('Cross-Origin-Resource-Policy: same-origin');
    header_remove('X-Powered-By');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// --- Session hardening ---
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name('montadak_sid');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// --- Request hardening ---
// 1) Same-origin check for every state-changing request (defence in depth on top of the CSRF token).
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $src = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
    if ($src !== '' && $src !== 'null') {
        $srcHost = strtolower((string) parse_url($src, PHP_URL_HOST));
        $okHost = false;
        foreach (['HTTP_HOST', 'HTTP_X_FORWARDED_HOST'] as $hk) {
            if (!empty($_SERVER[$hk])) {
                $h = strtolower((string) parse_url('//' . trim(explode(',', $_SERVER[$hk])[0]), PHP_URL_HOST));
                if ($h !== '' && $h === $srcHost) {
                    $okHost = true;
                }
            }
        }
        if ($srcHost !== '' && !$okHost) {
            http_response_code(403);
            exit('Cross-site request blocked.');
        }
    }
    // 2) Simple per-session flood limit on POST requests (240 per minute).
    $win = (int) floor(time() / 60);
    if (!isset($_SESSION['rl']) || $_SESSION['rl'][0] !== $win) {
        $_SESSION['rl'] = [$win, 0];
    }
    if (++$_SESSION['rl'][1] > 240) {
        http_response_code(429);
        header('Retry-After: 30');
        exit('Too many requests.');
    }
}
// 3) A stolen session cookie is useless from another browser: bind the session to the user agent.
$fp = hash('sha256', isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '');
if (isset($_SESSION['fp']) && !hash_equals($_SESSION['fp'], $fp)) {
    $_SESSION = [];
    session_regenerate_id(true);
}
$_SESSION['fp'] = $fp;
// 4) Pages shown to signed-in members are never stored by shared caches.
if (!empty($_SESSION['uid']) && !headers_sent()) {
    header('Cache-Control: private, no-cache, must-revalidate');
}
ensure_v35_schema();
ensure_poll_schema();
ensure_v41_schema();
ensure_v47_schema();
ensure_v5_schema();
ensure_v9_schema();
ensure_v11_schema();
ensure_v12_schema();
ensure_v14_schema();
track_visitor();
