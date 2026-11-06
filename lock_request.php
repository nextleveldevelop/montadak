<?php
/** طلب قفل عضوية: العضو يطلب قفل عضويته فقط؛ الإشراف يطلب قفل أي عضوية؛ المدير ونائبه يوافقون. */
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$me = (int) $user['id'];
$targetId = is_post() ? (int) (isset($_POST['target']) ? $_POST['target'] : 0) : (get_int('id') ?: $me);
$canOther = is_staff() || is_admin();
if (is_admin() && $targetId === $me) {
    fail_page('المدير لا يمكنه طلب قفل عضويته.', 403);
}
if ($targetId !== $me && !$canOther) {
    fail_page('يمكنك طلب قفل عضويتك أنت فقط.', 403);
}
$target = db_row('SELECT id, username, is_banned FROM users WHERE id = ?', 'i', [$targetId]);
if (!$target) {
    fail_page('العضو غير موجود.', 404);
}
$errors = [];
if (is_post()) {
    csrf_verify();
    $reason = mb_substr(post_str('reason'), 0, 250);
    if (mb_strlen($reason) < 5) {
        $errors[] = 'اكتب سببًا واضحًا (5 أحرف على الأقل).';
    } elseif ((int) $target['is_banned'] === 1) {
        $errors[] = 'هذه العضوية مقفلة بالفعل.';
    } elseif (db_val("SELECT 1 FROM account_requests WHERE target_id = ? AND status = 'pending'", 'i', [$targetId]) !== null) {
        $errors[] = 'يوجد طلب قفل قيد المعالجة لهذه العضوية.';
    } else {
        db_exec('INSERT INTO account_requests (target_id, requester_id, reason) VALUES (?, ?, ?)', 'iis', [$targetId, $me, $reason]);
        foreach (db_all("SELECT id FROM users WHERE role = 'admin'") as $ad) {
            notify($ad['id'], $me, 'follow', $user['username'] . ' طلب قفل عضوية ' . $target['username'], 'admin/requests.php');
        }
        flash('success', 'تم إرسال طلب القفل إلى المدير.');
        redirect($targetId === $me ? 'settings.php' : 'profile.php?id=' . $targetId);
    }
}
page_header('طلب قفل عضوية');
?>
<div class="thread-page-head"><h1 class="thread-page-title">طلب قفل عضوية</h1></div>
<?php foreach ($errors as $er): ?><p class="alert alert-error"><?= e($er) ?></p><?php endforeach; ?>
<section class="panel form-panel">
    <p>العضوية: <b><?= e($target['username']) ?></b><?= $targetId === $me ? ' (عضويتك)' : '' ?></p>
    <p class="hint">يصل الطلب إلى المدير ونائبه، فإذا وافقوا تُقفل العضوية ولا يمكن الدخول بها.</p>
    <form method="post" action="<?= e(url('lock_request.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="target" value="<?= (int) $targetId ?>">
        <label for="reason">السبب</label>
        <textarea id="reason" name="reason" rows="3" maxlength="250" required></textarea>
        <div class="form-actions"><button class="btn btn-primary" type="submit">إرسال الطلب</button></div>
    </form>
</section>
<?php page_footer();
