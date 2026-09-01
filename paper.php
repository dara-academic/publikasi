<?php
/* ------------------------------------------------------------------
   Halaman publik daftar paper Dara yang sudah terbit.

   Membaca manifes paper.json lewat muat_paper() dan menampilkannya
   sebagai kartu cover-forward, terbaru dulu, masing-masing menautkan ke
   paper aslinya di situs penerbit. Terbuka untuk umum.
   ------------------------------------------------------------------ */
require __DIR__ . '/sesi.php';

function ee($s): string { return htmlspecialchars((string) $s, ENT_QUOTES); }
function badge_kelas(string $indeks): string {
    if ($indeks === 'Scopus Q1') return 'k-q1';
    if ($indeks === 'Scopus Q2') return 'k-q2';
    if ($indeks === 'Scopus Q3' || $indeks === 'Scopus Q4') return 'k-q3';
    if (strncmp($indeks, 'SINTA', 5) === 0) return 'k-sinta';
    return 'k-q2';
}

$paper = array_reverse(muat_paper());
$stat  = baca_statistik();
$jml   = count($paper);
$masuk = pengguna_sekarang();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<script>(function(){try{if(localStorage.getItem('tema')==='dark')document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Paper Terbit, Dr. Despinur Dara</title>
<meta name="description" content="Daftar paper Dr. Despinur Dara yang sudah terbit di jurnal terindeks, dengan tautan ke paper aslinya.">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&family=Source+Serif+4:wght@400;600&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
</head>
<body class="ak" data-grup="bedah-publikasi">
<a class="skip-link" href="#konten">Lewati ke konten utama</a>
<header class="ak-bar">
  <div class="ak-bar-isi">
    <a class="ak-nama" href="index.html">Belajar Bersama Dara</a>
    <button class="nav-toggle" aria-label="Buka menu" aria-expanded="false"><span></span><span></span><span></span></button>
    <nav class="nav" aria-label="Navigasi utama">
        <a href="index.html">Beranda</a>
        <a href="mengajar.html">Pengajaran</a>
        <a href="materi.php">Materi</a>
        <a href="bedah-publikasi.html" class="active">Bedah Publikasi</a>
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
  <nav class="remah" aria-label="Jejak lokasi"><a href="index.html">Beranda</a><span class="remah-pisah">&rsaquo;</span><a href="bedah-publikasi.html">Bedah Publikasi</a><span class="remah-pisah">&rsaquo;</span><span class="remah-kini">Paper terbit</span></nav>

  <header class="hal-hero">
    <p class="kicker">Publikasi terbit</p>
    <h1>Paper terbit</h1>
    <p class="hal-lead">Paper Dr. Despinur Dara yang sudah terbit di jurnal terindeks. Ketuk kartunya untuk membuka paper aslinya di situs penerbit.</p>
    <?php if ($masuk && $masuk['peran'] === 'admin'): ?>
      <div class="hal-aksi"><a class="tombol-x utama" href="admin-bedah.php">Tambah paper</a></div>
    <?php endif; ?>
  </header>

  <?php if ($jml === 0): ?>
    <section class="hal-bagian">
      <p class="admin-kosong">Belum ada paper yang ditambahkan. Sementara ini, bedah paper lengkap ada di <a href="bedah-publikasi.html">Dapur publikasi</a>.</p>
    </section>
  <?php else: ?>
    <section class="hal-bagian">
      <div class="karya-rak">
        <?php foreach ($paper as $p): ?>
          <a class="karya-kartu" href="buka.php?t=paper&amp;id=<?= (int) $p['id'] ?>" target="_blank" rel="noopener noreferrer">
            <span class="karya-sampul">
              <?php if (!empty($p['sampul'])): ?>
                <img src="unggahan/paper/<?= ee($p['sampul']) ?>" alt="Sampul <?= ee($p['jurnal']) ?>" loading="lazy" decoding="async">
              <?php else: ?>
                <span class="karya-sampul-kosong" aria-hidden="true"><?= ee(mb_strtoupper(mb_substr($p['jurnal'], 0, 1))) ?></span>
              <?php endif; ?>
              <span class="karya-badge <?= badge_kelas($p['indeks']) ?>"><?= ee($p['indeks']) ?></span>
            </span>
            <span class="karya-teks">
              <b><?= ee($p['judul']) ?></b>
              <span class="karya-venue"><?= ee($p['jurnal']) ?> &middot; <?= ee($p['tahun']) ?><?php $bk = (int) ($stat['buka:paper:' . (int) $p['id']] ?? 0); if ($bk > 0): ?> &middot; <?= angka_ringkas($bk) ?>&times; dibuka<?php endif; ?></span>
              <?php if (!empty($p['ringkasan'])): ?><span class="karya-venue"><?= ee($p['ringkasan']) ?></span><?php endif; ?>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

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
          <li><a href="mailto:dara@unj.ac.id"><svg class="kaki-ikon" viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M3 6.5l9 6 9-6"/></svg>Hubungi lewat surel</a></li>
          <li><a href="kolaborasi.html"><svg class="kaki-ikon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="8.5" r="3"/><circle cx="16.5" cy="8.5" r="3"/><path d="M2.5 19.5c.6-3 2.8-4.7 5.5-4.7 1.6 0 3 .6 4 1.7 1-1.1 2.4-1.7 4-1.7 2.7 0 4.9 1.7 5.5 4.7"/></svg>Ajak kolaborasi riset</a></li>
          <li><a href="masuk.php"><svg class="kaki-ikon" viewBox="0 0 24 24" aria-hidden="true"><rect x="5.5" y="10.5" width="13" height="9.5" rx="2"/><path d="M8.5 10.5V7.8a3.5 3.5 0 0 1 7 0v2.7"/><path d="M12 14.5v2.5"/></svg>Masuk area bimbingan</a></li>
        </ul>
      </div>
    </div>
  </div>
</footer>

<script>
document.addEventListener('click', function (e) {
  var tgl = e.target.closest('.nav-toggle');
  if (tgl) {
    var open = document.querySelector('.nav').classList.toggle('open');
    tgl.setAttribute('aria-expanded', open);
  }
});
</script>
<script src="assets/cari.js?v=<?= filemtime(__DIR__ . '/assets/cari.js') ?>" defer></script>
</body>
</html>
