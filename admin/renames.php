<?php
require __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();

if (is_post()) {
    csrf_verify();
    $action = post_str('action');
    $rid = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
    $req = db_row("SELECT id, user_id, new_name FROM rename_requests WHERE id = ? AND status = 'pending'", 'i', [$rid]);
    if (!$req) {
        flash('error', 'الطلب غير موجود أو تمت معالجته.');
        redirect('admin/renames.php');
    }
    if ($action === 'reject') {
        db_exec("UPDATE rename_requests SET status = 'rejected', handled_by = ? WHERE id = ?", 'ii', [$admin['id'], $rid]);
        flash('success', 'تم رفض الطلب.');
    } elseif ($action === 'approve') {
        if (!preg_match('/^(?=.{3,30}$)[\p{L}\p{N}_.\-]+(?: [\p{L}\p{N}_.\-]+)*$/u', $req['new_name'])
            || db_val('SELECT 1 FROM users WHERE username = ? AND id <> ?', 'si', [$req['new_name'], $req['user_id']]) !== null) {
            flash('error', 'الاسم لم يعد متاحًا. ارفض الطلب.');
        } else {
            try {
                $oldName = (string) db_val('SELECT username FROM users WHERE id = ?', 'i', [$req['user_id']]);
                db_exec('UPDATE users SET username = ? WHERE id = ?', 'si', [$req['new_name'], $req['user_id']]);
                db_exec("UPDATE rename_requests SET status = 'approved', handled_by = ?, old_name = ?, handled_at = NOW() WHERE id = ?", 'isi', [$admin['id'], $oldName, $rid]);
                flash('success', 'تم تغيير الاسم.');
            } catch (mysqli_sql_exception $ex) {
                if ((int) $ex->getCode() !== 1062) {
                    throw $ex;
                }
                flash('error', 'الاسم مستخدم الآن. ارفض الطلب.');
            }
        }
    }
    redirect('admin/renames.php');
}

$rows = db_all(
    "SELECT r.id, r.new_name, r.reason, r.created_at, u.id AS uid, u.username
       FROM rename_requests r JOIN users u ON u.id = r.user_id
      WHERE r.status = 'pending' ORDER BY r.id"
);

page_header('طلبات تغيير الأسماء', 'admin');
?>
<h1 class="page-title">لوحة الإدارة</h1>
<?php admin_nav('renames'); ?>

<?php if (!$rows): ?>
    <div class="panel empty"><p>لا توجد طلبات.</p></div>
<?php else: ?>
    <table class="board-table">
        <thead><tr><th scope="col">العضو</th><th scope="col">الاسم المطلوب</th><th scope="col">السبب</th><th scope="col" class="last">التاريخ</th><th scope="col"><span class="sr-only">إجراء</span></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= user_link($r['uid'], $r['username']) ?> <span class="meta">#<?= (int) $r['uid'] ?></span></td>
                <td><strong><?= e($r['new_name']) ?></strong></td>
                <td><?= e($r['reason']) ?></td>
                <td class="last"><span class="when"><?= e(fmt_when($r['created_at'])) ?></span></td>
                <td>
                    <?php foreach ([['approve', 'موافقة', 'btn btn-small btn-primary'], ['reject', 'رفض', 'btn btn-small']] as $b): ?>
                        <form method="post" action="<?= e(url('admin/renames.php')) ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="<?= e($b[0]) ?>">
                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <button type="submit" class="<?= e($b[2]) ?>"><?= e($b[1]) ?></button>
                        </form>
                    <?php endforeach; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php page_footer();
