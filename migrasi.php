<?php
/* ------------------------------------------------------------------
   Migrasi satu kali dari berkas JSON ke MySQL.

   Prasyaratnya dua: data/konfig.php berisi kredensial database sudah
   terpasang, dan kode migrasi di dalamnya ikut disebut di alamat.
   Kodenya perlu karena halaman ini berjalan sebelum ada yang bisa
   masuk lewat database, jadi ia tidak bisa berlindung di balik sesi.

   Yang dikerjakan: membuat tabel bila belum ada, menyalin pengguna,
   rekap bimbingan, dan antrean pendaftaran dari berkas JSON, lalu
   menandai migrasi selesai di tabel meta. Sejak tanda itu terpasang,
   seluruh situs otomatis membaca dan menulis MySQL. Berkas JSON tidak
   dihapus, ia tinggal sebagai arsip. Menjalankan halaman ini dua kali
   tidak menggandakan data karena migrasi yang sudah selesai menolak
   berjalan lagi.
   ------------------------------------------------------------------ */
require __DIR__ . '/sesi.php';
header('Content-Type: text/plain; charset=utf-8');

if (!is_file(BERKAS_KONFIG)) {
    exit("Belum ada data/konfig.php. Unggah dulu berkas konfigurasinya.\n");
}
$k = require BERKAS_KONFIG;
if (empty($k['kode_migrasi']) || !hash_equals($k['kode_migrasi'], (string) ($_GET['kode'] ?? ''))) {
    http_response_code(403);
    exit("Kode migrasi tidak cocok. Panggil: /migrasi.php?kode=KODE_DARI_KONFIG\n");
}

try {
    $d = new PDO(
        'mysql:host=' . $k['host'] . ';dbname=' . $k['nama_db'] . ';charset=utf8mb4',
        $k['pengguna_db'], $k['sandi_db'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Throwable $e) {
    exit("Gagal tersambung ke database: " . $e->getMessage() . "\n"
       . "Periksa host, nama_db, pengguna_db, dan sandi_db di data/konfig.php.\n");
}

$d->exec("CREATE TABLE IF NOT EXISTS meta (
    kunci VARCHAR(64) PRIMARY KEY,
    nilai VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$d->exec("CREATE TABLE IF NOT EXISTS pengguna (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(120) NOT NULL UNIQUE,
    nama VARCHAR(120) NOT NULL,
    peran ENUM('admin','dosen','mahasiswa') NOT NULL DEFAULT 'mahasiswa',
    sandi VARCHAR(255) NOT NULL,
    wajib_ganti TINYINT(1) NOT NULL DEFAULT 1,
    dibuat VARCHAR(32) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$d->exec("CREATE TABLE IF NOT EXISTS bimbingan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(120) NOT NULL,
    kelompok VARCHAR(120) NOT NULL,
    peran VARCHAR(16) NOT NULL DEFAULT '-',
    tahap ENUM('belum','judul','sempro','lulus') NOT NULL DEFAULT 'belum',
    keterangan TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$d->exec("CREATE TABLE IF NOT EXISTS pendaftaran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(120) NOT NULL,
    kelompok VARCHAR(120) NOT NULL,
    kontak VARCHAR(160) NOT NULL,
    waktu VARCHAR(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "Tabel siap.\n";

$m = $d->query("SELECT nilai FROM meta WHERE kunci = 'migrasi'")->fetch();
if ($m && $m['nilai'] === 'selesai') {
    exit("Migrasi sudah pernah selesai. Tidak ada yang diulang.\n");
}

$d->beginTransaction();

$n = 0;
if (is_file(BERKAS_PENGGUNA)) {
    $j = json_decode((string) file_get_contents(BERKAS_PENGGUNA), true);
    $s = $d->prepare('INSERT IGNORE INTO pengguna (email, nama, peran, sandi, wajib_ganti, dibuat) VALUES (?,?,?,?,?,?)');
    foreach (($j['pengguna'] ?? []) as $p) {
        $s->execute([$p['email'], $p['nama'], $p['peran'], $p['sandi'],
                     (int) !empty($p['wajib_ganti']), $p['dibuat'] ?? '']);
        $n += $s->rowCount();
    }
}
echo "Pengguna tersalin: $n\n";

$n = 0;
$diperbarui = date('Y-m-d');
if (is_file(BERKAS_BIMBINGAN)) {
    $j = json_decode((string) file_get_contents(BERKAS_BIMBINGAN), true);
    $diperbarui = $j['diperbarui'] ?? $diperbarui;
    $s = $d->prepare('INSERT INTO bimbingan (nama, kelompok, peran, tahap, keterangan) VALUES (?,?,?,?,?)');
    foreach (($j['mahasiswa'] ?? []) as $x) {
        $s->execute([$x['nama'], $x['kelompok'], $x['peran'], $x['tahap'], $x['keterangan']]);
        $n++;
    }
}
echo "Rekap bimbingan tersalin: $n\n";

$n = 0;
if (is_file(BERKAS_ANTREAN)) {
    $j = json_decode((string) file_get_contents(BERKAS_ANTREAN), true) ?: [];
    $s = $d->prepare('INSERT INTO pendaftaran (nama, kelompok, kontak, waktu) VALUES (?,?,?,?)');
    foreach ($j as $x) {
        $s->execute([$x['nama'], $x['kelompok'], $x['kontak'], $x['waktu']]);
        $n++;
    }
}
echo "Antrean pendaftaran tersalin: $n\n";

$s = $d->prepare('REPLACE INTO meta (kunci, nilai) VALUES (?,?)');
$s->execute(['diperbarui', $diperbarui]);
$s->execute(['migrasi', 'selesai']);
$d->commit();

echo "\nMIGRASI SELESAI. Situs kini membaca dan menulis MySQL.\n";
echo "Berkas JSON di data/ tinggal sebagai arsip dan boleh diunduh lalu dihapus.\n";
