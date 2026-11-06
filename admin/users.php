<?php
require __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();

$q = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 50) : '';
$flt = isset($_GET['f']) && in_array($_GET['f'], ['banned', 'muted', 'warned', 'sanctioned'], true) ? $_GET['f'] : '';
$fForum = (int) (isset($_GET['forum']) ? $_GET['forum'] : 0);
$fBy = isset($_GET['by']) && is_string($_GET['by']) ? mb_substr(trim($_GET['by']), 0, 50) : '';
$qs = http_build_query(array_filter(['q' => $q, 'f' => $flt, 'forum' => $fForum ?: '', 'by' => $fBy], function ($v) { return $v !== '' && $v !== null; }));

if (is_post()) {
    csrf_verify();
    $action = post_str('action');
    $targetId = (int) (isset($_POST['user_id']) ? $_POST['user_id'] : 0);
    $back = 'admin/users.php';

    if ($targetId === (int) $admin['id']) {
        flash('error', 'لا يمكنك تغيير صلاحيات حسابك أو حظره بنفسك.');
        redirect($back);
    }
    $actorDeputy = (int) db_val('SELECT is_deputy FROM users WHERE id = ?', 'i', [$admin['id']]) === 1;
    $tRow = db_row('SELECT role, is_deputy FROM users WHERE id = ?', 'i', [$targetId]);
    if ($actorDeputy && $tRow && $tRow['role'] === 'admin' && (int) $tRow['is_deputy'] === 0) {
        flash('error', 'لا يمكن لنائب المدير تعديل رتبة المدير الأصلي أو حظره.');
        redirect($back);
    }
    $updates = [
        'ban'     => ['UPDATE users SET is_banned = 1 WHERE id = ?', 'تم حظر العضو.'],
        'unban'   => ['UPDATE users SET is_banned = 0 WHERE id = ?', 'تم رفع الحظر.'],
        'make_deputy' => ["UPDATE users SET role = 'admin', is_deputy = 1 WHERE id = ? AND role = 'user'", 'تمت ترقية العضو إلى نائب مدير.'],
        'demote'  => ["UPDATE users SET role = 'user', is_deputy = 0 WHERE id = ?", 'تمت إعادة العضو إلى عضو عادي.'],
    ];
    if ($targetId === 1 && $action !== 'unban') {
        flash('error', 'لا يمكن تعديل المدير الأصلي.');
        redirect($back);
    }
    if (isset($updates[$action])) {
        db_exec($updates[$action][0], 'i', [$targetId]);
        flash('success', $updates[$action][1]);
    }
    redirect($back);
}

$perPage = 30;
$where = '';
$types = '';
$params = [];
$conds = [];
if ($q !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $conds[] = '(username LIKE ? OR email LIKE ?)';
    $types .= 'ss';
    $params = array_merge($params, [$like, $like]);
}
if ($flt === 'banned') {
    $conds[] = 'is_banned = 1';
} elseif ($flt === 'muted') {
    $conds[] = 'muted_until > UTC_TIMESTAMP()';
}
if ($flt === 'warned' || $flt === 'sanctioned' || $fForum || $fBy !== '') {
    $sc = 'EXISTS (SELECT 1 FROM sanctions s WHERE s.user_id = users.id';
    if ($flt === 'warned') { $sc .= " AND s.type = 'warning'"; }
    if ($flt === 'muted') { $sc .= " AND s.type = 'mute'"; }
    if ($fForum) { $sc .= ' AND s.forum_id = ?'; $types .= 'i'; $params[] = $fForum; }
    if ($fBy !== '') { $sc .= ' AND s.by_user_id IN (SELECT id FROM users WHERE username = ?)'; $types .= 's'; $params[] = $fBy; }
    $conds[] = $sc . ')';
}
if ($conds) {
    $where = 'WHERE ' . implode(' AND ', $conds);
}
$total = (int) db_val('SELECT COUNT(*) FROM users ' . $where, $types, $params);
$pages = max(1, (int) ceil($total / $perPage));
$page = min(page_param(), $pages);
$users = db_all(
    'SELECT id, username, email, role, is_deputy, is_banned, created_at, last_seen FROM users ' . $where . ' ORDER BY id DESC LIMIT ? OFFSET ?',
    $types . 'ii',
    array_merge($params, [$perPage, ($page - 1) * $perPage])
);

$sanBy = [];
if ($users) {
    $ids = implode(',', array_map('intval', array_column($users, 'id')));
    foreach (db_all("SELECT s.user_id, s.type, s.created_at, f.name AS fname, b.username AS byname FROM sanctions s LEFT JOIN forums f ON f.id = s.forum_id LEFT JOIN users b ON b.id = s.by_user_id WHERE s.user_id IN ($ids) ORDER BY s.id DESC") as $s) {
        if (!isset($sanBy[$s['user_id']]) || count($sanBy[$s['user_id']]) < 3) { $sanBy[$s['user_id']][] = $s; }
    }
}
$allForums = db_all('SELECT id, name FROM forums ORDER BY sort_order, id');
$stNames = ['warning' => 'تحذير', 'mute' => 'كتم', 'unmute' => 'رفع كتم'];
page_header('إدارة الأعضاء', 'admin');
?>
<h1 class="page-title">لوحة الإدارة</h1>
<?php admin_nav('users'); ?>

<form class="pg-bar" action="<?= e(url('admin/users.php')) ?>" method="get" role="search">
    <label class="pg-search"><?= icon('search') ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="اسم العضو أو بريده" maxlength="50"></label>
    <select name="f" aria-label="الحالة">
        <option value="">كل الأعضاء</option>
        <?php foreach (['banned' => 'المحظورون / المقفلة عضويتهم', 'muted' => 'المكتومون حاليًا', 'warned' => 'عليهم تحذير', 'sanctioned' => 'عليهم أي عقوبة'] as $k => $lbl): ?>
            <option value="<?= $k ?>"<?= $flt === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="forum" aria-label="المنتدى"><option value="0">كل المنتديات</option><?php foreach ($allForums as $af): ?><option value="<?= (int) $af['id'] ?>"<?= $fForum === (int) $af['id'] ? ' selected' : '' ?>><?= e($af['name']) ?></option><?php endforeach; ?></select>
    <input type="text" name="by" value="<?= e($fBy) ?>" placeholder="نُفّذت بواسطة (اسم المشرف)" maxlength="50">
    <button type="submit" class="btn btn-primary">تصفية</button>
</form>

<table class="board-table users-table">
    <thead>
        <tr>
            <th scope="col">العضو</th>
            <th scope="col" class="author-col">البريد</th>
            <th scope="col">الصلاحية</th>
            <th scope="col">العقوبات</th>
            <th scope="col" class="last">الانضمام</th>
            <th scope="col">إجراءات</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <?php $isMe = (int) $u['id'] === (int) $admin['id']; ?>
        <tr>
            <td class="main">
                <?= user_link($u['id'], $u['username']) ?> <span class="meta">#<?= (int) $u['id'] ?></span>
                <?php if ($u['is_banned']): ?><span class="badge badge-danger">محظور</span><?php endif; ?>
            </td>
            <td class="author-col" dir="ltr"><?= e($u['email']) ?></td>
            <td><?= e(role_label($u['role'])) ?></td>
            <td class="meta">
                <?php if (!empty($u['is_banned'])): ?><span class="pg-tag">مقفل</span><?php endif; ?>
                <?php foreach (isset($sanBy[$u['id']]) ? $sanBy[$u['id']] : [] as $s): ?>
                    <div><?= e($stNames[$s['type']]) ?> · <?= e($s['fname'] ?: '—') ?> · <?= e($s['byname'] ?: '—') ?></div>
                <?php endforeach; ?>
            </td>
            <td class="last"><time><?= e(fmt_time($u['created_at'], 'Y-m-d')) ?></time></td>
            <td>
                <?php if ($isMe): ?>
                    <span class="meta">حسابك</span>
                <?php else: ?>
                    <?php
                    $buttons = [
                        $u['is_banned'] ? ['unban', 'رفع الحظر', ''] : ['ban', 'حظر', 'هل تريد حظر ' . $u['username'] . '؟'],
                        $u['role'] === 'admin' ? ['demote', 'إلغاء النيابة/الإدارة', 'إلغاء صلاحيات ' . $u['username'] . '؟'] : ['make_deputy', 'جعله نائب مدير', 'ترقية ' . $u['username'] . ' إلى نائب مدير؟'],
                    ];
                    if ($u['role'] !== 'admin') {
                                            }
                    foreach ($buttons as $b): ?>
                        <form method="post" action="<?= e(url('admin/users.php' . ($qs !== '' ? '?' . $qs : ''))) ?>" class="inline-form"<?= $b[2] !== '' ? ' data-confirm="' . e($b[2]) . '"' : '' ?>>
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                            <input type="hidden" name="action" value="<?= e($b[0]) ?>">
                            <button type="submit" class="btn btn-small"><?= e($b[1]) ?></button>
                        </form>
                    <?php endforeach; ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?= pagination_html($page, $pages, 'admin/users.php' . ($qs !== '' ? '?' . $qs : '')) ?>
<?php page_footer();
