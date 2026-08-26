<?php
/* ------------------------------------------------------------------
   Rincian bimbingan. Hanya untuk yang sudah masuk.

   Admin dan dosen melihat seluruh tabel. Mahasiswa melihat rekap
   agregat ditambah barisnya sendiri saja, karena kemajuan skripsi
   orang lain bukan konsumsi teman seangkatannya. Pencocokan baris
   memakai nama pada akun.
   ------------------------------------------------------------------ */
require __DIR__ . '/../sesi.php';
$pengguna = wajib_masuk_segar();
$admin = in_array($pengguna['peran'], ['admin', 'dosen'], true);

$data = muat_bimbingan();
$daftar = $data['mahasiswa'] ?? [];

$LABEL = ['lulus' => 'Lulus', 'sempro' => 'Sampai sempro',
          'judul' => 'Tahap judul/topik', 'belum' => 'Belum ada kemajuan'];

$kelompok = [];
foreach ($daftar as $m) $kelompok[$m['kelompok']][] = $m;

$milikku = null;
if (!$admin) {
    foreach ($daftar as $m) {
        if (strcasecmp(trim($m['nama']), trim($pengguna['nama'])) === 0) {
            $milikku = $m;
            break;
        }
    }
}
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Rincian Bimbingan, Portal Dr. Despinur Dara</title>
<link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="../assets/style.css?v=<?= filemtime(__DIR__ . '/../assets/style.css') ?>">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Source+Serif+4:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="ak" data-grup="mengajar">
<header class="ak-bar">
  <div class="ak-bar-isi">
    <a class="ak-nama" href="../index.html">Despinur Dara</a>
    <span class="rekap-siapa">Masuk sebagai <b><?= e($pengguna['nama']) ?></b>
      <?php if ($admin): ?>&middot; <a href="../akun.php">Kelola akun</a><?php endif; ?>
      &middot; <a href="../ganti-sandi.php">Ganti sandi</a>
      &middot; <a href="../keluar.php">Keluar</a></span>
  </div>
</header>
<div class="ak-halaman">
<main class="ak-utama" id="konten">
  <nav class="remah" aria-label="Jejak lokasi"><a href="../index.html">Beranda</a><span class="remah-pisah">&rsaquo;</span><a href="index.html">Bimbingan karya ilmiah</a><span class="remah-pisah">&rsaquo;</span><span class="remah-kini">Rincian bimbingan</span></nav>

  <section class="hero hero-tipis">
    <div class="container">
      <p class="kicker">Area terbatas</p>
      <h1>Rincian bimbingan</h1>
      <p class="lead">Rekap per <?= e($data['diperbarui'] ?? '-') ?>.
        <?= $admin ? 'Anda melihat seluruh daftar sebagai ' . e($pengguna['peran']) . '.'
                   : 'Anda melihat rekap keseluruhan dan baris Anda sendiri.' ?></p>
    </div>
  </section>

  <div class="container">
<?php if (!$daftar): ?>
    <p class="masuk-galat">Berkas data bimbingan belum terunggah di server.
      Unggah <code>bimbingan.json</code> ke folder <code>data/</code>.</p>
<?php elseif ($admin): ?>
<?php foreach ($kelompok as $nama_k => $anggota): ?>
    <h2><?= e($nama_k) ?> <span class="rekap-jumlah"><?= count($anggota) ?> mahasiswa</span></h2>
    <div class="rekap-gulir"><table class="rekap-tabel">
      <thead><tr><th>Nama</th><th>Peran</th><th>Tahap</th><th>Keterangan</th></tr></thead>
      <tbody>
<?php foreach ($anggota as $m): ?>
        <tr>
          <td><?= e($m['nama']) ?></td>
          <td><?= e($m['peran']) ?></td>
          <td><span class="rekap-tahap tahap-<?= e($m['tahap']) ?>"><?= e($LABEL[$m['tahap']]) ?></span></td>
          <td class="rekap-ket"><?= e($m['keterangan']) ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table></div>
<?php endforeach; ?>
<?php else: ?>
    <h2>Status Anda</h2>
<?php if ($milikku): ?>
    <div class="rekap-gulir"><table class="rekap-tabel">
      <thead><tr><th>Nama</th><th>Kelompok</th><th>Peran pembimbing</th><th>Tahap</th></tr></thead>
      <tbody><tr>
        <td><?= e($milikku['nama']) ?></td>
        <td><?= e($milikku['kelompok']) ?></td>
        <td><?= e($milikku['peran']) ?></td>
        <td><span class="rekap-tahap tahap-<?= e($milikku['tahap']) ?>"><?= e($LABEL[$milikku['tahap']]) ?></span></td>
      </tr></tbody>
    </table></div>
    <p>Kalau status ini tidak sesuai dengan kondisi Anda, sampaikan lewat
      <a href="mailto:dara@unj.ac.id">dara@unj.ac.id</a> supaya rekapnya dibetulkan.</p>
<?php else: ?>
    <p>Baris atas nama <b><?= e($pengguna['nama']) ?></b> belum ada di rekap.
      Hubungi <a href="mailto:dara@unj.ac.id">dara@unj.ac.id</a> untuk pencocokan nama akun.</p>
<?php endif; ?>
<?php endif; ?>
  </div>
</main>
</div>
</body>
</html>
