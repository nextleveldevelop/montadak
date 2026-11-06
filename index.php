<?php
require __DIR__ . '/includes/bootstrap.php';
ensure_section_mod_forums();

$me = current_user();

if (mt_rand(1, 40) === 1) {
    auto_archive(); // no-op unless ARCHIVE_AFTER_DAYS is set in config.php
}

$forums = db_all(
    'SELECT f.id, f.category, f.name, f.description, f.icon, f.image, f.access, f.closed,
            (SELECT COUNT(*) FROM threads t WHERE t.forum_id = f.id AND t.is_hidden = 0 AND t.is_archived = 0) AS thread_count,
            (SELECT COALESCE(SUM(t.reply_count), 0) FROM threads t WHERE t.forum_id = f.id AND t.is_hidden = 0 AND t.is_archived = 0) AS reply_total,
            lt.id AS last_thread_id, lt.title AS last_title, lt.last_post_at AS last_at,
            lu.id AS last_user_id, lu.username AS last_username,
            0 AS online_now
       FROM forums f
       LEFT JOIN threads lt ON lt.id = (
            SELECT t2.id FROM threads t2 WHERE t2.forum_id = f.id AND t2.is_hidden = 0 AND t2.is_archived = 0
             ORDER BY t2.last_post_at DESC, t2.id DESC LIMIT 1)
       LEFT JOIN users lu ON lu.id = lt.last_post_user_id
      ORDER BY f.sort_order, f.id'
);

// Group forums by category, keeping the admin-defined order.
$groups = [];
foreach ($forums as $f) {
    if (!forum_access_ok($f)) {
        continue;
    }
    $groups[$f['category']][] = $f;
}

// Supervisors (red) and moderators (yellow) of every forum, in one query.
$staffBy = [];
foreach (db_all('SELECT s.forum_id, s.level, u.id, u.username FROM forum_staff s JOIN users u ON u.id = s.user_id ORDER BY u.username') as $r) {
    $staffBy[(int) $r['forum_id']][$r['level']][] = $r;
}

$staffNames = function ($list) {
    $o = [];
    foreach ($list as $m) {
        $o[] = e($m['username']);
    }
    return implode('، ', $o);
};

// Administrators reorder categories and forums with the arrows
$isAdmin = is_admin();
$moveBtn = function ($type, $id, $dir) {
    $label = $dir === 'up' ? 'نقل إلى الأعلى' : 'نقل إلى الأسفل';
    return '<form method="post" action="' . e(url('reorder.php')) . '" class="inline-form">' . csrf_field()
        . '<input type="hidden" name="type" value="' . e($type) . '"><input type="hidden" name="id" value="' . e($id) . '"><input type="hidden" name="dir" value="' . e($dir) . '">'
        . '<button type="submit" class="arrow-btn" title="' . e($label) . '" aria-label="' . e($label) . '">' . ($dir === 'up' ? '▲' : '▼') . '</button></form>';
};

page_header('', 'home');
?>

<?php if (!$groups): ?>
    <div class="panel empty">
        <p>لا توجد منتديات بعد.</p>
        <?php if (is_admin()): ?>
            <p><a class="btn" href="<?= e(url('admin/forums.php')) ?>">أنشئ أول منتدى</a></p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
$hp = max(1, get_int('mp', 1));
$hTotal = (int) db_val('SELECT COUNT(*) FROM user_posts');
$hPages = max(1, (int) ceil($hTotal / 10));
$hp = min($hp, $hPages);
$homeMini = db_all('SELECT p.id, p.user_id, p.title, p.tag, p.content, p.created_at, u.username FROM user_posts p JOIN users u ON u.id = p.user_id ORDER BY p.id DESC LIMIT 10 OFFSET ' . (($hp - 1) * 10));
?>
<?php $hm = home_mode(); ?>
<div class="home-split <?= $hm === 'both' ? '' : 'one one-' . $hm ?>">
<?php if ($hm !== 'posts'): ?>
<div class="home-main">
<?php foreach ($groups as $category => $items): ?>
    <section>
        <table class="board-table home-board">
            <thead>
                <tr>
                    <th scope="col" class="icon-col"><?= icon('folder') ?><span class="sr-only">المنتدى</span></th>
                    <th scope="col" class="cat-title"><?= e($category) ?>
                        <?php if ($isAdmin): ?><span class="move-arrows"><?= $moveBtn('category', $category, 'up') ?><?= $moveBtn('category', $category, 'down') ?></span><?php endif; ?>
                    </th>
                    <th scope="col" class="num"><span class="lbl-short" title="مواضيع"><?= icon('doc') ?></span><span class="sr-only">مواضيع</span></th>
                    <th scope="col" class="num"><span class="lbl-short" title="ردود"><?= icon('chat') ?></span><span class="sr-only">ردود</span></th>
                    <th scope="col" class="last" title="آخر مشاركة"><?= icon('clock') ?><span class="sr-only">آخر مشاركة</span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $f): ?>
                <?php $fid = (int) $f['id']; ?>
                <tr>
                    <td class="icon-col"><?= forum_icon_html($f) ?>
                        <?php $sp = isset($staffBy[$fid]['supervisor']) ? $staffBy[$fid]['supervisor'] : []; if ($sp): ?>
                        <button type="button" class="staff-info-btn" aria-label="مشرفو المنتدى" title="مشرفو المنتدى" aria-expanded="false"><?= icon('question') ?></button>
                        <div class="staff-pop" hidden><div><b>المشرفون على المنتدى:</b> <?= $staffNames($sp) ?></div></div>
                        <?php endif; ?>
                        <?php if ($isAdmin): ?><span class="move-arrows"><?= $moveBtn('forum', $fid, 'up') ?><?= $moveBtn('forum', $fid, 'down') ?></span><?php endif; ?>
                    </td>
                    <td class="main">
                        <a class="row-title" href="<?= e(url('forum.php?id=' . $fid)) ?>"><?= e($f['name']) ?></a><?php if (!empty($f['closed'])): ?> <span class="lock-mark" title="منتدى مقفول من الإدارة"><?= icon('lock') ?></span><?php endif; ?>
                        <?php if ($f['description'] !== ''): ?>
                            <p class="row-desc"><?= e($f['description']) ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="num count-topics"><?= (int) $f['thread_count'] ?></td>
                    <td class="num count-replies"><?= (int) $f['reply_total'] ?></td>
                    <td class="last">
                        <?php if ($f['last_thread_id']): ?>
                            <a class="when" href="<?= e(url('thread.php?id=' . $f['last_thread_id'])) ?>" title="<?= e($f['last_title']) ?>"><?= e(fmt_when($f['last_at'])) ?></a>
                            <?= user_link($f['last_user_id'], $f['last_username'], $fid) ?>
                        <?php else: ?>
                            <span class="meta">لا توجد مشاركات</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php if ($hm !== 'forums'): ?>
<aside class="home-posts">
    <h2 class="home-posts-title"><?= icon('news') ?> من منشورات الأعضاء</h2>
    <?php if (!$homeMini): ?><p class="pp-none">لم ينشر أحد موضوعًا في صفحته بعد.</p><?php else: ?>
        <?php mini_cards($homeMini, true); ?>
        <?= pagination_html($hp, $hPages, 'index.php?x=1', 'mp') ?>
    <?php endif; ?>
</aside>
<?php endif; ?>
</div>

<?php page_footer();
