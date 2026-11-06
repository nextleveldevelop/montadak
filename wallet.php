<?php
/** المحفظة الشخصية: المواضيع والردود المحفوظة. */
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$tab = (isset($_GET['tab']) && in_array($_GET['tab'], ['replies', 'posts'], true)) ? $_GET['tab'] : 'threads';
$next = safe_next(current_app_path());

$nT = (int) db_val("SELECT COUNT(*) FROM saved_items WHERE user_id = ? AND item_type = 'thread'", 'i', [$user['id']]);
$nM = (int) db_val("SELECT COUNT(*) FROM saved_items WHERE user_id = ? AND item_type = 'mini'", 'i', [$user['id']]);
$nR = (int) db_val("SELECT COUNT(*) FROM saved_items WHERE user_id = ? AND item_type = 'reply'", 'i', [$user['id']]);

if ($tab === 'posts') {
    $rows = db_all("SELECT p.id, p.title, p.tag, p.content, p.created_at, p.user_id, u.username, s.created_at AS saved_at FROM saved_items s JOIN user_posts p ON p.id = s.item_id JOIN users u ON u.id = p.user_id WHERE s.user_id = ? AND s.item_type = 'mini' ORDER BY s.created_at DESC LIMIT 500", 'i', [$user['id']]);
} elseif ($tab === 'threads') {
    $rows = db_all("SELECT t.id, t.title, t.created_at, t.is_hidden, t.user_id, f.name AS forum_name, f.category, f.access, u.username AS author, s.created_at AS saved_at
        FROM saved_items s JOIN threads t ON t.id = s.item_id JOIN forums f ON f.id = t.forum_id JOIN users u ON u.id = t.user_id
        WHERE s.user_id = ? AND s.item_type = 'thread' ORDER BY s.created_at DESC LIMIT 500", 'i', [$user['id']]);
} else {
    $rows = db_all("SELECT r.id, r.content, r.created_at, r.thread_id, t.title, t.is_hidden, t.user_id, f.name AS forum_name, f.category, f.access, u.username AS author, s.created_at AS saved_at
        FROM saved_items s JOIN replies r ON r.id = s.item_id JOIN threads t ON t.id = r.thread_id JOIN forums f ON f.id = t.forum_id JOIN users u ON u.id = r.user_id
        WHERE s.user_id = ? AND s.item_type = 'reply' AND (r.is_hidden = 0 OR " . (is_admin() ? '1' : '0') . ") ORDER BY s.created_at DESC LIMIT 500", 'i', [$user['id']]);
}
$rows = array_values(array_filter($rows, function ($r) { if (!isset($r['forum_name'])) { return true; } return forum_access_ok($r) && can_view_thread(['id' => isset($r['thread_id']) ? $r['thread_id'] : $r['id'], 'is_hidden' => $r['is_hidden'], 'user_id' => $r['user_id']] + $r); }));

$perPage = 10;
$total = count($rows);
$pages = max(1, (int) ceil($total / $perPage));
$page = min(page_param(), $pages);
$rows = array_slice($rows, ($page - 1) * $perPage, $perPage);
page_header('المحفظة الشخصية', 'wallet');
page_hero('bookmark', 'المحفظة الشخصية', 'المواضيع والردود التي حفظتها لتعود إليها لاحقًا');
?>
<nav class="pg-tabs">
    <a href="<?= e(url('wallet.php?tab=posts')) ?>"<?= $tab === 'posts' ? ' aria-current="page"' : '' ?>><?= icon('news') ?> المنشورات (<?= $nM ?>)</a>
    <a href="<?= e(url('wallet.php')) ?>"<?= $tab === 'threads' ? ' aria-current="page"' : '' ?>><?= icon('doc') ?> المواضيع (<?= $nT ?>)</a>
    <a href="<?= e(url('wallet.php?tab=replies')) ?>"<?= $tab === 'replies' ? ' aria-current="page"' : '' ?>><?= icon('chat') ?> الردود (<?= $nR ?>)</a>
</nav>
<?php if (!$rows): ?>
    <div class="panel empty"><p>لا يوجد شيء محفوظ هنا. استخدم أيقونة الحفظ <?= icon('bookmark') ?> في صفحة الموضوع.</p></div>
<?php else: ?>
    <div class="wallet-list">
    <?php if ($tab === 'posts'): ?>
        <?php mini_cards($rows, true); ?>
    <?php else: ?>
    <?php foreach ($rows as $r): ?>
        <?php $tid = $tab === 'threads' ? (int) $r['id'] : (int) $r['thread_id'];
        $href = $tab === 'threads' ? 'thread.php?id=' . $tid : 'thread.php?id=' . $tid . '&page=' . (int) reply_page($tid, $r['id']) . '#post-' . (int) $r['id']; ?>
        <article class="mini-card">
            <header><span class="mini-tag"><?= e($r['forum_name']) ?></span><a class="mini-title" href="<?= e(url($href)) ?>"><?= e($r['title']) ?></a></header>
            <?php if ($tab === 'replies'): ?><p><?= e(mini_excerpt($r['content'], 40)) ?></p><?php endif; ?>
            <footer><?= e($r['author']) ?> · <time><?= e(fmt_when($r['created_at'])) ?></time> · حُفظ <?= e(fmt_when($r['saved_at'])) ?>
                <?= save_button($tab === 'threads' ? 'thread' : 'reply', $r['id'], $next, true) ?></footer>
        </article>
    <?php endforeach; ?>
    <?php endif; ?>
    </div>
    <?= pagination_html($page, $pages, 'wallet.php?tab=' . $tab) ?>
<?php endif; ?>
<?php page_footer();
