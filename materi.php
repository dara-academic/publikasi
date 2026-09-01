<?php
/* ------------------------------------------------------------------
   Halaman publik daftar materi kuliah yang diunggah dosen.

   Membaca manifes materi.json lewat muat_materi(), lalu menampilkannya
   sebagai daftar unduhan yang dikelompokkan per mata kuliah. Terbuka
   untuk umum, seperti materi lain di situs ini yang memang karya sendiri
   dan bebas diunduh. Berkasnya dilayani dari folder unggahan.
   ------------------------------------------------------------------ */
require __DIR__ . '/sesi.php';

const MK_NAMA = [
    'pengantar-manajemen'          => 'Pengantar Manajemen',
    'pengadaan-sdm-aparatur'       => 'Pengadaan SDM Aparatur',
    'kompensasi-perlindungan-sdm'  => 'Kompensasi dan Perlindungan SDM Aparatur',
    'pelatihan-dan-pengembangan'   => 'Pelatihan dan Pengembangan',
    'manajemen-kinerja'            => 'Manajemen Kinerja',
    'simulasi-bisnis'              => 'Simulasi Bisnis',
    'management-information-system'=> 'Management Information System',
];

function ee($s): string { return htmlspecialchars((string) $s, ENT_QUOTES); }
function ukuran_manusia(int $b): string {
    if ($b >= 1048576) return round($b / 1048576, 1) . ' MB';
    if ($b >= 1024)    return round($b / 1024) . ' KB';
    return $b . ' B';
}

$materi = muat_materi();
$stat = baca_statistik();
$per_mk = [];
foreach (array_reverse($materi) as $m) {
    if (array_key_exists($m['mk'], MK_NAMA)) $per_mk[$m['mk']][] = $m;
}
$jml = count($materi);
$masuk = pengguna_sekarang();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<script>(function(){try{if(localStorage.getItem('tema')==='dark')document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Materi Kuliah, Dr. Despinur Dara</title>
<meta name="description" content="Materi kuliah manajemen dan sumber daya manusia yang bisa diunduh bebas, diperbarui berkala oleh Dr. Despinur Dara.">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&family=Source+Serif+4:wght@400;600&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
</head>
<body class="ak" data-grup="materi">
<a class="skip-link" href="#konten">Lewati ke konten utama</a>
<header class="ak-bar">
  <div class="ak-bar-isi">
    <a class="ak-nama" href="index.html">Belajar Bersama Dara</a>
    <button class="nav-toggle" aria-label="Buka menu" aria-expanded="false"><span></span><span></span><span></span></button>
    <nav class="nav" aria-label="Navigasi utama">
        <a href="index.html">Beranda</a>
        <a href="mengajar.html">Pengajaran</a>
        <a href="materi.php" class="active">Materi</a>
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
  <nav class="remah" aria-label="Jejak lokasi"><a href="index.html">Beranda</a><span class="remah-pisah">&rsaquo;</span><a href="mengajar.html">Pengajaran</a><span class="remah-pisah">&rsaquo;</span><span class="remah-kini">Materi kuliah</span></nav>

  <header class="hal-hero">
    <p class="kicker">Materi untuk diunduh</p>
    <h1>Materi kuliah</h1>
    <p class="hal-lead">Berkas materi tiap mata kuliah, bisa diunduh bebas dan menyebut sumbernya. Diperbarui berkala begitu ada materi baru.</p>
    <?php if ($masuk && $masuk['peran'] === 'admin'): ?>
      <div class="hal-aksi"><a class="tombol-x utama" href="admin-materi.php">Unggah materi baru</a></div>
    <?php endif; ?>
  </header>

  <?php if ($jml === 0): ?>
    <section class="hal-bagian">
      <p class="admin-kosong">Belum ada materi yang diunggah. Silakan cek lagi nanti.</p>
    </section>
  <?php else: ?>
    <?php foreach (MK_NAMA as $slug => $nama): ?>
      <?php if (empty($per_mk[$slug])) continue; ?>
      <section class="hal-bagian">
        <h2><?= ee($nama) ?></h2>
        <ul class="unduh-daftar">
          <?php foreach ($per_mk[$slug] as $m): ?>
            <li>
              <a class="unduh-item" href="unduh.php?mk=<?= ee($m['mk']) ?>&amp;f=<?= ee($m['berkas']) ?>" target="_blank" rel="noopener">
                <span class="unduh-ikon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M12 12v6"/><path d="M9.5 15.5 12 18l2.5-2.5"/></svg>
                </span>
                <span class="unduh-teks">
                  <b><?= ee($m['judul']) ?></b>
                  <?php if (!empty($m['deskripsi'])): ?><span class="unduh-ket"><?= ee($m['deskripsi']) ?></span><?php endif; ?>
                  <span class="unduh-meta">PDF &middot; <?= ukuran_manusia((int) ($m['ukuran'] ?? 0)) ?> &middot; <?= ee($m['tanggal']) ?><?php $u = (int) ($stat['unduh:' . $m['mk'] . '/' . $m['berkas']] ?? 0); if ($u > 0): ?> &middot; <?= angka_ringkas($u) ?>&times; diunduh<?php endif; ?></span>
                </span>
                <span class="unduh-aksi">Unduh</span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endforeach; ?>
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
