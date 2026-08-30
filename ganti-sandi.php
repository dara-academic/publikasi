<?php
/* ------------------------------------------------------------------
   Ganti kata sandi. Wajib bagi pemegang sandi bawaan, terbuka bagi
   siapa pun yang ingin mengganti. Sandi lama diminta lagi di sini
   supaya sesi yang tertinggal terbuka di komputer umum tidak bisa
   digunakan orang lain untuk membajak akun.
   ------------------------------------------------------------------ */
require __DIR__ . '/sesi.php';
$pengguna = wajib_masuk();
$galat = '';
$wajib = !empty($pengguna['wajib_ganti']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lama = (string) ($_POST['sandi_lama'] ?? '');
    $baru = (string) ($_POST['sandi_baru'] ?? '');
    $ulang = (string) ($_POST['sandi_ulang'] ?? '');
    if (!csrf_sah()) {
        $galat = 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } elseif (strlen($baru) < 10) {
        $galat = 'Kata sandi baru minimal 10 karakter.';
    } elseif ($baru !== $ulang) {
        $galat = 'Kata sandi baru dan ulangannya tidak sama.';
    } else {
        $sah = false;
        foreach (muat_pengguna() as $p) {
            if ($p['email'] === $pengguna['email']) {
                if (password_verify($lama, $p['sandi'])) {
                    perbarui_pengguna($p['email'], [
                        'sandi' => password_hash($baru, PASSWORD_DEFAULT),
                        'wajib_ganti' => false,
                    ]);
                    $_SESSION['pengguna']['wajib_ganti'] = false;
                    $sah = true;
                }
                break;
            }
        }
        if ($sah) {
            header('Location: ' . ($pengguna['peran'] === 'admin' ? '/akun.php' : '/bimbingan/rekap.php'));
            exit;
        }
        $galat = 'Kata sandi lama tidak cocok.';
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
<title>Ganti Kata Sandi, Portal Dr. Despinur Dara</title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&family=Source+Serif+4:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="ak halaman-masuk" data-grup="mengajar">
<header class="ak-bar">
  <div class="ak-bar-isi">
    <a class="ak-nama" href="index.html">Belajar Bersama Dara</a>
    <span class="rekap-siapa"><b><?= htmlspecialchars($pengguna['nama'], ENT_QUOTES) ?></b>
      &middot; <a href="keluar.php">Keluar</a></span>
  </div>
</header>
<main class="masuk-panggung">
  <section class="masuk-kartu">
    <p class="kicker">Keamanan akun</p>
    <h1>Ganti kata sandi</h1>
    <p class="masuk-keterangan">
      <?= $wajib
          ? 'Anda masih memakai kode akses awal. Ganti dengan kata sandi pilihan sendiri untuk melanjutkan.'
          : 'Masukkan kata sandi lama, lalu tentukan penggantinya. Minimal 10 karakter.' ?>
    </p>
    <?php if ($galat): ?>
    <p class="masuk-galat" role="alert"><?= htmlspecialchars($galat, ENT_QUOTES) ?></p>
    <?php endif; ?>
    <form method="post" action="ganti-sandi.php">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <label class="masuk-label" for="sandi_lama"><?= $wajib ? 'Kode akses awal' : 'Kata sandi lama' ?></label>
      <input class="masuk-input" id="sandi_lama" name="sandi_lama" type="password"
             autocomplete="current-password" required autofocus>
      <label class="masuk-label" for="sandi_baru">Kata sandi baru</label>
      <input class="masuk-input" id="sandi_baru" name="sandi_baru" type="password"
             autocomplete="new-password" minlength="10" required>
      <label class="masuk-label" for="sandi_ulang">Ulangi kata sandi baru</label>
      <input class="masuk-input" id="sandi_ulang" name="sandi_ulang" type="password"
             autocomplete="new-password" minlength="10" required>
      <button class="masuk-tombol" type="submit">Simpan kata sandi baru</button>
    </form>
  </section>
</main>
</body>
</html>
