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
    $sem = (string) ($m['semester'] ?? '125');
    if (!isset($out[$mk])) $out[$mk] = [];
    $out[$mk][$sem] = ($out[$mk][$sem] ?? 0) + 1;
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
