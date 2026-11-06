<?php
/** متابعة / إلغاء متابعة وسم. */
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
if (!is_post()) {
    redirect('index.php');
}
csrf_verify();
$tag = post_str('tag');
if (!in_array($tag, mini_tags(), true)) {
    fail_page('الوسم غير موجود.', 404);
}
if (is_following_tag($tag)) {
    db_exec('DELETE FROM tag_follows WHERE user_id = ? AND tag = ?', 'is', [$user['id'], $tag]);
    flash('success', 'تم إلغاء متابعة الوسم.');
} else {
    db_exec('INSERT IGNORE INTO tag_follows (user_id, tag) VALUES (?, ?)', 'is', [$user['id'], $tag]);
    flash('success', 'أصبحت تتابع الوسم #' . $tag . ' وسيصلك إشعار بكل منشور جديد.');
}
redirect(safe_next(post_str('next')));
