<?php
/** JSON: unread notification count + the newest ones (polled every couple of seconds). */
require __DIR__ . '/includes/bootstrap.php';
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$user = current_user();
if ($user === null) {
    echo json_encode(['unread' => 0, 'items' => []]);
    exit;
}
$uid = (int) $user['id'];
if (is_post()) {
    $sent = isset($_POST['csrf']) ? $_POST['csrf'] : '';
    if (is_string($sent) && hash_equals(csrf_token(), $sent)) {
        try {
            db_exec('UPDATE notifications SET is_read = 1 WHERE user_id = ?', 'i', [$uid]);
        } catch (Throwable $e) {
        }
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode(['ok' => true]);
    exit;
}
session_write_close();
$after = get_int('after');
if (isset($_GET['a']) && $_GET['a'] === 'list') {
    $rows = [];
    try {
        foreach (db_all('SELECT id, type, text, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 15', 'i', [$uid]) as $r) {
            $rows[] = ['id' => (int) $r['id'], 'type' => $r['type'], 'text' => $r['text'], 'read' => (int) $r['is_read'], 'when' => fmt_when($r['created_at']), 'url' => url('notifications.php?go=' . (int) $r['id'])];
        }
    } catch (Throwable $e) {
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode(['list' => $rows], JSON_UNESCAPED_UNICODE);
    exit;
}
try {
    $unread = (int) db_val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', 'i', [$uid]);
    $max = (int) db_val('SELECT COALESCE(MAX(id), 0) FROM notifications WHERE user_id = ?', 'i', [$uid]);
    $items = [];
    if ($after > 0) {
        foreach (db_all('SELECT id, text, type FROM notifications WHERE user_id = ? AND id > ? AND is_read = 0 ORDER BY id LIMIT 5', 'ii', [$uid, $after]) as $r) {
            $items[] = ['id' => (int) $r['id'], 'text' => $r['text'], 'type' => $r['type'], 'url' => url('notifications.php?go=' . (int) $r['id'])];
        }
    }
    $out = ['unread' => $unread, 'max' => $max, 'items' => $items];
} catch (Throwable $e) {
    $out = ['unread' => 0, 'max' => 0, 'items' => []];
}
while (ob_get_level() > 0) {
    ob_end_clean();
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
