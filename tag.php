<?php
/** صفحة وسم: كل المواضيع الصغيرة ضمن مجال واحد. */
require __DIR__ . '/includes/bootstrap.php';
$tag = isset($_GET['t']) ? trim((string) $_GET['t']) : '';
if (!in_array($tag, mini_tags(), true)) {
    fail_page('هذا الوسم غير موجود.', 404);
}
$page = max(1, get_int('page', 1));
$total = (int) db_val('SELECT COUNT(*) FROM user_posts WHERE tag = ?', 's', [$tag]);
$pages = max(1, (int) ceil($total / 10));
$page = min($page, $pages);
$rows = db_all('SELECT p.id, p.user_id, p.title, p.tag, p.content, p.created_at, u.username FROM user_posts p JOIN users u ON u.id = p.user_id WHERE p.tag = ? ORDER BY p.id DESC LIMIT 10 OFFSET ' . (($page - 1) * 10), 's', [$tag]);
page_header('#' . $tag);
$followBtn = '';
if (current_user() !== null) {
    $on = is_following_tag($tag);
    $followBtn = '<form method="post" action="' . e(url('tag_follow.php')) . '" class="inline-form">' . csrf_field() . '<input type="hidden" name="tag" value="' . e($tag) . '"><input type="hidden" name="next" value="' . e(safe_next(current_app_path())) . '"><button class="btn" type="submit">' . icon($on ? 'check' : 'bell') . ' ' . ($on ? 'أتابع الوسم' : 'متابعة الوسم') . '</button></form>';
}
page_hero('news', '#' . $tag, $total . ' موضوع في هذا المجال', $followBtn);
?>
<nav class="tag-cloud" aria-label="الوسوم">
    <?php foreach (mini_tags() as $tg): ?><a class="mini-tag<?= $tg === $tag ? ' on' : '' ?>" href="<?= e(url('tag.php?t=' . rawurlencode($tg))) ?>">#<?= e($tg) ?></a><?php endforeach; ?>
</nav>
<?php if (!$rows): ?><div class="panel empty"><p>لا توجد مواضيع بهذا الوسم بعد.</p></div><?php else: ?>
    <div class="mini-grid"><?php mini_cards($rows, true); ?></div>
    <?= pagination_html($page, $pages, 'tag.php?t=' . rawurlencode($tag)) ?>
<?php endif; ?>
<?php page_footer();
