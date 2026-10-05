<?php
/**
 * Membuat berkas akun awal (users.json) dengan satu akun per peran.
 *
 *   php server/seed.php --out /home/semestat/manrisk-data/users.json
 *   php server/seed.php --out users.json --reset        # timpa berkas yang ada
 *
 * Kata sandi dibangkitkan acak dan hanya dicetak sekali ke layar.
 * Berkas keluaran berisi hash bcrypt, bukan kata sandi.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/bootstrap.php';

$opts = getopt('', ['out:', 'reset']);
$out = $opts['out'] ?? (dirname(__DIR__, 2) . '/manrisk-data/users.json');
if (is_file($out) && !isset($opts['reset'])) {
    fwrite(STDERR, "Berkas $out sudah ada. Tambahkan --reset untuk menimpanya.\n");
    exit(1);
}

$roles = [
    ['admin',      'Admin Sistem',       'Super Admin',        'Direktorat Teknologi Informasi'],
    ['riskadmin',  'Yudi Pratama',       'Risk Administrator', 'Unit Manajemen Risiko'],
    ['manager',    'Osmond',             'Risk Manager',       'Unit Manajemen Risiko'],
    ['officer',    'Fajar Nugroho',      'Risk Officer',       'Biro Umum & Pengadaan'],
    ['owner',      'Hendra Wijaya',      'Risk Owner',         'Direktorat Teknologi Informasi'],
    ['management', 'Ir. Taufik Rahman',  'Management',         'Pimpinan'],
    ['auditor',    'Nurul Hidayah',      'Auditor',            'Inspektorat'],
];


$users = [];
$shown = [];
foreach ($roles as [$slug, $name, $role, $unit]) {
    $pw = mr_random_password();
    $users[] = [
        'id' => 'u_' . $slug,
        'email' => $slug . '@manrisk.id',
        'name' => $name,
        'role' => $role,
        'unit' => $unit,
        'hash' => password_hash($pw, PASSWORD_DEFAULT),
        'active' => true,
        'must_change' => false,
        'created' => date('c'),
        'last_login' => null,
    ];
    $shown[] = [$slug . '@manrisk.id', $pw, $role];
}

$dir = dirname($out);
if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
    fwrite(STDERR, "Tidak dapat membuat folder $dir\n");
    exit(1);
}
file_put_contents($out, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
chmod($out, 0600);

echo "Akun tersimpan di $out\n\n";
printf("%-24s %-16s %s\n", 'Email', 'Kata sandi', 'Peran');
foreach ($shown as [$e, $p, $r]) {
    printf("%-24s %-16s %s\n", $e, $p, $r);
}
