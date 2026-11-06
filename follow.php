<?php
/** POST-only: follow / unfollow a member, a forum or a topic. */
require __DIR__ . '/includes/bootstrap.php';
if (!is_post()) {
    redirect('follows.php');
}
$user = require_login();
csrf_verify();
$type = post_str('type');
$id = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
$tables = ['user' => 'SELECT id FROM users WHERE id = ?', 'forum' => 'SELECT id FROM forums WHERE id = ?', 'thread' => 'SELECT id FROM threads WHERE id = ? AND is_hidden = 0'];
if (!isset($tables[$type]) || db_val($tables[$type], 'i', [$id]) === null || ($type === 'user' && $id === (int) $user['id'])) {
    fail_page('لا يمكن متابعة هذا العنصر.', 400);
}
if (is_following($type, $id)) {
    db_exec('DELETE FROM follows WHERE user_id = ? AND target_type = ? AND target_id = ?', 'isi', [$user['id'], $type, $id]);
    flash('success', 'تم إلغاء المتابعة.');
} else {
    db_exec('INSERT IGNORE INTO follows (user_id, target_type, target_id) VALUES (?, ?, ?)', 'isi', [$user['id'], $type, $id]);
    if ($type === 'user') {
        notify($id, $user['id'], 'follow', $user['username'] . ' بدأ بمتابعتك', 'profile.php?id=' . (int) $user['id']);
    }
    flash('success', 'تمت المتابعة. ستصلك إشعارات بجديد ما تتابعه.');
}
$n = post_str('next');
if (BASE !== '' && strpos($n, BASE . '/') === 0) {
    $n = substr($n, strlen(BASE));
}
$n = ltrim($n, '/');
redirect(($n !== '' && ($s = safe_next($n)) !== 'index.php') ? $s : 'follows.php');
