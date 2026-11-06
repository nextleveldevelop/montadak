<?php
require __DIR__ . '/includes/bootstrap.php';

if (current_user() !== null) {
    redirect('index.php');
}

$errors = [];
$username = '';
$email = '';
$gender = '';

if (is_post()) {
    csrf_verify();

    $username = post_str('username');
    $email = strtolower(post_str('email'));
    $gender = post_str('gender');
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $confirm = isset($_POST['confirm_password']) && is_string($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    if (post_str('website') !== '') {
        // Honeypot field filled in: this is a bot. Pretend nothing happened.
        redirect('index.php');
    }

    $username = trim(preg_replace('/\s+/u', ' ', $username));
    if (!preg_match('/^(?=.{3,30}$)[\p{L}\p{N}_.\-]+(?: [\p{L}\p{N}_.\-]+)*$/u', $username)) {
        $errors[] = 'اسم العضوية يجب أن يكون من 3 إلى 30 حرفًا، ويحتوي على حروف أو أرقام أو (_ . -)، ويمكن أن يضم كلمات تفصلها مسافة واحدة مثل Real Madrid.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $errors[] = 'البريد الإلكتروني غير صالح.';
    }
    if (($pwErr = password_policy_error($password)) !== null) {
        $errors[] = $pwErr;
    } elseif ($password !== $confirm) {
        $errors[] = 'كلمتا السر غير متطابقتين.';
    }

    if (!$errors) {
        $taken = db_val('SELECT 1 FROM users WHERE username = ? OR email = ? LIMIT 1', 'ss', [$username, $email]);
        if ($taken !== null) {
            $errors[] = 'اسم العضوية أو البريد الإلكتروني مستخدم من قبل.';
        }
    }

    if (!in_array($gender, ['male', 'female'], true)) {
        $errors[] = 'اختر الجنس (ذكر أو أنثى). لا يمكن تغييره بعد التسجيل.';
    }
    if (!$errors) {
        // The very first member becomes the administrator.
        $role = (int) db_val('SELECT COUNT(*) FROM users') === 0 ? 'admin' : 'user';
        try {
            $needsOk = ($role !== 'admin' && site_setting('approve_new', '0') === '1') ? 1 : 0;
            db_exec(
                'INSERT INTO users (username, email, password_hash, role, timezone, is_approved, gender) VALUES (?, ?, ?, ?, ?, ?, ?)',
                'sssssis',
                [$username, $email, password_hash($password, PASSWORD_DEFAULT), $role, DEFAULT_TIMEZONE, $needsOk ? 0 : 1, $gender]
            );
            if ($needsOk) {
                $newId = db_insert_id();
                foreach (db_all("SELECT id FROM users WHERE role = 'admin'") as $ad) {
                    notify($ad['id'], $newId, 'follow', 'عضوية جديدة بانتظار موافقتك: ' . $username, 'admin/requests.php');
                }
                flash('success', 'تم إنشاء حسابك وهو بانتظار موافقة المدير. ستتمكن من الدخول بعد الموافقة.');
                redirect('login.php');
            }
            login_user(db_insert_id());
            flash('success', $role === 'admin'
                ? 'أهلًا بك! تم إنشاء حسابك كمدير للمنتدى. ابدأ بإنشاء المنتديات من لوحة الإدارة.'
                : 'أهلًا بك في ' . site_name() . '! تم إنشاء حسابك.');
            redirect($role === 'admin' ? 'admin/forums.php' : 'index.php');
        } catch (mysqli_sql_exception $ex) {
            if ((int) $ex->getCode() === 1062) { // duplicate key: someone registered the same name a moment ago
                $errors[] = 'اسم العضوية أو البريد الإلكتروني مستخدم من قبل.';
            } else {
                throw $ex;
            }
        }
    }
}

page_header('عضوية جديدة', 'register');
?>
<div class="panel form-panel narrow">
    <h1>تسجيل عضوية جديدة</h1>
    <?php foreach ($errors as $err): ?>
        <div class="notice notice-error" role="alert"><?= e($err) ?></div>
    <?php endforeach; ?>
    <form method="post" action="<?= e(url('register.php')) ?>" autocomplete="on">
        <?= csrf_field() ?>
        <label for="username">اسم العضوية</label>
        <input type="text" id="username" name="username" value="<?= e($username) ?>" required minlength="3" maxlength="30" autocomplete="username" autofocus>

        <label for="email">البريد الإلكتروني</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required maxlength="190" autocomplete="email" dir="ltr">

        <label for="gender">الجنس</label>
        <p class="hint">لا يمكن تغيير هذا الخيار بعد التسجيل.</p>
        <select id="gender" name="gender" required>
            <option value="">— اختر —</option>
            <option value="male"<?= $gender === 'male' ? ' selected' : '' ?>>ذكر</option>
            <option value="female"<?= $gender === 'female' ? ' selected' : '' ?>>أنثى</option>
        </select>

        <label for="password">كلمة السر</label>
        <p class="hint">كلمة السر: 10 أحرف على الأقل وتحتوي على حروف وأرقام ورموز.</p>
        <input type="password" id="password" name="password" required minlength="10" maxlength="72" autocomplete="new-password" dir="ltr">

        <label for="confirm_password">تأكيد كلمة السر</label>
        <input type="password" id="confirm_password" name="confirm_password" required minlength="10" maxlength="72" autocomplete="new-password" dir="ltr">

        <div class="hp" aria-hidden="true">
            <label for="website">اترك هذا الحقل فارغًا</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">إنشاء الحساب</button>
        </div>
    </form>
    <p class="hint">لديك حساب؟ <a href="<?= e(url('login.php')) ?>">سجّل الدخول</a></p>
</div>
<?php page_footer();
