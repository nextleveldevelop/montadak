<?php
/** إحصائيات المنتدى: بطاقات مؤشرات + رسوم SVG + جداول (بدون مكتبات خارجية). */
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$days = 14;
$k = db_row(
    'SELECT (SELECT COUNT(*) FROM users) AS members,
            (SELECT COUNT(*) FROM users WHERE created_at >= (NOW() - INTERVAL 7 DAY)) AS members7,
            (SELECT COUNT(*) FROM users WHERE last_seen >= (NOW() - INTERVAL ' . (int) ONLINE_WINDOW_MINUTES . ' MINUTE)) AS online,
            (SELECT COUNT(*) FROM threads) AS threads,
            (SELECT COUNT(*) FROM threads WHERE created_at >= (NOW() - INTERVAL 7 DAY)) AS threads7,
            (SELECT COUNT(*) FROM replies) AS replies,
            (SELECT COUNT(*) FROM replies WHERE created_at >= (NOW() - INTERVAL 7 DAY)) AS replies7,
            (SELECT COUNT(*) FROM forums) AS forums,
            (SELECT COUNT(*) FROM messages) AS messages,
            (SELECT COALESCE(SUM(views), 0) FROM threads) AS views,
            (SELECT COUNT(*) FROM users WHERE is_banned = 1) AS banned'
);

// Daily series for the last N days (filled with zeros).
$series = function ($table) use ($days) {
    $rows = db_all('SELECT DATE(created_at) AS d, COUNT(*) AS c FROM ' . $table . ' WHERE created_at >= (CURDATE() - INTERVAL ' . ($days - 1) . ' DAY) GROUP BY DATE(created_at)');
    $by = [];
    foreach ($rows as $r) {
        $by[$r['d']] = (int) $r['c'];
    }
    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime('-' . $i . ' day'));
        $out[$d] = isset($by[$d]) ? $by[$d] : 0;
    }
    return $out;
};
$sMembers = $series('users');
$sThreads = $series('threads');
$sReplies = $series('replies');

$topForums = db_all(
    'SELECT f.id, f.name, f.category,
            (SELECT COUNT(*) FROM threads t WHERE t.forum_id = f.id) AS th,
            (SELECT COUNT(*) FROM replies r JOIN threads t2 ON t2.id = r.thread_id WHERE t2.forum_id = f.id) AS rp
       FROM forums f ORDER BY th DESC, rp DESC LIMIT 8'
);
$topMembers = db_all(
    'SELECT u.id, u.username,
            (SELECT COUNT(*) FROM threads t WHERE t.user_id = u.id) AS th,
            (SELECT COUNT(*) FROM replies r WHERE r.user_id = u.id) AS rp
       FROM users u ORDER BY (th + rp) DESC LIMIT 8'
);
$topThreads = db_all('SELECT id, title, views, reply_count FROM threads WHERE is_hidden = 0 ORDER BY views DESC LIMIT 8');

/** Smooth-ish line/area chart as inline SVG; $lines = [label => [date => n]]. */
function svg_lines(array $lines, array $colors)
{
    $w = 640; $h = 220; $pl = 34; $pb = 26; $pt = 10; $pr = 10;
    $max = 1;
    foreach ($lines as $s) { $max = max($max, max($s)); }
    $max = (int) ceil($max / 4) * 4 ?: 4;
    $n = count(reset($lines));
    $x = function ($i) use ($w, $pl, $pr, $n) { return $pl + ($n > 1 ? $i * ($w - $pl - $pr) / ($n - 1) : 0); };
    $y = function ($v) use ($h, $pb, $pt, $max) { return $h - $pb - $v * ($h - $pb - $pt) / $max; };
    $o = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" class="ch-svg" role="img" aria-label="رسم بياني">';
    for ($g = 0; $g <= 4; $g++) {
        $v = $max * $g / 4; $yy = $y($v);
        $o .= '<line x1="' . $pl . '" x2="' . ($w - $pr) . '" y1="' . $yy . '" y2="' . $yy . '" class="ch-grid"/><text x="' . ($pl - 6) . '" y="' . ($yy + 4) . '" class="ch-tx" text-anchor="end">' . (int) $v . '</text>';
    }
    $keys = array_keys(reset($lines));
    foreach ($keys as $i => $d) {
        if ($i % 2 === 0) { $o .= '<text x="' . $x($i) . '" y="' . ($h - 7) . '" class="ch-tx" text-anchor="middle">' . substr($d, 8) . '/' . substr($d, 5, 2) . '</text>'; }
    }
    $ci = 0;
    foreach ($lines as $label => $s) {
        $c = $colors[$ci++ % count($colors)];
        $pts = []; $i = 0;
        foreach ($s as $v) { $pts[] = round($x($i), 1) . ',' . round($y($v), 1); $i++; }
        $o .= '<polygon points="' . $x(0) . ',' . $y(0) . ' ' . implode(' ', $pts) . ' ' . $x($n - 1) . ',' . $y(0) . '" fill="' . $c . '" opacity=".10"/>';
        $o .= '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke="' . $c . '" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>';
        $i = 0;
        foreach ($s as $d => $v) { $o .= '<circle cx="' . round($x($i), 1) . '" cy="' . round($y($v), 1) . '" r="3" fill="' . $c . '"><title>' . e($label . ' ' . $d . ': ' . $v) . '</title></circle>'; $i++; }
    }
    return $o . '</svg>';
}

/** Donut chart; $parts = [label => [value, color]]. */
function svg_donut(array $parts)
{
    $total = 0; foreach ($parts as $p) { $total += $p[0]; }
    $r = 15.9155; $off = 25; $o = '<svg viewBox="0 0 42 42" class="ch-donut" role="img" aria-label="توزيع المحتوى"><circle cx="21" cy="21" r="' . $r . '" fill="none" class="ch-ring" stroke-width="5"/>';
    if ($total > 0) {
        foreach ($parts as $label => $p) {
            $pct = $p[0] * 100 / $total;
            $o .= '<circle cx="21" cy="21" r="' . $r . '" fill="none" stroke="' . $p[1] . '" stroke-width="5" stroke-dasharray="' . round($pct, 2) . ' ' . round(100 - $pct, 2) . '" stroke-dashoffset="' . round($off, 2) . '"><title>' . e($label . ': ' . $p[0]) . '</title></circle>';
            $off -= $pct;
        }
    }
    return $o . '<text x="21" y="22.5" text-anchor="middle" class="ch-big">' . $total . '</text></svg>';
}

$kpis = [
    ['users', 'الأعضاء', $k['members'], '+' . (int) $k['members7'] . ' هذا الأسبوع'],
    ['doc', 'المواضيع', $k['threads'], '+' . (int) $k['threads7'] . ' هذا الأسبوع'],
    ['chat', 'الردود', $k['replies'], '+' . (int) $k['replies7'] . ' هذا الأسبوع'],
    ['eye', 'القراءات', $k['views'], 'إجمالي مشاهدات المواضيع'],
    ['bell', 'متصلون الآن', $k['online'], 'خلال آخر ' . (int) ONLINE_WINDOW_MINUTES . ' دقائق'],
    ['mail', 'الرسائل الخاصة', $k['messages'], (int) $k['forums'] . ' منتدى · ' . (int) $k['banned'] . ' محظور'],
];
$maxF = 1; foreach ($topForums as $f) { $maxF = max($maxF, (int) $f['th'] + (int) $f['rp']); }

page_header('الإحصائيات', 'admin');
?>
<h1 class="page-title">لوحة الإدارة</h1>
<?php admin_nav('stats'); ?>

<div class="dash">
    <?php foreach ($kpis as $c): ?>
        <div class="kpi"><span class="kpi-ico"><?= icon($c[0]) ?></span><div><small><?= e($c[1]) ?></small><b><?= number_format((int) $c[2]) ?></b><em><?= e($c[3]) ?></em></div></div>
    <?php endforeach; ?>
</div>

<div class="dash-2">
    <section class="ch-card ch-wide">
        <h2>النشاط خلال آخر <?= $days ?> يومًا</h2>
        <div class="ch-legend"><span><i style="background:#0f766e"></i>مواضيع</span><span><i style="background:#2563eb"></i>ردود</span><span><i style="background:#f59e0b"></i>أعضاء جدد</span></div>
        <?= svg_lines(['مواضيع' => $sThreads, 'ردود' => $sReplies, 'أعضاء جدد' => $sMembers], ['#0f766e', '#2563eb', '#f59e0b']) ?>
    </section>
    <section class="ch-card">
        <h2>توزيع المحتوى</h2>
        <?= svg_donut(['مواضيع' => [(int) $k['threads'], '#0f766e'], 'ردود' => [(int) $k['replies'], '#2563eb'], 'رسائل خاصة' => [(int) $k['messages'], '#f59e0b']]) ?>
        <div class="ch-legend ch-legend-col"><span><i style="background:#0f766e"></i>مواضيع <?= (int) $k['threads'] ?></span><span><i style="background:#2563eb"></i>ردود <?= (int) $k['replies'] ?></span><span><i style="background:#f59e0b"></i>رسائل <?= (int) $k['messages'] ?></span></div>
    </section>
</div>

<div class="dash-2">
    <section class="ch-card ch-wide">
        <h2>أكثر المنتديات نشاطًا</h2>
        <?php foreach ($topForums as $f): $tot = (int) $f['th'] + (int) $f['rp']; ?>
            <div class="hbar"><a href="<?= e(url('forum.php?id=' . (int) $f['id'])) ?>"><?= e($f['name']) ?></a>
                <div class="hbar-track"><i style="width:<?= max(2, round($tot * 100 / $maxF)) ?>%"></i></div>
                <span><?= (int) $f['th'] ?> / <?= (int) $f['rp'] ?></span></div>
        <?php endforeach; ?>
        <p class="meta">الأرقام: مواضيع / ردود</p>
    </section>
    <section class="ch-card">
        <h2>أنشط الأعضاء</h2>
        <div class="pro-wrap"><table class="pro-table pro-small"><tbody>
        <?php foreach ($topMembers as $i => $m): ?>
            <tr><td class="pt-n"><?= $i + 1 ?></td><td><?= user_link($m['id'], $m['username']) ?></td><td class="pt-c"><b><?= (int) $m['th'] + (int) $m['rp'] ?></b></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </section>
</div>

<section class="ch-card">
    <h2>أكثر المواضيع قراءة</h2>
    <div class="pro-wrap"><table class="pro-table">
        <thead><tr><th>الموضوع</th><th class="pt-c"><?= icon('chat') ?></th><th class="pt-c"><?= icon('eye') ?></th></tr></thead>
        <tbody>
        <?php foreach ($topThreads as $t): ?>
            <tr><td><a href="<?= e(url('thread.php?id=' . (int) $t['id'])) ?>"><?= e($t['title']) ?></a></td><td class="pt-c"><?= (int) $t['reply_count'] ?></td><td class="pt-c"><b><?= (int) $t['views'] ?></b></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>
<?php page_footer();
