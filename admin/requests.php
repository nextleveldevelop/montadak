<?php
require __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();

if (is_post()) {
    csrf_verify();
    $a = post_str('action');
    $id = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
    if ($a === 'approve_user') {
        db_exec('UPDATE users SET is_approved = 1 WHERE id = ?', 'i', [$id]);
        flash('success', 'تمت الموافقة على العضوية.');
    } elseif ($a === 'reject_user') {
        db_exec("DELETE FROM users WHERE id = ? AND is_approved = 0 AND role <> 'admin'", 'i', [$id]);
        flash('success', 'تم رفض العضوية الجديدة وحذفها.');
    } elseif ($a === 'approve_lock' || $a === 'reject_lock') {
        $r = db_row("SELECT id, target_id, requester_id FROM account_requests WHERE id = ? AND status = 'pending'", 'i', [$id]);
        if ($r) {
            if ($a === 'approve_lock' && (int) $r['target_id'] !== (int) $admin['id'] && db_val('SELECT role FROM users WHERE id = ?', 'i', [$r['target_id']]) !== 'admin') {
                db_exec('UPDATE users SET is_banned = 1 WHERE id = ?', 'i', [$r['target_id']]);
                mod_log_add($r['target_id'], $admin['id'], 'lock', 'قفل عضوية بعد موافقة المدير على طلب ' . (string) db_val('SELECT username FROM users WHERE id = ?', 'i', [$r['requester_id']]));
            }
            db_exec('UPDATE account_requests SET status = ?, decided_by = ? WHERE id = ?', 'sii', [$a === 'approve_lock' ? 'approved' : 'rejected', $admin['id'], $id]);
            notify($r['requester_id'], $admin['id'], 'follow', $a === 'approve_lock' ? 'تمت الموافقة على طلب قفل العضوية.' : 'تم رفض طلب قفل العضوية.', 'index.php');
            flash('success', $a === 'approve_lock' ? 'تم قفل العضوية.' : 'تم رفض الطلب.');
        }
    } elseif ($a === 'lock_now' || $a === 'unlock_now') {
        $u = db_row('SELECT id, username FROM users WHERE username = ?', 's', [post_str('username')]);
        $uRole = $u ? (string) db_val('SELECT role FROM users WHERE id = ?', 'i', [$u['id']]) : '';
        if (!$u || (int) $u['id'] === (int) $admin['id'] || $uRole === 'admin') {
            flash('error', 'اسم العضوية غير صالح.');
        } else {
            db_exec('UPDATE users SET is_banned = ? WHERE id = ?', 'ii', [$a === 'lock_now' ? 1 : 0, $u['id']]);
            mod_log_add($u['id'], $admin['id'], $a === 'lock_now' ? 'lock' : 'unlock', $a === 'lock_now' ? 'قفل مباشر للعضوية' : 'إعادة فتح العضوية');
            flash('success', $a === 'lock_now' ? 'تم قفل العضوية.' : 'تمت إعادة فتح العضوية.');
        }
    } elseif ($a === 'approve_nom' || $a === 'reject_nom') {
        $n = db_row("SELECT * FROM staff_nominations WHERE id = ? AND status = 'pending_admin'", 'i', [$id]);
        if ($n) {
            $err = $a === 'approve_nom' ? apply_nomination($n) : null;
            if ($err !== null) {
                flash('error', $err);
            } else {
                db_exec('UPDATE staff_nominations SET status = ? WHERE id = ?', 'si', [$a === 'approve_nom' ? 'approved' : 'rejected', $id]);
                notify($n['nominator_id'], $admin['id'], 'follow', $a === 'approve_nom' ? 'وافق المدير على ترشيحك وتم التعيين.' : 'رفض المدير الترشيح.', 'nominations.php');
                notify($n['nominee_id'], $admin['id'], 'follow', $a === 'approve_nom' ? 'تم تعيينك في الإشراف.' : 'لم يتم اعتماد ترشيحك للإشراف.', 'index.php');
                mod_log_add($n['nominee_id'], $admin['id'], 'nominate', ($a === 'approve_nom' ? 'تعيين' : 'رفض تعيين') . ' (' . ($n['kind'] === 'supervisor' ? 'مشرف' : 'نائب مراقب') . ')');
                flash('success', $a === 'approve_nom' ? 'تم اعتماد الترشيح والتعيين.' : 'تم رفض الترشيح.');
            }
        }
    } elseif ($a === 'toggle_approval') {
        site_set('approve_new', post_str('approve_new') === '1' ? '1' : '0');
        flash('success', 'تم حفظ الإعداد.');
    }
    redirect('admin/requests.php');
}

$pendingUsers = db_all('SELECT id, username, email, created_at FROM users WHERE is_approved = 0 ORDER BY id');
$locks = db_all("SELECT r.id, r.reason, r.created_at, t.username AS target, q.username AS requester FROM account_requests r JOIN users t ON t.id = r.target_id JOIN users q ON q.id = r.requester_id WHERE r.status = 'pending' ORDER BY r.id");
$noms = db_all("SELECT n.id, n.kind, n.reason, n.category, u.username AS nominee, q.username AS nominator, f.name AS fname FROM staff_nominations n JOIN users u ON u.id = n.nominee_id JOIN users q ON q.id = n.nominator_id LEFT JOIN forums f ON f.id = n.forum_id WHERE n.status = 'pending_admin' ORDER BY n.id");
$form = function ($action, $id, $label, $cls = 'btn btn-small') {
    return '<form method="post" action="' . e(url('admin/requests.php')) . '" class="inline-form">' . csrf_field() . '<input type="hidden" name="action" value="' . e($action) . '"><input type="hidden" name="id" value="' . (int) $id . '"><button class="' . $cls . '" type="submit">' . e($label) . '</button></form>';
};
page_header('طلبات الإدارة');
admin_nav('requests');
?>
<h1 class="page-title">طلبات الموافقة والقفل</h1>

<section class="panel form-panel">
    <h2>الموافقة على العضويات الجديدة</h2>
    <form method="post" action="<?= e(url('admin/requests.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="toggle_approval">
        <label class="check"><input type="checkbox" name="approve_new" value="1"<?= site_setting('approve_new', '0') === '1' ? ' checked' : '' ?>> يجب أن يوافق المدير على كل عضوية جديدة قبل أن تدخل</label>
        <button class="btn btn-primary" type="submit">حفظ</button>
    </form>
    <?php if (!$pendingUsers): ?><p class="meta">لا توجد عضويات بانتظار الموافقة.</p><?php endif; ?>
    <ul class="follow-list"><?php foreach ($pendingUsers as $u): ?>
        <li><span><b><?= e($u['username']) ?></b> <span class="meta"><?= e($u['email']) ?> — <?= e(fmt_when($u['created_at'])) ?></span></span>
        <span><?= $form('approve_user', $u['id'], 'موافقة', 'btn btn-small btn-primary') ?> <?= $form('reject_user', $u['id'], 'رفض') ?></span></li>
    <?php endforeach; ?></ul>
</section>

<section class="panel form-panel">
    <h2>طلبات قفل العضويات</h2>
    <?php if (!$locks): ?><p class="meta">لا توجد طلبات.</p><?php endif; ?>
    <ul class="follow-list"><?php foreach ($locks as $r): ?>
        <li><span>قفل <b><?= e($r['target']) ?></b> — طلب من <?= e($r['requester']) ?><br><span class="meta"><?= e($r['reason']) ?> (<?= e(fmt_when($r['created_at'])) ?>)</span></span>
        <span><?= $form('approve_lock', $r['id'], 'موافقة وقفل', 'btn btn-small btn-primary') ?> <?= $form('reject_lock', $r['id'], 'رفض') ?></span></li>
    <?php endforeach; ?></ul>
</section>

<section class="panel form-panel">
    <h2>ترشيحات الإشراف بانتظار قرار المدير</h2>
    <?php if (!$noms): ?><p class="meta">لا توجد ترشيحات.</p><?php endif; ?>
    <ul class="follow-list"><?php foreach ($noms as $n): ?>
        <li><span><b><?= e($n['nominee']) ?></b> — <?= $n['kind'] === 'supervisor' ? 'مشرف على «' . e($n['fname']) . '»' : 'نائب مراقب للقسم «' . e($n['category']) . '»' ?> — رشّحه <?= e($n['nominator']) ?><br><span class="meta"><?= e($n['reason']) ?></span></span>
        <span><?= $form('approve_nom', $n['id'], 'موافقة وتعيين', 'btn btn-small btn-primary') ?> <?= $form('reject_nom', $n['id'], 'رفض') ?></span></li>
    <?php endforeach; ?></ul>
</section>

<section class="panel form-panel">
    <h2>قفل أو إعادة فتح مباشرة</h2>
    <form method="post" action="<?= e(url('admin/requests.php')) ?>">
        <?= csrf_field() ?>
        <label for="un">اسم العضوية</label>
        <input type="text" id="un" name="username" required maxlength="30">
        <div class="form-actions"><button class="btn btn-primary" name="action" value="lock_now" type="submit">قفل العضوية</button> <button class="btn" name="action" value="unlock_now" type="submit">إعادة فتحها</button></div>
    </form>
</section>
<?php page_footer();
