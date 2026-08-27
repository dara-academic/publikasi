<?php
/* ------------------------------------------------------------------
   Halaman masuk.

   Dua wajah dalam satu halaman. Selama berkas pengguna belum ada,
   halaman ini menawarkan pembuatan akun admin pertama, dan hanya mau
   melakukannya kalau kode setup yang diunggah manual ke server ikut
   dimasukkan. Dengan begitu tidak pernah ada kata sandi bawaan yang
   bisa lupa diganti. Sesudah akun admin lahir, wajah setup hilang
   selamanya dan yang tersisa formulir masuk biasa.
   ------------------------------------------------------------------ */
require __DIR__ . '/sesi.php';
mulai_sesi();

if (($p = pengguna_sekarang()) !== null) {
    header('Location: ' . ($p['peran'] === 'admin' ? '/akun.php' : '/bimbingan/rekap.php'));
    exit;
}

$mode_setup = !ada_pengguna() && is_file(BERKAS_SETUP);
$galat = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '?';
    if (!csrf_sah()) {
        $galat = 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } elseif (!boleh_mencoba($ip)) {
        $galat = 'Terlalu banyak percobaan. Tunggu 15 menit lalu coba lagi.';
    } elseif ($mode_setup && isset($_POST['kode_setup'])) {
        $kode_benar = trim((string) file_get_contents(BERKAS_SETUP));
        $kode  = trim((string) ($_POST['kode_setup'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $nama  = trim((string) ($_POST['nama'] ?? ''));
        $sandi = (string) ($_POST['sandi'] ?? '');
        if (!hash_equals($kode_benar, $kode)) {
            catat_gagal($ip);
            $galat = 'Kode setup tidak cocok.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $galat = 'Alamat surel tidak sah.';
        } elseif (strlen($sandi) < 10) {
            $galat = 'Kata sandi minimal 10 karakter.';
        } elseif ($nama === '') {
            $galat = 'Nama wajib diisi.';
        } else {
            tambah_pengguna([
                'email'  => $email,
                'nama'   => $nama,
                'peran'  => 'admin',
                'sandi'  => password_hash($sandi, PASSWORD_DEFAULT),
                'wajib_ganti' => false,
                'dibuat' => date('Y-m-d'),
            ]);
            rename(BERKAS_SETUP, BERKAS_SETUP . '.terpakai');
            session_regenerate_id(true);
            $_SESSION['pengguna'] = ['email' => $email, 'nama' => $nama, 'peran' => 'admin'];
            header('Location: /bimbingan/rekap.php');
            exit;
        }
    } else {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $sandi = (string) ($_POST['sandi'] ?? '');
        $cocok = null;
        foreach (muat_pengguna() as $p) {
            if (hash_equals($p['email'], $email)) { $cocok = $p; break; }
        }
        if ($cocok !== null && password_verify($sandi, $cocok['sandi'])) {
            session_regenerate_id(true);
            $_SESSION['pengguna'] = [
                'email'       => $cocok['email'],
                'nama'        => $cocok['nama'],
                'peran'       => $cocok['peran'],
                'wajib_ganti' => !empty($cocok['wajib_ganti']),
            ];
            header('Location: ' . (!empty($cocok['wajib_ganti']) ? '/ganti-sandi.php'
                : ($cocok['peran'] === 'admin' ? '/akun.php' : '/bimbingan/rekap.php')));
            exit;
        }
        catat_gagal($ip);
        $galat = 'Surel atau kata sandi tidak cocok.';
    }
}

$csrf = htmlspecialchars(token_csrf(), ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Masuk, Portal Dr. Despinur Dara</title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Source+Serif+4:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="ak halaman-masuk" data-grup="mengajar">
<header class="ak-bar">
  <div class="ak-bar-isi">
    <a class="ak-nama" href="index.html">Despinur Dara</a>
  </div>
</header>

<main class="masuk-panggung">
  <section class="masuk-kartu" aria-labelledby="judul-masuk">
    <p class="kicker"><?= $mode_setup ? 'Persiapan pertama' : 'Area bimbingan' ?></p>
    <h1 id="judul-masuk"><?= $mode_setup ? 'Buat akun admin' : 'Masuk' ?></h1>
    <p class="masuk-keterangan">
      <?= $mode_setup
          ? 'Berkas pengguna belum ada di server. Masukkan kode setup yang Anda unggah untuk membuat akun admin pertama.'
          : 'Rincian bimbingan hanya terbuka untuk mahasiswa bimbingan dan dosen.' ?>
    </p>

    <?php if ($galat): ?>
    <p class="masuk-galat" role="alert"><?= htmlspecialchars($galat, ENT_QUOTES) ?></p>
    <?php endif; ?>

    <form method="post" action="masuk.php">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <?php if ($mode_setup): ?>
      <label class="masuk-label" for="kode_setup">Kode setup</label>
      <input class="masuk-input" id="kode_setup" name="kode_setup" type="text"
             autocomplete="off" required>
      <label class="masuk-label" for="nama">Nama lengkap</label>
      <input class="masuk-input" id="nama" name="nama" type="text"
             autocomplete="name" required>
      <?php endif; ?>
      <label class="masuk-label" for="email"><?= $mode_setup ? 'Alamat surel' : 'Surel atau nama pengguna' ?></label>
      <input class="masuk-input" id="email" name="email" type="text"
             autocomplete="username" required autofocus>
      <label class="masuk-label" for="sandi">Kata sandi</label>
      <div class="masuk-sandi">
        <input class="masuk-input" id="sandi" name="sandi"
               type="password" autocomplete="<?= $mode_setup ? 'new-password' : 'current-password' ?>"
               <?= $mode_setup ? 'minlength="10"' : '' ?> required>
        <button type="button" class="masuk-intip" aria-label="Tampilkan kata sandi"
                onclick="var s=document.getElementById('sandi');s.type=s.type==='password'?'text':'password';this.textContent=s.type==='password'?'Lihat':'Tutup'">Lihat</button>
      </div>
      <button class="masuk-tombol" type="submit">
        <?= $mode_setup ? 'Buat akun dan masuk' : 'Masuk' ?>
      </button>
    </form>

<?php if (!$mode_setup): ?>
    <div class="masuk-daftar">
      <p>Mahasiswa bimbingan baru dan belum punya akun?</p>
      <a class="masuk-daftar-tombol" href="bimbingan/daftar.php">Daftar sebagai mahasiswa</a>
    </div>
<?php else: ?>
    <p class="masuk-kaki">Kode setup hanya berlaku sekali. Sesudah akun admin
      dibuat, formulir ini tidak akan muncul lagi.</p>
<?php endif; ?>
  </section>
  <p class="masuk-pulang"><a href="bimbingan/index.html">&larr; Kembali ke halaman Bimbingan</a></p>
</main>
</body>
</html>
