<?php
/* ------------------------------------------------------------------
   Pendaftaran akun mahasiswa bimbingan.

   Mahasiswa membuat akunnya sendiri: NIM menjadi nama pengguna dan
   sandi dipilih sendiri. Pendaftaran menunggu persetujuan Dr. Dara di
   panel Manajemen bimbingan; setelah disetujui, mahasiswa langsung bisa
   masuk dengan NIM dan sandinya. Yang disimpan hanya hash sandi.

   Pagar formulir publik: token CSRF, pembatas percobaan per alamat,
   kolom jebakan untuk bot, dan tolakan untuk NIM yang sudah terdaftar
   atau masih menunggu.
   ------------------------------------------------------------------ */
require __DIR__ . '/../bimbingan-inti.php';
mulai_sesi();

$galat = '';
$sukses = false;
$isi = ['nama' => '', 'nim' => '', 'jenjang' => '', 'angkatan' => '', 'judul' => '', 'kontak' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '?';
    foreach ($isi as $k => $_) $isi[$k] = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($_POST[$k] ?? ''))));
    $isi['nim'] = preg_replace('/\s+/', '', $isi['nim']);
    $isi['kontak'] = strtolower($isi['kontak']);
    $sandi = (string) ($_POST['sandi'] ?? '');
    $sandi2 = (string) ($_POST['sandi2'] ?? '');

    if ((string) ($_POST['situs_web'] ?? '') !== '') {
        $sukses = true;                       /* bot dibiarkan merasa berhasil */
    } elseif (!csrf_sah()) {
        $galat = 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } elseif (!boleh_mencoba('daftar-' . $ip)) {
        $galat = 'Terlalu banyak percobaan dari jaringan ini. Coba lagi nanti.';
    } elseif (mb_strlen($isi['nama']) < 5 || mb_strlen($isi['nama']) > 80 || !preg_match('/^[\p{L}\'.,\- ]+$/u', $isi['nama'])) {
        $galat = 'Tulis nama lengkap sesuai data akademik, tanpa angka atau simbol.';
    } elseif (!preg_match('/^[0-9]{6,20}$/', $isi['nim'])) {
        $galat = 'NIM hanya berisi angka, 6 sampai 20 digit.';
    } elseif (!isset(JENJANG[$isi['jenjang']])) {
        $galat = 'Pilih jenjang.';
    } elseif ($isi['angkatan'] !== '' && !preg_match('/^20\d\d$/', $isi['angkatan'])) {
        $galat = 'Angkatan ditulis empat angka, misalnya 2024.';
    } elseif (!filter_var($isi['kontak'], FILTER_VALIDATE_EMAIL)) {
        $galat = 'Alamat surel tidak sah.';
    } elseif (mb_strlen($sandi) < 8) {
        $galat = 'Kata sandi minimal 8 karakter.';
    } elseif ($sandi !== $sandi2) {
        $galat = 'Kedua kata sandi tidak sama.';
    } elseif (empty($_POST['setuju'])) {
        $galat = 'Centang persetujuan penampilan data di papan monitoring.';
    } else {
        $menunggu = muat_daftar_akun();
        $nim_antre = array_column($menunggu, 'nim');
        if (nama_pengguna_ada($isi['nim'])) {
            $galat = 'NIM ini sudah punya akun. Silakan masuk, atau hubungi dara@unj.ac.id bila lupa sandi.';
        } elseif (in_array($isi['nim'], $nim_antre, true)) {
            $galat = 'NIM ini sudah mendaftar dan sedang menunggu persetujuan.';
        } elseif (count($menunggu) >= 200) {
            $galat = 'Antrean pendaftaran penuh. Hubungi dara@unj.ac.id.';
        } else {
            tambah_daftar_akun([
                'nama' => $isi['nama'], 'nim' => $isi['nim'], 'jenjang' => $isi['jenjang'],
                'angkatan' => $isi['angkatan'], 'judul' => mb_substr($isi['judul'], 0, 300),
                'kontak' => $isi['kontak'], 'sandi' => password_hash($sandi, PASSWORD_DEFAULT),
                'waktu' => date('c'), 'setuju_tampil' => date('c'),
            ]);
            catat_gagal('daftar-' . $ip);     /* sekaligus penjatah: 5 kiriman per 15 menit */
            $sukses = true;
        }
    }
}
$csrf = htmlspecialchars(token_csrf(), ENT_QUOTES);
function e($s): string { return htmlspecialchars((string) $s, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, follow">
<title>Daftar Akun Bimbingan, Portal Dr. Despinur Dara</title>
<link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="../assets/style.css?v=<?= filemtime(__DIR__ . '/../assets/style.css') ?>">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600..800&family=Figtree:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="ak halaman-masuk" data-grup="mengajar">
<header class="ak-bar">
  <div class="ak-bar-isi">
    <a class="ak-nama" href="../index.html"><svg class="ak-logo" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="8" fill="#e9b949"/><path d="M7.5 10.2c3-.9 5.9-.6 8.5 1.3v12.3c-2.6-1.9-5.5-2.2-8.5-1.3z" fill="#0f4c5c"/><path d="M24.5 10.2c-3-.9-5.9-.6-8.5 1.3v12.3c2.6-1.9 5.5-2.2 8.5-1.3z" fill="#0f4c5c" opacity=".72"/></svg><span>Belajar Bersama Dara</span></a>
  </div>
</header>
<main class="masuk-panggung">
  <section class="masuk-kartu">
<?php if ($sukses): ?>
    <p class="kicker">Pendaftaran terkirim</p>
    <h1>Menunggu persetujuan</h1>
    <p class="masuk-keterangan">Pendaftaran Anda sudah diterima. Setelah disetujui
      Dr. Dara, masuk ke area bimbingan dengan <b>NIM</b> dan kata sandi yang Anda buat.</p>
    <p class="masuk-kaki"><a href="progres.php">&larr; Lihat papan monitoring</a></p>
<?php else: ?>
    <p class="kicker">Mahasiswa bimbingan</p>
    <h1>Daftar akun</h1>
    <p class="masuk-keterangan">Untuk mahasiswa S1, S2, dan S3 yang dibimbing Dr. Dara.
      Akun aktif setelah disetujui. <a href="panduan.html">Baca panduan</a> sebelum mendaftar.</p>
<?php if ($galat): ?>
    <p class="masuk-galat" role="alert"><?= e($galat) ?></p>
<?php endif; ?>
    <form method="post" action="daftar.php">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <div class="jebakan" aria-hidden="true">
        <label for="situs_web">Situs web</label>
        <input id="situs_web" name="situs_web" type="text" tabindex="-1" autocomplete="off">
      </div>
      <label class="masuk-label" for="nama">Nama lengkap sesuai data akademik</label>
      <input class="masuk-input" id="nama" name="nama" type="text" value="<?= e($isi['nama']) ?>"
             autocomplete="name" minlength="5" maxlength="80" required autofocus>
      <label class="masuk-label" for="nim">NIM</label>
      <input class="masuk-input" id="nim" name="nim" type="text" value="<?= e($isi['nim']) ?>"
             inputmode="numeric" pattern="[0-9]{6,20}" maxlength="20" required>
      <label class="masuk-label" for="jenjang">Jenjang</label>
      <select class="masuk-input" id="jenjang" name="jenjang" required>
        <option value="" disabled<?= $isi['jenjang'] === '' ? ' selected' : '' ?>>Pilih jenjang</option>
<?php foreach (JENJANG as $j => $t): ?>
        <option value="<?= $j ?>"<?= $isi['jenjang'] === $j ? ' selected' : '' ?>><?= $j ?> &middot; <?= $t ?></option>
<?php endforeach; ?>
      </select>
      <label class="masuk-label" for="angkatan">Angkatan</label>
      <input class="masuk-input" id="angkatan" name="angkatan" type="text" value="<?= e($isi['angkatan']) ?>"
             inputmode="numeric" pattern="20[0-9]{2}" maxlength="4" placeholder="2024">
      <label class="masuk-label" for="judul">Judul penelitian (bila sudah ada)</label>
      <input class="masuk-input" id="judul" name="judul" type="text" value="<?= e($isi['judul']) ?>" maxlength="300">
      <label class="masuk-label" for="kontak">Surel aktif</label>
      <input class="masuk-input" id="kontak" name="kontak" type="email" value="<?= e($isi['kontak']) ?>"
             autocomplete="email" required>
      <label class="masuk-label" for="sandi">Kata sandi</label>
      <input class="masuk-input" id="sandi" name="sandi" type="password" minlength="8"
             autocomplete="new-password" required>
      <label class="masuk-label" for="sandi2">Ulangi kata sandi</label>
      <input class="masuk-input" id="sandi2" name="sandi2" type="password" minlength="8"
             autocomplete="new-password" required>
      <label class="pd-setuju"><input type="checkbox" name="setuju" value="1" required<?= !empty($_POST['setuju']) ? ' checked' : '' ?>>
        <span>Saya setuju nama, judul penelitian, dan status bimbingan saya ditampilkan di papan monitoring.</span></label>
      <button class="masuk-tombol" type="submit">Kirim pendaftaran</button>
    </form>
    <p class="masuk-kaki">NIM dan surel tidak ditampilkan di mana pun. Papan monitoring
      hanya menampilkan nama, judul, dan status bimbingan.</p>
<?php endif; ?>
  </section>
  <p class="masuk-pulang"><a href="progres.php">&larr; Kembali ke papan monitoring</a></p>
</main>
</body>
</html>
