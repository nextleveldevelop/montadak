<?php
require __DIR__ . '/includes/bootstrap.php';

$next = safe_next(isset($_GET['next']) ? $_GET['next'] : (isset($_POST['next']) ? $_POST['next'] : ''));

if (current_user() !== null) {
    redirect($next);
}

const LOGIN_MAX_FAILURES = 6;   // per network and per account name...
const LOGIN_WINDOW_MINUTES = 15; // ...within this window

$error = null;
$login = '';

if (is_post()) {
    csrf_verify();
    $login = post_str('login');
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $ip = throttle_key('i', client_ip());
    $acct = throttle_key('u', $login);

    $failures = 0;
    foreach ([$ip, $acct] as $k) {   // limit per network AND per account name
        $failures = max($failures, (int) db_val(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > (NOW() - INTERVAL ' . LOGIN_WINDOW_MINUTES . ' MINUTE)',
            's',
            [$k]
        ));
    }

    if ($failures >= LOGIN_MAX_FAILURES) {
        $error = 'محاولات دخول فاشلة كثيرة. انتظر ' . LOGIN_WINDOW_MINUTES . ' دقيقة ثم حاول مرة أخرى.';
    } elseif ($login === '' || $password === '') {
        $error = 'أدخل اسم العضوية وكلمة السر.';
    } else {
        $row = db_row(
            'SELECT id, password_hash, is_banned FROM users WHERE username = ? OR email = ? LIMIT 1',
            'ss',
            [$login, strtolower($login)]
        );
        if ($row && password_verify($password, $row['password_hash'])) {
            if ((int) $row['is_banned'] === 1) {
                $error = 'هذا الحساب محظور.';
            } elseif ((int) db_val('SELECT is_approved FROM users WHERE id = ?', 'i', [$row['id']]) === 0) {
                $error = 'حسابك بانتظار موافقة المدير.';
            } else {
                if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
                    db_exec('UPDATE users SET password_hash = ? WHERE id = ?', 'si', [password_hash($password, PASSWORD_DEFAULT), $row['id']]);
                }
                db_exec('DELETE FROM login_attempts WHERE ip IN (?, ?)', 'ss', [$ip, $acct]);
                log_login((int) $row['id'], true);
                login_user((int) $row['id']);
                redirect($next);
            }
        } else {
            usleep(random_int(250000, 600000)); // slows down guessing
            db_exec('INSERT INTO login_attempts (ip) VALUES (?)', 's', [$ip]);
            db_exec('INSERT INTO login_attempts (ip) VALUES (?)', 's', [$acct]);
            if ($row) {
                log_login((int) $row['id'], false); // shows up in that member's "login attempts" log
            }
            db_exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
            $error = 'اسم العضوية أو كلمة السر غير صحيحة.';
        }
    }
}

page_header('تسجيل الدخول', 'login');
?>
<div class="panel form-panel narrow">
    <h1>تسجيل الدخول</h1>
    <?php if ($error !== null): ?>
        <div class="notice notice-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('login.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">

        <label for="login">اسم العضوية أو البريد الإلكتروني</label>
        <input type="text" id="login" name="login" value="<?= e($login) ?>" required autocomplete="username" autofocus>

        <label for="password">كلمة السر</label>
        <input type="password" id="password" name="password" required autocomplete="current-password" dir="ltr">

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">دخول</button>
        </div>
    </form>
    <p class="hint">ليس لديك حساب؟ <a href="<?= e(url('register.php')) ?>">سجّل عضوية جديدة</a></p>
</div>
<?php page_footer();
