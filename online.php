<?php
require __DIR__ . '/includes/bootstrap.php';

$perPage = 15;
$where = 'u.last_seen >= (NOW() - INTERVAL ' . (int) ONLINE_WINDOW_MINUTES . ' MINUTE)' . hidden_members_sql('u');
$total = (int) db_val('SELECT COUNT(*) FROM users u WHERE ' . $where);
$pages = max(1, (int) ceil($total / $perPage));
$page = min(page_param(), $pages);
$members = db_all(
    'SELECT u.id, u.username, u.role, u.hide_online, u.last_seen FROM users u WHERE ' . $where . ' ORDER BY u.last_seen DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage)
);

page_header('المتواجدون حاليًا', 'online');
?>
<?php $oc = online_counts(); ?>
<?php page_hero('users', 'المتواجدون حاليًا', 'الأعضاء النشطون خلال آخر ' . (int) ONLINE_WINDOW_MINUTES . ' دقائق'); ?>
<div class="pg-kpis">
    <div><b><?= (int) $oc['members'] ?></b><span>أعضاء</span></div>
    <div><b><?= (int) $oc['staff'] ?></b><span>مشرفون</span></div>
    <div><b><?= (int) $oc['guests'] ?></b><span>زوار</span></div>
</div>

<?php if (!$members): ?>
    <div class="panel empty"><p>لا أحد متصل الآن.</p></div>
<?php else: ?>
    <ul class="pg-people">
        <?php foreach ($members as $m): ?>
            <li>
                <i class="pg-dot"></i>
                <?= user_link($m['id'], $m['username']) ?>
                <?php if ((int) $m['hide_online'] === 1): ?><span class="pg-tag"><?= e(fem_text('مخفي', $m['id'])) ?></span><?php endif; ?>
                <?php if (member_level($m['id']) > 0): ?><span class="pg-tag"><?= e(level_label(member_level($m['id']))) ?></span><?php endif; ?>
                <span class="meta"><time><?= e(fmt_time($m['last_seen'], 'H:i')) ?></time></span>
            </li>
        <?php endforeach; ?>
    </ul>
    <?= pagination_html($page, $pages, 'online.php') ?>
<?php endif; ?>
<?php page_footer();
