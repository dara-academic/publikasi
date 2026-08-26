<?php
/* Keluar: sesi dimatikan seluruhnya, lalu pulang ke beranda. */
require __DIR__ . '/sesi.php';
mulai_sesi();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: /');
exit;
