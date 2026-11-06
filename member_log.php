
<?php
/**
 * سجل العقوبات والإجراءات على عضو
 * أدوات التحذير والكتم والترشيح للإشراف.
 */

require __DIR__ . '/includes/bootstrap.php';

// --------------------------------------------------
// Authentication and permissions
// --------------------------------------------------

$me = require_login();
$mySup = supervised_forums();

if (!is_admin() && empty($mySup)) {
    fail_page('هذه الصفحة للإشراف فقط.', 403);
}

// --------------------------------------------------
// Target member
// --------------------------------------------------

$id = get_int('id');

if ($id < 1) {
    fail_page('معرّف العضو غير صالح.', 400);
}

$t = db_row(
    'SELECT id, username, role, is_deputy, is_banned, muted_until
     FROM users
     WHERE id = ?',
    'i',
    [$id]
);

if (!$t) {
    fail_page('العضو غير موجود.', 404);
}

// --------------------------------------------------
// Sanctions history
// --------------------------------------------------

$sanc = db_all(
    "SELECT
        s.type,
        s.reason,
        s.until,
        s.created_at,
        f.name AS fname,
        u.username AS byname
     FROM sanctions s
     LEFT JOIN forums f ON f.id = s.forum_id
     LEFT JOIN users u ON u.id = s.by_user_id
     WHERE s.user_id = ?
     ORDER BY s.id DESC
     LIMIT 100",
    'i',
    [$id]
);

// --------------------------------------------------
// Moderation log
// --------------------------------------------------

$log = db_all(
    "SELECT
        l.kind,
        l.text,
        l.created_at,
        u.username AS byname
     FROM mod_log l
     LEFT JOIN users u ON u.id = l.by_user_id
     WHERE l.user_id = ?
     ORDER BY l.id DESC
     LIMIT 100",
    'i',
    [$id]
);

// --------------------------------------------------
// Current supervisor assignments
// --------------------------------------------------

$tSup = db_all(
    "SELECT f.id, f.name
     FROM forum_staff s
     JOIN forums f ON f.id = s.forum_id
     WHERE s.user_id = ?
       AND s.level = 'supervisor'
     ORDER BY f.name",
    'i',
    [$id]
);

// --------------------------------------------------
// Labels
// --------------------------------------------------

$names = [
    'warning' => 'تحذير',
    'warn'    => 'تحذير',
    'mute'    => 'كتم',
    'unmute'  => 'رفع كتم',
];

$klog = [
    'lock'   => 'قفل عضوية',
    'unlock' => 'إعادة فتح',
    'staff'  => 'إشراف',
];

$back = 'member_log.php?id=' . $id;

// --------------------------------------------------
// Build forum dropdown
// supervised_forums() returns [forum_id => forum_name]
// --------------------------------------------------

$opts = '';

foreach ($mySup as $forumId => $forumName) {
    $opts .= '<option value="' . (int) $forumId . '">'
        . e($forumName)
        . '</option>';
}

// --------------------------------------------------
// Page header
// --------------------------------------------------

page_header('سجل ' . $t['username']);

$heroDescription =
    '<a href="' . e(url('profile.php?id=' . $id)) . '" style="color:#c5f5ea">'
    . 'صفحة العضوية</a>';

if (!empty($t['is_banned'])) {
    $heroDescription .= ' · <b>العضوية مقفلة</b>';
}

$isMuted = (
    !empty($t['muted_until'])
    && strtotime($t['muted_until'] . ' UTC') > time()
);

if ($isMuted) {
    $heroDescription .= ' · <b>مكتوم حاليًا</b>';
}

page_hero(
    'shield',
    'سجل العقوبات: ' . $t['username'],
    $heroDescription
);

?>

<?php if ($t['role'] !== 'admin' && $id !== (int) $me['id']): ?>

<div class="pg-cols">

    <!-- Warning and mute -->
    <section class="pg-card">
        <h2><?= icon('flag') ?> تحذير</h2>
        <?php if (!empty($mySup)): ?>
            <form method="post" action="<?= e(url('sanction.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= (int) $id ?>">
                <input type="hidden" name="back" value="<?= e($back) ?>">
                <input type="hidden" name="action" value="warn">
                <select name="forum_id" required><?= $opts ?></select>
                <input type="text" name="reason" placeholder="السبب" maxlength="255" required>
                <button class="btn btn-primary" type="submit">توجيه تحذير</button>
            </form>
            <h2 style="margin-top:1rem"><?= icon('lock') ?> كتم أو رفع الكتم<?= is_admin() ? '' : ' (في منتديات تشرف عليها)' ?></h2>
            <form method="post" action="<?= e(url('sanction.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= (int) $id ?>">
                <input type="hidden" name="back" value="<?= e($back) ?>">
                <div class="mute-forums">
                    <?php if (is_admin()): ?><label class="check"><input type="checkbox" name="scope" value="all"> <b>جميع المنتديات</b></label><?php endif; ?>
                    <?php foreach ($mySup as $forumId => $forumName): ?>
                        <label class="check"><input type="checkbox" name="forum_ids[]" value="<?= (int) $forumId ?>"> <?= e($forumName) ?></label>
                    <?php endforeach; ?>
                </div>
                <input type="text" name="reason" placeholder="السبب (للكتم)" maxlength="255">
                <input type="number" name="days" min="1" max="30" value="1" title="أيام الكتم">
                <select name="action"><option value="mute">كتم</option><option value="unmute">رفع الكتم</option></select>
                <button class="btn btn-primary" type="submit">تنفيذ</button>
            </form>
        <?php else: ?>
            <p class="meta">لا توجد منتديات متاحة لتنفيذ هذا الإجراء.</p>
        <?php endif; ?>
    </section>
    <!-- Supervisor nomination -->
    <section class="pg-card" id="nominate">

        <h2>
            <?= icon('shield') ?>
            <?= is_admin() ? 'تعيين مشرف' : 'ترشيح للإشراف' ?>
        </h2>

        <?php if (!empty($mySup)): ?>

            <form method="post" action="<?= e(url('member_admin.php')) ?>">

                <?= csrf_field() ?>

                <input type="hidden" name="user_id" value="<?= (int) $id ?>">
                <input type="hidden" name="back" value="<?= e($back) ?>">
                <input type="hidden" name="action" value="nominate">

                <select name="forum_id" required>
                    <?php foreach ($mySup as $forumId => $forumName): ?>
                        <option value="<?= (int) $forumId ?>">
                            <?= e($forumName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input
                    type="text"
                    name="reason"
                    placeholder="سبب الترشيح"
                    maxlength="250"
                >

                <button class="btn btn-primary" type="submit">
                    <?= is_admin() ? 'تعيين' : 'ترشيح' ?>
                </button>

            </form>

        <?php else: ?>

            <p class="meta">لا توجد منتديات متاحة.</p>

        <?php endif; ?>

        <?php if (is_admin() && !empty($tSup)): ?>

            <p class="meta">يشرف حاليًا على:</p>

            <?php foreach ($tSup as $f): ?>

                <form
                    method="post"
                    action="<?= e(url('member_admin.php')) ?>"
                    class="inline-form"
                    onsubmit="return confirm('إزالة الإشراف؟')"
                >

                    <?= csrf_field() ?>

                    <input type="hidden" name="user_id" value="<?= (int) $id ?>">
                    <input type="hidden" name="back" value="<?= e($back) ?>">
                    <input type="hidden" name="action" value="remove_super">
                    <input type="hidden" name="forum_id" value="<?= (int) $f['id'] ?>">

                    <button class="btn btn-small" type="submit">
                        <?= e($f['name']) ?> ✕
                    </button>

                </form>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>

</div>

<?php endif; ?>


<!-- Sanctions history -->
<section class="pg-card">

    <h2><?= icon('clock') ?> العقوبات (الحالية والسابقة)</h2>

    <?php if (empty($sanc)): ?>

        <p class="meta">لا توجد عقوبات.</p>

    <?php else: ?>

        <div class="pro-wrap">

            <table class="pro-table">

                <thead>
                    <tr>
                        <th>النوع</th>
                        <th>المنتدى</th>
                        <th>السبب</th>
                        <th>بواسطة</th>
                        <th class="pt-c">التاريخ</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($sanc as $s): ?>

                        <tr>

                            <td>
                                <?= e($names[$s['type']] ?? $s['type'] ?? 'إجراء') ?>

                                <?php if (!empty($s['until'])): ?>
                                    <small>
                                        (حتى <?= e(fmt_time($s['until'], 'Y/m/d')) ?>)
                                    </small>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= e($s['fname'] ?: '—') ?>
                            </td>

                            <td>
                                <?= e($s['reason'] ?? '') ?>
                            </td>

                            <td>
                                <?= e($s['byname'] ?: '—') ?>
                            </td>

                            <td class="pt-c">
                                <?= e(fmt_time($s['created_at'], 'Y/m/d H:i')) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>


<!-- Moderation and account history -->
<section class="pg-card">

    <h2><?= icon('lock') ?> سجل القفل والإشراف</h2>

    <?php if (empty($log)): ?>

        <p class="meta">لا توجد سجلات.</p>

    <?php else: ?>

        <ul class="pg-list">

            <?php foreach ($log as $l): ?>

                <li>

                    <span>
                        <b>
                            <?= e($klog[$l['kind']] ?? $l['kind'] ?? 'إجراء') ?>
                        </b>

                        — <?= e($l['text'] ?? '') ?>
                    </span>

                    <span class="meta">
                        <?= e($l['byname'] ?: '') ?>
                        ·
                        <?= e(fmt_time($l['created_at'], 'Y/m/d')) ?>
                    </span>

                </li>

            <?php endforeach; ?>

        </ul>

    <?php endif; ?>

</section>

<?php page_footer(); ?>
