<?php
/* ------------------------------------------------------------------
   Umpan JSON "baru ditambahkan": gabungan materi, paper, dan buku
   terbaru dari manifes, diurutkan dari yang paling baru. Dipakai beranda
   untuk menampilkan bagian yang terisi otomatis tiap ada unggahan baru.
   ------------------------------------------------------------------ */
require __DIR__ . '/sesi.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

$MK_NAMA = mk_nama();

$items = [];
foreach (muat_materi() as $m) {
    $mk = (string) ($m['mk'] ?? '');
    $items[] = [
        'tipe'    => 'Materi',
        'judul'   => (string) $m['judul'],
        'ket'     => $MK_NAMA[$mk] ?? 'Materi kuliah',
        'tanggal' => (string) ($m['tanggal'] ?? ''),
        'url'     => isset($MK_NAMA[$mk]) ? mk_url($mk) : 'mengajar.html',   /* mk_url: tambahan admin tak punya .html */
    ];
}
foreach (muat_paper() as $p) {
    $items[] = [
        'tipe'    => 'Paper',
        'judul'   => (string) $p['judul'],
        'ket'     => (string) $p['jurnal'] . ' · ' . (string) $p['indeks'],
        'tanggal' => (string) ($p['tanggal'] ?? ''),
        'url'     => 'paper.php',
    ];
}
foreach (muat_buku() as $b) {
    $items[] = [
        'tipe'    => 'Buku',
        'judul'   => (string) $b['judul'],
        'ket'     => (string) ($b['penerbit'] ?: 'Buku terbit'),
        'tanggal' => (string) ($b['tanggal'] ?? ''),
        'url'     => 'buku.php',
    ];
}

/* ?tipe=materi: beranda hanya butuh materi, karena paper dan buku sudah tampil di Karya terbaru */
if (($_GET['tipe'] ?? '') === 'materi') {
    $items = array_values(array_filter($items, fn($x) => $x['tipe'] === 'Materi'));
}
usort($items, fn($a, $b) => strcmp($b['tanggal'], $a['tanggal']));
echo json_encode(['items' => array_slice($items, 0, 6)], JSON_UNESCAPED_UNICODE);
