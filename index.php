<?php
/**
 * Gerbang aplikasi: tampilkan halaman masuk bila belum ada sesi,
 * atau kirim aplikasi (index.html) bila sudah masuk.
 */
declare(strict_types=1);
require __DIR__ . '/server/bootstrap.php';
mr_session_start();
mr_no_store();

$user = mr_current_user();
if (!$user) {
    $csrf = $_SESSION['csrf'];
    $expired = !empty($_SESSION['expired']);
    unset($_SESSION['expired']);
    require __DIR__ . '/server/login.php';
    exit;
}

header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/index.html');
