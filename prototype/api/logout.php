<?php
/** POST: keluar dari aplikasi. */
declare(strict_types=1);
require dirname(__DIR__) . '/server/bootstrap.php';
mr_session_start();
mr_require_post();
$user = mr_current_user();
if ($user) {
    mr_log('logout', $user['email']);
}
mr_logout();
mr_json(200, ['ok' => true]);
