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
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600..800&family=Figtree:wght@400;500;600;700;800&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
</head>
<body class="ak" data-grup="mengajar" data-hal="/mata-kuliah/<?= ee($slug) ?>">
<a class="skip-link" href="#konten">Lewati ke konten utama</a>
<header class="ak-bar">
  <div class="ak-bar-isi">
    <a class="ak-nama" href="index.html"><svg class="ak-logo" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="8" fill="#e9b949"/><path d="M7.5 10.2c3-.9 5.9-.6 8.5 1.3v12.3c-2.6-1.9-5.5-2.2-8.5-1.3z" fill="#0f4c5c"/><path d="M24.5 10.2c-3-.9-5.9-.6-8.5 1.3v12.3c2.6-1.9 5.5-2.2 8.5-1.3z" fill="#0f4c5c" opacity=".72"/></svg><span>Belajar Bersama Dara</span></a>
    <button class="nav-toggle" aria-label="Buka menu" aria-expanded="false"><span></span><span></span><span></span></button>
    <div class="cari">
      <label class="sr-only" for="cari">Cari isi situs</label>
      <input class="cari-input" id="cari" type="search" autocomplete="off" placeholder="Cari materi, paper" data-naik="">
      <div class="cari-hasil" hidden></div>
    </div>
    <nav class="nav" aria-label="Navigasi utama">
        <a href="index.html">Beranda</a>
        <div class="nav-group active">
          <button class="nav-btn" type="button" aria-expanded="false" aria-haspopup="true">Pengajaran<span class="caret" aria-hidden="true">&#9662;</span></button>
          <div class="nav-menu">
            <a href="mengajar.html">Ringkasan pengajaran</a>
            <a href="mata-kuliah/index.html">Semua materi kuliah</a>
            <a href="bimbingan/index.html">Bimbingan karya ilmiah</a>
            <a href="tersimpan.html">Materi tersimpan</a>
          </div>
        </div>
        <div class="nav-group">
          <button class="nav-btn" type="button" aria-expanded="false" aria-haspopup="true">Bedah Publikasi<span class="caret" aria-hidden="true">&#9662;</span></button>
          <div class="nav-menu">
            <a href="bedah-publikasi.html">Ringkasan bedah publikasi</a>
            <a href="scopus/index.html">Paper Scopus</a>
            <a href="buku/index.html">Buku</a>
            <a href="mulai-dari-sini.html">Mulai dari sini</a>
            <a href="glosarium.html">Glosarium istilah</a>
          </div>
        </div>
        <a href="penelitian.html">Penelitian</a>
        <a href="kolaborasi.html">Kolaborasi</a>
        <a href="tentang.html">Profil</a>
        <a class="nav-masuk" href="masuk.php"><svg viewBox="0 0 24 24" aria-hidden="true" class="nav-masuk-ikon"><rect x="5" y="10.5" width="14" height="10" rx="2.2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/></svg>Masuk</a>
    </nav>
    <button class="tema-tombol" type="button" aria-label="Ganti tema terang/gelap" title="Ganti tema" data-tema-tombol><svg class="ikon ikon-bulan" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5z"/></svg><svg class="ikon ikon-matahari" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M4.6 4.6L6 6M18 18l1.4 1.4M2.5 12h2M19.5 12h2M4.6 19.4L6 18M18 6l1.4-1.4"/></svg></button>
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
        <p class="kaki-brand-nama"><svg class="ak-logo" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="8" fill="#e9b949"/><path d="M7.5 10.2c3-.9 5.9-.6 8.5 1.3v12.3c-2.6-1.9-5.5-2.2-8.5-1.3z" fill="#0f4c5c"/><path d="M24.5 10.2c-3-.9-5.9-.6-8.5 1.3v12.3c2.6-1.9 5.5-2.2 8.5-1.3z" fill="#0f4c5c" opacity=".72"/></svg>Dr. Despinur Dara</p>
        <p class="kaki-brand-ket">Dosen Manajemen SDM, Fakultas Ekonomi Universitas Negeri Jakarta. Materi kuliah, publikasi, dan bimbingan dalam satu tempat.</p>
      </div>
      <div class="kaki-kolom">
        <h4>Jelajahi</h4>
        <ul>
          <li><a href="mata-kuliah/index.html"><svg class="kaki-ikon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5.5C10 4 7 3.6 4 4v14c3-.4 6 0 8 1.5 2-1.5 5-1.9 8-1.5V4c-3-.4-6 0-8 1.5z"/><path d="M12 5.5v14"/></svg>Materi kuliah</a></li>
          <li><a href="bedah-publikasi.html"><svg class="kaki-ikon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5L20.5 20.5"/><path d="M8 10.5h5M10.5 8v5"/></svg>Bedah publikasi</a></li>
          <li><a href="penelitian.html"><svg class="kaki-ikon" viewBox="0 0 24 24" aria-hidden="true"><path d="M9.5 3.5h5"/><path d="M10.5 3.5v5.2L5.2 17a3 3 0 0 0 2.6 4.5h8.4a3 3 0 0 0 2.6-4.5l-5.3-8.3V3.5"/><path d="M7.5 14.5h9"/></svg>Penelitian</a></li>
          <li><a href="kolaborasi.html"><svg class="kaki-ikon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="8.5" r="3"/><circle cx="16.5" cy="8.5" r="3"/><path d="M2.5 19.5c.6-3 2.8-4.7 5.5-4.7 1.6 0 3 .6 4 1.7 1-1.1 2.4-1.7 4-1.7 2.7 0 4.9 1.7 5.5 4.7"/></svg>Kolaborasi</a></li>
          <li><a href="tentang.html"><svg class="kaki-ikon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8.2" r="3.6"/><path d="M5 20c.8-3.6 3.6-5.6 7-5.6s6.2 2 7 5.6"/></svg>Profil</a></li>
        </ul>
      </div>
      <div class="kaki-kolom">
        <h4>Profil akademik</h4>
        <ul>
          <li><a href="https://scholar.google.com/citations?user=eeb8VR0AAAAJ&amp;hl=id" target="_blank" rel="noopener noreferrer"><svg class="kaki-ikon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4L2.5 9 12 14l9.5-5z"/><path d="M6 11.2V16c0 1.5 2.7 2.6 6 2.6s6-1.1 6-2.6v-4.8"/></svg>Google Scholar</a></li>
          <li><a href="https://www.scopus.com/authid/detail.uri?authorId=57219945924" target="_blank" rel="noopener noreferrer"><svg class="kaki-ikon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5l8.5 4.2-8.5 4.2-8.5-4.2z"/><path d="M3.5 12l8.5 4.2 8.5-4.2"/><path d="M3.5 16.3l8.5 4.2 8.5-4.2"/></svg>Scopus</a></li>
          <li><a href="https://sinta.kemdiktisaintek.go.id/authors/profile/6728883/" target="_blank" rel="noopener noreferrer"><svg class="kaki-ikon" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M8.5 13h7M8.5 16.5h5"/></svg>SINTA</a></li>
          <li><a href="https://orcid.org/0000-0001-7291-4643" target="_blank" rel="noopener noreferrer"><svg class="kaki-ikon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.2 10.4v6"/><path d="M9.2 7.9h.01"/><path d="M12.7 16.4v-6h1.8a3 3 0 0 1 0 6z"/></svg>ORCID</a></li>
          <li><a href="https://www.linkedin.com/in/despinur-dara-77674193/" target="_blank" rel="noopener noreferrer"><svg class="kaki-ikon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2.5"/><path d="M7.6 10.6v6M7.6 7.5h.01"/><path d="M11.6 16.6v-6M11.6 13.2c0-1.5 1-2.6 2.5-2.6s2.5 1.1 2.5 2.6v3.4"/></svg>LinkedIn</a></li>
        </ul>
      </div>
      <div class="kaki-kontak kaki-kolom">
        <h4>Bantuan</h4>
        <ul>
          <li><a href="mailto:dara@unj.ac.id"><svg class="kaki-ikon" viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M3 6.5l9 6 9-6"/></svg>Hubungi lewat surel</a></li>
          <li><a href="tersimpan.html"><svg class="kaki-ikon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 3.5h11a1 1 0 0 1 1 1v16l-6.5-3.8L5.5 20.5v-16a1 1 0 0 1 1-1z"/></svg>Materi tersimpan</a></li>
          <li><a href="kolaborasi.html"><svg class="kaki-ikon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="8.5" r="3"/><circle cx="16.5" cy="8.5" r="3"/><path d="M2.5 19.5c.6-3 2.8-4.7 5.5-4.7 1.6 0 3 .6 4 1.7 1-1.1 2.4-1.7 4-1.7 2.7 0 4.9 1.7 5.5 4.7"/></svg>Ajak kolaborasi riset</a></li>
          <li><a href="masuk.php"><svg class="kaki-ikon" viewBox="0 0 24 24" aria-hidden="true"><rect x="5.5" y="10.5" width="13" height="9.5" rx="2"/><path d="M8.5 10.5V7.8a3.5 3.5 0 0 1 7 0v2.7"/><path d="M12 14.5v2.5"/></svg>Masuk area bimbingan</a></li>
        </ul>
      </div>
    </div>
    <p class="kaki-bawah">&copy; 2026 Dr. Despinur Dara &middot; Materi ajar boleh dipakai ulang dengan mencantumkan sumber &middot; Tanpa iklan</p>
  </div>
</footer>

<script>
document.addEventListener('click', function (e) {
  var btn = e.target.closest('.nav-btn');
  document.querySelectorAll('.nav-group.open').forEach(function (g) {
    if (!btn || g !== btn.parentElement) {
      g.classList.remove('open');
      g.querySelector('.nav-btn').setAttribute('aria-expanded', 'false');
    }
  });
  if (btn) {
    var open = btn.parentElement.classList.toggle('open');
    btn.setAttribute('aria-expanded', open);
    return;
  }
  var tgl = e.target.closest('.nav-toggle');
  if (tgl) {
    var open2 = document.querySelector('.nav').classList.toggle('open');
    tgl.setAttribute('aria-expanded', open2);
  }
});
document.addEventListener('keydown', function (e) {
  if (e.key !== 'Escape') return;
  var terbuka = document.querySelector('.nav-group.open');
  if (terbuka) {
    terbuka.classList.remove('open');
    var b = terbuka.querySelector('.nav-btn');
    b.setAttribute('aria-expanded', 'false');
    b.focus();
    return;
  }
  var nav = document.querySelector('.nav.open');
  if (nav) {
    nav.classList.remove('open');
    var t = document.querySelector('.nav-toggle');
    t.setAttribute('aria-expanded', 'false');
    t.focus();
  }
});
</script>
<script src="assets/cari.js?v=<?= filemtime(__DIR__ . '/assets/cari.js') ?>" defer></script>
</body>
</html>
