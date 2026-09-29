<?php
/* ------------------------------------------------------------------
   Beranda panel admin. Hanya admin.

   Halaman pertama sesudah dosen masuk. Isinya menu ke tiap bagian
   pengelolaan, bukan salah satu isinya, supaya tidak tercampur:
   Bimbingan & akun, Materi kuliah, dan Bedah paper. Tiap kartu membawa
   ringkasan angka bagiannya.
   ------------------------------------------------------------------ */
require __DIR__ . '/bimbingan-inti.php';
$pengguna = wajib_masuk_segar();
if ($pengguna['peran'] !== 'admin') {
    header('Location: /bimbingan/rekap.php');
    exit;
}

function ee($s): string { return htmlspecialchars((string) $s, ENT_QUOTES); }

$antre   = array_merge(muat_daftar_akun(), muat_antrean());
$akun    = muat_pengguna();
$rekap_b = muat_mhs();
$materi  = muat_materi();
$paper   = muat_paper();
$buku    = muat_buku();
$stat    = baca_statistik();
$total_lihat = 0; foreach ($stat as $k => $v) if (strncmp($k, 'lihat:', 6) === 0) $total_lihat += (int) $v;
$komentar_pending = count(array_filter(komentar_semua(), fn($k) => ($k['status'] ?? '') !== 'setuju'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<script>(function(){try{if(localStorage.getItem('tema')==='dark')document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Panel Admin, Portal Dr. Despinur Dara</title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Figtree:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="ak" data-grup="mengajar">
<header class="ak-bar">
  <div class="ak-bar-isi">
    <a class="ak-nama" href="index.html"><svg class="ak-logo" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="8" fill="#e9b949"/><path d="M7.5 10.2c3-.9 5.9-.6 8.5 1.3v12.3c-2.6-1.9-5.5-2.2-8.5-1.3z" fill="#0f4c5c"/><path d="M24.5 10.2c-3-.9-5.9-.6-8.5 1.3v12.3c2.6-1.9 5.5-2.2 8.5-1.3z" fill="#0f4c5c" opacity=".72"/></svg><span>Belajar Bersama Dara</span></a>
    <span class="rekap-siapa"><b><?= ee($pengguna['nama']) ?></b>
      &middot; <a href="index.html">Ke situs</a>
      &middot; <a href="ganti-sandi.php">Ganti sandi</a>
      &middot; <a href="keluar.php">Keluar</a></span>
  </div>
</header>

<div class="admin-band">
  <div class="admin-band-isi">
    <p class="admin-lencana">Panel admin</p>
    <h1>Selamat datang, <?= ee($pengguna['nama']) ?></h1>
    <p class="admin-band-lead">Pilih bagian yang mau dikelola. Tiap bagian punya
    halamannya sendiri supaya tidak tercampur.</p>
  </div>
</div>

<main class="ak-utama admin-utama" id="konten">
  <div class="admin-pintu-rak">

    <a class="admin-pintu" href="admin-bimbingan.php">
      <span class="admin-pintu-ikon" aria-hidden="true">&#127891;</span>
      <b>Manajemen bimbingan</b>
      <span class="admin-pintu-ket">Tambah mahasiswa S1, S2, S3, perbarui status, dan setujui pendaftaran akun.</span>
      <span class="admin-pintu-tanda"><?= count($antre) ?> menunggu &middot; <?= count($rekap_b) ?> mahasiswa &middot; <?= count(array_filter($rekap_b, 'sudah_lulus')) ?> lulus</span>
      <span class="admin-pintu-panah" aria-hidden="true">&rarr;</span>
    </a>

    <a class="admin-pintu" href="akun.php">
      <span class="admin-pintu-ikon" aria-hidden="true">&#128273;</span>
      <b>Akun</b>
      <span class="admin-pintu-ket">Tambah akun dosen atau admin, reset kode akses, hapus akun.</span>
      <span class="admin-pintu-tanda"><?= count($akun) ?> akun</span>
      <span class="admin-pintu-panah" aria-hidden="true">&rarr;</span>
    </a>

    <a class="admin-pintu" href="admin-materi.php">
      <span class="admin-pintu-ikon" aria-hidden="true">&#128218;</span>
      <b>Materi kuliah</b>
      <span class="admin-pintu-ket">Unggah dan kelola berkas PDF materi kuliah untuk diunduh mahasiswa.</span>
      <span class="admin-pintu-tanda"><?= count($materi) ?> materi terunggah</span>
      <span class="admin-pintu-panah" aria-hidden="true">&rarr;</span>
    </a>

    <a class="admin-pintu" href="admin-bedah.php">
      <span class="admin-pintu-ikon" aria-hidden="true">&#128300;</span>
      <b>Bedah paper</b>
      <span class="admin-pintu-ket">Tambahkan paper Dara yang sudah terbit dan kelola daftarnya.</span>
      <span class="admin-pintu-tanda"><?= count($paper) ?> paper terdaftar</span>
      <span class="admin-pintu-panah" aria-hidden="true">&rarr;</span>
    </a>

    <a class="admin-pintu" href="admin-buku.php">
      <span class="admin-pintu-ikon" aria-hidden="true">&#128214;</span>
      <b>Buku</b>
      <span class="admin-pintu-ket">Tambahkan buku Dara yang sudah terbit dan kelola daftarnya.</span>
      <span class="admin-pintu-tanda"><?= count($buku) ?> buku terdaftar</span>
      <span class="admin-pintu-panah" aria-hidden="true">&rarr;</span>
    </a>

    <a class="admin-pintu" href="admin-statistik.php">
      <span class="admin-pintu-ikon" aria-hidden="true">&#128202;</span>
      <b>Statistik</b>
      <span class="admin-pintu-ket">Kunjungan, unduhan, dan buka paper atau buku, dihitung sendiri di server.</span>
      <span class="admin-pintu-tanda"><?= number_format($total_lihat, 0, ',', '.') ?> kunjungan</span>
      <span class="admin-pintu-panah" aria-hidden="true">&rarr;</span>
    </a>

    <a class="admin-pintu" href="admin-komentar.php">
      <span class="admin-pintu-ikon" aria-hidden="true">&#128172;</span>
      <b>Tanya jawab</b>
      <span class="admin-pintu-ket">Setujui, balas, atau hapus pertanyaan dari pengunjung.</span>
      <span class="admin-pintu-tanda"><?= (int) $komentar_pending ?> menunggu ditinjau</span>
      <span class="admin-pintu-panah" aria-hidden="true">&rarr;</span>
    </a>

    <a class="admin-pintu" href="admin-cadangan.php">
      <span class="admin-pintu-ikon" aria-hidden="true">&#128190;</span>
      <b>Cadangan data</b>
      <span class="admin-pintu-ket">Unduh salinan materi, komentar, akun, dan statistik. Semuanya hanya ada di server ini.</span>
      <span class="admin-pintu-tanda">Simpan berkala di luar server</span>
      <span class="admin-pintu-panah" aria-hidden="true">&rarr;</span>
    </a>

  </div>
</main>
</body>
</html>
