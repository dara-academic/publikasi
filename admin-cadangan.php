<?php
/* ------------------------------------------------------------------
   Cadangan data. Hanya admin.

   Seluruh isi dinamis situs ini hidup di dua folder yang sengaja berada
   di luar Git: data (manifes materi, paper, buku, mata kuliah, komentar,
   akun, statistik, log kunjungan) dan unggahan (berkas PDF dan sampul).
   Kalau hosting bermasalah atau ada yang terhapus, keduanya tidak bisa
   dipulihkan dari mana pun. Halaman ini membungkusnya jadi satu berkas
   ZIP yang bisa diunduh dan disimpan di tempat lain.
   ------------------------------------------------------------------ */
require __DIR__ . '/sesi.php';
$pengguna = wajib_masuk_segar();
if ($pengguna['peran'] !== 'admin') {
    header('Location: /bimbingan/rekap.php');
    exit;
}

const DIR_DATA     = __DIR__ . '/data';
const DIR_UNGGAHAN = __DIR__ . '/unggahan';

function ee($s): string { return htmlspecialchars((string) $s, ENT_QUOTES); }
function ukuran_manusia(int $b): string {
    if ($b >= 1073741824) return round($b / 1073741824, 1) . ' GB';
    if ($b >= 1048576)    return round($b / 1048576, 1) . ' MB';
    if ($b >= 1024)       return round($b / 1024) . ' KB';
    return $b . ' B';
}

/* Kumpulkan berkas satu folder beserta jalur relatifnya untuk masuk ZIP. */
function berkas_folder(string $dir, string $awalan): array {
    if (!is_dir($dir)) return [];
    $keluar = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($it as $berkas) {
        if (!$berkas->isFile()) continue;
        $nyata = $berkas->getRealPath();
        $rel = $awalan . '/' . str_replace('\\', '/', substr($nyata, strlen(realpath($dir)) + 1));
        $keluar[] = ['nyata' => $nyata, 'rel' => $rel, 'ukuran' => $berkas->getSize()];
    }
    return $keluar;
}

$isi_data     = berkas_folder(DIR_DATA, 'data');
$isi_unggahan = berkas_folder(DIR_UNGGAHAN, 'unggahan');
$besar_data     = array_sum(array_column($isi_data, 'ukuran'));
$besar_unggahan = array_sum(array_column($isi_unggahan, 'ukuran'));
$ada_zip = class_exists('ZipArchive');

$galat = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_sah()) {
        $galat = 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } elseif (!$ada_zip) {
        $galat = 'Ekstensi ZipArchive tidak tersedia di server ini.';
    } else {
        $jenis = ($_POST['jenis'] ?? 'data') === 'lengkap' ? 'lengkap' : 'data';
        $isi = $jenis === 'lengkap' ? array_merge($isi_data, $isi_unggahan) : $isi_data;

        if (!$isi) {
            $galat = 'Tidak ada berkas untuk dicadangkan.';
        } else {
            $tmp = tempnam(sys_get_temp_dir(), 'cadangan');
            $zip = new ZipArchive();
            if ($tmp === false || $zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
                $galat = 'Gagal membuat berkas cadangan sementara di server.';
            } else {
                $zip->addFromString('BACA-DULU.txt',
                    "Cadangan portal despinurdara.id\n"
                    . 'Dibuat: ' . date('Y-m-d H:i') . "\n"
                    . 'Jenis: ' . ($jenis === 'lengkap' ? 'data + berkas unggahan' : 'data saja') . "\n\n"
                    . "Cara memulihkan: salin kembali isi folder data/ dan unggahan/\n"
                    . "ke akar situs di server, lalu pastikan izin folder tetap bisa ditulis.\n\n"
                    . "Berkas ini memuat data pribadi dan kata sandi terenkripsi.\n"
                    . "Simpan di tempat yang aman, jangan dibagikan.\n");
                foreach ($isi as $b) $zip->addFile($b['nyata'], $b['rel']);
                $zip->close();

                $nama = 'cadangan-dara-' . $jenis . '-' . date('Ymd-His') . '.zip';
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $nama . '"');
                header('Content-Length: ' . filesize($tmp));
                header('X-Robots-Tag: noindex');
                readfile($tmp);
                @unlink($tmp);
                exit;
            }
        }
    }
}

$csrf = ee(token_csrf());
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Cadangan data, Portal Dr. Despinur Dara</title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&family=Source+Serif+4:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="ak" data-grup="mengajar">
<header class="ak-bar">
  <div class="ak-bar-isi">
    <a class="ak-nama" href="index.html">Belajar Bersama Dara</a>
    <span class="rekap-siapa"><b><?= ee($pengguna['nama']) ?></b>
      &middot; <a href="admin.php">Panel admin</a>
      &middot; <a href="keluar.php">Keluar</a></span>
  </div>
</header>

<div class="admin-band">
  <div class="admin-band-isi">
    <p class="admin-lencana">Panel admin</p>
    <h1>Cadangan data</h1>
    <p class="admin-band-lead">Semua materi, komentar, akun, dan statistik hanya tersimpan di server ini
    dan tidak ikut Git. Unduh cadangannya berkala, simpan di komputer atau Drive.</p>
  </div>
</div>

<nav class="admin-menu" aria-label="Menu panel admin">
  <a href="admin.php">&larr; Panel admin</a>
  <a href="akun.php">Bimbingan &amp; akun</a>
  <a href="admin-materi.php">Materi kuliah</a>
  <a href="admin-bedah.php">Bedah paper</a>
  <a href="admin-buku.php">Buku</a>
  <a href="admin-statistik.php">Statistik</a>
  <a href="admin-komentar.php">Tanya jawab</a>
  <a href="admin-cadangan.php" class="active">Cadangan</a>
</nav>

<main class="ak-utama admin-utama" id="konten">

  <?php if ($galat): ?>
    <p class="masuk-galat" role="alert"><?= ee($galat) ?></p>
  <?php endif; ?>

  <div class="prog-band-angka stat-ringkas">
    <div><b><?= count($isi_data) ?></b><span>berkas data</span></div>
    <div><b><?= ee(ukuran_manusia($besar_data)) ?></b><span>ukuran data</span></div>
    <div><b><?= count($isi_unggahan) ?></b><span>berkas unggahan</span></div>
    <div><b><?= ee(ukuran_manusia($besar_unggahan)) ?></b><span>ukuran unggahan</span></div>
  </div>

  <?php if (!$ada_zip): ?>
    <section class="admin-kartu">
      <p class="admin-kosong">Server ini tidak punya ekstensi ZipArchive, jadi cadangan otomatis
      belum bisa dibuat. Sementara ini, unduh folder <b>data</b> dan <b>unggahan</b> lewat File Manager
      atau FTP di hPanel Hostinger.</p>
    </section>
  <?php else: ?>

  <section class="admin-kartu">
    <h2>Cadangan data</h2>
    <p class="admin-sub">Isi folder <b>data</b>: manifes materi, paper, buku, mata kuliah, komentar,
    akun, statistik, dan log kunjungan. Kecil, dan ini yang paling tidak tergantikan.
    Unduh ini rutin, misalnya tiap pekan.</p>
    <form method="post" action="admin-cadangan.php">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="jenis" value="data">
      <button class="masuk-tombol" type="submit">Unduh cadangan data (<?= ee(ukuran_manusia($besar_data)) ?>)</button>
    </form>
  </section>

  <section class="admin-kartu">
    <h2>Cadangan lengkap</h2>
    <p class="admin-sub">Folder <b>data</b> ditambah seluruh berkas PDF dan gambar sampul di folder
    <b>unggahan</b>. Ukurannya jauh lebih besar dan butuh waktu. Unduh sesekali saja,
    misalnya tiap akhir semester atau sesudah banyak mengunggah materi.</p>
    <form method="post" action="admin-cadangan.php">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="jenis" value="lengkap">
      <button class="masuk-tombol" type="submit">Unduh cadangan lengkap (<?= ee(ukuran_manusia($besar_data + $besar_unggahan)) ?>)</button>
    </form>
  </section>

  <?php endif; ?>

  <section class="admin-kartu">
    <h2>Cara memulihkan</h2>
    <p>Buka berkas ZIP-nya, lalu salin kembali isi folder <b>data</b> dan <b>unggahan</b> ke akar situs
    di server lewat File Manager hPanel. Sesudah itu pastikan izin kedua folder masih bisa ditulis,
    biasanya 755. Situs langsung memakai data itu tanpa perlu perubahan lain.</p>
    <p class="stat-catatan">Berkas cadangan memuat data pribadi mahasiswa dan kata sandi terenkripsi.
    Simpan di tempat aman dan jangan dibagikan.</p>
  </section>

  <p class="admin-pulang"><a href="admin.php">&larr; Kembali ke panel admin</a></p>
</main>
</body>
</html>
