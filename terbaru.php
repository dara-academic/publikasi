<?php
/* ------------------------------------------------------------------
   Umpan JSON "baru ditambahkan": gabungan materi, paper, dan buku
   terbaru dari manifes, diurutkan dari yang paling baru. Dipakai beranda
   untuk menampilkan bagian yang terisi otomatis tiap ada unggahan baru.
   ------------------------------------------------------------------ */
require __DIR__ . '/sesi.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

const MK_NAMA = [
    'pengantar-manajemen'          => 'Pengantar Manajemen',
    'pengadaan-sdm-aparatur'       => 'Pengadaan SDM Aparatur',
    'kompensasi-perlindungan-sdm'  => 'Kompensasi dan Perlindungan SDM',
    'pelatihan-dan-pengembangan'   => 'Pelatihan dan Pengembangan',
    'manajemen-kinerja'            => 'Manajemen Kinerja',
    'simulasi-bisnis'              => 'Simulasi Bisnis',
    'management-information-system'=> 'Management Information System',
];

$items = [];
foreach (muat_materi() as $m) {
    $items[] = [
        'tipe'    => 'Materi',
        'judul'   => (string) $m['judul'],
        'ket'     => MK_NAMA[$m['mk']] ?? 'Materi kuliah',
        'tanggal' => (string) ($m['tanggal'] ?? ''),
        'url'     => 'materi.php',
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

usort($items, fn($a, $b) => strcmp($b['tanggal'], $a['tanggal']));
echo json_encode(['items' => array_slice($items, 0, 6)], JSON_UNESCAPED_UNICODE);
