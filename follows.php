<?php
/** متابعاتي: members, forums and topics you follow. */
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$uid = (int) $user['id'];
$users = db_all("SELECT u.id, u.username FROM follows f JOIN users u ON u.id = f.target_id WHERE f.user_id = ? AND f.target_type = 'user' ORDER BY u.username", 'i', [$uid]);
$forums = db_all("SELECT fo.id, fo.name FROM follows f JOIN forums fo ON fo.id = f.target_id WHERE f.user_id = ? AND f.target_type = 'forum' ORDER BY fo.name", 'i', [$uid]);
$threads = db_all("SELECT t.id, t.title FROM follows f JOIN threads t ON t.id = f.target_id WHERE f.user_id = ? AND f.target_type = 'thread' AND t.is_hidden = 0 ORDER BY t.last_post_at DESC LIMIT 200", 'i', [$uid]);
$tags = array_column(db_all('SELECT tag FROM tag_follows WHERE user_id = ? ORDER BY tag', 'i', [$uid]), 'tag');
$followers = (int) db_val("SELECT COUNT(*) FROM follows WHERE target_type = 'user' AND target_id = ?", 'i', [$uid]);

$unfollow = function ($type, $id) {
    return '<form method="post" action="' . e(url('follow.php')) . '" class="inline-form">' . csrf_field()
        . '<input type="hidden" name="type" value="' . e($type) . '"><input type="hidden" name="id" value="' . (int) $id . '">'
        . '<input type="hidden" name="next" value="follows.php"><button type="submit" class="btn btn-small">إلغاء المتابعة</button></form>';
};

$pp = 10;
$paged = function (array &$arr, $param, &$pg, &$pgs) use ($pp) {
    $pgs = max(1, (int) ceil(count($arr) / $pp));
    $pg = min(max(1, get_int($param, 1)), $pgs);
    $arr = array_slice($arr, ($pg - 1) * $pp, $pp);
};
$others = function ($except) {
    $q = [];
    foreach (['pu', 'pf', 'pt', 'pg'] as $k) {
        if ($k !== $except && get_int($k, 1) > 1) { $q[$k] = get_int($k, 1); }
    }
    return 'follows.php?' . http_build_query($q);
};
$nUsers = count($users); $nForums = count($forums); $nThreads = count($threads); $nTags = count($tags);
$paged($users, 'pu', $puP, $puN); $paged($forums, 'pf', $pfP, $pfN); $paged($threads, 'pt', $ptP, $ptN); $paged($tags, 'pg', $pgP, $pgN);
page_header('متابعاتي', 'follows');
?>
<?php page_hero('bell', 'متابعاتي', 'كل ما تتابعه في مكان واحد. يتابعك <b>' . $followers . '</b> عضو.'); ?>
<div class="pg-cols">
<section class="pg-card"><h2><?= icon('users') ?> الأعضاء الذين أتابعهم (<?= $nUsers ?>)</h2>
<?php if (!$users): ?><p class="meta">لا تتابع أي عضو.</p><?php endif; ?>
<ul class="pg-list"><?php foreach ($users as $r): ?><li><?= user_link($r['id'], $r['username']) ?> <?= $unfollow('user', $r['id']) ?></li><?php endforeach; ?></ul><?= pagination_html($puP, $puN, $others('pu'), 'pu') ?></section>
<section class="pg-card"><h2><?= icon('folder') ?> المنتديات التي أتابعها (<?= $nForums ?>)</h2>
<?php if (!$forums): ?><p class="meta">لا تتابع أي منتدى.</p><?php endif; ?>
<ul class="pg-list"><?php foreach ($forums as $r): ?><li><a href="<?= e(url('forum.php?id=' . (int) $r['id'])) ?>"><?= e($r['name']) ?></a> <?= $unfollow('forum', $r['id']) ?></li><?php endforeach; ?></ul><?= pagination_html($pfP, $pfN, $others('pf'), 'pf') ?></section>
<section class="pg-card"><h2><?= icon('doc') ?> المواضيع التي أتابعها (<?= $nThreads ?>)</h2>
<?php if (!$threads): ?><p class="meta">لا تتابع أي موضوع.</p><?php endif; ?>
<ul class="pg-list"><?php foreach ($threads as $r): ?><li><a href="<?= e(url('thread.php?id=' . (int) $r['id'])) ?>"><?= e($r['title']) ?></a> <?= $unfollow('thread', $r['id']) ?></li><?php endforeach; ?></ul><?= pagination_html($ptP, $ptN, $others('pt'), 'pt') ?></section>
<section class="pg-card"><h2><?= icon('news') ?> الوسوم التي أتابعها (<?= $nTags ?>)</h2>
<?php if (!$tags): ?><p class="meta">لا تتابع أي وسم. افتح صفحة وسم واضغط «متابعة الوسم».</p><?php endif; ?>
<ul class="pg-list"><?php foreach ($tags as $tg): ?><li><a href="<?= e(url('tag.php?t=' . rawurlencode($tg))) ?>">#<?= e($tg) ?></a>
<form method="post" action="<?= e(url('tag_follow.php')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="tag" value="<?= e($tg) ?>"><input type="hidden" name="next" value="follows.php"><button type="submit" class="btn btn-small">إلغاء المتابعة</button></form></li><?php endforeach; ?></ul><?= pagination_html($pgP, $pgN, $others('pg'), 'pg') ?></section>
</div>
<?php page_footer();
