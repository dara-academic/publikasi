<?php
/* ------------------------------------------------------------------
   Daftar lulusan bimbingan: mahasiswa yang sudah mencapai status akhir
   jenjangnya (Lulus Sidang Skripsi, Lulus Sidang Thesis, Lulus Sidang
   Terbuka). Publik: nama, judul, jenjang, dan tanggal lulus.
   ------------------------------------------------------------------ */
require __DIR__ . '/../bimbingan-inti.php';

$lulus = array_values(array_filter(muat_mhs(), 'sudah_lulus'));
usort($lulus, fn($a, $b) => [$b['tgl_status'], $a['nama']] <=> [$a['tgl_status'], $b['nama']]);
$per_j = [];
foreach (JENJANG as $j => $_) $per_j[$j] = array_values(array_filter($lulus, fn($m) => $m['jenjang'] === $j));

function e($s): string { return htmlspecialchars((string) $s, ENT_QUOTES); }
$BULAN = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
function tgl_indo(string $t, array $B): string {
    return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $t, $x) ? (int) $x[3] . ' ' . $B[(int) $x[2]] . ' ' . $x[1] : '-';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lulusan Bimbingan, Dr. Despinur Dara</title>
<meta name="description" content="<?= count($lulus) ?> mahasiswa S1, S2, dan S3 yang lulus di bawah bimbingan Dr. Despinur Dara, beserta judul penelitiannya.">
<link rel="canonical" href="https://despinurdara.id/bimbingan/lulus.php">
<link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="../assets/style.css?v=<?= filemtime(__DIR__ . '/../assets/style.css') ?>">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Figtree:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="ak" data-grup="mengajar">
<header class="ak-bar">
  <div class="ak-bar-isi">
    <a class="ak-nama" href="../index.html"><svg class="ak-logo" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="8" fill="#e9b949"/><path d="M7.5 10.2c3-.9 5.9-.6 8.5 1.3v12.3c-2.6-1.9-5.5-2.2-8.5-1.3z" fill="#0f4c5c"/><path d="M24.5 10.2c-3-.9-5.9-.6-8.5 1.3v12.3c2.6-1.9 5.5-2.2 8.5-1.3z" fill="#0f4c5c" opacity=".72"/></svg><span>Belajar Bersama Dara</span></a>
    <span class="rekap-siapa"><a href="index.html">Bimbingan</a>
      &middot; <a href="progres.php">Monitoring</a>
      &middot; <a href="../masuk.php">Masuk</a></span>
  </div>
</header>
<div class="ak-halaman bim-lebar">
<main class="ak-utama" id="konten">
  <nav class="remah" aria-label="Jejak lokasi"><a href="../index.html">Beranda</a><span class="remah-pisah">&rsaquo;</span><a href="progres.php">Monitoring bimbingan</a><span class="remah-pisah">&rsaquo;</span><span class="remah-kini">Lulusan</span></nav>
  <div class="container">
    <header class="hal-hero">
      <p class="kicker">Bimbingan</p>
      <h1>Lulusan bimbingan</h1>
      <p class="hal-lead"><?= count($lulus) ?> mahasiswa lulus: <?= count($per_j['S1']) ?> S1, <?= count($per_j['S2']) ?> S2, <?= count($per_j['S3']) ?> S3.</p>
    </header>
<?php if (!$lulus): ?>
    <p>Belum ada lulusan yang tercatat.</p>
<?php endif; ?>
<?php foreach (JENJANG as $j => $t): if (!$per_j[$j]) continue; ?>
    <h2><?= $j ?> &middot; <?= $t ?> <span class="rekap-jumlah"><?= count($per_j[$j]) ?></span></h2>
    <div class="rekap-gulir"><table class="rekap-tabel">
      <thead><tr><th>Nama</th><th>Judul penelitian</th><th>Kelompok</th><th>Lulus</th></tr></thead>
      <tbody>
<?php foreach ($per_j[$j] as $m): ?>
        <tr><td><b><?= e($m['nama']) ?></b></td><td class="rekap-ket"><?= e($m['judul'] ?: '-') ?></td>
          <td><?= e($m['kelompok']) ?></td><td><?= e(tgl_indo((string) $m['tgl_status'], $BULAN)) ?></td></tr>
<?php endforeach; ?>
      </tbody>
    </table></div>
<?php endforeach; ?>
    <p class="bim-catatan"><a href="progres.php">&larr; Kembali ke papan monitoring</a></p>
  </div>
</main>
</div>
</body>
</html>
