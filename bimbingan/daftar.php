<?php
/* ------------------------------------------------------------------
   Pendaftaran mahasiswa bimbingan.

   Alur bisnisnya: mahasiswa mengisi formulir ini, namanya langsung
   muncul di papan monitoring dengan status belum diverifikasi dan warna
   abu, lalu admin memutuskan di panel kelola. Disetujui berarti masuk
   daftar bimbingan resmi dan dibuatkan akun; ditolak berarti hilang.

   Formulir publik yang menulis ke server adalah pintu masuk sampah,
   jadi ada empat pagar: token CSRF, pembatas percobaan per alamat,
   kolom jebakan tak kasatmata untuk bot, dan tolakan untuk nama yang
   sudah terdaftar di rekap maupun antrean.
   ------------------------------------------------------------------ */
require __DIR__ . '/../sesi.php';
mulai_sesi();

$KELOMPOK = [
    'Skripsi Angkatan 2023 (Kelas Kerja Sama BKN)',
    'Skripsi Angkatan 2024',
    'Skripsi Angkatan 2025',
    'Tesis S2',
    'Disertasi S3',
];

$galat = '';
$sukses = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '?';
    $nama = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['nama'] ?? ''))));
    $kelompok = (string) ($_POST['kelompok'] ?? '');
    $kontak = strtolower(trim((string) ($_POST['kontak'] ?? '')));
    $jebakan = (string) ($_POST['situs_web'] ?? '');

    if ($jebakan !== '') {
        $sukses = true;                       /* bot dibiarkan merasa berhasil */
    } elseif (!csrf_sah()) {
        $galat = 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } elseif (!boleh_mencoba('daftar-' . $ip)) {
        $galat = 'Terlalu banyak percobaan dari jaringan ini. Coba lagi nanti.';
    } elseif (mb_strlen($nama) < 5 || mb_strlen($nama) > 80
              || !preg_match('/^[\p{L}\'.,\- ]+$/u', $nama)) {
        $galat = 'Tulis nama lengkap yang wajar, tanpa angka atau simbol.';
    } elseif (!in_array($kelompok, $KELOMPOK, true)) {
        $galat = 'Pilih kelompok bimbingan.';
    } elseif (!filter_var($kontak, FILTER_VALIDATE_EMAIL)) {
        $galat = 'Alamat surel tidak sah.';
    } else {
        $sudah = [];
        foreach ((muat_bimbingan()['mahasiswa'] ?? []) as $m) {
            $sudah[] = mb_strtolower($m['nama']);
        }
        $antre = muat_antrean();
        foreach ($antre as $a) $sudah[] = mb_strtolower($a['nama']);
        if (in_array(mb_strtolower($nama), $sudah, true)) {
            $galat = 'Nama ini sudah ada di daftar bimbingan atau antrean verifikasi.';
        } elseif (count($antre) >= 200) {
            $galat = 'Antrean pendaftaran penuh. Hubungi dara@unj.ac.id.';
        } else {
            tambah_antrean([
                'nama' => $nama,
                'kelompok' => $kelompok,
                'kontak' => $kontak,
                'waktu' => date('c'),
            ]);
            catat_gagal('daftar-' . $ip);     /* sekaligus penjatah: 5 kiriman per 15 menit */
            $sukses = true;
        }
    }
}
$csrf = htmlspecialchars(token_csrf(), ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, follow">
<title>Daftar Bimbingan, Portal Dr. Despinur Dara</title>
<link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="../assets/style.css?v=<?= filemtime(__DIR__ . '/../assets/style.css') ?>">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&family=Source+Serif+4:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="ak halaman-masuk" data-grup="mengajar">
<header class="ak-bar">
  <div class="ak-bar-isi">
    <a class="ak-nama" href="../index.html">Despinur Dara</a>
  </div>
</header>
<main class="masuk-panggung">
  <section class="masuk-kartu">
<?php if ($sukses): ?>
    <p class="kicker">Pendaftaran terkirim</p>
    <h1>Menunggu verifikasi</h1>
    <p class="masuk-keterangan">Nama Anda sudah masuk antrean dan tampil di
      papan monitoring dengan status belum diverifikasi. Setelah Dr. Dara
      memverifikasi, status Anda berubah dan kode akses area bimbingan
      dikirim ke surel yang Anda daftarkan.</p>
    <p class="masuk-kaki"><a href="progres.php">&larr; Lihat papan monitoring</a></p>
<?php else: ?>
    <p class="kicker">Mahasiswa bimbingan baru</p>
    <h1>Daftar bimbingan</h1>
    <p class="masuk-keterangan">Isi formulir ini kalau Anda mahasiswa yang
      akan atau baru mulai dibimbing Dr. Dara. Pendaftaran diverifikasi
      manual, jadi pastikan nama sesuai data akademik.</p>
    <?php if ($galat): ?>
    <p class="masuk-galat" role="alert"><?= htmlspecialchars($galat, ENT_QUOTES) ?></p>
    <?php endif; ?>
    <form method="post" action="daftar.php">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <div class="jebakan" aria-hidden="true">
        <label for="situs_web">Situs web</label>
        <input id="situs_web" name="situs_web" type="text" tabindex="-1" autocomplete="off">
      </div>
      <label class="masuk-label" for="nama">Nama lengkap sesuai data akademik</label>
      <input class="masuk-input" id="nama" name="nama" type="text"
             autocomplete="name" minlength="5" maxlength="80" required autofocus>
      <label class="masuk-label" for="kelompok">Kelompok bimbingan</label>
      <select class="masuk-input" id="kelompok" name="kelompok" required>
        <option value="" disabled selected>Pilih kelompok</option>
<?php foreach ($KELOMPOK as $k): ?>
        <option><?= htmlspecialchars($k, ENT_QUOTES) ?></option>
<?php endforeach; ?>
      </select>
      <label class="masuk-label" for="kontak">Surel aktif</label>
      <input class="masuk-input" id="kontak" name="kontak" type="email"
             autocomplete="email" required>
      <button class="masuk-tombol" type="submit">Kirim pendaftaran</button>
    </form>
    <p class="masuk-kaki">Surel hanya dipakai untuk mengirim kode akses dan
      tidak ditampilkan di mana pun.</p>
<?php endif; ?>
  </section>
  <p class="masuk-pulang"><a href="progres.php">&larr; Kembali ke papan monitoring</a></p>
</main>
</body>
</html>
