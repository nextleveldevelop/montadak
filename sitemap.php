<?php
/** sitemap.xml — served at /sitemap.xml (see .htaccess). Only when the administrator enabled it in هوية المنتدى. */
require __DIR__ . '/includes/bootstrap.php';

if (site_setting('sitemap_enabled', '0') !== '1') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Sitemap is disabled.');
}

$base = site_url_base();
$x = function ($s) { return htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8'); };
$iso = function ($ts) { return $ts ? gmdate('Y-m-d\TH:i:s\Z', strtotime($ts . ' UTC')) : null; };

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
echo '<url><loc>' . $x($base . '/index.php') . '</loc></url>' . "\n";
foreach (db_all('SELECT id FROM forums ORDER BY sort_order, id') as $f) {
    echo '<url><loc>' . $x($base . '/forum.php?id=' . (int) $f['id']) . '</loc></url>' . "\n";
}
foreach (db_all('SELECT id, last_post_at FROM threads WHERE is_hidden = 0 ORDER BY id DESC LIMIT 45000') as $t) {
    $m = $iso($t['last_post_at']);
    echo '<url><loc>' . $x($base . '/thread.php?id=' . (int) $t['id']) . '</loc>' . ($m ? '<lastmod>' . $m . '</lastmod>' : '') . '</url>' . "\n";
}
echo '</urlset>' . "\n";
