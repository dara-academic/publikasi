<?php
/* ------------------------------------------------------------------
   Ringkasan jumlah materi unggahan per mata kuliah dan per semester.

   Dipakai halaman Pengajaran untuk memisahkan kartu mata kuliah ke tab
   "Semester lalu" dan "Semester ini" tanpa memuat semua materi satu per
   satu. Bentuk keluaran: { "<slug-mk>": { "125": 3, "124": 0 }, ... }.
   ------------------------------------------------------------------ */
require __DIR__ . '/sesi.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$out = [];
foreach (muat_materi() as $m) {
    $mk = (string) ($m['mk'] ?? '');
    if ($mk === '') continue;
    $sem = (string) ($m['semester'] ?? SEM_INI);
    if (!isset($out[$mk])) $out[$mk] = [];
    $out[$mk][$sem] = ($out[$mk][$sem] ?? 0) + 1;
}

/* Mata kuliah tambahan (buatan admin) supaya tampil di halaman Pengajaran. */
$tambahan = [];
foreach (muat_matkul() as $mm) {
    $slug = (string) ($mm['slug'] ?? '');
    if ($slug === '') continue;
    $jml = isset($out[$slug]) ? array_sum($out[$slug]) : 0;
    $tambahan[] = ['slug' => $slug, 'nama' => (string) ($mm['nama'] ?? $slug), 'jml' => $jml];
}

echo json_encode([
    'sem_ini'  => SEM_INI,
    'sem_lalu' => SEM_LALU,
    'kuliah'   => (object) $out,
    'tambahan' => $tambahan,
], JSON_UNESCAPED_UNICODE);
