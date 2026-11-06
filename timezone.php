<?php
/** POST-only: the time-zone selector in the header. Members save it to their account, guests to the session. */
require __DIR__ . '/includes/bootstrap.php';

if (!is_post()) {
    redirect('index.php');
}
csrf_verify();

$tz = post_str('tz');
if (isset(timezone_choices()[$tz])) {
    $u = current_user();
    if ($u !== null) {
        db_exec('UPDATE users SET timezone = ? WHERE id = ?', 'si', [$tz, $u['id']]);
    } else {
        $_SESSION['tz'] = $tz;
    }
}
redirect(safe_next(post_str('next')));
