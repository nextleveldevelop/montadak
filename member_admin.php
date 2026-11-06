<?php
/** إجراءات إشرافية على عضو: ترشيح للإشراف (المشرف يرشّح والمدير يعيّن مباشرة)، قفل/فتح، إزالة إشراف، نائب مدير. */
require __DIR__ . '/includes/bootstrap.php';
$actor = require_login();
if (!is_post()) {
    redirect('index.php');
}
csrf_verify();
$back = safe_next(post_str('back'));
$action = post_str('action');
$tid = (int) (isset($_POST['user_id']) ? $_POST['user_id'] : 0);
$t = db_row('SELECT id, username, role, is_deputy, is_banned FROM users WHERE id = ?', 'i', [$tid]);
$isAdmin = is_admin();
$mySup = supervised_forums();
if (!$t || $tid === (int) $actor['id'] || (!$isAdmin && !$mySup)) {
    fail_page('طلب غير صالح.', 403);
}
$targetIsAdmin = $t['role'] === 'admin';
$fail = function ($msg) use ($back) {
    flash('error', $msg);
    redirect($back);
};
if ($targetIsAdmin && $action !== 'remove_deputy') {
    $fail('لا يمكن تنفيذ هذا الإجراء على مدير.');
}
$forumId = (int) (isset($_POST['forum_id']) ? $_POST['forum_id'] : 0);
$mine = array_keys($mySup); // supervised_forums() returns [id => name]

switch ($action) {
    case 'nominate':
        if (!in_array($forumId, array_map('intval', $mine), true)) {
            $fail('اختر منتدى أنت مشرف عليه.');
        }
        if (db_val("SELECT 1 FROM forum_staff WHERE forum_id = ? AND user_id = ?", 'ii', [$forumId, $tid]) !== null) {
            $fail('العضو مشرف بالفعل في هذا المنتدى.');
        }
        if ($isAdmin) {
            apply_nomination(['kind' => 'supervisor', 'forum_id' => $forumId, 'nominee_id' => $tid]);
            mod_log_add($tid, $actor['id'], 'staff', 'تعيين مشرف في منتدى #' . $forumId);
            notify($tid, $actor['id'], 'follow', 'تم تعيينك مشرفًا على أحد المنتديات.', 'forum.php?id=' . $forumId);
            flash('success', 'تم تعيين العضو مشرفًا.');
        } else {
            if (db_val("SELECT 1 FROM staff_nominations WHERE nominee_id = ? AND forum_id = ? AND status = 'pending_admin'", 'ii', [$tid, $forumId]) !== null) {
                $fail('يوجد ترشيح قيد المراجعة لهذا العضو.');
            }
            db_exec("INSERT INTO staff_nominations (kind, nominee_id, nominator_id, forum_id, reason, status) VALUES ('supervisor', ?, ?, ?, ?, 'pending_admin')", 'iiis', [$tid, $actor['id'], $forumId, mb_substr(post_str('reason'), 0, 250)]);
            foreach (db_all("SELECT id FROM users WHERE role = 'admin'") as $ad) {
                notify($ad['id'], $actor['id'], 'follow', $actor['username'] . ' رشّح ' . $t['username'] . ' للإشراف.', 'admin/requests.php');
            }
            flash('success', 'تم إرسال الترشيح إلى المدير.');
        }
        break;
    case 'lock':
    case 'unlock':
        if (!$isAdmin) {
            $fail('القفل المباشر للمدير فقط. يمكنك طلب القفل من المدير.');
        }
        db_exec('UPDATE users SET is_banned = ? WHERE id = ?', 'ii', [$action === 'lock' ? 1 : 0, $tid]);
        mod_log_add($tid, $actor['id'], $action, $action === 'lock' ? 'قفل العضوية' : 'إعادة فتح العضوية');
        flash('success', $action === 'lock' ? 'تم قفل العضوية.' : 'تمت إعادة فتح العضوية.');
        break;
    case 'remove_super':
        if (!$isAdmin) {
            $fail('إزالة الإشراف للمدير فقط.');
        }
        db_exec("DELETE FROM forum_staff WHERE forum_id = ? AND user_id = ? AND level = 'supervisor'", 'ii', [$forumId, $tid]);
        mod_log_add($tid, $actor['id'], 'staff', 'إزالة الإشراف من منتدى #' . $forumId);
        flash('success', 'تمت إزالة الإشراف.');
        break;
    case 'make_deputy':
        if (!is_full_admin() || $targetIsAdmin) {
            $fail('ترقية نائب المدير للمدير فقط.');
        }
        db_exec("UPDATE users SET role = 'admin', is_deputy = 1 WHERE id = ? AND role = 'user'", 'i', [$tid]);
        mod_log_add($tid, $actor['id'], 'staff', 'ترقية إلى نائب مدير');
        flash('success', 'تمت ترقية العضو إلى نائب مدير (لا يستطيع أن يصبح المدير الأصلي).');
        break;
    case 'remove_deputy':
        if (!is_full_admin() || !$targetIsAdmin || (int) $t['is_deputy'] !== 1 || is_owner($tid)) {
            $fail('لا يمكن تنفيذ هذا الإجراء.');
        }
        db_exec("UPDATE users SET role = 'user', is_deputy = 0 WHERE id = ?", 'i', [$tid]);
        mod_log_add($tid, $actor['id'], 'staff', 'إزالة نيابة المدير');
        flash('success', 'تمت إزالة نيابة المدير.');
        break;
    default:
        $fail('إجراء غير معروف.');
}
redirect($back);
