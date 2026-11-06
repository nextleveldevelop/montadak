<?php
/** /robots.txt — generated on the fly (see .htaccess). One single source: the text saved in the admin settings + the Sitemap line. */
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=300');
echo robots_txt_output();
