<?php
/* ------------------------------------------------------------------
   Penerima ketukan kunjungan halaman.

   Dipanggil oleh skrip kecil di tiap halaman lewat navigator.sendBeacon.
   Hanya mencatat jumlah kunjungan per path, tanpa cookie, tanpa data
   pribadi, tanpa pihak ketiga. Path disaring ketat supaya tidak bisa
   dipakai menaruh kunci sembarangan.
   ------------------------------------------------------------------ */
require __DIR__ . '/sesi.php';
http_response_code(204);
header('Content-Type: text/plain');

$p = (string) ($_POST['p'] ?? '');
$p = (string) (parse_url($p, PHP_URL_PATH) ?? '');
if ($p === '') $p = '/';
if (strlen($p) > 120 || strpos($p, '..') !== false) exit;
if (!preg_match('#^/[a-zA-Z0-9/_.\-]*$#', $p)) exit;

catat_statistik('lihat:' . $p);
