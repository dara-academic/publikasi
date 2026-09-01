<?php
/* ------------------------------------------------------------------
   Umpan JSON materi unggahan untuk satu mata kuliah.

   Dipakai halaman mata kuliah (mata-kuliah/<slug>.html) buat menyisipkan
   materi yang diunggah dosen ke daftar pertemuan lewat JavaScript, tanpa
   mengubah halaman statis. Hanya slug mata kuliah yang sah yang dilayani.
   ------------------------------------------------------------------ */
require __DIR__ . '/sesi.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$mk = (string) ($_GET['mk'] ?? '');
if (!preg_match('/^[a-z0-9\-]+$/', $mk)) {
    echo json_encode(['mk' => '', 'materi' => []]);
    exit;
}

$stat = baca_statistik();
$keluar = [];
foreach (muat_materi() as $m) {
    if (($m['mk'] ?? '') !== $mk) continue;
    $u = (int) ($stat['unduh:' . $m['mk'] . '/' . $m['berkas']] ?? 0);
    $sampul = (string) ($m['sampul'] ?? '');
    $keluar[] = [
        'judul'     => (string) ($m['judul'] ?? ''),
        'deskripsi' => (string) ($m['deskripsi'] ?? ''),
        'berkas'    => (string) ($m['berkas'] ?? ''),
        'mk'        => (string) ($m['mk'] ?? ''),
        'semester'  => (string) ($m['semester'] ?? '125'),
        'pertemuan' => (int) ($m['pertemuan'] ?? 0),
        'sampul'    => $sampul !== '' ? 'unggahan/' . $m['mk'] . '/' . $sampul : '',
        'ukuran'    => (int) ($m['ukuran'] ?? 0),
        'tanggal'   => (string) ($m['tanggal'] ?? ''),
        'unduh'     => $u,
        'unduh_teks'=> $u > 0 ? angka_ringkas($u) : '',
    ];
}
/* Materi terbaru di atas. */
$keluar = array_reverse($keluar);

echo json_encode(['mk' => $mk, 'materi' => $keluar], JSON_UNESCAPED_UNICODE);
