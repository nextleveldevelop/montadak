<?php
/** رقابة مؤقتة: منع عضو من كتابة المواضيع والردود في منتدى معين لفترة محددة. */
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$me = (int) $user['id'];
$forums = [];
foreach (db_all("SELECT id, category, name FROM forums WHERE access = 'public' ORDER BY category, sort_order, id") as $r) {
    if (forum_level((int) $r['id']) >= 1) {
        $forums[(int) $r['id']] = $r['category'] . ' ‹ ' . $r['name'];
    }
}
if (!$forums) {
    fail_page('هذه الصفحة للمشرفين والمراقبين ونوابهم والمدير.', 403);
}
$durations = [1 => 'ساعة', 6 => '6 ساعات', 24 => 'يوم', 72 => '3 أيام', 168 => 'أسبوع', 720 => '30 يومًا'];
$username = isset($_GET['user']) && is_string($_GET['user']) ? mb_substr($_GET['user'], 0, 30) : '';
$errors = [];
if (is_post()) {
    csrf_verify();
    $a = post_str('action');
    if ($a === 'lift') {
        $b = db_row('SELECT id, user_id, forum_id FROM forum_bans WHERE id = ?', 'i', [(int) (isset($_POST['id']) ? $_POST['id'] : 0)]);
        if ($b && isset($forums[(int) $b['forum_id']])) {
            db_exec('UPDATE forum_bans SET until = NOW() WHERE id = ?', 'i', [$b['id']]);
            mod_log_add($b['user_id'], $me, 'forum_ban', 'رفع المنع من «' . $forums[(int) $b['forum_id']] . '»');
            flash('success', 'تم رفع المنع.');
        }
        redirect('forum_ban.php');
    }
    $username = post_str('username');
    $fid = (int) (isset($_POST['forum_id']) ? $_POST['forum_id'] : 0);
    $hours = (int) (isset($_POST['hours']) ? $_POST['hours'] : 0);
    $reason = mb_substr(post_str('reason'), 0, 250);
    $target = db_row('SELECT id, username FROM users WHERE username = ?', 's', [$username]);
    if (!isset($forums[$fid])) {
        $errors[] = 'اختر منتدى تشرف عليه.';
    } elseif (!isset($durations[$hours])) {
        $errors[] = 'اختر مدة صالحة.';
    } elseif (!$target || (int) $target['id'] === $me) {
        $errors[] = 'اسم العضوية غير صالح.';
    } elseif (mb_strlen($reason) < 3) {
        $errors[] = 'اكتب سببًا واضحًا.';
    } elseif (member_level((int) $target['id'], $fid) >= forum_level($fid) && !is_admin()) {
        $errors[] = 'لا يمكنك تطبيق الرقابة على من هو بمستواك أو أعلى.';
    } else {
        db_exec('INSERT INTO forum_bans (user_id, forum_id, until, reason, by_id) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), ?, ?)', 'iiisi', [$target['id'], $fid, $hours, $reason, $me]);
        mod_log_add($target['id'], $me, 'forum_ban', 'منع من «' . $forums[$fid] . '» لمدة ' . $durations[$hours] . ' — ' . $reason);
        notify($target['id'], $me, 'follow', 'مُنعت من الكتابة في منتدى «' . $forums[$fid] . '» لمدة ' . $durations[$hours] . '.', 'index.php');
        flash('success', 'تم تطبيق الرقابة على العضو.');
        redirect('forum_ban.php');
    }
}
$ids = implode(',', array_map('intval', array_keys($forums)));
$active = db_all('SELECT b.id, b.forum_id, b.until, b.reason, u.username FROM forum_bans b JOIN users u ON u.id = b.user_id WHERE b.until > NOW() AND b.forum_id IN (' . $ids . ') ORDER BY b.id DESC LIMIT 100');
page_header('الرقابة المؤقتة');
?>
<div class="thread-page-head"><h1 class="thread-page-title">الرقابة المؤقتة على الأعضاء</h1></div>
<?php foreach ($errors as $er): ?><p class="alert alert-error"><?= e($er) ?></p><?php endforeach; ?>
<section class="panel form-panel">
    <form method="post" action="<?= e(url('forum_ban.php')) ?>">
        <?= csrf_field() ?>
        <label for="username">اسم العضوية</label>
        <input type="text" id="username" name="username" value="<?= e($username) ?>" required maxlength="30">
        <label for="forum_id">المنتدى</label>
        <select id="forum_id" name="forum_id"><?php foreach ($forums as $fid => $fname): ?><option value="<?= (int) $fid ?>"><?= e($fname) ?></option><?php endforeach; ?></select>
        <label for="hours">المدة</label>
        <select id="hours" name="hours"><?php foreach ($durations as $h => $label): ?><option value="<?= (int) $h ?>"><?= e($label) ?></option><?php endforeach; ?></select>
        <label for="reason">السبب</label>
        <input type="text" id="reason" name="reason" maxlength="250" required>
        <div class="form-actions"><button class="btn btn-primary" type="submit">تطبيق الرقابة</button></div>
    </form>
</section>
<section class="panel form-panel">
    <h2>الرقابة الجارية</h2>
    <?php if (!$active): ?><p class="meta">لا توجد.</p><?php endif; ?>
    <ul class="follow-list"><?php foreach ($active as $b): ?>
        <li><span><b><?= e($b['username']) ?></b> — <?= e($forums[(int) $b['forum_id']]) ?> — حتى <?= e(fmt_when($b['until'])) ?><br><span class="meta"><?= e($b['reason']) ?></span></span>
        <form method="post" action="<?= e(url('forum_ban.php')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="lift"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><button class="btn btn-small" type="submit">رفع المنع</button></form></li>
    <?php endforeach; ?></ul>
</section>
<?php page_footer();
