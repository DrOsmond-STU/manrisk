<?php
/** POST {current, next}: ganti kata sandi pengguna yang sedang masuk. */
declare(strict_types=1);
require dirname(__DIR__) . '/server/bootstrap.php';
mr_session_start();
$in = mr_require_post();

$user = mr_current_user();
if (!$user) {
    mr_json(401, ['error' => 'Sesi berakhir. Silakan masuk kembali.']);
}
$current = (string) ($in['current'] ?? '');
$next = (string) ($in['next'] ?? '');

if (!password_verify($current, $user['hash'])) {
    mr_log('password_fail', $user['email']);
    mr_json(422, ['error' => 'Kata sandi saat ini salah.', 'field' => 'current']);
}
if ($msg = mr_check_password($next)) {
    mr_json(422, ['error' => $msg, 'field' => 'next']);
}
if (hash_equals($current, $next)) {
    mr_json(422, ['error' => 'Kata sandi baru harus berbeda dari yang lama.', 'field' => 'next']);
}

mr_update_json('users.json', [], function (array $users) use ($user, $next) {
    foreach ($users as &$u) {
        if ($u['id'] === $user['id']) {
            $u['hash'] = password_hash($next, PASSWORD_DEFAULT);
            $u['must_change'] = false;
            $u['password_changed'] = date('c');
        }
    }
    return $users;
});
session_regenerate_id(true);
$_SESSION['csrf'] = bin2hex(random_bytes(32));
mr_log('password_changed', $user['email']);
mr_json(200, ['ok' => true, 'csrf' => $_SESSION['csrf']]);
