<?php
/* ------------------------------------------------------------------
   Penyalur unduhan materi sekaligus penghitungnya.

   Materi ditautkan lewat berkas ini, bukan langsung ke PDF-nya, supaya
   tiap unduhan bisa dihitung. Sesudah dicatat, berkas PDF disajikan apa
   adanya. Nama mata kuliah dan berkas disaring ketat agar tidak bisa
   dipakai menembus keluar folder unggahan.
   ------------------------------------------------------------------ */
require __DIR__ . '/sesi.php';

$mk = (string) ($_GET['mk'] ?? '');
$f  = basename((string) ($_GET['f'] ?? ''));

/* Dua tempat sah: materi unggahan admin, dan materi kuliah lama. */
$path = '';
if (preg_match('/^[a-z0-9\-]+$/', $mk) && preg_match('/^[a-zA-Z0-9._\-]+\.pdf$/', $f)) {
    foreach ([__DIR__ . '/unggahan/' . $mk . '/' . $f,
              __DIR__ . '/assets/materi/124/' . $mk . '/' . $f] as $cand) {
        if (is_file($cand)) { $path = $cand; break; }
    }
}
if ($path === '') {
    http_response_code(404);
    echo 'Berkas tidak ditemukan.';
    exit;
}

catat_statistik('unduh:' . $mk . '/' . $f);

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $f . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=86400');
readfile($path);
exit;
