<?php
/* ------------------------------------------------------------------
   Penyalur buka paper/buku sekaligus penghitungnya.

   Kartu paper dan buku menautkan ke sini, bukan langsung ke situs
   penerbit, supaya tiap klik bisa dihitung. Alamat tujuan diambil dari
   manifes berdasarkan id, jadi tidak mungkin dipakai mengalihkan ke
   alamat sembarangan.
   ------------------------------------------------------------------ */
require __DIR__ . '/sesi.php';

$t  = (string) ($_GET['t'] ?? '');
$id = (int) ($_GET['id'] ?? -1);

if ($t === 'paper')      $baris = muat_paper();
elseif ($t === 'buku')   $baris = muat_buku();
else { http_response_code(404); echo 'Tidak ditemukan.'; exit; }

$url = '';
foreach ($baris as $r) {
    if ((int) ($r['id'] ?? -1) === $id) { $url = (string) ($r['tautan'] ?? ''); break; }
}

if ($url === '' || !preg_match('#^https?://#i', $url)) {
    http_response_code(404);
    echo 'Tautan tidak tersedia.';
    exit;
}

catat_statistik('buka:' . $t . ':' . $id);
header('Location: ' . $url);
exit;
