<?php
/** إعلان موضوع لقائمة من الأعضاء: يصلهم برسالة خاصة بعنوان «إعلان: …» وبإشعار. */
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$me = (int) $user['id'];
$id = is_post() ? (int) (isset($_POST['id']) ? $_POST['id'] : 0) : get_int('id');
$t = db_row('SELECT t.id, t.title, t.forum_id, f.category, f.access FROM threads t JOIN forums f ON f.id = t.forum_id WHERE t.id = ?', 'i', [$id]);
if (!$t || !forum_access_ok($t) || !can_moderate((int) $t['forum_id'])) {
    fail_page('الإعلان متاح لمشرفي المنتدى ومراقب القسم ونائبه والمدير ونائبه.', 403);
}
$self = 'announce.php?id=' . (int) $t['id'];
$errors = [];
if (is_post()) {
    csrf_verify();
    $a = post_str('action');
    if ($a === 'add') {
        $u = db_row('SELECT id FROM users WHERE username = ? AND is_banned = 0 AND is_approved = 1', 's', [post_str('username')]);
        if (!$u || (int) $u['id'] === $me) {
            flash('error', 'اسم العضوية غير صالح.');
        } elseif ((int) db_val('SELECT COUNT(*) FROM announce_members WHERE owner_id = ?', 'i', [$me]) >= 500) {
            flash('error', 'قائمتك وصلت الحد الأقصى (500).');
        } else {
            db_exec('INSERT IGNORE INTO announce_members (owner_id, member_id) VALUES (?, ?)', 'ii', [$me, $u['id']]);
            flash('success', 'تمت إضافة العضو إلى قائمتك.');
        }
        redirect($self);
    }
    if ($a === 'add_followers') {
        db_exec("INSERT IGNORE INTO announce_members (owner_id, member_id) SELECT ?, f.user_id FROM follows f JOIN users u ON u.id = f.user_id WHERE f.target_type = 'forum' AND f.target_id = ? AND f.user_id <> ? AND u.is_banned = 0 LIMIT 300", 'iii', [$me, $t['forum_id'], $me]);
        flash('success', 'تمت إضافة متابعي المنتدى إلى قائمتك.');
        redirect($self);
    }
    if ($a === 'remove') {
        db_exec('DELETE FROM announce_members WHERE owner_id = ? AND member_id = ?', 'ii', [$me, (int) (isset($_POST['member']) ? $_POST['member'] : 0)]);
        redirect($self);
    }
    if ($a === 'send') {
        $ids = [];
        if (isset($_POST['m']) && is_array($_POST['m'])) {
            foreach ($_POST['m'] as $v) {
                $ids[(int) $v] = true;
            }
        }
        $note = mb_substr(post_str('note'), 0, 500);
        $block = mute_message();
        if ($block === null) {
            $block = flood_message(5);
        }
        if ($block !== null) {
            $errors[] = $block;
        } elseif (!$ids || count($ids) > 200) {
            $errors[] = 'اختر من 1 إلى 200 عضو من قائمتك.';
        } else {
            $link = site_url_base() . '/thread.php?id=' . (int) $t['id'];
            $subject = mb_substr('إعلان: ' . $t['title'], 0, 150);
            $body = ($note !== '' ? $note . "\n\n" : '') . $t['title'] . "\n" . $link;
            $sent = 0;
            foreach (db_all('SELECT member_id FROM announce_members WHERE owner_id = ?', 'i', [$me]) as $r) {
                $mid = (int) $r['member_id'];
                if (!isset($ids[$mid])) {
                    continue;
                }
                db_exec('INSERT INTO messages (sender_id, receiver_id, subject, body) VALUES (?, ?, ?, ?)', 'iiss', [$me, $mid, $subject, $body]);
                notify($mid, $me, 'message', 'إعلان من ' . $user['username'] . ': «' . mb_substr($t['title'], 0, 60) . '»', 'thread.php?id=' . (int) $t['id']);
                $sent++;
            }
            mark_action();
            flash('success', 'تم إرسال الإعلان إلى ' . $sent . ' عضو.');
            redirect('thread.php?id=' . (int) $t['id']);
        }
    }
}
$members = db_all('SELECT u.id, u.username FROM announce_members a JOIN users u ON u.id = a.member_id WHERE a.owner_id = ? ORDER BY u.username', 'i', [$me]);
page_header('إعلان الموضوع');
breadcrumbs([['الرئيسية', 'index.php'], [$t['title'], 'thread.php?id=' . (int) $t['id']], ['إعلان', null]]);
?>
<div class="thread-page-head"><h1 class="thread-page-title">إعلان الموضوع</h1></div>
<?php foreach ($errors as $er): ?><p class="alert alert-error"><?= e($er) ?></p><?php endforeach; ?>
<section class="panel form-panel">
    <p>الموضوع: <b><?= e($t['title']) ?></b></p>
    <p class="hint">يصل الإعلان للأعضاء المحددين برسالة خاصة عنوانها «إعلان: <?= e(mb_substr($t['title'], 0, 40)) ?>» وبإشعار.</p>

    <h2>قائمتك (<?= count($members) ?>)</h2>
    <form method="post" action="<?= e(url('announce.php')) ?>" class="inline-form">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="action" value="add">
        <input type="text" name="username" placeholder="اسم عضوية لإضافته" maxlength="30" required>
        <button class="btn btn-small" type="submit">إضافة</button>
    </form>
    <form method="post" action="<?= e(url('announce.php')) ?>" class="inline-form">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="action" value="add_followers">
        <button class="btn btn-small" type="submit">إضافة متابعي هذا المنتدى</button>
    </form>

    <?php if (!$members): ?>
        <p class="meta">قائمتك فارغة. أضف أعضاء بأسمائهم أو أضف متابعي المنتدى.</p>
    <?php else: ?>
        <form method="post" action="<?= e(url('announce.php')) ?>">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="action" value="send">
            <ul class="follow-list">
            <?php foreach ($members as $m): ?>
                <li><label class="check"><input type="checkbox" name="m[]" value="<?= (int) $m['id'] ?>" checked> <?= e($m['username']) ?></label>
                    <button class="btn btn-small" type="submit" form="rm-<?= (int) $m['id'] ?>">إزالة</button></li>
            <?php endforeach; ?>
            </ul>
            <label for="note">ملاحظة مرفقة (اختياري)</label>
            <textarea id="note" name="note" rows="3" maxlength="500"></textarea>
            <div class="form-actions"><button class="btn btn-primary" type="submit">إرسال الإعلان للمحددين</button></div>
        </form>
        <?php foreach ($members as $m): ?>
            <form id="rm-<?= (int) $m['id'] ?>" method="post" action="<?= e(url('announce.php')) ?>" hidden><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="action" value="remove"><input type="hidden" name="member" value="<?= (int) $m['id'] ?>"></form>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php page_footer();
