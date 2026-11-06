<?php
/**
 * Montadak web installer.
 *
 * Open  https://your-domain/install.php  and follow the 4 steps:
 *   1) server requirements  2) database  3) site + administrator  4) done.
 * When it finishes it locks itself (config/installed.lock) and tries to delete this file.
 *
 * No JavaScript, no external requests. All input is validated, every query is prepared,
 * the administrator password is hashed with password_hash().
 */

declare(strict_types=0);

define('MTD_ROOT', __DIR__);
define('MTD_MIN_PHP', '7.4.0');
const MTD_LOCK = MTD_ROOT . '/config/installed.lock';
const MTD_CONFIG = MTD_ROOT . '/config/config.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');
mb_internal_encoding('UTF-8');

// ---------------------------------------------------------------------------------- hardening
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
header_remove('X-Powered-By');

if (is_file(MTD_LOCK)) {
    http_response_code(403);
    mtd_page('المنتدى مثبّت بالفعل', 0, '<div class="box err"><h2>التثبيت مقفل</h2><p>هذا المنتدى مثبّت بالفعل. لأسباب أمنية احذف الملف <code>install.php</code> من الخادم.</p>'
        . '<p>لإعادة التثبيت من الصفر: احذف الملف <code>config/installed.lock</code> و<code>config/config.php</code> واستخدم قاعدة بيانات فارغة.</p></div>');
    exit;
}

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name('montadak_install');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict']);
session_start();
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// ---------------------------------------------------------------------------------- helpers
function h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mtd_csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">';
}

function mtd_post($key, $max = 255)
{
    $v = isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : '';
    return mb_substr($v, 0, $max);
}

function mtd_page($title, $step, $body)
{
    $steps = ['المتطلبات', 'قاعدة البيانات', 'الموقع والمدير', 'اكتمل'];
    echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<meta name="robots" content="noindex,nofollow"><title>' . h($title) . ' — تثبيت المنتدى</title><style>';
    echo ':root{--a:#0f766e;--b:#115e59;--line:#cfe3dc;--bg:#eef4f2;--ink:#12312d}*{box-sizing:border-box}';
    echo 'body{margin:0;background:var(--bg);color:var(--ink);font-family:Tahoma,"Segoe UI",Arial,sans-serif;line-height:1.7}';
    echo '.top{background:linear-gradient(135deg,var(--a),var(--b) 60%,#164e63);color:#fff;padding:1.4rem 1rem;text-align:center}';
    echo '.top h1{margin:0;font-size:1.5rem}.top p{margin:.2rem 0 0;opacity:.9;font-size:.92rem}';
    echo '.wrap{max-width:720px;margin:1.2rem auto;padding:0 1rem}';
    echo '.steps{display:flex;gap:.4rem;margin:0 0 1rem;padding:0;list-style:none}.steps li{flex:1;text-align:center;padding:.45rem .2rem;border-radius:10px;background:#dfeae6;font-size:.82rem;color:#4b615b}';
    echo '.steps li.on{background:var(--a);color:#fff;font-weight:700}.steps li.ok{background:#bfe3d9;color:#0b4f4a}';
    echo '.box{background:#fff;border:1px solid var(--line);border-radius:16px;padding:1.3rem;box-shadow:0 4px 16px rgba(15,118,110,.08);margin-bottom:1rem}';
    echo '.box h2{margin:0 0 .8rem;font-size:1.15rem;color:#0b4f4a}.err{border-color:#f2a6a6;background:#fff5f5}.ok-box{border-color:#9fd8bf;background:#f1faf5}';
    echo 'label{display:block;margin:.9rem 0 .3rem;font-weight:700;font-size:.92rem}.hint{margin:.1rem 0 .4rem;color:#5b6f6a;font-size:.82rem}';
    echo 'input[type=text],input[type=password],input[type=email],input[type=number],select{width:100%;padding:.65rem .8rem;border:1px solid #b9d6cd;border-radius:12px;font:inherit;background:#fbfefd}';
    echo 'input:focus,select:focus{outline:0;border-color:var(--a);box-shadow:0 0 0 3px rgba(15,118,110,.18)}';
    echo '.row{display:grid;grid-template-columns:1fr 1fr;gap:.8rem}@media(max-width:560px){.row{grid-template-columns:1fr}}';
    echo '.btn{display:inline-block;margin-top:1.1rem;padding:.7rem 1.8rem;border:0;border-radius:12px;background:linear-gradient(135deg,var(--a),var(--b));color:#fff;font:inherit;font-weight:700;cursor:pointer;text-decoration:none}';
    echo '.btn.gray{background:#dfeae6;color:var(--ink)}table{width:100%;border-collapse:collapse}td,th{padding:.5rem .6rem;border-bottom:1px solid #e3efeb;text-align:start;font-size:.92rem}';
    echo '.pass{color:#0a7a3d;font-weight:700}.fail{color:#b3261e;font-weight:700}.warn{color:#9a6b00;font-weight:700}code{background:#eef4f2;padding:.05rem .4rem;border-radius:6px;direction:ltr;unicode-bidi:embed}';
    echo '.errlist{margin:.2rem 0;padding-inline-start:1.2rem;color:#9b1c1c}';
    echo '</style></head><body><div class="top"><h1>تثبيت المنتدى</h1><p>معالج تثبيت آمن في أربع خطوات</p></div><div class="wrap">';
    if ($step >= 1) {
        echo '<ol class="steps">';
        foreach ($steps as $i => $s) {
            $n = $i + 1;
            echo '<li class="' . ($n === $step ? 'on' : ($n < $step ? 'ok' : '')) . '">' . $n . '. ' . h($s) . '</li>';
        }
        echo '</ol>';
    }
    echo $body . '</div></body></html>';
}

function mtd_errors(array $errors)
{
    if (!$errors) {
        return '';
    }
    $o = '<div class="box err"><ul class="errlist">';
    foreach ($errors as $e) {
        $o .= '<li>' . h($e) . '</li>';
    }
    return $o . '</ul></div>';
}

function mtd_requirements()
{
    $dirs = ['config', 'assets/images/avatars', 'assets/images/forums', 'assets/images/backgrounds', 'assets/uploads'];
    $rows = [];   // [label, status(pass|fail|warn), detail, required]
    $rows[] = ['إصدار PHP ' . MTD_MIN_PHP . ' أو أحدث', version_compare(PHP_VERSION, MTD_MIN_PHP, '>=') ? 'pass' : 'fail', PHP_VERSION, true];
    foreach (['mysqli' => 'mysqli (الاتصال بقاعدة البيانات)', 'mbstring' => 'mbstring (النصوص العربية)', 'json' => 'json', 'session' => 'session', 'hash' => 'hash'] as $ext => $label) {
        $rows[] = ['امتداد ' . $label, extension_loaded($ext) ? 'pass' : 'fail', extension_loaded($ext) ? 'متوفر' : 'غير متوفر', true];
    }
    $rows[] = ['الدالة random_bytes / password_hash', (function_exists('random_bytes') && function_exists('password_hash')) ? 'pass' : 'fail', '', true];
    $rows[] = ['امتداد gd (رفع الصور وتصغيرها)', extension_loaded('gd') ? 'pass' : 'warn', extension_loaded('gd') ? 'متوفر' : 'مفعّل ضروري لرفع الصور — يمكنك المتابعة وتفعيله لاحقًا', false];
    $rows[] = ['امتداد fileinfo (فحص نوع الملفات)', extension_loaded('fileinfo') ? 'pass' : 'warn', extension_loaded('fileinfo') ? 'متوفر' : 'موصى به لحماية الرفع', false];
    foreach ($dirs as $d) {
        $p = MTD_ROOT . '/' . $d;
        if (!is_dir($p)) {
            @mkdir($p, 0755, true);
        }
        $ok = is_dir($p) && is_writable($p);
        $rows[] = ['صلاحية الكتابة على <code>' . h($d) . '/</code>', $ok ? 'pass' : 'fail', $ok ? 'قابل للكتابة' : 'غير قابل للكتابة — غيّر الصلاحيات إلى 755 أو 775', true];
    }
    $rows[] = ['وجود ملف <code>install/schema.sql</code>', is_readable(MTD_ROOT . '/install/schema.sql') ? 'pass' : 'fail', '', true];
    $rows[] = ['اتصال آمن HTTPS', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'pass' : 'warn', 'يوصى بتفعيل HTTPS قبل إدخال كلمات السر', false];
    $all = true;
    foreach ($rows as $r) {
        if ($r[3] && $r[1] === 'fail') {
            $all = false;
        }
    }
    return [$rows, $all];
}

/** Connects with the given settings. Returns [mysqli|null, friendly error]. */
function mtd_connect(array $c)
{
    mysqli_report(MYSQLI_REPORT_OFF);
    $m = mysqli_init();
    $m->options(MYSQLI_OPT_CONNECT_TIMEOUT, 8);
    if (!@$m->real_connect($c['host'], $c['user'], $c['pass'], $c['name'], (int) $c['port'])) {
        $no = (int) $m->connect_errno;
        $map = [
            1045 => 'اسم المستخدم أو كلمة السر غير صحيحة.',
            1049 => 'قاعدة البيانات غير موجودة. أنشئها أولًا من لوحة الاستضافة (MySQL Databases).',
            1044 => 'هذا المستخدم لا يملك صلاحية على قاعدة البيانات المحددة.',
            2002 => 'تعذّر الوصول إلى خادم قاعدة البيانات. تحقق من العنوان (غالبًا localhost).',
            2003 => 'تعذّر الوصول إلى خادم قاعدة البيانات. تحقق من العنوان والمنفذ.',
            2006 => 'انقطع الاتصال بخادم قاعدة البيانات.',
        ];
        return [null, (isset($map[$no]) ? $map[$no] : 'تعذّر الاتصال بقاعدة البيانات.') . ' (رمز الخطأ ' . $no . ')'];
    }
    $m->set_charset('utf8mb4');
    return [$m, ''];
}

function mtd_db_checks($m)
{
    $errors = [];
    $info = (string) $m->server_info;
    $isMaria = stripos($info, 'mariadb') !== false;
    if (preg_match('/(\d+)\.(\d+)\.(\d+)/', $info, $v)) {
        $ver = $v[1] . '.' . $v[2] . '.' . $v[3];
        if ($isMaria && preg_match('/(\d+)\.(\d+)\.(\d+)-MariaDB/i', $info, $mv)) {
            $ver = $mv[1] . '.' . $mv[2] . '.' . $mv[3];
        }
        $need = $isMaria ? '10.1.0' : '5.6.0';
        if (version_compare($ver, $need, '<')) {
            $errors[] = 'إصدار قاعدة البيانات (' . $info . ') قديم. المطلوب ' . ($isMaria ? 'MariaDB 10.1' : 'MySQL 5.6') . ' أو أحدث.';
        }
    }
    $res = $m->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
    $n = $res ? (int) $res->fetch_row()[0] : 0;
    if ($n > 0) {
        $errors[] = 'قاعدة البيانات ليست فارغة (' . $n . ' جدول). استخدم قاعدة بيانات جديدة فارغة حتى لا تُتلف بياناتك.';
    }
    if (!$errors) {
        $ok = $m->query('CREATE TABLE `_mtd_probe` (id INT NOT NULL) ENGINE=InnoDB');
        if (!$ok) {
            $errors[] = 'لا يملك المستخدم صلاحية إنشاء الجداول (CREATE) أو محرك InnoDB غير متاح.';
        } else {
            $m->query('DROP TABLE `_mtd_probe`');
        }
    }
    return $errors;
}

function mtd_drop_all($m)
{
    try {
        $m->query('SET FOREIGN_KEY_CHECKS = 0');
        $res = $m->query('SHOW TABLES');
        $t = [];
        while ($res && ($r = $res->fetch_row())) {
            $t[] = $r[0];
        }
        foreach ($t as $name) {
            $m->query('DROP TABLE IF EXISTS `' . str_replace('`', '``', $name) . '`');
        }
        $m->query('SET FOREIGN_KEY_CHECKS = 1');
    } catch (Throwable $e) {
    }
}

function mtd_config_text(array $db, $siteName, $tz)
{
    $q = function ($v) {
        return var_export((string) $v, true);
    };
    return "<?php\n/**\n * Montadak configuration — generated by install.php on " . gmdate('Y-m-d H:i') . " UTC.\n * Keep this file private. Never share or publish it.\n */\n\n"
        . "// --- Database ---\n"
        . "define('DB_HOST', " . $q($db['host']) . ");\n"
        . "define('DB_PORT', " . (int) $db['port'] . ");\n"
        . "define('DB_NAME', " . $q($db['name']) . ");\n"
        . "define('DB_USER', " . $q($db['user']) . ");\n"
        . "define('DB_PASS', " . $q($db['pass']) . ");\n\n"
        . "// --- Site ---\n"
        . "define('SITE_NAME', " . $q($siteName) . ");\n"
        . "define('DEFAULT_TIMEZONE', " . $q($tz) . ");\n\n"
        . "// Show detailed errors on screen. Keep false on a live site.\n"
        . "define('APP_DEBUG', false);\n\n"
        . "// Leave null to auto-detect the folder the forum is installed in.\n"
        . "define('BASE_URL', null);\n";
}

// ---------------------------------------------------------------------------------- routing
$errors = [];
$step = isset($_GET['step']) ? (int) $_GET['step'] : 1;
if ($step < 1 || $step > 3) {
    $step = 1;
}
list($reqRows, $reqOk) = mtd_requirements();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sent = isset($_POST['csrf']) && is_string($_POST['csrf']) ? $_POST['csrf'] : '';
    if (!hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(403);
        mtd_page('خطأ', 0, '<div class="box err"><h2>انتهت الجلسة</h2><p>أعد تحميل الصفحة وحاول مرة أخرى.</p><a class="btn" href="install.php">العودة</a></div>');
        exit;
    }
    $action = mtd_post('action', 20);

    // ---- step 2: database
    if ($action === 'db') {
        $step = 2;
        $_SESSION['tries'] = (isset($_SESSION['tries']) ? (int) $_SESSION['tries'] : 0) + 1;
        if ($_SESSION['tries'] > 30) {
            $errors[] = 'محاولات كثيرة. أغلق المتصفح وأعد المحاولة لاحقًا.';
        }
        $db = [
            'host' => trim(mtd_post('db_host', 255)),
            'port' => (int) mtd_post('db_port', 5),
            'name' => trim(mtd_post('db_name', 64)),
            'user' => trim(mtd_post('db_user', 80)),
            'pass' => mtd_post('db_pass', 128),
        ];
        if ($db['port'] < 1 || $db['port'] > 65535) {
            $db['port'] = 3306;
        }
        if (!preg_match('/^[A-Za-z0-9._:\-\[\]]{1,255}$/', $db['host'])) {
            $errors[] = 'عنوان الخادم غير صالح (مثال: localhost).';
        }
        if (!preg_match('/^[A-Za-z0-9_$\-]{1,64}$/', $db['name'])) {
            $errors[] = 'اسم قاعدة البيانات يحتوي حروفًا غير مسموحة.';
        }
        if ($db['user'] === '' || preg_match('/[\x00-\x1f]/', $db['user'] . $db['pass'])) {
            $errors[] = 'اسم المستخدم مطلوب ولا يجوز أن يحتوي رموزًا تحكمية.';
        }
        if (!$errors) {
            list($m, $err) = mtd_connect($db);
            if ($m === null) {
                $errors[] = $err;
            } else {
                foreach (mtd_db_checks($m) as $e) {
                    $errors[] = $e;
                }
                $m->close();
            }
        }
        if (!$errors) {
            $_SESSION['db'] = $db;
            session_regenerate_id(true);
            header('Location: install.php?step=3');
            exit;
        }
    }

    // ---- step 3: site + administrator + install
    if ($action === 'install') {
        $step = 3;
        if (empty($_SESSION['db'])) {
            header('Location: install.php?step=2');
            exit;
        }
        $db = $_SESSION['db'];
        $siteName = trim(mtd_post('site_name', 60));
        $tz = mtd_post('timezone', 64);
        $user = trim(mtd_post('admin_user', 30));
        $email = strtolower(trim(mtd_post('admin_email', 190)));
        $pass = mtd_post('admin_pass', 200);
        $pass2 = mtd_post('admin_pass2', 200);
        $gender = mtd_post('gender', 6);

        if (mb_strlen($siteName) < 2) {
            $errors[] = 'اكتب اسم المنتدى (حرفان على الأقل).';
        }
        if (!in_array($tz, DateTimeZone::listIdentifiers(), true)) {
            $errors[] = 'المنطقة الزمنية غير صالحة.';
        }
        if (!preg_match('/^(?=.{3,30}$)[\p{L}\p{N}_.\-]+(?: [\p{L}\p{N}_.\-]+)*$/u', $user)) {
            $errors[] = 'اسم المدير: من 3 إلى 30 حرفًا (حروف وأرقام و _ . - فقط).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $errors[] = 'البريد الإلكتروني غير صالح.';
        }
        if (strlen($pass) < 10) {
            $errors[] = 'كلمة السر قصيرة: 10 أحرف على الأقل.';
        } elseif (strlen($pass) > 72) {
            $errors[] = 'كلمة السر طويلة جدًا (72 بايت كحد أقصى).';
        } elseif (!preg_match('/\p{L}/u', $pass) || !preg_match('/\d/', $pass) || !preg_match('/[^\p{L}\d\s]/u', $pass)) {
            $errors[] = 'كلمة السر يجب أن تحتوي على حروف وأرقام ورموز (مثل ! @ # $ %).';
        } elseif ($pass !== $pass2) {
            $errors[] = 'تأكيد كلمة السر لا يطابقها.';
        }
        if (!in_array($gender, ['male', 'female'], true)) {
            $errors[] = 'اختر الجنس. لا يمكن تغييره لاحقًا.';
        }

        if (!$errors) {
            list($m, $err) = mtd_connect($db);
            if ($m === null) {
                $errors[] = $err;
            } else {
                $dbErr = mtd_db_checks($m);
                if ($dbErr) {
                    $errors = $dbErr;
                }
            }
        }

        if (!$errors) {
            $installed = false;
            try {
                mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                $sql = (string) file_get_contents(MTD_ROOT . '/install/schema.sql');
                if ($sql === '') {
                    throw new RuntimeException('schema.sql is empty');
                }
                $m->multi_query($sql);
                do {
                    $res = $m->store_result();
                    if ($res) {
                        $res->free();
                    }
                } while ($m->more_results() && $m->next_result());

                $m->query("SET time_zone = '+00:00'");

                // The application adds some columns/tables itself (ensure_* functions). Run them now,
                // BEFORE creating the administrator, so the users table is complete.
                $warn = '';
                foreach ([['DB_HOST', $db['host']], ['DB_PORT', $db['port']], ['DB_NAME', $db['name']], ['DB_USER', $db['user']], ['DB_PASS', $db['pass']], ['SITE_NAME', $siteName], ['DEFAULT_TIMEZONE', $tz],
                          ['APP_DEBUG', false], ['BASE_URL', null], ['APP_ROOT', MTD_ROOT], ['BASE', ''], ['THREADS_PER_PAGE', 20], ['POSTS_PER_PAGE', 15], ['ONLINE_WINDOW_MINUTES', 5], ['MAX_POST_LENGTH', 20000]] as $c) {
                    if (!defined($c[0])) {
                        define($c[0], $c[1]);
                    }
                }
                date_default_timezone_set('UTC');
                foreach (['db', 'functions', 'icons', 'layout', 'notify', 'extras'] as $f) {
                    require_once MTD_ROOT . '/includes/' . $f . '.php';
                }
                foreach (['ensure_v35_schema', 'ensure_poll_schema', 'ensure_v41_schema', 'ensure_v47_schema', 'ensure_v5_schema', 'ensure_v9_schema', 'ensure_v11_schema', 'ensure_v12_schema', 'ensure_v14_schema'] as $fn) {
                    if (function_exists($fn)) {
                        try {
                            $fn();
                        } catch (Throwable $e2) {
                            $warn = 'ملاحظة: سيُكمل المنتدى تجهيز بعض الجداول تلقائيًا عند أول زيارة.';
                            error_log('[montadak-install] ' . $fn . ': ' . $e2->getMessage());
                        }
                    }
                }
                // Safety net: the column the registration/admin insert needs.
                $chk = $m->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_approved'");
                if ($chk && (int) $chk->fetch_row()[0] === 0) {
                    $m->query('ALTER TABLE users ADD COLUMN is_approved TINYINT(1) NOT NULL DEFAULT 1');
                }

                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $st = $m->prepare("INSERT INTO users (username, email, password_hash, role, timezone, is_approved, gender) VALUES (?, ?, ?, 'admin', ?, 1, ?)");
                $st->bind_param('sssss', $user, $email, $hash, $tz, $gender);
                $st->execute();
                $adminId = (int) $m->insert_id;
                $st->close();
                $st = $m->prepare('INSERT INTO site_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
                $k = 'site_name';
                $st->bind_param('ss', $k, $siteName);
                $st->execute();
                $st->close();

                $cfg = mtd_config_text($db, $siteName, $tz);
                if (@file_put_contents(MTD_CONFIG, $cfg, LOCK_EX) === false) {
                    throw new RuntimeException('cannot write config');
                }
                @chmod(MTD_CONFIG, 0640);
                $installed = true;
            } catch (Throwable $e) {
                mtd_drop_all($m);
                @unlink(MTD_CONFIG);
                error_log('[montadak-install] ' . get_class($e) . ': ' . $e->getMessage());
                $detail = ($e instanceof mysqli_sql_exception ? '#' . $e->getCode() . ' ' : '') . mb_substr(trim(preg_replace('/\s+/', ' ', $e->getMessage())), 0, 220);
                $errors[] = 'فشل التثبيت وتمت إعادة قاعدة البيانات إلى حالتها الفارغة. السبب: '
                    . (strpos($e->getMessage(), 'cannot write config') !== false ? 'تعذّرت كتابة config/config.php (تحقق من الصلاحيات).' : 'خطأ أثناء التثبيت (' . get_class($e) . ').');
                $errors[] = 'التفاصيل التقنية: ' . $detail;
            }
            $m->close();

            if ($installed) {
                // Lock: the installation is complete.
                @file_put_contents(MTD_LOCK, 'installed ' . gmdate('c') . "\n", LOCK_EX);
                @chmod(MTD_LOCK, 0640);
                $_SESSION = [];
                session_destroy();
                $removed = @unlink(__FILE__);

                $self = rtrim(str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/install.php')), '/');
                $body = '<div class="box ok-box"><h2>🎉 تم تثبيت المنتدى بنجاح</h2>'
                    . '<p>حساب المدير: <b>' . h($user) . '</b> — رقم العضوية 1 (المدير الأصلي).</p>'
                    . ($warn !== '' ? '<p class="warn">' . h($warn) . '</p>' : '')
                    . '<p><a class="btn" href="' . h($self . '/login.php') . '">تسجيل الدخول</a> <a class="btn gray" href="' . h($self . '/index.php') . '">الصفحة الرئيسية</a></p></div>'
                    . '<div class="box ' . ($removed ? 'ok-box' : 'err') . '"><h2>الأمان</h2><ul>'
                    . '<li>' . ($removed ? 'تم حذف <code>install.php</code> تلقائيًا.' : '<b class="fail">احذف الملف <code>install.php</code> الآن يدويًا من الخادم.</b>') . '</li>'
                    . '<li>تم قفل التثبيت بالملف <code>config/installed.lock</code>.</li>'
                    . '<li>ملف الإعدادات <code>config/config.php</code> محمي من الوصول المباشر. لا تشاركه مع أحد.</li>'
                    . '<li>من لوحة الإدارة اضبط اسم المنتدى وشعاره والأقسام والمنتديات.</li></ul></div>';
                mtd_page('اكتمل التثبيت', 4, $body);
                exit;
            }
        }
    }
}

// ---------------------------------------------------------------------------------- views
if ($step === 1) {
    $b = '<div class="box"><h2>فحص متطلبات الخادم</h2><table><tbody>';
    foreach ($reqRows as $r) {
        $cls = $r[1];
        $label = $cls === 'pass' ? '✔ متوفر' : ($cls === 'warn' ? '⚠ تنبيه' : '✘ ناقص');
        $b .= '<tr><td>' . $r[0] . '</td><td class="' . $cls . '">' . $label . '</td><td>' . ($r[2] !== '' && $cls !== 'pass' ? h($r[2]) : ($cls === 'pass' ? h($r[2]) : '')) . '</td></tr>';
    }
    $b .= '</tbody></table>';
    if ($reqOk) {
        $b .= '<a class="btn" href="install.php?step=2">التالي: قاعدة البيانات</a>';
    } else {
        $b .= '<p class="fail">أصلح البنود الناقصة ثم أعد تحميل الصفحة.</p><a class="btn gray" href="install.php">إعادة الفحص</a>';
    }
    mtd_page('المتطلبات', 1, $b . '</div>');
    exit;
}

if ($step === 2) {
    if (!$reqOk) {
        header('Location: install.php');
        exit;
    }
    $d = isset($_SESSION['db']) ? $_SESSION['db'] : ['host' => 'localhost', 'port' => 3306, 'name' => '', 'user' => ''];
    if (isset($db) && is_array($db)) {
        $d = $db;
    }
    $b = mtd_errors($errors) . '<form method="post" action="install.php?step=2" autocomplete="off">' . mtd_csrf_field() . '<input type="hidden" name="action" value="db">'
        . '<div class="box"><h2>بيانات قاعدة البيانات</h2><p class="hint">أنشئ قاعدة بيانات فارغة ومستخدمًا لها من لوحة الاستضافة (MySQL Databases)، ثم أدخل بياناتها هنا.</p>'
        . '<div class="row"><div><label for="h">عنوان الخادم</label><input type="text" id="h" name="db_host" value="' . h($d['host']) . '" required maxlength="255" dir="ltr"></div>'
        . '<div><label for="p">المنفذ</label><input type="number" id="p" name="db_port" value="' . (int) $d['port'] . '" min="1" max="65535" dir="ltr"></div></div>'
        . '<label for="n">اسم قاعدة البيانات</label><input type="text" id="n" name="db_name" value="' . h($d['name']) . '" required maxlength="64" dir="ltr">'
        . '<div class="row"><div><label for="u">اسم المستخدم</label><input type="text" id="u" name="db_user" value="' . h($d['user']) . '" required maxlength="80" dir="ltr"></div>'
        . '<div><label for="w">كلمة السر</label><input type="password" id="w" name="db_pass" maxlength="128" dir="ltr" autocomplete="new-password"></div></div>'
        . '<button class="btn" type="submit">اختبار الاتصال والمتابعة</button> <a class="btn gray" href="install.php">السابق</a></div></form>';
    mtd_page('قاعدة البيانات', 2, $b);
    exit;
}

// step 3
if (empty($_SESSION['db'])) {
    header('Location: install.php?step=2');
    exit;
}
$siteNameV = isset($siteName) ? $siteName : 'منتداك';
$tzV = isset($tz) && $tz !== '' ? $tz : 'Africa/Casablanca';
$userV = isset($user) ? $user : '';
$emailV = isset($email) ? $email : '';
$genderV = isset($gender) ? $gender : '';
$opts = '';
foreach (DateTimeZone::listIdentifiers() as $z) {
    $opts .= '<option value="' . h($z) . '"' . ($z === $tzV ? ' selected' : '') . '>' . h($z) . '</option>';
}
$b = mtd_errors($errors) . '<form method="post" action="install.php?step=3" autocomplete="off">' . mtd_csrf_field() . '<input type="hidden" name="action" value="install">'
    . '<div class="box"><h2>بيانات الموقع</h2>'
    . '<label for="s">اسم المنتدى</label><input type="text" id="s" name="site_name" value="' . h($siteNameV) . '" required maxlength="60">'
    . '<label for="t">المنطقة الزمنية</label><select id="t" name="timezone">' . $opts . '</select></div>'
    . '<div class="box"><h2>حساب المدير</h2><p class="hint">هذا الحساب يصبح المدير الأصلي (رقم العضوية 1) ولا يمكن لأحد أخذ مكانه.</p>'
    . '<label for="a">اسم المستخدم</label><input type="text" id="a" name="admin_user" value="' . h($userV) . '" required maxlength="30">'
    . '<label for="e">البريد الإلكتروني</label><input type="email" id="e" name="admin_email" value="' . h($emailV) . '" required maxlength="190" dir="ltr">'
    . '<div class="row"><div><label for="x">كلمة السر</label><input type="password" id="x" name="admin_pass" required maxlength="200" dir="ltr" autocomplete="new-password"></div>'
    . '<div><label for="y">تأكيد كلمة السر</label><input type="password" id="y" name="admin_pass2" required maxlength="200" dir="ltr" autocomplete="new-password"></div></div>'
    . '<p class="hint">10 أحرف على الأقل، وتحتوي حروفًا وأرقامًا ورموزًا.</p>'
    . '<label for="g">الجنس <span class="hint">(لا يمكن تغييره بعد التثبيت)</span></label><select id="g" name="gender" required><option value="">— اختر —</option>'
    . '<option value="male"' . ($genderV === 'male' ? ' selected' : '') . '>ذكر</option><option value="female"' . ($genderV === 'female' ? ' selected' : '') . '>أنثى</option></select>'
    . '<button class="btn" type="submit">تثبيت المنتدى</button> <a class="btn gray" href="install.php?step=2">السابق</a></div></form>';
mtd_page('الموقع والمدير', 3, $b);
