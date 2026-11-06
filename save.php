<?php
/** حفظ / إلغاء حفظ موضوع أو رد في المحفظة الشخصية. */
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
if (!is_post()) {
    redirect('wallet.php');
}
csrf_verify();
$type = in_array(post_str('type'), ['reply', 'mini'], true) ? post_str('type') : 'thread';
$id = (int) post_str('id');
$next = safe_next(post_str('next'));
if ($type === 'mini') {
    if (db_val('SELECT 1 FROM user_posts WHERE id = ?', 'i', [$id]) === null) {
        fail_page('المنشور غير موجود.', 404);
    }
    $tid = 0;
} elseif ($type === 'reply') {
    $tid = (int) db_val('SELECT thread_id FROM replies WHERE id = ?', 'i', [$id]);
} else {
    $tid = $id;
}
$t = $type === 'mini' ? ['ok' => 1] : ($tid > 0 ? db_row('SELECT t.id, t.is_hidden, t.user_id, f.category, f.access FROM threads t JOIN forums f ON f.id = t.forum_id WHERE t.id = ?', 'i', [$tid]) : null);
if (!$t || ($type !== 'mini' && (!forum_access_ok($t) || !can_view_thread($t)))) {
    fail_page('العنصر غير موجود.', 404);
}
if (is_saved($type, $id)) {
    db_exec('DELETE FROM saved_items WHERE user_id = ? AND item_type = ? AND item_id = ?', 'isi', [$user['id'], $type, $id]);
    flash('success', 'أُزيل من المحفظة الشخصية.');
} else {
    db_exec('INSERT IGNORE INTO saved_items (user_id, item_type, item_id) VALUES (?, ?, ?)', 'isi', [$user['id'], $type, $id]);
    flash('success', 'تم الحفظ في المحفظة الشخصية.');
}
redirect($next);
