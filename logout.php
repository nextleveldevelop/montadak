<?php
require __DIR__ . '/includes/bootstrap.php';

// Logging out changes state, so it is a POST with a CSRF token (not a plain link).
if (!is_post()) {
    redirect('index.php');
}
csrf_verify();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

session_start(); // fresh session just to carry the confirmation message
session_regenerate_id(true);
flash('success', 'تم تسجيل الخروج.');
redirect('index.php');
