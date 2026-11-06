<?php
/**
 * POST-only: staff act on members.
 *  - warn: one forum (supervisor of that forum or admin)
 *  - mute / unmute: one forum, a group of forums, or all forums (all = admin only).
 *    A supervisor can only mute in forums he supervises.
 */
require __DIR__ . '/includes/bootstrap.php';

if (!is_post()) {
    redirect('index.php');
}
$actor = require_login();
csrf_verify();

$back = safe_next(post_str('back'));
$action = post_str('action');
$targetId = (int) (isset($_POST['user_id']) ? $_POST['user_id'] : 0);
$reason = post_str('reason');

$target = db_row('SELECT id, username, role FROM users WHERE id = ?', 'i', [$targetId]);
if (!$target || !in_array($action, ['warn', 'mute', 'unmute'], true)) {
    fail_page('طلب غير صالح.', 400);
}
if ($targetId === (int) $actor['id'] || $target['role'] === 'admin') {
    fail_page('لا يمكن تنفيذ هذا الإجراء على هذا العضو.', 403);
}
$isAdm = is_admin();

// Forums the actor may act in
$allowed = supervised_forums(); // [id => name]
if (!$allowed) {
    fail_page('ليس لديك صلاحية تنفيذ هذا الإجراء.', 403);
}
$fail = function ($msg) use ($back) {
    flash('error', $msg);
    redirect($back);
};

if ($action !== 'unmute' && (mb_strlen($reason) < 3 || mb_strlen($reason) > 255)) {
    $fail('اكتب سببًا واضحًا (من 3 إلى 255 حرفًا).');
}

if ($action === 'warn') {
    $forumId = (int) (isset($_POST['forum_id']) ? $_POST['forum_id'] : 0);
    if (!isset($allowed[$forumId])) {
        $fail('اختر منتدى أنت مشرف عليه.');
    }
    db_exec("INSERT INTO sanctions (user_id, by_user_id, type, reason, forum_id) VALUES (?, ?, 'warning', ?, ?)", 'iisi', [$targetId, $actor['id'], $reason, $forumId]);
    db_exec('INSERT INTO messages (sender_id, receiver_id, subject, body) VALUES (?, ?, ?, ?)', 'iiss',
        [$actor['id'], $targetId, 'تحذير من الإشراف', "وصلك تحذير من أحد المشرفين.\nالسبب: " . $reason]);
    flash('success', 'تم توجيه تحذير إلى ' . $target['username'] . '.');
    redirect($back);
}

// mute / unmute scope
$scopeAll = post_str('scope') === 'all';
$ids = [];
if ($scopeAll) {
    if (!$isAdm) {
        $fail('الكتم في جميع المنتديات للمدير فقط.');
    }
} else {
    $posted = isset($_POST['forum_ids']) && is_array($_POST['forum_ids']) ? $_POST['forum_ids'] : [];
    foreach ($posted as $v) {
        $v = (int) $v;
        if (!isset($allowed[$v])) {
            $fail('لا يمكنك الكتم في منتدى لا تشرف عليه.');
        }
        $ids[$v] = $v;
    }
    if (!$ids) {
        $fail('اختر منتدى واحدًا على الأقل.');
    }
}

if ($action === 'mute') {
    $days = max(1, min(30, (int) (isset($_POST['days']) ? $_POST['days'] : 1)));
    $until = gmdate('Y-m-d H:i:s', time() + $days * 86400);
    db_transaction(function () use ($scopeAll, $ids, $targetId, $actor, $reason, $until, $days) {
        if ($scopeAll) {
            db_exec("INSERT INTO sanctions (user_id, by_user_id, type, reason, until, forum_id) VALUES (?, ?, 'mute', ?, ?, NULL)", 'iiss', [$targetId, $actor['id'], $reason, $until]);
            db_exec('UPDATE users SET muted_until = ? WHERE id = ?', 'si', [$until, $targetId]);
            return;
        }
        foreach ($ids as $fid) {
            db_exec("INSERT INTO sanctions (user_id, by_user_id, type, reason, until, forum_id) VALUES (?, ?, 'mute', ?, ?, ?)", 'iissi', [$targetId, $actor['id'], $reason, $until, $fid]);
            db_exec('INSERT INTO forum_bans (user_id, forum_id, until, reason, by_id) VALUES (?, ?, ?, ?, ?)', 'iissi', [$targetId, $fid, $until, mb_substr($reason, 0, 255), $actor['id']]);
        }
    });
    db_exec('INSERT INTO messages (sender_id, receiver_id, subject, body) VALUES (?, ?, ?, ?)', 'iiss',
        [$actor['id'], $targetId, 'تم كتمك مؤقتًا', 'لن تستطيع المشاركة ' . ($scopeAll ? 'في جميع المنتديات' : 'في ' . count($ids) . ' منتدى') . ' لمدة ' . $days . " يومًا.\nالسبب: " . $reason]);
    flash('success', 'تم كتم ' . $target['username'] . ' لمدة ' . $days . ' يومًا' . ($scopeAll ? ' في جميع المنتديات.' : ' في ' . count($ids) . ' منتدى.'));
} else {
    db_transaction(function () use ($scopeAll, $ids, $targetId, $actor) {
        if ($scopeAll) {
            db_exec('UPDATE users SET muted_until = NULL WHERE id = ?', 'i', [$targetId]);
            db_exec('UPDATE forum_bans SET until = NOW() WHERE user_id = ? AND until > NOW()', 'i', [$targetId]);
            db_exec("INSERT INTO sanctions (user_id, by_user_id, type, reason, forum_id) VALUES (?, ?, 'unmute', '', NULL)", 'ii', [$targetId, $actor['id']]);
            return;
        }
        foreach ($ids as $fid) {
            db_exec('UPDATE forum_bans SET until = NOW() WHERE user_id = ? AND forum_id = ? AND until > NOW()', 'ii', [$targetId, $fid]);
            db_exec("INSERT INTO sanctions (user_id, by_user_id, type, reason, forum_id) VALUES (?, ?, 'unmute', '', ?)", 'iii', [$targetId, $actor['id'], $fid]);
        }
    });
    flash('success', 'تم رفع الكتم عن ' . $target['username'] . '.');
}
redirect($back);
