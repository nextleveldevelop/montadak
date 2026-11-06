<?php
require __DIR__ . '/includes/bootstrap.php';

$forumId = get_int('id');
$forum = db_row('SELECT id, category, name, description, icon, image, access, closed FROM forums WHERE id = ?', 'i', [$forumId]);
if ($forum && !forum_access_ok($forum)) {
    $forum = null;
}
if (!$forum) {
    fail_page('هذا المنتدى غير موجود.', 404, 'المنتدى غير موجود');
}

$me = current_user();
$level = forum_level($forumId);
$isStaff = $level >= 1;
$mailOpen = $isStaff ? (int) db_val("SELECT COUNT(*) FROM forum_mail WHERE forum_id = ? AND status = 'open'", 'i', [$forumId]) : 0;
$reportOpen = $isStaff ? (int) db_val("SELECT COUNT(*) FROM reports rp JOIN threads tt ON tt.id = rp.thread_id WHERE rp.status = 'open' AND tt.forum_id = ?", 'i', [$forumId]) : 0;

$sort = 'last';
$sortSql = 't.last_post_at DESC';

$hf = hidden_filter('t');   // hidden topics are shown only to people allowed to open them
$un = unread_parts('t');    // "new" marker per thread
$cols = thread_list_columns($un[0]);
$joins = thread_list_joins() . ' ' . $un[1];

// Forum links (وصلات المنتدى)
$links = db_all(
    'SELECT t.id, t.title, t.link_level FROM threads t
      WHERE t.forum_id = ? AND t.link_level > 0 AND t.is_archived = 0' . $hf[0] . '
      ORDER BY t.link_level DESC, t.title LIMIT 20',
    'i' . $hf[1],
    array_merge([$forumId], $hf[2])
);

// One list: pinned first, then featured (level 2 = featured in every forum), then the rest.
$total = (int) db_val(
    'SELECT COUNT(*) FROM threads t WHERE t.forum_id = ? AND t.is_archived = 0' . $hf[0],
    'i' . $hf[1],
    array_merge([$forumId], $hf[2])
);
$pages = max(1, (int) ceil($total / THREADS_PER_PAGE));
$page = min(page_param(), $pages);
$offset = ($page - 1) * THREADS_PER_PAGE;

$threads = db_all(
    'SELECT ' . $cols . ' FROM threads t ' . $joins . '
      WHERE t.forum_id = ? AND t.is_archived = 0' . $hf[0] . '
      ORDER BY t.is_pinned DESC, (t.feature_level > 0) DESC, ' . $sortSql . ', t.id DESC
      LIMIT ? OFFSET ?',
    $un[2] . 'i' . $hf[1] . 'ii',
    array_merge($un[3], [$forumId], $hf[2], [THREADS_PER_PAGE, $offset])
);

$staff = ['supervisor' => []];
foreach (db_all('SELECT s.level, u.id, u.username FROM forum_staff s JOIN users u ON u.id = s.user_id WHERE s.forum_id = ? ORDER BY u.username', 'i', [$forumId]) as $r) {
    if ($r['level'] === 'supervisor') { $staff['supervisor'][] = $r; }
}
$allForums = array_values(array_filter(db_all('SELECT id, category, name, access FROM forums ORDER BY sort_order, id'), 'forum_access_ok'));

$viewingForum = $forumId; // counted in "members in this forum"
page_header($forum['name']);
breadcrumbs([['الرئيسية', 'index.php'], [$forum['category'], 'index.php'], [$forum['name'], null]]);
?>
<section class="fh">
    <div class="fh-icon"><?= forum_icon_html($forum) ?></div>
    <div class="fh-body">
        <h1 class="fh-name"><?= e($forum['name']) ?><?php if (!empty($forum['closed'])): ?> <span class="lock-mark" title="منتدى مقفول من الإدارة"><?= icon('lock') ?></span><?php endif; ?></h1>
        <?php if (!empty($forum['closed'])): ?><p class="fh-closed"><?= icon('lock') ?> هذا المنتدى مقفول من الإدارة: يمكنك قراءة المواضيع فقط ولا يمكن إضافة مواضيع أو ردود جديدة.</p><?php endif; ?>
        <?php if ($forum['description'] !== ''): ?><p class="fh-desc"><?= e($forum['description']) ?></p><?php endif; ?>
        <?php if ($staff['supervisor']): ?>
            <p class="fh-staff"><?= icon('shield') ?><span>المشرفون:</span>
                <?php foreach ($staff['supervisor'] as $i => $m): ?><?= $i ? ' + ' : '' ?><?= user_link($m['id'], $m['username'], $forumId) ?><?php endforeach; ?>
            </p>
        <?php endif; ?>
    </div>
</section>

<?php if ($me === null): ?><?= guest_cta('forum.php?id=' . $forumId, 'لكتابة موضوع جديد أو المشاركة يلزمك حساب في المنتدى.') ?><?php endif; ?>
<div class="forum-bar" role="toolbar" aria-label="أدوات المنتدى">
    <?php if ($me !== null): ?>
        <?php if (empty($forum['closed']) || is_admin()): ?>
        <a class="fb-btn fb-primary" href="<?= e(url('new_thread.php?forum_id=' . $forumId)) ?>"><?= icon('pencil') ?><span>موضوع جديد</span></a>
        <?php endif; ?>
        <?= follow_button('forum', $forumId, 'fb-btn') ?>
        <span class="fb-btn fb-count"><?= icon('bell') ?><span>المتابعون: <?= follower_count('forum', $forumId) ?></span></span>
        <a class="fb-btn" href="<?= e(url('forum_mail.php?forum=' . $forumId)) ?>"><?= icon('mail') ?><span><?= $isStaff ? 'بريد المنتدى' : 'مراسلة المشرفين' ?></span><?php if ($mailOpen > 0): ?><b class="tool-badge"><?= $mailOpen ?></b><?php endif; ?></a>
    <?php endif; ?>
    <?php if (is_admin()): ?>
        <a class="fb-btn" href="<?= e(url('forum_edit.php?id=' . $forumId)) ?>"><?= icon('sliders') ?><span>إدارة المنتدى</span></a>
    <?php endif; ?>
    <?php if ($isStaff): ?>
        <a class="fb-btn" href="<?= e(url('moderation.php?tab=reports&forum=' . $forumId)) ?>"><?= icon('flag') ?><span>شكاوي المنتدى</span><?php if ($reportOpen > 0): ?><b class="tool-badge"><?= $reportOpen ?></b><?php endif; ?></a>
    <?php endif; ?>
    <form method="get" action="<?= e(url('forum.php')) ?>">
        <label for="jump"><span class="sr-only">انتقل إلى المنتدى</span></label>
        <select id="jump" name="id" data-autosubmit aria-label="انتقل إلى المنتدى">
            <?php foreach ($allForums as $af): ?>
                <option value="<?= (int) $af['id'] ?>"<?= (int) $af['id'] === $forumId ? ' selected' : '' ?>><?= e($af['category'] . ' ‹ ' . $af['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="btn btn-small">انتقل</button></noscript>
    </form>
</div>

<?php if ($links): ?>
    <section class="forum-links" aria-label="وصلات المنتدى">
        <?php foreach ($links as $l): ?>
            <a class="<?= (int) $l['link_level'] === 2 ? 'fl-main' : 'fl-normal' ?>" href="<?= e(url('thread.php?id=' . $l['id'])) ?>">♦ <?= e($l['title']) ?></a>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php if (!$threads): ?>
    <div class="panel empty">
        <p>لا توجد مواضيع في هذا المنتدى بعد. كن أول من يكتب موضوعًا.</p>
    </div>
<?php else: ?>
    <?php thread_table($threads, ['forum' => false, 'options' => $me !== null, 'current_forum' => $forumId]); ?>
    <?= pagination_html($page, $pages, 'forum.php?id=' . $forumId) ?>
<?php endif; ?>
<?php page_footer();
