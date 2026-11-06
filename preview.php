<?php
/** Renders what a post will look like (same sanitizer/renderer as real posts). */
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (current_user() === null || !is_post()) {
    http_response_code(401);
    echo json_encode(['error' => 'login']);
    exit;
}
$sent = isset($_POST['csrf']) ? $_POST['csrf'] : '';
if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
    http_response_code(419);
    echo json_encode(['error' => 'csrf']);
    exit;
}
$content = rich_str('content');
echo json_encode(['html' => $content === '' ? '' : post_html($content)], JSON_UNESCAPED_UNICODE);
