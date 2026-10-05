<?php
/** GET: status sesi + token CSRF untuk formulir masuk. */
declare(strict_types=1);
require dirname(__DIR__) . '/server/bootstrap.php';
mr_session_start();

$user = mr_current_user();
$expired = !empty($_SESSION['expired']);
unset($_SESSION['expired']);

$public = $user ? mr_public_user($user) : null;
if ($public) {
    $public['lastLogin'] = $_SESSION['prev_login'] ?? null;
}

mr_json(200, [
    'authenticated' => (bool) $user,
    'user' => $public,
    'csrf' => $_SESSION['csrf'],
    'expired' => $expired,
    'idleLimit' => MR_IDLE_LIMIT,
]);
