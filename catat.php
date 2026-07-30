<?php
/*
 * Pencatat kunjungan mandiri.
 *
 * Dipilih ketimbang layanan pihak ketiga supaya tidak ada satu pun data
 * pengunjung yang keluar dari server sendiri, dan supaya situs tidak perlu
 * memasang banner persetujuan kuki.
 *
 * Yang dicatat sengaja dibatasi pada hal yang benar-benar dibutuhkan untuk
 * memutuskan bagian mana yang layak dikembangkan: alamat halaman, situs
 * perujuk, dan lebar layar. Alamat IP tidak pernah disimpan, hanya dijadikan
 * bahan sidik harian yang tidak bisa dikembalikan menjadi IP semula, dan
 * garamnya berganti tiap hari sehingga pengunjung yang sama pada hari berbeda
 * tidak dapat dirangkai menjadi satu jejak.
 */

header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate');
// GIF 1x1 tembus pandang, dikirim lebih dulu supaya halaman tidak menunggu
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); }

$dir = __DIR__ . '/data';
if (!is_dir($dir)) { @mkdir($dir, 0755, true); }

// Hanya menerima permintaan dari situs sendiri
$asal = $_SERVER['HTTP_REFERER'] ?? '';
if ($asal !== '' && strpos($asal, $_SERVER['HTTP_HOST'] ?? '') === false) { exit; }

// Perayap mesin pencari tidak dihitung sebagai pembaca
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|monitor|curl|wget/i', $ua)) { exit; }

function bersih($t, $panjang = 300) {
    $t = preg_replace('/[\x00-\x1F\x7F"]/u', '', (string) $t);
    return mb_substr(trim($t), 0, $panjang);
}

$hal   = bersih($_GET['h'] ?? '', 200);
$rujuk = bersih($_GET['r'] ?? '', 200);
$lebar = (int) ($_GET['w'] ?? 0);
if ($hal === '') { exit; }

// Rujukan dari situs sendiri tidak menarik, cukup ditandai kosong
if ($rujuk !== '' && strpos($rujuk, $_SERVER['HTTP_HOST'] ?? '') !== false) { $rujuk = ''; }

// Sidik kunjungan: tidak dapat dikembalikan ke IP, dan berganti tiap hari
$hari  = gmdate('Y-m-d');
$garam = $hari . '|' . ($_SERVER['SERVER_NAME'] ?? 'dara');
$sidik = substr(hash('sha256', $garam . '|' . ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $ua), 0, 12);

$baris = sprintf("%s\t%s\t%s\t%s\t%d\n", gmdate('c'), $sidik, $hal, $rujuk, $lebar);
@file_put_contents($dir . '/' . $hari . '.tsv', $baris, FILE_APPEND | LOCK_EX);
