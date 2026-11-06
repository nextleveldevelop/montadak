<?php
/** أعجبني / إلغاء الإعجاب لمنشور قصير. */
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
if (!is_post()) {
    redirect('index.php');
}
csrf_verify();
$id = (int) post_str('id');
if (db_val('SELECT 1 FROM user_posts WHERE id = ?', 'i', [$id]) === null) {
    fail_page('المنشور غير موجود.', 404);
}
if (mini_liked($id)) {
    db_exec('DELETE FROM mini_likes WHERE post_id = ? AND user_id = ?', 'ii', [$id, $user['id']]);
} else {
    db_exec('INSERT IGNORE INTO mini_likes (post_id, user_id) VALUES (?, ?)', 'ii', [$id, $user['id']]);
}
redirect(safe_next(post_str('next')));
