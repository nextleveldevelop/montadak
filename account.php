<?php
/** One page for the small account tools (the links of settings.php). */
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$uid = (int) $user['id'];

$sections = [
    'password'    => 'تغيير كلمتك السرية',
    'email'       => 'تغيير بريدك الإلكتروني',
    'profile'     => 'تغيير بياناتك الشخصية وخيارات العضوية',
    'avatar'      => 'صورة العضوية',
    'bg'          => 'خلفية العضوية',
    'live'        => 'المحادثة الفورية',
    'rename'      => 'طلب تغيير إسم العضوية',
];
$do = (isset($_GET['do']) && is_string($_GET['do']) && isset($sections[$_GET['do']])) ? $_GET['do'] : null;
if ($do === null) {
    redirect('settings.php');
}

$errors = [];
$zones = timezone_choices();
$bio = $user['bio'];
$country = $user['country'];
$timezone = $user['timezone'];
$hideOnline = (int) $user['hide_online'];
$realName = $user['real_name'];
$city = $user['city'];
$social = $user['social_status'];
$birth = $user['birth_date'] !== null ? $user['birth_date'] : '';
$cv = $user['cv'];
$education = $user['education'];
$experience = $user['experience'];
$interests = $user['interests'];
$signature = '';
ensure_privacy_cols();
$showRN = (int) db_val('SELECT show_real_name FROM users WHERE id = ?', 'i', [$uid]);
$showCountry = (int) db_val('SELECT show_country FROM users WHERE id = ?', 'i', [$uid]);
$showSocial = (int) db_val('SELECT show_social FROM users WHERE id = ?', 'i', [$uid]);
$showBirth = (int) db_val('SELECT show_birth FROM users WHERE id = ?', 'i', [$uid]);
$newEmail = '';
$newName = '';
$reason = '';

if (is_post()) {
    csrf_verify();

    if ($do === 'password') {
        $current = isset($_POST['current_password']) && is_string($_POST['current_password']) ? $_POST['current_password'] : '';
        $new = isset($_POST['new_password']) && is_string($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm = isset($_POST['confirm_password']) && is_string($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        $hash = db_val('SELECT password_hash FROM users WHERE id = ?', 'i', [$uid]);
        if (!password_verify($current, (string) $hash)) {
            $errors[] = 'كلمة السر الحالية غير صحيحة.';
        } elseif (($pwErr = password_policy_error($new)) !== null) {
            $errors[] = $pwErr;
        } elseif ($new !== $confirm) {
            $errors[] = 'كلمتا السر الجديدتان غير متطابقتين.';
        } else {
            db_exec('UPDATE users SET password_hash = ? WHERE id = ?', 'si', [password_hash($new, PASSWORD_DEFAULT), $uid]);
            session_regenerate_id(true);
            flash('success', 'تم تغيير كلمة السر.');
            redirect('settings.php');
        }
    } elseif ($do === 'email') {
        $current = isset($_POST['current_password']) && is_string($_POST['current_password']) ? $_POST['current_password'] : '';
        $newEmail = strtolower(post_str('email'));
        $hash = db_val('SELECT password_hash FROM users WHERE id = ?', 'i', [$uid]);
        if (!password_verify($current, (string) $hash)) {
            $errors[] = 'كلمة السر الحالية غير صحيحة.';
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL) || strlen($newEmail) > 190) {
            $errors[] = 'البريد الإلكتروني غير صالح.';
        } elseif (db_val('SELECT 1 FROM users WHERE email = ? AND id <> ?', 'si', [$newEmail, $uid]) !== null) {
            $errors[] = 'هذا البريد مستخدم من عضو آخر.';
        } else {
            try {
                db_exec('UPDATE users SET email = ? WHERE id = ?', 'si', [$newEmail, $uid]);
                flash('success', 'تم تغيير بريدك الإلكتروني.');
                redirect('settings.php');
            } catch (mysqli_sql_exception $ex) {
                if ((int) $ex->getCode() !== 1062) {
                    throw $ex;
                }
                $errors[] = 'هذا البريد مستخدم من عضو آخر.';
            }
        }
    } elseif ($do === 'profile') {
        $bio = post_str('bio');
        $country = post_str('country');
        $timezone = post_str('timezone');
        $hideOnline = post_str('hide_online') === '1' ? 1 : 0;
        $realName = post_str('real_name');
        $city = post_str('city');
        $social = post_str('social_status');
        $birth = post_str('birth_date');
        $cv = post_str('cv');
        $education = post_str('education');
        $experience = post_str('experience');
        $interests = post_str('interests');
        $showRN = post_str('show_real_name') === '1' ? 1 : 0;
        $showCountry = post_str('show_country') === '1' ? 1 : 0;
        $showSocial = post_str('show_social') === '1' ? 1 : 0;
        $showBirth = post_str('show_birth') === '1' ? 1 : 0;
        if (mb_strlen($realName) > 60 || mb_strlen($city) > 60) {
            $errors[] = 'الاسم الحقيقي والمدينة: 60 حرفًا كحد أقصى.';
        }
        if ($social !== '' && !in_array($social, social_statuses(), true)) {
            $errors[] = 'الحالة الاجتماعية غير صالحة.';
        }
        if ($birth !== '') {
            $bd = DateTime::createFromFormat('Y-m-d', $birth);
            if (!$bd || $bd->format('Y-m-d') !== $birth || $birth < '1900-01-01' || $birth > date('Y-m-d')) {
                $errors[] = 'تاريخ الميلاد غير صالح.';
            }
        }
        if (mb_strlen($cv) > 1000 || mb_strlen($experience) > 1000) {
            $errors[] = 'السيرة الذاتية والخبرات: 1000 حرف كحد أقصى لكل منهما.';
        }
        if (mb_strlen($education) > 255 || mb_strlen($interests) > 500) {
            $errors[] = 'المؤهل العلمي (255 حرفًا) والاهتمامات (500 حرف) أطول من المسموح.';
        }
        if (mb_strlen($bio) > 500) {
            $errors[] = 'النبذة طويلة جدًا (500 حرف كحد أقصى).';
        }
        if ($country !== '' && !in_array($country, countries(), true)) {
            $errors[] = 'الدولة غير صالحة.';
        }
        if (!isset($zones[$timezone])) {
            $errors[] = 'المنطقة الزمنية غير صالحة.';
        }
        if (!$errors) {
            db_exec(
                'UPDATE users SET bio = ?, country = ?, timezone = ?, hide_online = ?, real_name = ?, city = ?, social_status = ?, birth_date = ?, cv = ?, education = ?, experience = ?, interests = ? WHERE id = ?',
                'sssissssssssi',
                [$bio, $country, $timezone, $hideOnline, $realName, $city, $social, $birth !== '' ? $birth : null, $cv, $education, $experience, $interests, $uid]
            );
            db_exec('UPDATE users SET show_real_name = ?, show_country = ?, show_social = ?, show_birth = ? WHERE id = ?', 'iiiii', [$showRN, $showCountry, $showSocial, $showBirth, $uid]);
            flash('success', 'تم حفظ بياناتك.');
            redirect(post_str('inline') === '1' ? 'profile.php?id=' . $uid : 'settings.php');
        }
    } elseif ($do === 'live') {
        $on = post_str('live_msgs') === '1' ? 1 : 0;
        db_exec('UPDATE users SET live_msgs = ? WHERE id = ?', 'ii', [$on, $uid]);
        flash('success', $on ? 'تم تفعيل المحادثة الفورية.' : 'تم إيقاف المحادثة الفورية.');
        redirect('account.php?do=live');
    } elseif ($do === 'bg') {
        $old = db_val('SELECT profile_bg FROM users WHERE id = ?', 'i', [$uid]);
        if (post_str('remove_bg') === '1') {
            delete_profile_bg($old);
            db_exec("UPDATE users SET profile_bg = '' WHERE id = ?", 'i', [$uid]);
            flash('success', 'تمت إزالة الخلفية.');
            redirect('account.php?do=bg');
        }
        $err = '';
        $new = save_profile_bg(isset($_FILES['bg']) ? $_FILES['bg'] : null, $err);
        if ($new === null) {
            $errors[] = $err;
        } elseif ($new === '') {
            $errors[] = 'اختر صورة أولًا.';
        } else {
            delete_profile_bg($old);
            db_exec('UPDATE users SET profile_bg = ? WHERE id = ?', 'si', [$new, $uid]);
            flash('success', 'تم تحديث خلفية عضويتك.');
            redirect('account.php?do=bg');
        }
    } elseif ($do === 'avatar') {
        $old = db_val('SELECT avatar FROM users WHERE id = ?', 'i', [$uid]);
        if (post_str('remove_avatar') === '1') {
            delete_avatar($old);
            db_exec("UPDATE users SET avatar = '' WHERE id = ?", 'i', [$uid]);
            flash('success', 'تمت إزالة صورتك.');
            redirect('account.php?do=avatar');
        }
        $err = '';
        $new = save_avatar(isset($_FILES['avatar']) ? $_FILES['avatar'] : null, $err);
        if ($new === null) {
            $errors[] = $err;
        } elseif ($new === '') {
            $errors[] = 'اختر صورة أولًا.';
        } else {
            delete_avatar($old);
            db_exec('UPDATE users SET avatar = ? WHERE id = ?', 'si', [$new, $uid]);
            flash('success', 'تم تحديث صورة عضويتك.');
            redirect('account.php?do=avatar');
        }
    } elseif ($do === 'signature') {
        $signature = rich_str('signature');
        if (mb_strlen(rich_plain($signature)) > 300 || mb_strlen($signature) > 1900) {
            $errors[] = 'التوقيع طويل جدًا (300 حرف كحد أقصى).';
        } else {
            db_exec('UPDATE users SET signature = ? WHERE id = ?', 'si', [$signature, $uid]);
            flash('success', 'تم حفظ توقيعك.');
            redirect('settings.php');
        }
    } elseif ($do === 'badges') {
        $badgeId = (int) (isset($_POST['badge_id']) ? $_POST['badge_id'] : 0);
        if ($badgeId === 0) {
            db_exec('UPDATE users SET current_badge_id = NULL WHERE id = ?', 'i', [$uid]);
            flash('success', 'تم إخفاء الوسام.');
            redirect('account.php?do=badges');
        }
        if (db_val("SELECT 1 FROM user_badges WHERE user_id = ? AND badge_id = ? AND status = 'approved' AND (expires_at IS NULL OR expires_at > NOW())", 'ii', [$uid, $badgeId]) === null) {
            $errors[] = 'لا تملك هذا الوسام.';
        } else {
            db_exec('UPDATE users SET current_badge_id = ? WHERE id = ?', 'ii', [$badgeId, $uid]);
            flash('success', 'تم تغيير وسامك الحالي.');
            redirect('account.php?do=badges');
        }
    } elseif ($do === 'rename') {
        $newName = trim(preg_replace('/\s+/u', ' ', post_str('new_name')));
        $reason = post_str('reason');
        if (!preg_match('/^(?=.{3,30}$)[\p{L}\p{N}_.\-]+(?: [\p{L}\p{N}_.\-]+)*$/u', $newName)) {
            $errors[] = 'الاسم الجديد يجب أن يكون من 3 إلى 30 حرفًا (حروف أو أرقام أو _ . -، ويمكن كلمات تفصلها مسافة واحدة).';
        } elseif (mb_strtolower($newName) === mb_strtolower($user['username'])) {
            $errors[] = 'هذا هو اسمك الحالي.';
        } elseif (db_val('SELECT 1 FROM users WHERE username = ?', 's', [$newName]) !== null) {
            $errors[] = 'هذا الاسم مستخدم من عضو آخر.';
        } elseif (mb_strlen($reason) > 255) {
            $errors[] = 'السبب طويل جدًا.';
        } elseif (db_val("SELECT 1 FROM rename_requests WHERE user_id = ? AND status = 'pending'", 'i', [$uid]) !== null) {
            $errors[] = 'لديك طلب قيد المراجعة بالفعل.';
        } else {
            db_exec('INSERT INTO rename_requests (user_id, new_name, reason) VALUES (?, ?, ?)', 'iss', [$uid, $newName, $reason]);
            flash('success', 'تم إرسال طلبك إلى الإدارة.');
            redirect('account.php?do=rename');
        }
    }
}

// ---- data for the page ----
$badgesOwned = [];
$logRows = [];
$requests = [];
if ($do === 'badges') {
    $badgesOwned = db_all(
        'SELECT b.id, b.name, b.icon, b.description, ub.created_at
           FROM user_badges ub JOIN badges b ON b.id = ub.badge_id
          WHERE ub.user_id = ? AND ub.status = \'approved\' AND (ub.expires_at IS NULL OR ub.expires_at > NOW()) ORDER BY ub.created_at DESC',
        'i',
        [$uid]
    );
} elseif ($do === 'connections' || $do === 'failed') {
    $logRows = db_all(
        'SELECT agent, created_at FROM login_log WHERE user_id = ? AND success = ? ORDER BY id DESC LIMIT 40',
        'ii',
        [$uid, $do === 'connections' ? 1 : 0]
    );
} elseif ($do === 'rename') {
    $requests = db_all('SELECT new_name, status, created_at FROM rename_requests WHERE user_id = ? ORDER BY id DESC LIMIT 10', 'i', [$uid]);
}
$current = (int) db_val('SELECT COALESCE(current_badge_id, 0) FROM users WHERE id = ?', 'i', [$uid]);

page_header($sections[$do], 'settings');
breadcrumbs([['الرئيسية', 'index.php'], ['خيارات وبيانات عضويتك', 'settings.php'], [$sections[$do], null]]);
?>
<div class="acc"><a class="acc-back" href="<?= e(url('settings.php')) ?>"><?= icon('reply') ?> العودة إلى خياراتي</a>
<h1 class="acc-title"><?= e($sections[$do]) ?></h1>
<?php foreach ($errors as $err): ?>
    <div class="notice notice-error" role="alert"><?= e($err) ?></div>
<?php endforeach; ?>

<?php if ($do === 'password'): ?>
    <form class="panel form-panel narrow" method="post" action="<?= e(url('account.php?do=password')) ?>">
        <?= csrf_field() ?>
        <label for="current_password">كلمة السر الحالية</label>
        <input type="password" id="current_password" name="current_password" required autocomplete="current-password" dir="ltr">
        <label for="new_password">كلمة السر الجديدة</label>
        <input type="password" id="new_password" name="new_password" required minlength="10" maxlength="72" autocomplete="new-password" dir="ltr">
        <label for="confirm_password">تأكيد كلمة السر الجديدة</label>
        <input type="password" id="confirm_password" name="confirm_password" required minlength="10" maxlength="72" autocomplete="new-password" dir="ltr">
        <div class="form-actions"><button type="submit" class="btn btn-primary">تغيير كلمة السر</button></div>
    </form>

<?php elseif ($do === 'email'): ?>
    <form class="panel form-panel narrow" method="post" action="<?= e(url('account.php?do=email')) ?>">
        <?= csrf_field() ?>
        <p class="hint">بريدك الحالي: <span dir="ltr"><?= e($user['email']) ?></span></p>
        <label for="email">البريد الجديد</label>
        <input type="email" id="email" name="email" value="<?= e($newEmail) ?>" required maxlength="190" dir="ltr" autocomplete="email">
        <label for="current_password">كلمة السر الحالية (للتأكيد)</label>
        <input type="password" id="current_password" name="current_password" required autocomplete="current-password" dir="ltr">
        <div class="form-actions"><button type="submit" class="btn btn-primary">تغيير البريد</button></div>
    </form>

<?php elseif ($do === 'profile'): ?>
    <form class="panel form-panel" method="post" action="<?= e(url('account.php?do=profile')) ?>">
        <?= csrf_field() ?>
        <label for="bio">نبذة عنك</label>
        <textarea id="bio" name="bio" rows="4" maxlength="500"><?= e($bio) ?></textarea>
        <label for="real_name">الاسم الحقيقي</label>
        <input type="text" id="real_name" name="real_name" value="<?= e($realName) ?>" maxlength="60">
        <label class="check show-opt"><input type="checkbox" name="show_real_name" value="1"<?= $showRN ? ' checked' : '' ?>> إظهار في صفحة عضويتي</label>
        <label for="city">المدينة</label>
        <input type="text" id="city" name="city" value="<?= e($city) ?>" maxlength="60">
        <label for="social_status">الحالة الاجتماعية</label>
        <select id="social_status" name="social_status">
            <option value="">— غير محددة —</option>
            <?php foreach (social_statuses() as $st): ?><option value="<?= e($st) ?>"<?= $st === $social ? ' selected' : '' ?>><?= e($st) ?></option><?php endforeach; ?>
        </select>
        <label class="check show-opt"><input type="checkbox" name="show_social" value="1"<?= $showSocial ? ' checked' : '' ?>> إظهار في صفحة عضويتي</label>
        <label for="birth_date">تاريخ الميلاد</label>
        <input type="date" id="birth_date" name="birth_date" value="<?= e($birth) ?>" min="1900-01-01" max="<?= e(date('Y-m-d')) ?>">
        <label class="check show-opt"><input type="checkbox" name="show_birth" value="1"<?= $showBirth ? ' checked' : '' ?>> إظهار في صفحة عضويتي</label>
        <label for="country">الدولة</label>
        <select id="country" name="country">
            <option value="">— غير محددة —</option>
            <?php foreach (countries() as $c): ?>
                <option value="<?= e($c) ?>"<?= $c === $country ? ' selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="check show-opt"><input type="checkbox" name="show_country" value="1"<?= $showCountry ? ' checked' : '' ?>> إظهار في صفحة عضويتي</label>
        <label for="timezone">المنطقة الزمنية</label>
        <select id="timezone" name="timezone">
            <?php foreach ($zones as $key => $label): ?>
                <option value="<?= e($key) ?>"<?= $key === $timezone ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="hint">تُعرض كل الأوقات في المنتدى حسب هذه المنطقة.</p>
        <label class="check"><input type="checkbox" name="hide_online" value="1"<?= $hideOnline ? ' checked' : '' ?>> إخفاء تواجدي: لا يظهر اسمي في قوائم المتواجدين (يراه المشرفون والمراقبون والمدير فقط)</label>
        <div class="form-actions"><button type="submit" class="btn btn-primary">حفظ</button></div>
    </form>

<?php elseif ($do === 'live'): ?>
    <?php $liveOn = (int) db_val('SELECT live_msgs FROM users WHERE id = ?', 'i', [$uid]); ?>
    <form class="panel form-panel" method="post" action="<?= e(url('account.php?do=live')) ?>">
        <?= csrf_field() ?>
        <h2>المحادثة الفورية (مثل الماسنجر)</h2>
        <p class="hint">عند التفعيل يظهر زر محادثات في أسفل كل صفحة. إذا راسلك عضو تفتح لك نافذة صغيرة تتحدث فيها معه مباشرة، وتصلك ردوده دون تحديث الصفحة، وتبقى الرسائل القديمة أمامك في نفس المحادثة. يمكنك إيقافها في أي وقت.</p>
        <label class="check"><input type="checkbox" name="live_msgs" value="1"<?= $liveOn ? ' checked' : '' ?>> تفعيل المحادثة الفورية</label>
        <div class="form-actions"><button type="submit" class="btn btn-primary">حفظ</button></div>
    </form>

<?php elseif ($do === 'bg'): ?>
    <?php $myBg = (string) db_val('SELECT profile_bg FROM users WHERE id = ?', 'i', [$uid]); ?>
    <form class="panel form-panel" method="post" enctype="multipart/form-data" action="<?= e(url('account.php?do=bg')) ?>">
        <?= csrf_field() ?>
        <?php if (preg_match('/^[a-f0-9]{24}\.jpg$/', $myBg)): ?><div class="bg-preview" style="background-image:url('<?= e(url('assets/images/backgrounds/' . $myBg)) ?>')"></div><?php endif; ?>
        <ul class="bg-rules">
            <li>المقاس الموصى به: <b>1200 × 300 بكسل</b> (نسبة 4:1).</li>
            <li>الحد الأدنى <b>800 × 200</b> والأقصى 5000 × 5000 بكسل.</li>
            <li>الصيغ: PNG أو JPG أو WEBP، حتى <b>3 ميغابايت</b>.</li>
            <li>تُقصّ الصورة من المنتصف تلقائيًا وتُعاد معالجتها لحمايتك وحماية الموقع.</li>
        </ul>
        <label for="bg">اختر الصورة</label>
        <input type="file" id="bg" name="bg" accept="image/png,image/jpeg,image/webp">
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">رفع الخلفية</button>
            <?php if ($myBg !== ''): ?><button type="submit" name="remove_bg" value="1" class="btn">إزالة الخلفية</button><?php endif; ?>
        </div>
    </form>

<?php elseif ($do === 'avatar'): ?>
    <?php $myAvatar = db_val('SELECT avatar FROM users WHERE id = ?', 'i', [$uid]); ?>
    <form class="panel form-panel avatar-form" method="post" enctype="multipart/form-data" action="<?= e(url('account.php?do=avatar')) ?>">
        <?= csrf_field() ?>
        <div class="avatar-preview"><?= avatar_html($myAvatar, 'avatar avatar-lg', 128) ?></div>
        <label for="avatar">اختر صورة (PNG أو JPG أو GIF، حتى 2 ميغابايت). تُقصّ مربعة وتُصغّر تلقائيًا.</label>
        <input type="file" id="avatar" name="avatar" accept="image/png,image/jpeg,image/gif">
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">رفع الصورة</button>
            <?php if ($myAvatar !== ''): ?><button type="submit" name="remove_avatar" value="1" class="btn">إزالة الصورة</button><?php endif; ?>
        </div>
    </form>

<?php elseif ($do === 'signature'): ?>
    <form class="panel form-panel" method="post" action="<?= e(url('account.php?do=signature')) ?>">
        <?= csrf_field() ?>
        <label for="signature">التوقيع (يظهر تحت مشاركاتك، 300 حرف كحد أقصى)</label>
        <textarea id="signature" name="signature" rows="4" data-rich="1"><?= e($signature) ?></textarea>
        <div class="form-actions"><button type="submit" class="btn btn-primary">حفظ التوقيع</button></div>
    </form>

<?php elseif ($do === 'badges'): ?>
    <?php if (!$badgesOwned): ?>
        <div class="panel empty"><p>لم تُمنح أي وسام بعد. .</p></div>
    <?php else: ?>
        <form method="post" action="<?= e(url('account.php?do=badges')) ?>" class="panel">
            <?= csrf_field() ?>
            <p>اختر الوسام الذي يظهر بجانب مشاركاتك:</p>
            <label class="radio"><input type="radio" name="badge_id" value="0"<?= $current === 0 ? ' checked' : '' ?>> بدون وسام</label>
            <?php foreach ($badgesOwned as $b): ?>
                <label class="radio badge-choice">
                    <input type="radio" name="badge_id" value="<?= (int) $b['id'] ?>"<?= $current === (int) $b['id'] ? ' checked' : '' ?>>
                    <?= icon($b['icon']) ?> <strong><?= e($b['name']) ?></strong>
                    <span class="meta"><?= e($b['description']) ?> — منذ <?= e(fmt_time($b['created_at'], 'Y/m/d')) ?></span>
                </label>
            <?php endforeach; ?>
            <div class="form-actions"><button type="submit" class="btn btn-primary">تغيير الوسام الحالي</button></div>
        </form>
    <?php endif; ?>

<?php elseif ($do === 'rename'): ?>
    <form class="panel form-panel narrow" method="post" action="<?= e(url('account.php?do=rename')) ?>">
        <?= csrf_field() ?>
        <p class="hint">اسمك الحالي: <strong><?= e($user['username']) ?></strong>. يراجع المدير الطلب ثم يُطبَّق الاسم الجديد.</p>
        <label for="new_name">الاسم الجديد</label>
        <input type="text" id="new_name" name="new_name" value="<?= e($newName) ?>" required minlength="3" maxlength="30">
        <label for="reason">السبب (اختياري)</label>
        <input type="text" id="reason" name="reason" value="<?= e($reason) ?>" maxlength="255">
        <div class="form-actions"><button type="submit" class="btn btn-primary">إرسال الطلب</button></div>
    </form>
    <?php if ($requests): ?>
        <h2>طلباتك السابقة</h2>
        <ul class="plain-list">
            <?php foreach ($requests as $r): ?>
                <li><?= e($r['new_name']) ?> <span class="meta"><?= e(['pending' => 'قيد المراجعة', 'approved' => 'تمت الموافقة', 'rejected' => 'مرفوض'][$r['status']]) ?> — <?= e(fmt_when($r['created_at'])) ?></span></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

<?php else: /* connections / failed */ ?>
    <p class="hint">احترامًا للخصوصية لا تُعرض عناوين IP ولا تُحفظ كاملة، ويُحذف السجل تلقائيًا بعد 180 يومًا.</p>
    <p class="lede"><?= $do === 'connections' ? 'آخر عمليات دخول ناجحة إلى حسابك. إن رأيت شيئًا لا تعرفه فغيّر كلمة السر فورًا.' : 'محاولات دخول فاشلة بكلمة سر خاطئة على حسابك.' ?></p>
    <?php if (!$logRows): ?>
        <div class="panel empty"><p>لا توجد سجلات.</p></div>
    <?php else: ?>
        <table class="board-table">
            <thead><tr><th scope="col" class="last">التاريخ</th><th scope="col" class="author-col">المتصفح</th></tr></thead>
            <tbody>
            <?php foreach ($logRows as $r): ?>
                <tr>
                    <td class="last"><span class="when"><?= e(fmt_when($r['created_at'])) ?></span></td>
                    <td class="author-col" dir="ltr"><?= e(mb_strimwidth($r['agent'], 0, 60, '…')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php endif; ?>
<?php ?></div>
<?php page_footer();
