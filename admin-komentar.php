<?php
/* ------------------------------------------------------------------
   Moderasi tanya jawab. Hanya admin.

   Pertanyaan yang masuk dari halaman publik berstatus pending. Di sini
   admin menyetujui (tampil ke publik), membalas (sekaligus menyetujui),
   atau menghapus. Balasan tampil sebagai jawaban Dr. Dara di bawah
   pertanyaan.
   ------------------------------------------------------------------ */
require __DIR__ . '/sesi.php';
$pengguna = wajib_masuk_segar();
if ($pengguna['peran'] !== 'admin') {
    header('Location: /bimbingan/rekap.php');
    exit;
}

function ee($s): string { return htmlspecialchars((string) $s, ENT_QUOTES); }

$pesan = $_SESSION['pesan_komentar'] ?? '';
unset($_SESSION['pesan_komentar']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_sah()) {
    $aksi = (string) ($_POST['aksi'] ?? '');
    $no   = (int) ($_POST['no'] ?? -1);
    if ($aksi === 'setujui') { setujui_komentar($no); $_SESSION['pesan_komentar'] = 'Pertanyaan disetujui dan tampil ke publik.'; }
    elseif ($aksi === 'hapus') { hapus_komentar($no); $_SESSION['pesan_komentar'] = 'Pertanyaan dihapus.'; }
    elseif ($aksi === 'balas') {
        $teks = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['balasan'] ?? ''))));
        if ($teks !== '') { balas_komentar($no, mb_substr($teks, 0, 1500)); $_SESSION['pesan_komentar'] = 'Balasan disimpan dan pertanyaan tampil ke publik.'; }
        else { $_SESSION['pesan_komentar'] = 'Balasan kosong, tidak disimpan.'; }
    }
    header('Location: /admin-komentar.php');
    exit;
}

$csrf   = ee(token_csrf());
$semua  = komentar_semua();
$pending = array_values(array_filter($semua, fn($k) => ($k['status'] ?? '') !== 'setuju'));
$setuju  = array_reverse(array_values(array_filter($semua, fn($k) => ($k['status'] ?? '') === 'setuju')));

function kartu_komentar(array $k, string $csrf, bool $is_pending): string {
    $bal = trim((string) ($k['balasan'] ?? ''));
    $h  = '<div class="mod-item">';
    $h .= '<p class="mod-meta"><b>' . ee($k['nama']) . '</b><span>' . ee($k['waktu']) . '</span><a href="' . ee($k['hal']) . '" target="_blank" rel="noopener">' . ee($k['hal']) . '</a></p>';
    $h .= '<p class="mod-isi">' . ee($k['isi']) . '</p>';
    if ($bal !== '') $h .= '<div class="komentar-balas"><b>Balasan Dr. Dara</b><p>' . ee($bal) . '</p></div>';
    $h .= '<form method="post" action="admin-komentar.php" class="mod-balas-form">';
    $h .= '<input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="no" value="' . (int) $k['no'] . '">';
    $h .= '<textarea name="balasan" class="masuk-input" rows="2" placeholder="Tulis balasan (opsional), lalu Balas">' . ee($bal) . '</textarea>';
    $h .= '<div class="mod-aksi">';
    $h .= '<button class="masuk-tombol" type="submit" formaction="admin-komentar.php" name="aksi" value="balas">Balas &amp; tampilkan</button>';
    if ($is_pending) $h .= '<button class="mod-setuju" type="submit" name="aksi" value="setujui">Setujui saja</button>';
    $h .= '<button class="materi-hapus" type="submit" name="aksi" value="hapus" onclick="return confirm(\'Hapus pertanyaan ini?\');">Hapus</button>';
    $h .= '</div></form></div>';
    return $h;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Moderasi Tanya Jawab, Portal Dr. Despinur Dara</title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&family=Source+Serif+4:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="ak" data-grup="mengajar">
<header class="ak-bar">
  <div class="ak-bar-isi">
    <a class="ak-nama" href="index.html">Belajar Bersama Dara</a>
    <span class="rekap-siapa"><b><?= ee($pengguna['nama']) ?></b>
      &middot; <a href="admin.php">Panel admin</a>
      &middot; <a href="keluar.php">Keluar</a></span>
  </div>
</header>

<div class="admin-band">
  <div class="admin-band-isi">
    <p class="admin-lencana">Panel admin</p>
    <h1>Moderasi tanya jawab</h1>
    <p class="admin-band-lead">Setujui, balas, atau hapus pertanyaan dari pengunjung.
    Ada <?= count($pending) ?> menunggu ditinjau.</p>
  </div>
</div>

<nav class="admin-menu" aria-label="Menu panel admin">
  <a href="admin.php">&larr; Panel admin</a>
  <a href="akun.php">Bimbingan &amp; akun</a>
  <a href="admin-materi.php">Materi kuliah</a>
  <a href="admin-bedah.php">Bedah paper</a>
  <a href="admin-buku.php">Buku</a>
  <a href="admin-statistik.php">Statistik</a>
  <a href="admin-komentar.php" class="active">Tanya jawab</a>
  <a href="admin-cadangan.php">Cadangan</a>
</nav>

<main class="ak-utama admin-utama" id="konten">

  <?php if ($pesan): ?><p class="admin-pesan" role="status"><?= ee($pesan) ?></p><?php endif; ?>

  <section class="admin-kartu">
    <h2>Menunggu ditinjau <span class="mod-jml"><?= count($pending) ?></span></h2>
    <?php if (!$pending): ?>
      <p class="admin-kosong">Tidak ada pertanyaan baru.</p>
    <?php else: foreach ($pending as $k) echo kartu_komentar($k, $csrf, true); endif; ?>
  </section>

  <section class="admin-kartu">
    <h2>Sudah tampil <span class="mod-jml"><?= count($setuju) ?></span></h2>
    <?php if (!$setuju): ?>
      <p class="admin-kosong">Belum ada yang disetujui.</p>
    <?php else: foreach ($setuju as $k) echo kartu_komentar($k, $csrf, false); endif; ?>
  </section>

  <p class="admin-pulang"><a href="admin.php">&larr; Kembali ke panel admin</a></p>
</main>
</body>
</html>
