<?php
/* ------------------------------------------------------------------
   Data hidup untuk beranda: jumlah materi terbuka, total unduhan, dan
   materi yang paling banyak diunduh.

   Statistik unduhan berupa penghitung kumulatif per berkas, tanpa
   tanggal, jadi yang ditampilkan jujur disebut "paling banyak diunduh",
   bukan "minggu ini". Judul materi semester lalu dibaca dari
   assets/materi-124.json (dibuat skrip/peta_materi.py); judul materi
   unggahan dibaca dari manifes materi. Yang keluar hanya angka agregat
   dan judul materi publik.
   ------------------------------------------------------------------ */
require __DIR__ . '/sesi.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

$nama  = mk_nama();
$peta  = json_decode((string) @file_get_contents(__DIR__ . '/assets/materi-124.json'), true) ?: [];

/* judul materi unggahan, dikunci dengan "<mk>/<berkas>" */
$unggah = [];
foreach (muat_materi() as $m) {
    $mk = (string) ($m['mk'] ?? '');
    $b  = basename((string) ($m['berkas'] ?? ''));
    if ($mk === '' || $b === '' || !isset($nama[$mk])) continue;
    $unggah[$mk . '/' . $b] = ['j' => (string) ($m['judul'] ?? $b), 'u' => mk_url($mk)];
}

$total = 0;
$per   = [];
foreach (baca_statistik() as $k => $n) {
    if (strncmp($k, 'unduh:', 6) !== 0) continue;
    $n = (int) $n;
    $total += $n;
    $kunci = substr($k, 6);
    $info  = $peta[$kunci] ?? $unggah[$kunci] ?? null;
    if ($info === null) continue;                 /* berkas yang sudah dihapus */
    $mk = strtok($kunci, '/');
    $per[] = ['j' => $info['j'], 'u' => $info['u'], 'mk' => $nama[$mk] ?? '', 'n' => $n];
}
usort($per, fn($a, $b) => $b['n'] <=> $a['n']);

echo json_encode([
    'materi'  => count($peta) + count($unggah),
    'unduhan' => $total,
    'populer' => array_slice(array_values(array_filter($per, fn($x) => $x['n'] >= 2)), 0, 5),
], JSON_UNESCAPED_UNICODE);
