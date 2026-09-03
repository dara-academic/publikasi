<?php
/* ------------------------------------------------------------------
   Halaman mata kuliah dinamis untuk mata kuliah tambahan yang dibuat
   admin (yang tidak punya halaman statis sendiri). Menampilkan daftar
   materi yang diunggah untuk mata kuliah itu, lengkap dengan unduhan yang
   terhitung, penanda baru, dan tanya jawab. Mata kuliah bawaan yang sudah
   punya halaman lengkap dialihkan ke halaman statisnya.
   ------------------------------------------------------------------ */
require __DIR__ . '/sesi.php';

$slug = (string) ($_GET['mk'] ?? '');

if ($slug !== '' && mk_statis($slug)) {
    header('Location: /mata-kuliah/' . rawurlencode($slug) . '.html', true, 302);
    exit;
}
$nama = mk_nama();
if ($slug === '' || !isset($nama[$slug])) {
    header('Location: /mengajar.html', true, 302);
    exit;
}
$judul_mk = $nama[$slug];

function ee($s): string { return htmlspecialchars((string) $s, ENT_QUOTES); }
function ukuran_manusia(int $b): string {
    if ($b >= 1048576) return round($b / 1048576, 1) . ' MB';
    if ($b >= 1024)    return round($b / 1024) . ' KB';
    return $b . ' B';
}

$stat = baca_statistik();
$daftar = [];
foreach (muat_materi() as $m) {
    if (($m['mk'] ?? '') === $slug) $daftar[] = $m;
}
/* Urut per nomor pertemuan; yang tanpa nomor di belakang, terbaru dulu. */
usort($daftar, function ($a, $b) {
    $pa = (int) ($a['pertemuan'] ?? 0) ?: 999;
    $pb = (int) ($b['pertemuan'] ?? 0) ?: 999;
    if ($pa !== $pb) return $pa <=> $pb;
    return strcmp((string) ($b['tanggal'] ?? ''), (string) ($a['tanggal'] ?? ''));
});
$hari_ini = time();
$masuk = pengguna_sekarang();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<script>(function(){try{if(localStorage.getItem('tema')==='dark')document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= ee($judul_mk) ?>, Materi Kuliah</title>
<meta name="description" content="Materi mata kuliah <?= ee($judul_mk) ?> dari Dr. Despinur Dara, bisa diunduh bebas dengan menyebut sumbernya.">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&family=Source+Serif+4:wght@400;600&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
</head>
<body class="ak" data-grup="mengajar" data-hal="/mata-kuliah/<?= ee($slug) ?>">
<a class="skip-link" href="#konten">Lewati ke konten utama</a>
<header class="ak-bar">
  <div class="ak-bar-isi">
    <a class="ak-nama" href="index.html">Belajar Bersama Dara</a>
    <button class="nav-toggle" aria-label="Buka menu" aria-expanded="false"><span></span><span></span><span></span></button>
    <div class="cari">
      <label class="sr-only" for="cari">Cari isi situs</label>
      <input class="cari-input" id="cari" type="search" autocomplete="off" placeholder="Cari materi, paper, istilah" data-naik="">
      <div class="cari-hasil" hidden></div>
    </div>
    <nav class="nav" aria-label="Navigasi utama">
        <a href="index.html">Beranda</a>
        <a href="mengajar.html" class="active">Pengajaran</a>
        <a href="bedah-publikasi.html">Bedah Publikasi</a>
        <a href="penelitian.html">Penelitian</a>
        <a href="kolaborasi.html">Kolaborasi</a>
        <a href="tentang.html">Profil</a>
        <a class="nav-masuk" href="masuk.php"><svg viewBox="0 0 24 24" aria-hidden="true" class="nav-masuk-ikon"><rect x="5" y="10.5" width="14" height="10" rx="2.2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/></svg>Masuk</a>
    </nav>
    <button class="tema-tombol" type="button" aria-label="Ganti tema terang/gelap" title="Ganti tema" data-tema-tombol>&#127769;</button>
  </div>
</header>

<div class="ak-halaman">
<main class="ak-utama" id="konten">
  <nav class="remah" aria-label="Jejak lokasi"><a href="index.html">Beranda</a><span class="remah-pisah">&rsaquo;</span><a href="mengajar.html">Pengajaran</a><span class="remah-pisah">&rsaquo;</span><span class="remah-kini"><?= ee($judul_mk) ?></span></nav>

  <header class="hal-hero">
    <p class="kicker">Mata kuliah</p>
    <h1><?= ee($judul_mk) ?></h1>
    <p class="hal-lead">Materi kuliah ini bisa diunduh bebas dengan menyebut sumbernya, diunggah bertahap begitu ada yang baru.</p>
    <?php if ($masuk && $masuk['peran'] === 'admin'): ?>
      <div class="hal-aksi"><a class="tombol-x utama" href="admin-materi.php">Unggah materi</a></div>
    <?php endif; ?>
  </header>

  <div class="container glos-wrap">
    <div class="section-head"><h2>Materi per pertemuan</h2></div>
    <?php if (!$daftar): ?>
      <p class="admin-kosong">Belum ada materi untuk mata kuliah ini. Cek lagi nanti.</p>
    <?php else: ?>
      <ol class="tl">
        <?php foreach ($daftar as $m):
            $berkas = (string) ($m['berkas'] ?? '');
            $sampul = (string) ($m['sampul'] ?? '');
            $pert   = (int) ($m['pertemuan'] ?? 0);
            $u      = (int) ($stat['unduh:' . $slug . '/' . $berkas] ?? 0);
            $tgl    = (string) ($m['tanggal'] ?? '');
            $baru   = $tgl !== '' && ($hari_ini - (strtotime($tgl) ?: 0)) <= 864000;
        ?>
          <li class="tl-butir tl-ada tl-unggah">
            <span class="tl-no"><?= $pert ? 'P' . $pert : '+' ?></span>
            <?php if ($sampul !== ''): ?>
              <span class="tl-sampul"><img src="unggahan/<?= ee($slug) ?>/<?= ee($sampul) ?>" alt="Sampul <?= ee($m['judul'] ?? 'materi') ?>" width="320" height="180" loading="lazy" decoding="async"><?php if ($baru): ?><span class="tl-baru">Baru</span><?php endif; ?></span>
            <?php else: ?>
              <span class="tl-sampul tl-sampul-kosong" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M12 12v6"/><path d="M9.5 15.5 12 18l2.5-2.5"/></svg><?php if ($baru): ?><span class="tl-baru">Baru</span><?php endif; ?></span>
            <?php endif; ?>
            <div class="tl-teks">
              <b><?= ee($m['judul'] ?? 'Materi') ?></b>
              <?php if (!empty($m['deskripsi'])): ?><span class="tl-topik"><?= ee($m['deskripsi']) ?></span><?php endif; ?>
              <a class="pk-unduh" href="unduh.php?mk=<?= ee($slug) ?>&amp;f=<?= ee($berkas) ?>" target="_blank" rel="noopener">Unduh PDF <span><?= ukuran_manusia((int) ($m['ukuran'] ?? 0)) ?><?php if ($u > 0): ?> &middot; <?= angka_ringkas($u) ?>&times; diunduh<?php endif; ?></span></a>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </div>
</main>
</div>

<footer class="site-footer">
  <div class="container">
    <div class="kaki-peta">
      <div class="kaki-brand">
        <p class="kaki-brand-nama">Dr. Despinur Dara</p>
        <p class="kaki-brand-ket">Dosen &amp; peneliti Manajemen SDM, Fakultas Ekonomi Universitas Negeri Jakarta. Portal belajar terbuka: materi kuliah, bedah publikasi, dan bimbingan.</p>
      </div>
      <div class="kaki-kontak">
        <h4>Butuh sesuatu?</h4>
        <ul>
          <li><a href="mailto:dara@unj.ac.id">Hubungi lewat surel</a></li>
          <li><a href="mengajar.html">Semua mata kuliah</a></li>
          <li><a href="masuk.php">Masuk area bimbingan</a></li>
        </ul>
      </div>
    </div>
  </div>
</footer>

<script>
document.addEventListener('click', function (e) {
  var tgl = e.target.closest('.nav-toggle');
  if (tgl) { var open = document.querySelector('.nav').classList.toggle('open'); tgl.setAttribute('aria-expanded', open); }
});
</script>
<script src="assets/cari.js?v=<?= filemtime(__DIR__ . '/assets/cari.js') ?>" defer></script>
</body>
</html>
