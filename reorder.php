<?php
/** POST-only, administrators: move a category or a forum up or down on the home page. */
require __DIR__ . '/includes/bootstrap.php';

if (!is_post()) {
    redirect('index.php');
}
require_admin();
csrf_verify();

$type = post_str('type');
$dir = post_str('dir') === 'down' ? 1 : -1;
$id = post_str('id');

// Current display order: categories in order of their first forum, forums by sort_order.
$order = [];
$index = [];
foreach (db_all('SELECT id, category FROM forums ORDER BY sort_order, id') as $r) {
    $c = (string) $r['category'];
    if (!isset($index[$c])) {
        $index[$c] = count($order);
        $order[] = ['name' => $c, 'ids' => []];
    }
    $order[$index[$c]]['ids'][] = (int) $r['id'];
}

if ($type === 'category' && isset($index[$id])) {
    $i = $index[$id];
    $j = $i + $dir;
    if (isset($order[$j])) {
        $tmp = $order[$i];
        $order[$i] = $order[$j];
        $order[$j] = $tmp;
    }
} elseif ($type === 'forum') {
    foreach ($order as $g => $group) {
        $pos = array_search((int) $id, $group['ids'], true);
        if ($pos !== false) {
            $j = $pos + $dir;
            if (isset($group['ids'][$j])) {
                $tmp = $order[$g]['ids'][$pos];
                $order[$g]['ids'][$pos] = $order[$g]['ids'][$j];
                $order[$g]['ids'][$j] = $tmp;
            }
            break;
        }
    }
}

// Write the new order back as 10, 20, 30 ...
db_transaction(function () use ($order) {
    $n = 10;
    foreach ($order as $group) {
        foreach ($group['ids'] as $fid) {
            db_exec('UPDATE forums SET sort_order = ? WHERE id = ?', 'ii', [$n, $fid]);
            $n += 10;
        }
    }
});
redirect('index.php');
