<?php
/* ------------------------------------------------------------------
   Rincian bimbingan, area terbatas.

   Mahasiswa melihat datanya sendiri: status, tahapan jenjangnya, dan
   riwayat perubahan status. Baris dicari lewat tautan akun (NIM), lalu
   nama sebagai cadangan. Dosen melihat seluruh daftar tanpa bisa
   mengubah; pengubahan hanya di panel Manajemen bimbingan milik admin.
   ------------------------------------------------------------------ */
require __DIR__ . '/../bimbingan-inti.php';
$pengguna = wajib_masuk_segar();
$admin = $pengguna['peran'] === 'admin';
$lihat_semua = in_array($pengguna['peran'], ['admin', 'dosen'], true);

$mhs = muat_mhs();
usort($mhs, fn($a, $b) => [$a['jenjang'], $a['kelompok'], $a['nama']] <=> [$b['jenjang'], $b['kelompok'], $b['nama']]);
$milikku = $lihat_semua ? null : mhs_milik($pengguna['email'], $pengguna['nama']);

function e($s): string { return htmlspecialchars((string) $s, ENT_QUOTES); }
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
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600..800&family=Figtree:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="ak" data-grup="mengajar">
<header class="ak-bar">
  <div class="ak-bar-isi">
    <a class="ak-nama" href="../index.html"><svg class="ak-logo" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="8" fill="#e9b949"/><path d="M7.5 10.2c3-.9 5.9-.6 8.5 1.3v12.3c-2.6-1.9-5.5-2.2-8.5-1.3z" fill="#0f4c5c"/><path d="M24.5 10.2c-3-.9-5.9-.6-8.5 1.3v12.3c2.6-1.9 5.5-2.2 8.5-1.3z" fill="#0f4c5c" opacity=".72"/></svg><span>Belajar Bersama Dara</span></a>
    <span class="rekap-siapa">Masuk sebagai <b><?= e($pengguna['nama']) ?></b>
      <?php if ($admin): ?>&middot; <a href="../admin-bimbingan.php">Kelola bimbingan</a><?php endif; ?>
      &middot; <a href="../ganti-sandi.php">Ganti sandi</a>
      &middot; <a href="../keluar.php">Keluar</a></span>
  </div>
</header>
<div class="ak-halaman">
<main class="ak-utama" id="konten">
  <nav class="remah" aria-label="Jejak lokasi"><a href="../index.html">Beranda</a><span class="remah-pisah">&rsaquo;</span><a href="progres.php">Monitoring bimbingan</a><span class="remah-pisah">&rsaquo;</span><span class="remah-kini">Rincian bimbingan</span></nav>

  <section class="hero hero-tipis">
    <div class="container">
      <p class="kicker">Area terbatas</p>
      <h1><?= $lihat_semua ? 'Rincian bimbingan' : 'Bimbingan saya' ?></h1>
      <p class="lead">Diperbarui <?= e(tanggal_data_mhs()) ?>.</p>
    </div>
  </section>

  <div class="container">
<?php if ($lihat_semua): ?>
<?php if ($admin): ?>
    <p><a class="btn primary" href="../admin-bimbingan.php">Buka manajemen bimbingan</a></p>
<?php endif; ?>
    <div class="rekap-gulir"><table class="rekap-tabel">
      <thead><tr><th>Nama</th><th>Jenjang</th><th>NIM</th><th>Judul</th><th>Status</th></tr></thead>
      <tbody>
<?php foreach ($mhs as $m): ?>
        <tr><td><b><?= e($m['nama']) ?></b><small class="bim-kecil"><?= e($m['kelompok']) ?> &middot; <?= e($m['peran']) ?></small></td>
          <td><?= e($m['jenjang']) ?></td><td><?= e($m['nim'] ?: '-') ?></td>
          <td class="rekap-ket"><?= e($m['judul'] ?: '-') ?></td>
          <td><?= e(label_status($m['jenjang'], $m['status'])) ?><?= $m['tgl_status'] !== '' ? '<small class="bim-kecil">sejak ' . e($m['tgl_status']) . '</small>' : '' ?></td></tr>
<?php endforeach; ?>
      </tbody>
    </table></div>
<?php elseif ($milikku): $m = $milikku; $urut = urut_status($m['jenjang'], $m['status']); ?>
    <div class="bim-saya">
      <div>
        <p class="bim-saya-nama"><?= e($m['nama']) ?></p>
        <p class="bim-kecil"><?= e($m['jenjang']) ?> &middot; <?= e(JENJANG[$m['jenjang']] ?? '') ?> &middot; <?= e($m['kelompok']) ?><?= $m['nim'] !== '' ? ' &middot; NIM ' . e($m['nim']) : '' ?></p>
        <p class="bim-saya-judul"><?= e($m['judul'] ?: 'Judul penelitian belum tercatat.') ?></p>
        <p>Status: <span class="bim-st bim-st-<?= sudah_lulus($m) ? 'lulus' : ($urut <= 0 ? 'awal' : 'tengah') ?>"><?= e(label_status($m['jenjang'], $m['status'])) ?></span></p>
      </div>
      <ol class="bim-tahapan">
<?php foreach (array_values(status_jenjang($m['jenjang'])) as $i => $lbl): ?>
        <li class="<?= $i < $urut ? 'sudah' : ($i === $urut ? 'kini' : '') ?>"><?= e($lbl) ?></li>
<?php endforeach; ?>
      </ol>
    </div>
    <h2>Riwayat status</h2>
    <ol class="bim-riwayat">
<?php foreach (array_reverse($m['riwayat'] ?? []) as $r): ?>
      <li><b><?= e(label_status($m['jenjang'], $r['ke'])) ?></b> <span><?= e($r['tgl']) ?><?= $r['catatan'] !== '' ? ' &middot; ' . e($r['catatan']) : '' ?></span></li>
<?php endforeach; ?>
<?php if (empty($m['riwayat'])): ?>
      <li><span>Belum ada riwayat.</span></li>
<?php endif; ?>
    </ol>
    <p>Bila data ini tidak sesuai, sampaikan lewat <a href="mailto:dara@unj.ac.id">dara@unj.ac.id</a>.</p>
<?php else: ?>
    <p>Akun <b><?= e($pengguna['nama']) ?></b> belum tertaut ke data bimbingan.
      Hubungi <a href="mailto:dara@unj.ac.id">dara@unj.ac.id</a>.</p>
<?php endif; ?>
  </div>
</main>
</div>
</body>
</html>
