<?php
/** مشاركة موضوع مع أعضاء آخرين عبر رسالة خاصة تصلهم. */
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$id = is_post() ? (int) (isset($_POST['id']) ? $_POST['id'] : 0) : get_int('id');
$t = db_row('SELECT t.id, t.title, t.forum_id, t.is_hidden, t.user_id, f.category, f.access FROM threads t JOIN forums f ON f.id = t.forum_id WHERE t.id = ?', 'i', [$id]);
if (!$t || !forum_access_ok($t) || !can_view_thread($t + ['id' => $t['id']])) {
    fail_page('الموضوع غير موجود.', 404);
}
$errors = [];
$to = '';
$note = '';
if (is_post()) {
    csrf_verify();
    $to = post_str('to');
    $note = mb_substr(post_str('note'), 0, 500);
    $msg = mute_message();
    if ($msg !== null) {
        $errors[] = $msg;
    }
    $names = array_values(array_unique(array_filter(array_map('trim', preg_split('~[,،\n]+~u', $to)))));
    if (!$names || count($names) > 10) {
        $errors[] = 'اكتب من 1 إلى 10 أسماء عضويات، يفصل بينها فاصلة.';
    }
    $targets = [];
    if (!$errors) {
        foreach ($names as $n) {
            $r = db_row('SELECT id, username FROM users WHERE username = ? AND is_banned = 0', 's', [$n]);
            if (!$r) {
                $errors[] = 'العضو غير موجود: ' . $n;
            } elseif ((int) $r['id'] === (int) $user['id']) {
                $errors[] = 'لا يمكنك المشاركة مع نفسك.';
            } else {
                $targets[] = $r;
            }
        }
    }
    if (!$errors) {
        $link = site_url_base() . '/thread.php?id=' . (int) $t['id'];
        $subject = mb_substr('مشاركة موضوع: ' . $t['title'], 0, 150);
        $body = ($note !== '' ? $note . "\n\n" : '') . 'الموضوع: ' . $t['title'] . "\n" . $link;
        foreach ($targets as $r) {
            db_exec('INSERT INTO messages (sender_id, receiver_id, subject, body) VALUES (?, ?, ?, ?)', 'iiss', [$user['id'], $r['id'], $subject, $body]);
            notify($r['id'], $user['id'], 'message', $user['username'] . ' شارك معك موضوعًا: «' . mb_substr($t['title'], 0, 60) . '»', 'inbox.php');
        }
        try { db_exec('INSERT IGNORE INTO thread_shares (thread_id, user_id) VALUES (?, ?)', 'ii', [$t['id'], $user['id']]); } catch (Throwable $e) {}
        flash('success', 'تمت مشاركة الموضوع مع ' . count($targets) . ' عضو.');
        redirect('thread.php?id=' . (int) $t['id']);
    }
}
page_header('مشاركة موضوع');
breadcrumbs([['الرئيسية', 'index.php'], [$t['title'], 'thread.php?id=' . (int) $t['id']], ['مشاركة', null]]);
?>
<div class="thread-page-head"><h1 class="thread-page-title">مشاركة الموضوع</h1></div>
<?php foreach ($errors as $er): ?><p class="alert alert-error"><?= e($er) ?></p><?php endforeach; ?>
<section class="panel form-panel">
    <p>الموضوع: <b><?= e($t['title']) ?></b></p>
    <form method="post" action="<?= e(url('share.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
        <label for="to">إلى (أسماء العضويات، يفصل بينها فاصلة — حتى 10)</label>
        <input type="text" id="to" name="to" value="<?= e($to) ?>" required maxlength="400">
        <label for="note">ملاحظة (اختياري)</label>
        <textarea id="note" name="note" rows="3" maxlength="500"><?= e($note) ?></textarea>
        <div class="form-actions"><button class="btn btn-primary" type="submit">إرسال المشاركة</button></div>
    </form>
</section>
<?php page_footer();
