<?php
/** POST {email, password}: masuk ke aplikasi. */
declare(strict_types=1);
require dirname(__DIR__) . '/server/bootstrap.php';
mr_session_start();
$in = mr_require_post();

$email = strtolower(trim((string) ($in['email'] ?? '')));
$password = (string) ($in['password'] ?? '');
if ($email === '' || $password === '') {
    mr_json(422, ['error' => 'Isi alamat email dan kata sandi.']);
}

$wait = mr_locked_for($email);
if ($wait > 0) {
    mr_log('login_locked', $email);
    mr_json(429, ['error' => sprintf('Terlalu banyak percobaan gagal. Coba lagi dalam %d menit.', (int) ceil($wait / 60)), 'retryAfter' => $wait]);
}

$user = mr_find_user($email);
// Selalu jalankan password_verify agar waktu respons tidak membocorkan email yang terdaftar.
$hash = $user['hash'] ?? '$2y$10$WgyrkyDPgzBxq1nPmSVL1OrPF6hEdfLyXiKiPSj2bOyU.c/ZMspQO';
$ok = password_verify($password, $hash) && $user && !empty($user['active']);

if (!$ok) {
    mr_register_fail($email);
    mr_log('login_fail', $email);
    usleep(random_int(200000, 400000));
    mr_json(401, ['error' => 'Email atau kata sandi salah.']);
}

mr_clear_fails($email);
session_regenerate_id(true);
$now = time();
$_SESSION['uid'] = $user['id'];
$_SESSION['since'] = $now;
$_SESSION['last'] = $now;
$_SESSION['csrf'] = bin2hex(random_bytes(32));

$previous = $user['last_login'] ?? null;
$_SESSION['prev_login'] = $previous;   // ditampilkan di menu akun sebagai "terakhir masuk"
mr_update_json('users.json', [], function (array $users) use ($user, $hash, $password) {
    foreach ($users as &$u) {
        if ($u['id'] === $user['id']) {
            $u['last_login'] = date('c');
            if (password_needs_rehash($u['hash'], PASSWORD_DEFAULT)) {
                $u['hash'] = password_hash($password, PASSWORD_DEFAULT);
            }
        }
    }
    return $users;
});
mr_log('login_ok', $email);

$public = mr_public_user($user);
$public['lastLogin'] = $previous;
mr_json(200, ['ok' => true, 'user' => $public, 'csrf' => $_SESSION['csrf']]);
