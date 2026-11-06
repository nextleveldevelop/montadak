<?php
/** JSON endpoint for the messenger-style live chat (private messages, same table as the inbox). */
require __DIR__ . '/includes/bootstrap.php';

ob_start(); // keep stray PHP notices out of the JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function live_out($data, $code = 200)
{
    http_response_code($code);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function live_text($body)
{
    return mb_substr(rich_plain($body), 0, 2000);
}

function live_row($m, $me)
{
    return ['id' => (int) $m['id'], 'mine' => (int) $m['sender_id'] === $me, 'text' => live_text($m['body']), 'ts' => (int) strtotime($m['created_at'] . ' UTC'), 'read' => !empty($m['is_read']) ? 1 : 0];
}

$user = current_user();
if ($user === null) {
    live_out(['error' => 'سجّل الدخول.'], 401);
}
$me = (int) $user['id'];
$x = user_extras();
if (empty($x['live_msgs'])) {
    live_out(['error' => 'المحادثة الفورية غير مفعّلة.'], 403);
}

if (is_post()) {
    $sent = isset($_POST['csrf']) ? $_POST['csrf'] : '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        live_out(['error' => 'انتهت الجلسة. أعد تحميل الصفحة.'], 419);
    }
    $to = (int) (isset($_POST['to']) ? $_POST['to'] : 0);
    if (post_str('act') === 'delete') {
        // remove the whole conversation from MY side only
        if ($to > 0) {
            db_exec('UPDATE messages SET sender_deleted = 1 WHERE sender_id = ? AND receiver_id = ?', 'ii', [$me, $to]);
            db_exec('UPDATE messages SET receiver_deleted = 1 WHERE sender_id = ? AND receiver_id = ?', 'ii', [$to, $me]);
        }
        live_out(['ok' => true]);
    }
    if (post_str('act') === 'typing') {
        if ($to > 0) {
            try {
                db_exec('INSERT INTO chat_typing (user_id, to_id, at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE at = NOW()', 'ii', [$me, $to]);
            } catch (Throwable $e) {
            }
        }
        live_out(['ok' => true]);
    }
    $body = post_str('body');
    $len = mb_strlen($body);
    $rcpt = $to > 0 ? db_row('SELECT id FROM users WHERE id = ?', 'i', [$to]) : null;
    if (!$rcpt || $to === $me) {
        live_out(['error' => 'عضو غير صالح.'], 422);
    }
    if ($len < 1 || $len > 1000) {
        live_out(['error' => 'الرسالة يجب أن تكون بين 1 و1000 حرف.'], 422);
    }
    if (is_blocked_by($to, $me) && !is_staff()) {
        live_out(['error' => 'هذا العضو منعك من مراسلته.'], 403);
    }
    $block = mute_message();
    $nowMs = microtime(true);
    if ($block === null && isset($_SESSION['live_last']) && $nowMs - (float) $_SESSION['live_last'] < 0.7) {
        $block = 'انتظر لحظة قبل الإرسال مرة أخرى.';
    }
    if ($block !== null) {
        live_out(['error' => $block], 429);
    }
    try {
    $id = db_exec('INSERT INTO messages (sender_id, receiver_id, subject, body) VALUES (?, ?, ?, ?)', 'iiss', [$me, $to, 'رسالة فورية', $body]);
} catch (Throwable $ex) {
    live_out(['error' => 'تعذر حفظ الرسالة في قاعدة البيانات.'], 500);
}
    $_SESSION['live_last'] = $nowMs;
    $row = db_row('SELECT id, sender_id, body, created_at, is_read FROM messages WHERE sender_id = ? AND receiver_id = ? ORDER BY id DESC LIMIT 1', 'ii', [$me, $to]);
    live_out(['ok' => true, 'msg' => live_row($row, $me)]);
}

session_write_close(); // polling must never hold the session lock
$a = isset($_GET['a']) && is_string($_GET['a']) ? $_GET['a'] : '';
$with = get_int('with');
$after = get_int('after');
$since = isset($_GET['since']) ? (int) $_GET['since'] : -1;

$pair = '((m.sender_id = ? AND m.receiver_id = ? AND m.sender_deleted = 0) OR (m.sender_id = ? AND m.receiver_id = ? AND m.receiver_deleted = 0))';

if ($a === 'list') {
    $rows = db_all(
        'SELECT u.id, u.username, u.avatar, x.last_id,
                (SELECT COUNT(*) FROM messages mm WHERE mm.sender_id = u.id AND mm.receiver_id = ? AND mm.is_read = 0 AND mm.receiver_deleted = 0) AS unread,
                (SELECT body FROM messages WHERE id = x.last_id) AS body
           FROM (SELECT IF(sender_id = ?, receiver_id, sender_id) AS pid, MAX(id) AS last_id
                   FROM messages WHERE (sender_id = ? AND sender_deleted = 0) OR (receiver_id = ? AND receiver_deleted = 0)
                  GROUP BY pid ORDER BY last_id DESC LIMIT 8) x
           JOIN users u ON u.id = x.pid ORDER BY x.last_id DESC',
        'iiii',
        [$me, $me, $me, $me]
    );
    $out = [];
    foreach ($rows as $r) {
        $out[] = ['id' => (int) $r['id'], 'name' => $r['username'], 'avatar' => preg_match('/^[a-f0-9]{24}\.png$/', (string) $r['avatar']) ? url('assets/images/avatars/' . $r['avatar']) : url('assets/images/default_avatar.svg'),
            'unread' => (int) $r['unread'], 'text' => mb_substr(live_text($r['body']), 0, 60)];
    }
    live_out(['list' => $out]);
}

if ($a === 'history' && $with > 0) {
    $rows = db_all('SELECT m.id, m.sender_id, m.body, m.created_at, m.is_read FROM messages m WHERE ' . $pair . ' ORDER BY m.id DESC LIMIT 30', 'iiii', [$me, $with, $with, $me]);
    db_exec('UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND receiver_deleted = 0', 'ii', [$with, $me]);
    $out = [];
    foreach (array_reverse($rows) as $m) {
        $out[] = live_row($m, $me);
    }
    $seen = (int) db_val('SELECT COALESCE(MAX(id), 0) FROM messages WHERE sender_id = ? AND receiver_id = ? AND is_read = 1', 'ii', [$me, $with]);
    live_out(['msgs' => $out, 'seen_upto' => $seen]);
}

if ($a === 'poll') {
    $max = (int) db_val('SELECT COALESCE(MAX(id), 0) FROM messages');
    $unread = (int) db_val('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0 AND receiver_deleted = 0', 'i', [$me]);
    $incoming = [];
    if ($since >= 0) {
        $rows = db_all('SELECT m.id, m.sender_id, m.body, u.username FROM messages m JOIN users u ON u.id = m.sender_id WHERE m.receiver_id = ? AND m.id > ? AND m.receiver_deleted = 0 ORDER BY m.id', 'ii', [$me, $since]);
        foreach ($rows as $r) {
            $incoming[] = ['id' => (int) $r['id'], 'from' => (int) $r['sender_id'], 'name' => $r['username'], 'text' => mb_substr(live_text($r['body']), 0, 80)];
        }
    }
    $msgs = [];
    if ($with > 0) {
        $rows = db_all('SELECT m.id, m.sender_id, m.body, m.created_at, m.is_read FROM messages m WHERE ' . $pair . ' AND m.id > ? ORDER BY m.id', 'iiiii', [$me, $with, $with, $me, $after]);
        foreach ($rows as $m) {
            $msgs[] = live_row($m, $me);
        }
        if ($rows) {
            db_exec('UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND receiver_deleted = 0', 'ii', [$with, $me]);
            $unread = (int) db_val('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0 AND receiver_deleted = 0', 'i', [$me]);
        }
    }
    $seen = $with > 0 ? (int) db_val('SELECT COALESCE(MAX(id), 0) FROM messages WHERE sender_id = ? AND receiver_id = ? AND is_read = 1', 'ii', [$me, $with]) : 0;
        $typing = false;
    $online = false;
    if ($with > 0) {
        try {
            $typing = db_val('SELECT 1 FROM chat_typing WHERE user_id = ? AND to_id = ? AND at >= (NOW() - INTERVAL 5 SECOND)', 'ii', [$with, $me]) !== null;
            $online = db_val('SELECT 1 FROM users WHERE id = ? AND hide_online = 0 AND last_seen >= (NOW() - INTERVAL ' . (int) ONLINE_WINDOW_MINUTES . ' MINUTE)', 'i', [$with]) !== null;
        } catch (Throwable $e) {
        }
    }
    live_out(['max_id' => $max, 'unread' => $unread, 'incoming' => $incoming, 'msgs' => $msgs, 'seen_upto' => $seen, 'typing' => $typing, 'online' => $online]);
}

live_out(['error' => 'طلب غير معروف.'], 400);
