<?php
/* ------------------------------------------------------------------
   Panel buku. Hanya admin.

   Setiap kali Dara menerbitkan buku baru, entrinya ditambahkan di sini:
   judul, penulis, penerbit, tahun, deskripsi singkat, tautan opsional ke
   tempat memperoleh buku, dan sampul opsional. Entri tampil sebagai kartu
   di halaman publik buku.php.

   Keamanan sama seperti bagian lain: hanya admin, CSRF wajib, sampul hanya
   gambar (cek MIME), ukuran dibatasi, nama berkas dibuat ulang.
   ------------------------------------------------------------------ */
require __DIR__ . '/sesi.php';
$pengguna = wajib_masuk_segar();
if ($pengguna['peran'] !== 'admin') {
    header('Location: /bimbingan/rekap.php');
    exit;
}

const MAKS_SAMPUL = 5242880; /* 5 MB */
const DIR_UNGGAH  = __DIR__ . '/unggahan';
const EXT_GAMBAR  = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

function ee($s): string { return htmlspecialchars((string) $s, ENT_QUOTES); }
function slugkan(string $s): string {
    $t = strtolower(trim($s));
    $t = preg_replace('/[^a-z0-9]+/', '-', $t);
    return trim((string) $t, '-');
}

$pesan = $_SESSION['pesan_buku'] ?? '';
unset($_SESSION['pesan_buku']);
$galat = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_sah()) {
        $galat = 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } else {
        $aksi = (string) ($_POST['aksi'] ?? '');

        if ($aksi === 'hapus') {
            $baris = hapus_buku((int) ($_POST['id'] ?? -1));
            if ($baris !== null && !empty($baris['sampul'])) {
                $path = DIR_UNGGAH . '/buku/' . basename((string) $baris['sampul']);
                if (is_file($path)) @unlink($path);
            }
            $_SESSION['pesan_buku'] = 'Buku dihapus.';
            header('Location: /admin-buku.php');
            exit;
        }

        if ($aksi === 'tambah') {
            $judul    = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['judul'] ?? ''))));
            $penulis  = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['penulis'] ?? ''))));
            $penerbit = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['penerbit'] ?? ''))));
            $tahun    = (string) ($_POST['tahun'] ?? '');
            $deskripsi= trim(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['deskripsi'] ?? ''))));
            $tautan   = trim((string) ($_POST['tautan'] ?? ''));

            if ($judul === '') {
                $galat = 'Judul buku wajib diisi.';
            } elseif (!preg_match('/^(19|20)\d{2}$/', $tahun)) {
                $galat = 'Tahun terbit tidak sah.';
            } elseif ($tautan !== '' && (!filter_var($tautan, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $tautan))) {
                $galat = 'Tautan harus berupa alamat web yang sah, atau dikosongkan.';
            } else {
                $sampul = '';
                $f = $_FILES['sampul'] ?? null;
                $ada = $f && is_array($f) && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
                if ($ada && ($f['error'] ?? 0) !== UPLOAD_ERR_OK) {
                    $galat = 'Sampul gagal diunggah. Coba berkas lain atau kosongkan.';
                } elseif ($ada) {
                    $ext  = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
                    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
                    if (($f['size'] ?? 0) > MAKS_SAMPUL) {
                        $galat = 'Ukuran sampul maksimal 5 MB.';
                    } elseif (!isset(EXT_GAMBAR[$ext]) || EXT_GAMBAR[$ext] !== $mime || !is_uploaded_file($f['tmp_name'])) {
                        $galat = 'Sampul harus berupa gambar JPG, PNG, atau WEBP.';
                    } else {
                        $dir = DIR_UNGGAH . '/buku';
                        if (!is_dir($dir)) @mkdir($dir, 0755, true);
                        if (!is_dir($dir) || !is_writable($dir)) {
                            $galat = 'Folder unggahan belum bisa ditulis. Cek izin folder unggahan.';
                        } else {
                            $nama = (slugkan($judul) ?: 'buku') . '-' . date('Ymd-His') . '.' . $ext;
                            if (@move_uploaded_file($f['tmp_name'], $dir . '/' . $nama)) {
                                @chmod($dir . '/' . $nama, 0644);
                                $sampul = $nama;
                            } else {
                                $galat = 'Gagal menyimpan sampul di server.';
                            }
                        }
                    }
                }

                if ($galat === '') {
                    tambah_buku([
                        'judul'     => $judul,
                        'penulis'   => $penulis,
                        'penerbit'  => $penerbit,
                        'tahun'     => $tahun,
                        'deskripsi' => $deskripsi,
                        'tautan'    => $tautan,
                        'sampul'    => $sampul,
                        'tanggal'   => date('Y-m-d'),
                        'oleh'      => $pengguna['nama'],
                    ]);
                    $_SESSION['pesan_buku'] = 'Buku "' . $judul . '" ditambahkan.';
                    header('Location: /admin-buku.php');
                    exit;
                }
            }
        }
    }
}

$csrf = ee(token_csrf());
$buku = array_reverse(muat_buku());
$jml  = count($buku);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Kelola Buku, Portal Dr. Despinur Dara</title>
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
      &middot; <a href="akun.php">Panel akun</a>
      &middot; <a href="buku.php">Lihat publik</a>
      &middot; <a href="keluar.php">Keluar</a></span>
  </div>
</header>

<div class="admin-band">
  <div class="admin-band-isi">
    <p class="admin-lencana">Panel admin</p>
    <h1>Kelola buku</h1>
    <p class="admin-band-lead">Tambahkan buku Dara yang sudah terbit. Entri langsung
    tampil di halaman <a href="buku.php">Buku terbit</a>. Saat ini ada <?= $jml ?> buku.</p>
  </div>
</div>

<nav class="admin-menu" aria-label="Menu panel admin">
  <a href="admin.php">&larr; Panel admin</a>
  <a href="akun.php">Bimbingan &amp; akun</a>
  <a href="admin-materi.php">Materi kuliah</a>
  <a href="admin-bedah.php">Bedah paper</a>
  <a href="admin-buku.php" class="active">Buku</a>
</nav>

<main class="ak-utama admin-utama" id="konten">

  <?php if ($pesan): ?>
    <p class="admin-pesan" role="status"><?= ee($pesan) ?></p>
  <?php endif; ?>
  <?php if ($galat): ?>
    <p class="masuk-galat" role="alert"><?= ee($galat) ?></p>
  <?php endif; ?>

  <section class="admin-kartu">
    <h2>Tambah buku terbit</h2>
    <form method="post" action="admin-buku.php" enctype="multipart/form-data" class="materi-form">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="aksi" value="tambah">

      <label class="masuk-label" for="judul">Judul buku</label>
      <input class="masuk-input" id="judul" name="judul" type="text" maxlength="220" required>

      <label class="masuk-label" for="penulis">Penulis (opsional)</label>
      <input class="masuk-input" id="penulis" name="penulis" type="text" maxlength="200"
             placeholder="Misalnya: Dr. Despinur Dara dan Dr. Donny Maha Putra">

      <div class="materi-form-baris">
        <div>
          <label class="masuk-label" for="penerbit">Penerbit (opsional)</label>
          <input class="masuk-input" id="penerbit" name="penerbit" type="text" maxlength="120">
        </div>
        <div>
          <label class="masuk-label" for="tahun">Tahun terbit</label>
          <input class="masuk-input" id="tahun" name="tahun" type="text" inputmode="numeric" pattern="(19|20)\d{2}" placeholder="2026" required>
        </div>
      </div>

      <label class="masuk-label" for="deskripsi">Deskripsi singkat (opsional)</label>
      <input class="masuk-input" id="deskripsi" name="deskripsi" type="text" maxlength="240"
             placeholder="Satu kalimat tentang isi buku">

      <label class="masuk-label" for="tautan">Tautan ke buku (opsional, misalnya toko atau penerbit)</label>
      <input class="masuk-input" id="tautan" name="tautan" type="url" placeholder="https://...">

      <label class="masuk-label" for="sampul">Sampul buku (opsional, JPG/PNG/WEBP maks. 5 MB)</label>
      <input class="masuk-input" id="sampul" name="sampul" type="file" accept="image/jpeg,image/png,image/webp">

      <button class="masuk-tombol" type="submit">Tambah buku</button>
    </form>
  </section>

  <section class="admin-kartu">
    <h2>Buku terdaftar</h2>
    <?php if ($jml === 0): ?>
      <p class="admin-kosong">Belum ada buku yang ditambahkan.</p>
    <?php else: ?>
      <ul class="materi-kelola">
        <?php foreach ($buku as $b): ?>
          <li>
            <div class="materi-kelola-teks">
              <b><?= ee($b['judul']) ?></b>
              <?php if ($b['penulis']): ?><span><?= ee($b['penulis']) ?></span><?php endif; ?>
              <span class="materi-kelola-meta"><?= $b['penerbit'] ? ee($b['penerbit']) . ' &middot; ' : '' ?><?= ee($b['tahun']) ?>
                <?= $b['sampul'] ? ' &middot; ada sampul' : ' &middot; tanpa sampul' ?>
                <?= $b['tautan'] ? ' &middot; <a href="' . ee($b['tautan']) . '" target="_blank" rel="noopener noreferrer">tautan</a>' : '' ?></span>
            </div>
            <form method="post" action="admin-buku.php" onsubmit="return confirm('Hapus buku ini?');">
              <input type="hidden" name="csrf" value="<?= $csrf ?>">
              <input type="hidden" name="aksi" value="hapus">
              <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
              <button class="materi-hapus" type="submit">Hapus</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <p class="admin-pulang"><a href="admin.php">&larr; Kembali ke panel admin</a></p>
</main>
</body>
</html>
