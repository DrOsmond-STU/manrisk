<?php
/**
 * Gerbang berkas JavaScript aplikasi: hanya dikirim ke sesi yang sudah masuk,
 * sehingga data demo dan logika aplikasi tidak dapat diunduh tanpa login.
 * Dipanggil lewat RewriteRule di .htaccess untuk assets/*.js.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/server/bootstrap.php';
mr_session_start();

$f = (string) ($_GET['f'] ?? '');
if (!preg_match('/^[A-Za-z0-9_-]+\.js$/', $f)) {
    mr_json(404, ['error' => 'Berkas tidak ditemukan.']);
}
$path = dirname(__DIR__) . '/assets/' . $f;
if (!is_file($path)) {
    mr_json(404, ['error' => 'Berkas tidak ditemukan.']);
}
if (!mr_current_user()) {
    mr_json(401, ['error' => 'Silakan masuk terlebih dahulu.']);
}

$etag = '"' . md5($f . filemtime($path) . filesize($path)) . '"';
mr_no_store();
header('Content-Type: application/javascript; charset=utf-8');
header('ETag: ' . $etag);
if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}
header('Content-Length: ' . filesize($path));
readfile($path);
