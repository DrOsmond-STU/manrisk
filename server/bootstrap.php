<?php
/**
 * ManRisk ERM — autentikasi sisi server.
 *
 * Akun disimpan di luar folder publik (MR_DATA_DIR/users.json) dengan kata sandi
 * berupa hash bcrypt. Sesi PHP memakai cookie HttpOnly + Secure + SameSite=Lax,
 * berakhir setelah 30 menit tanpa aktivitas atau 8 jam sejak masuk.
 */
declare(strict_types=1);

const MR_IDLE_LIMIT = 1800;        // 30 menit tanpa aktivitas
const MR_ABSOLUTE_LIMIT = 28800;   // 8 jam sejak masuk
const MR_MAX_FAILS = 5;            // percobaan gagal sebelum dikunci sementara
const MR_LOCK_WINDOW = 900;        // 15 menit
const MR_MIN_PASSWORD = 10;

function mr_data_dir(): string
{
    $dir = getenv('MR_DATA_DIR') ?: dirname(__DIR__, 2) . '/manrisk-data';
    return rtrim($dir, '/');
}

function mr_no_store(): void
{
    header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Vary: Cookie');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

function mr_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function mr_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $path = mr_data_dir() . '/sessions';
    if (is_dir($path) || @mkdir($path, 0700, true)) {
        session_save_path($path);
    }
    session_name('MRSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => mr_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string) MR_ABSOLUTE_LIMIT);
    session_start();

    $now = time();
    if (isset($_SESSION['uid'])) {
        $idle = $now - (int) ($_SESSION['last'] ?? 0);
        $age = $now - (int) ($_SESSION['since'] ?? 0);
        if ($idle > MR_IDLE_LIMIT || $age > MR_ABSOLUTE_LIMIT) {
            mr_logout();
            session_start();
            session_regenerate_id(true);
            $_SESSION['expired'] = true;
        } else {
            $_SESSION['last'] = $now;
        }
    }
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}

/* ---------- Penyimpanan JSON dengan kunci berkas ---------- */

function mr_read_json(string $name, $default)
{
    $file = mr_data_dir() . '/' . $name;
    if (!is_file($file)) {
        return $default;
    }
    $fh = fopen($file, 'r');
    if (!$fh) {
        return $default;
    }
    flock($fh, LOCK_SH);
    $raw = stream_get_contents($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    $data = json_decode((string) $raw, true);
    return is_array($data) ? $data : $default;
}

/** Ubah isi berkas JSON secara atomik: $fn menerima data lama dan mengembalikan data baru. */
function mr_update_json(string $name, $default, callable $fn)
{
    $dir = mr_data_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $fh = fopen($dir . '/' . $name, 'c+');
    if (!$fh) {
        throw new RuntimeException('Penyimpanan tidak dapat ditulis');
    }
    flock($fh, LOCK_EX);
    $raw = stream_get_contents($fh);
    $data = json_decode((string) $raw, true);
    $data = $fn(is_array($data) ? $data : $default);
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return $data;
}

/* ---------- Pengguna ---------- */

function mr_users(): array
{
    return mr_read_json('users.json', []);
}

function mr_find_user(string $email): ?array
{
    $email = strtolower(trim($email));
    foreach (mr_users() as $u) {
        if (strtolower($u['email']) === $email) {
            return $u;
        }
    }
    return null;
}

function mr_user_by_id(string $id): ?array
{
    foreach (mr_users() as $u) {
        if ($u['id'] === $id) {
            return $u;
        }
    }
    return null;
}

/** Data pengguna yang aman dikirim ke browser (tanpa hash). */
function mr_public_user(array $u): array
{
    return [
        'id' => $u['id'],
        'email' => $u['email'],
        'name' => $u['name'],
        'role' => $u['role'],
        'unit' => $u['unit'] ?? '',
        'lastLogin' => $u['last_login'] ?? null,
        'mustChange' => !empty($u['must_change']),
    ];
}

function mr_current_user(): ?array
{
    if (empty($_SESSION['uid'])) {
        return null;
    }
    $u = mr_user_by_id((string) $_SESSION['uid']);
    if (!$u || empty($u['active'])) {
        return null;
    }
    return $u;
}

function mr_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $p['path'],
            'secure' => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?: 'Lax',
        ]);
    }
    session_destroy();
}

/* ---------- Pembatasan percobaan masuk ---------- */

function mr_client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function mr_throttle_key(string $email): string
{
    return hash('sha256', strtolower(trim($email)) . '|' . mr_client_ip());
}

/** Detik tersisa sebelum boleh mencoba lagi (0 = boleh). */
function mr_locked_for(string $email): int
{
    $all = mr_read_json('throttle.json', []);
    $rec = $all[mr_throttle_key($email)] ?? null;
    if (!$rec || count($rec['fails'] ?? []) < MR_MAX_FAILS) {
        return 0;
    }
    $oldest = min($rec['fails']);
    $left = ($oldest + MR_LOCK_WINDOW) - time();
    return max(0, $left);
}

function mr_register_fail(string $email): void
{
    $key = mr_throttle_key($email);
    mr_update_json('throttle.json', [], function (array $all) use ($key) {
        $now = time();
        foreach ($all as $k => $rec) {   // buang catatan kedaluwarsa
            $all[$k]['fails'] = array_values(array_filter($rec['fails'] ?? [], fn ($t) => $t > $now - MR_LOCK_WINDOW));
            if (!$all[$k]['fails']) {
                unset($all[$k]);
            }
        }
        $all[$key]['fails'][] = $now;
        $all[$key]['fails'] = array_slice($all[$key]['fails'], -MR_MAX_FAILS);
        return $all;
    });
}

function mr_clear_fails(string $email): void
{
    $key = mr_throttle_key($email);
    mr_update_json('throttle.json', [], function (array $all) use ($key) {
        unset($all[$key]);
        return $all;
    });
}

/* ---------- Log keamanan ---------- */

function mr_log(string $event, string $email, string $detail = ''): void
{
    $line = sprintf("%s\t%s\t%s\t%s\t%s\n", date('c'), mr_client_ip(), $event, $email, $detail);
    @file_put_contents(mr_data_dir() . '/auth.log', $line, FILE_APPEND | LOCK_EX);
}

/* ---------- Respons API ---------- */

function mr_json(int $status, array $body): void
{
    mr_no_store();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Hanya POST JSON dari asal yang sama dengan token CSRF yang cocok. */
function mr_require_post(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        mr_json(405, ['error' => 'Metode tidak diizinkan.']);
    }
    $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), $token)) {
        mr_json(403, ['error' => 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.']);
    }
    $body = json_decode((string) file_get_contents('php://input'), true);
    return is_array($body) ? $body : [];
}
