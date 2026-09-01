<?php
/* ------------------------------------------------------------------
   Halaman publik daftar buku Dara yang sudah terbit.

   Membaca manifes buku.json lewat muat_buku() dan menampilkannya sebagai
   kartu sampul, terbaru dulu. Kartu menautkan ke tempat memperoleh buku
   bila tautannya diisi. Terbuka untuk umum.
   ------------------------------------------------------------------ */
require __DIR__ . '/sesi.php';

function ee($s): string { return htmlspecialchars((string) $s, ENT_QUOTES); }

$buku  = array_reverse(muat_buku());
$stat  = baca_statistik();
$jml   = count($buku);
$masuk = pengguna_sekarang();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<script>(function(){try{if(localStorage.getItem('tema')==='dark')document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Buku Terbit, Dr. Despinur Dara</title>
<meta name="description" content="Buku Dr. Despinur Dara yang sudah terbit, dari riset ke panduan praktis.">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&family=Source+Serif+4:wght@400;600&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
</head>
<body class="ak" data-grup="penelitian">
<a class="skip-link" href="#konten">Lewati ke konten utama</a>
<header class="ak-bar">
  <div class="ak-bar-isi">
    <a class="ak-nama" href="index.html">Belajar Bersama Dara</a>
    <button class="nav-toggle" aria-label="Buka menu" aria-expanded="false"><span></span><span></span><span></span></button>
    <nav class="nav" aria-label="Navigasi utama">
        <a href="index.html">Beranda</a>
        <a href="mengajar.html">Pengajaran</a>
        <a href="bedah-publikasi.html">Bedah Publikasi</a>
        <a href="penelitian.html" class="active">Penelitian</a>
        <a href="kolaborasi.html">Kolaborasi</a>
        <a href="tentang.html">Profil</a>
        <a class="nav-masuk" href="masuk.php"><svg viewBox="0 0 24 24" aria-hidden="true" class="nav-masuk-ikon"><rect x="5" y="10.5" width="14" height="10" rx="2.2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/></svg>Masuk</a>
    </nav>
    <button class="tema-tombol" type="button" aria-label="Ganti tema terang/gelap" title="Ganti tema" data-tema-tombol>&#127769;</button>
  </div>
</header>

<div class="ak-halaman">
<main class="ak-utama" id="konten">
  <nav class="remah" aria-label="Jejak lokasi"><a href="index.html">Beranda</a><span class="remah-pisah">&rsaquo;</span><a href="penelitian.html">Penelitian</a><span class="remah-pisah">&rsaquo;</span><span class="remah-kini">Buku terbit</span></nav>

  <header class="hal-hero">
    <p class="kicker">Karya buku</p>
    <h1>Buku terbit</h1>
    <p class="hal-lead">Buku Dr. Despinur Dara yang sudah terbit, dari hasil riset ke panduan praktis.</p>
    <?php if ($masuk && $masuk['peran'] === 'admin'): ?>
      <div class="hal-aksi"><a class="tombol-x utama" href="admin-buku.php">Tambah buku</a></div>
    <?php endif; ?>
  </header>

  <?php if ($jml === 0): ?>
    <section class="hal-bagian">
      <p class="admin-kosong">Belum ada buku yang ditambahkan. Sementara ini, daftar buku ada di <a href="buku/index.html">etalase buku</a>.</p>
    </section>
  <?php else: ?>
    <section class="hal-bagian">
      <div class="karya-rak">
        <?php foreach ($buku as $b): ?>
          <?php
            $tag  = !empty($b['tautan']) ? 'a' : 'div';
            $href = !empty($b['tautan']) ? ' href="buka.php?t=buku&amp;id=' . (int) $b['id'] . '" target="_blank" rel="noopener noreferrer"' : '';
            $bk   = (int) ($stat['buka:buku:' . (int) $b['id']] ?? 0);
          ?>
          <<?= $tag ?> class="karya-kartu"<?= $href ?>>
            <span class="karya-sampul">
              <?php if (!empty($b['sampul'])): ?>
                <img src="unggahan/buku/<?= ee($b['sampul']) ?>" alt="Sampul buku <?= ee($b['judul']) ?>" loading="lazy" decoding="async">
              <?php else: ?>
                <span class="karya-sampul-kosong" aria-hidden="true"><?= ee(mb_strtoupper(mb_substr($b['judul'], 0, 1))) ?></span>
              <?php endif; ?>
              <span class="karya-badge k-buku">Buku</span>
            </span>
            <span class="karya-teks">
              <b><?= ee($b['judul']) ?></b>
              <span class="karya-venue"><?= $b['penerbit'] ? ee($b['penerbit']) . ' &middot; ' : '' ?><?= ee($b['tahun']) ?><?php if ($bk > 0): ?> &middot; <?= angka_ringkas($bk) ?>&times; dibuka<?php endif; ?></span>
              <?php if (!empty($b['deskripsi'])): ?><span class="karya-venue"><?= ee($b['deskripsi']) ?></span><?php endif; ?>
            </span>
          </<?= $tag ?>>
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
