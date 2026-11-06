<?php
/** POST-only: stop a member from messaging you, or allow them again. */
require __DIR__ . '/includes/bootstrap.php';

if (!is_post()) {
    redirect('settings.php');
}
$user = require_login();
csrf_verify();

$targetId = (int) (isset($_POST['user_id']) ? $_POST['user_id'] : 0);
$action = post_str('action') === 'unblock' ? 'unblock' : 'block';
$n = post_str('next');
$back = ($n !== '' && ($s = safe_next($n)) !== 'index.php') ? $s : 'settings.php';

if ($targetId === (int) $user['id']) {
    flash('error', 'لا يمكنك منع نفسك.');
    redirect($back);
}
$target = db_row('SELECT id, username FROM users WHERE id = ?', 'i', [$targetId]);
if (!$target) {
    fail_page('العضو غير موجود.', 404);
}
if ($action === 'block') {
    db_exec('INSERT IGNORE INTO user_blocks (user_id, blocked_id) VALUES (?, ?)', 'ii', [$user['id'], $targetId]);
    flash('success', 'لن يستطيع ' . $target['username'] . ' مراسلتك بعد الآن.');
} else {
    db_exec('DELETE FROM user_blocks WHERE user_id = ? AND blocked_id = ?', 'ii', [$user['id'], $targetId]);
    flash('success', 'يستطيع ' . $target['username'] . ' مراسلتك من جديد.');
}
redirect($back);
