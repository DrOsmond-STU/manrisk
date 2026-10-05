<?php
/**
 * Pengelolaan akun (hanya Super Admin).
 *   GET  api/users.php           → daftar akun + daftar peran
 *   GET  api/users.php?log=1     → 300 entri log keamanan terakhir
 *   POST {action: create|update|reset|toggle|delete, ...}
 */
declare(strict_types=1);
require dirname(__DIR__) . '/server/bootstrap.php';
mr_session_start();

$me = mr_current_user();
if (!$me) {
    mr_json(401, ['error' => 'Sesi berakhir. Silakan masuk kembali.']);
}
if ($me['role'] !== 'Super Admin') {
    mr_log('users_forbidden', $me['email']);
    mr_json(403, ['error' => 'Hanya Super Admin yang dapat mengelola pengguna.']);
}

const MR_ROLES = ['Super Admin', 'Risk Administrator', 'Risk Manager', 'Risk Officer', 'Risk Owner', 'Management', 'Auditor'];

function mr_admin_user(array $u): array
{
    return mr_public_user($u) + [
        'active' => !empty($u['active']),
        'created' => $u['created'] ?? null,
        'passwordChanged' => $u['password_changed'] ?? null,
    ];
}

function mr_list(string $meId): array
{
    return ['users' => array_map('mr_admin_user', mr_users()), 'roles' => MR_ROLES, 'me' => $meId];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    if (isset($_GET['log'])) {
        $file = mr_data_dir() . '/auth.log';
        $rows = [];
        if (is_file($file)) {
            $lines = array_slice(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -300);
            foreach (array_reverse($lines) as $line) {
                $p = explode("\t", $line);
                $rows[] = ['t' => $p[0] ?? '', 'ip' => $p[1] ?? '', 'event' => $p[2] ?? '', 'email' => $p[3] ?? '', 'detail' => $p[4] ?? ''];
            }
        }
        mr_json(200, ['log' => $rows]);
    }
    mr_json(200, mr_list($me['id']));
}

$in = mr_require_post();
$action = (string) ($in['action'] ?? '');

/** Validasi profil; mengembalikan [name, email, role, unit] atau menghentikan dengan 422. */
function mr_validate_profile(array $in, ?string $selfId): array
{
    $name = trim((string) ($in['name'] ?? ''));
    $email = strtolower(trim((string) ($in['email'] ?? '')));
    $role = (string) ($in['role'] ?? '');
    $unit = trim((string) ($in['unit'] ?? ''));
    if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
        mr_json(422, ['error' => 'Nama harus 2–80 karakter.', 'field' => 'name']);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 120) {
        mr_json(422, ['error' => 'Alamat email tidak valid.', 'field' => 'email']);
    }
    if (!in_array($role, MR_ROLES, true)) {
        mr_json(422, ['error' => 'Peran tidak dikenal.', 'field' => 'role']);
    }
    if (mb_strlen($unit) > 80) {
        mr_json(422, ['error' => 'Unit maksimal 80 karakter.', 'field' => 'unit']);
    }
    $exists = mr_find_user($email);
    if ($exists && $exists['id'] !== $selfId) {
        mr_json(422, ['error' => 'Email sudah dipakai akun lain.', 'field' => 'email']);
    }
    return [$name, $email, $role, $unit];
}

/** Jumlah Super Admin aktif selain $exceptId. */
function mr_other_superadmins(string $exceptId): int
{
    $n = 0;
    foreach (mr_users() as $u) {
        if ($u['id'] !== $exceptId && $u['role'] === 'Super Admin' && !empty($u['active'])) {
            $n++;
        }
    }
    return $n;
}

switch ($action) {
    case 'create': {
        [$name, $email, $role, $unit] = mr_validate_profile($in, null);
        $manual = (string) ($in['password'] ?? '');
        $temp = null;
        if ($manual !== '') {
            if ($msg = mr_check_password($manual)) {
                mr_json(422, ['error' => $msg, 'field' => 'password']);
            }
            $password = $manual;
        } else {
            $temp = mr_random_password();
            $password = $temp;
        }
        $mustChange = array_key_exists('mustChange', $in) ? (bool) $in['mustChange'] : true;
        $id = 'u_' . substr(bin2hex(random_bytes(6)), 0, 10);
        mr_update_json('users.json', [], function (array $users) use ($id, $name, $email, $role, $unit, $password, $mustChange) {
            $users[] = [
                'id' => $id, 'email' => $email, 'name' => $name, 'role' => $role, 'unit' => $unit,
                'hash' => password_hash($password, PASSWORD_DEFAULT), 'active' => true,
                'must_change' => $mustChange, 'created' => date('c'), 'last_login' => null,
            ];
            return $users;
        });
        mr_log('user_create', $me['email'], $email . ' (' . $role . ')');
        mr_json(200, ['ok' => true, 'tempPassword' => $temp, 'created' => $id] + mr_list($me['id']));
    }

    case 'update': {
        $id = (string) ($in['id'] ?? '');
        $target = mr_user_by_id($id);
        if (!$target) {
            mr_json(404, ['error' => 'Akun tidak ditemukan.']);
        }
        [$name, $email, $role, $unit] = mr_validate_profile($in, $id);
        $active = array_key_exists('active', $in) ? (bool) $in['active'] : !empty($target['active']);
        if ($id === $me['id'] && (!$active || $role !== 'Super Admin')) {
            mr_json(422, ['error' => 'Anda tidak dapat menonaktifkan atau menurunkan peran akun sendiri.']);
        }
        $losingSuper = $target['role'] === 'Super Admin' && !empty($target['active']) && ($role !== 'Super Admin' || !$active);
        if ($losingSuper && mr_other_superadmins($id) === 0) {
            mr_json(422, ['error' => 'Harus tersisa minimal satu Super Admin aktif.']);
        }
        $changes = [];
        mr_update_json('users.json', [], function (array $users) use ($id, $name, $email, $role, $unit, $active, &$changes) {
            foreach ($users as &$u) {
                if ($u['id'] === $id) {
                    foreach (['name' => $name, 'email' => $email, 'role' => $role, 'unit' => $unit] as $k => $v) {
                        if (($u[$k] ?? '') !== $v) { $changes[] = "$k: " . ($u[$k] ?? '—') . " → $v"; $u[$k] = $v; }
                    }
                    if (!empty($u['active']) !== $active) { $changes[] = 'active: ' . ($active ? 'ya' : 'tidak'); $u['active'] = $active; }
                }
            }
            return $users;
        });
        mr_log('user_update', $me['email'], $target['email'] . ' · ' . implode('; ', $changes));
        mr_json(200, ['ok' => true] + mr_list($me['id']));
    }

    case 'toggle': {
        $id = (string) ($in['id'] ?? '');
        $target = mr_user_by_id($id);
        if (!$target) {
            mr_json(404, ['error' => 'Akun tidak ditemukan.']);
        }
        $active = empty($target['active']);
        if ($id === $me['id'] && !$active) {
            mr_json(422, ['error' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
        }
        if (!$active && $target['role'] === 'Super Admin' && mr_other_superadmins($id) === 0) {
            mr_json(422, ['error' => 'Harus tersisa minimal satu Super Admin aktif.']);
        }
        mr_update_json('users.json', [], function (array $users) use ($id, $active) {
            foreach ($users as &$u) {
                if ($u['id'] === $id) { $u['active'] = $active; }
            }
            return $users;
        });
        mr_log($active ? 'user_activate' : 'user_deactivate', $me['email'], $target['email']);
        mr_json(200, ['ok' => true] + mr_list($me['id']));
    }

    case 'reset': {
        $id = (string) ($in['id'] ?? '');
        $target = mr_user_by_id($id);
        if (!$target) {
            mr_json(404, ['error' => 'Akun tidak ditemukan.']);
        }
        $temp = mr_random_password();
        mr_update_json('users.json', [], function (array $users) use ($id, $temp) {
            foreach ($users as &$u) {
                if ($u['id'] === $id) {
                    $u['hash'] = password_hash($temp, PASSWORD_DEFAULT);
                    $u['must_change'] = true;
                    $u['password_changed'] = date('c');
                }
            }
            return $users;
        });
        mr_log('user_reset', $me['email'], $target['email']);
        mr_json(200, ['ok' => true, 'tempPassword' => $temp] + mr_list($me['id']));
    }

    case 'delete': {
        $id = (string) ($in['id'] ?? '');
        $target = mr_user_by_id($id);
        if (!$target) {
            mr_json(404, ['error' => 'Akun tidak ditemukan.']);
        }
        if ($id === $me['id']) {
            mr_json(422, ['error' => 'Anda tidak dapat menghapus akun sendiri.']);
        }
        if (!empty($target['last_login'])) {
            mr_json(422, ['error' => 'Akun yang pernah masuk tidak dihapus agar jejak auditnya tetap ada. Nonaktifkan saja.']);
        }
        if ($target['role'] === 'Super Admin' && !empty($target['active']) && mr_other_superadmins($id) === 0) {
            mr_json(422, ['error' => 'Harus tersisa minimal satu Super Admin aktif.']);
        }
        mr_update_json('users.json', [], fn (array $users) => array_values(array_filter($users, fn ($u) => $u['id'] !== $id)));
        mr_log('user_delete', $me['email'], $target['email']);
        mr_json(200, ['ok' => true] + mr_list($me['id']));
    }

    default:
        mr_json(400, ['error' => 'Aksi tidak dikenal.']);
}
