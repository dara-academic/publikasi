<?php
/* ------------------------------------------------------------------
   Umpan pencarian untuk materi yang diunggah dosen.

   Indeks pencarian utama (assets/cari.json) dibuat saat build, jadi tidak
   memuat materi yang diunggah belakangan. Berkas ini melengkapi indeks itu
   secara langsung: bentuk tiap entri sama persis dengan cari.json
   ({j,u,k,r,t}) dan menunjuk ke halaman mata kuliah tempat materi tampil,
   supaya materi baru tetap bisa ditemukan lewat kotak "Cari".
   ------------------------------------------------------------------ */
require __DIR__ . '/sesi.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$nama = mk_nama();
$out = [];
foreach (muat_materi() as $m) {
    $mk = (string) ($m['mk'] ?? '');
    if (!isset($nama[$mk])) continue;
    $judul = (string) ($m['judul'] ?? '');
    if ($judul === '') continue;
    $desk = (string) ($m['deskripsi'] ?? '');
    $r = $desk !== '' ? $desk : ('Materi ' . $nama[$mk] . ', bisa diunduh.');
    $t = mb_strtolower(trim($judul . ' ' . $desk . ' ' . $nama[$mk] . ' materi kuliah'));
    $out[] = [
        'j' => $judul,
        'u' => 'mata-kuliah/' . $mk . '.html',
        'k' => 'Materi kuliah',
        'r' => $r,
        't' => $t,
    ];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
