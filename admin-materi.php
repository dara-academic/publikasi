<?php
/* ------------------------------------------------------------------
   Panel unggah materi kuliah. Hanya admin.

   Dosen mengunggah berkas PDF materi lewat formulir ini. Berkasnya
   disimpan di folder unggahan yang bisa diakses web (bukan folder data
   yang diblokir), dan rujukannya dicatat di manifes materi.json. Halaman
   publik materi.php membaca manifes itu lalu menampilkannya sebagai
   daftar unduhan per mata kuliah.

   Keamanan: hanya admin, token CSRF wajib, hanya PDF (dicek ekstensi dan
   tipe MIME sekaligus), batas ukuran, nama berkas dibuat ulang dari judul
   supaya tidak ada nama berbahaya, dan folder unggahan dilarang
   mengeksekusi skrip lewat .htaccess.
   ------------------------------------------------------------------ */
require __DIR__ . '/sesi.php';
$pengguna = wajib_masuk_segar();
if ($pengguna['peran'] !== 'admin') {
    header('Location: /bimbingan/rekap.php');
    exit;
}

$MK = mk_nama();   /* mata kuliah dasar + tambahan dari admin */
const MAKS_UKURAN = 31457280; /* 30 MB */
const MAKS_SAMPUL = 5242880;  /* 5 MB */
const DIR_UNGGAH  = __DIR__ . '/unggahan';
const SAMPUL_MIME = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

function ee($s): string { return htmlspecialchars((string) $s, ENT_QUOTES); }
function slugkan(string $s): string {
    $t = strtolower(trim($s));
    $t = preg_replace('/[^a-z0-9]+/', '-', $t);
    return trim((string) $t, '-');
}
function ukuran_manusia(int $b): string {
    if ($b >= 1048576) return round($b / 1048576, 1) . ' MB';
    if ($b >= 1024)    return round($b / 1024) . ' KB';
    return $b . ' B';
}

/* Kecilkan gambar sampul jadi WebP (lebar maksimal 640) supaya ringan.
   Mengembalikan nama berkas hasil, atau '' bila GD tidak tersedia / gagal,
   sehingga pemanggil bisa jatuh balik menyimpan gambar aslinya. */
function sampul_ke_webp(string $src, string $mime, string $dir, string $dasar): string {
    if (!function_exists('imagewebp')) return '';
    $img = null;
    if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) $img = @imagecreatefromjpeg($src);
    elseif ($mime === 'image/png' && function_exists('imagecreatefrompng'))  $img = @imagecreatefrompng($src);
    elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) $img = @imagecreatefromwebp($src);
    if (!$img) return '';

    $w = imagesx($img); $h = imagesy($img);
    $maks = 640;
    if ($w > $maks && $h > 0) {
        $nh = (int) max(1, round($h * $maks / $w));
        $kecil = imagecreatetruecolor($maks, $nh);
        imagealphablending($kecil, false);
        imagesavealpha($kecil, true);
        imagecopyresampled($kecil, $img, 0, 0, 0, 0, $maks, $nh, $w, $h);
        imagedestroy($img);
        $img = $kecil;
    }
    $nama = $dasar . '-' . date('Ymd-His') . '-sampul.webp';
    $ok = @imagewebp($img, $dir . '/' . $nama, 82);
    imagedestroy($img);
    return $ok ? $nama : '';
}

$pesan = $_SESSION['pesan_materi'] ?? '';
unset($_SESSION['pesan_materi']);
$galat = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_sah()) {
        $galat = 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } else {
        $aksi = (string) ($_POST['aksi'] ?? '');

        if ($aksi === 'tambah_mk') {
            $nama_mk = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['nama_mk'] ?? ''))));
            if ($nama_mk === '' || mb_strlen($nama_mk) > 100) {
                $galat = 'Nama mata kuliah wajib diisi, maksimal 100 huruf.';
            } elseif (slug_mk($nama_mk) === '') {
                $galat = 'Nama mata kuliah tidak sah, pakai huruf atau angka.';
            } elseif (array_key_exists(slug_mk($nama_mk), mk_nama())) {
                $galat = 'Mata kuliah dengan nama itu sudah ada.';
            } else {
                tambah_matkul($nama_mk);
                $_SESSION['pesan_materi'] = 'Mata kuliah "' . $nama_mk . '" ditambahkan. Sekarang bisa diisi materi.';
                header('Location: /admin-materi.php');
                exit;
            }
        }

        if ($aksi === 'hapus_mk') {
            $slug_hapus = (string) ($_POST['slug_mk'] ?? '');
            $punya = false;
            foreach (muat_materi() as $mm) { if (($mm['mk'] ?? '') === $slug_hapus) { $punya = true; break; } }
            if (mk_statis($slug_hapus)) {
                $galat = 'Mata kuliah bawaan tidak bisa dihapus.';
            } elseif ($punya) {
                $galat = 'Masih ada materi di mata kuliah itu. Hapus materinya dulu.';
            } else {
                hapus_matkul($slug_hapus);
                $_SESSION['pesan_materi'] = 'Mata kuliah dihapus.';
                header('Location: /admin-materi.php');
                exit;
            }
        }

        if ($aksi === 'hapus') {
            $baris = hapus_materi((int) ($_POST['id'] ?? -1));
            if ($baris !== null && !empty($baris['berkas']) && array_key_exists($baris['mk'], $MK)) {
                $path = DIR_UNGGAH . '/' . $baris['mk'] . '/' . basename((string) $baris['berkas']);
                if (is_file($path)) @unlink($path);
                if (!empty($baris['sampul'])) {
                    $ps = DIR_UNGGAH . '/' . $baris['mk'] . '/' . basename((string) $baris['sampul']);
                    if (is_file($ps)) @unlink($ps);
                }
            }
            $_SESSION['pesan_materi'] = 'Materi dihapus.';
            header('Location: /admin-materi.php');
            exit;
        }

        if ($aksi === 'unggah') {
            $mk        = (string) ($_POST['mk'] ?? '');
            $judul     = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['judul'] ?? ''))));
            $deskripsi = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['deskripsi'] ?? ''))));
            $semester  = (string) ($_POST['semester'] ?? SEM_INI);
            if (!in_array($semester, semester_pilihan(), true)) $semester = SEM_INI;
            $pertemuan = (int) ($_POST['pertemuan'] ?? 0);
            if ($pertemuan < 0 || $pertemuan > 40) $pertemuan = 0;
            $f         = $_FILES['berkas'] ?? null;

            if (!array_key_exists($mk, $MK)) {
                $galat = 'Mata kuliah tidak sah.';
            } elseif ($judul === '') {
                $galat = 'Judul materi wajib diisi.';
            } elseif (mb_strlen($judul) > 140) {
                $galat = 'Judul terlalu panjang.';
            } elseif ($f === null || !is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $galat = ($f && ($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE)
                    ? 'Berkas melebihi batas server. Pecah atau kecilkan PDF-nya.'
                    : 'Berkas gagal diunggah. Pastikan Anda memilih satu berkas PDF.';
            } elseif (($f['size'] ?? 0) <= 0 || $f['size'] > MAKS_UKURAN) {
                $galat = 'Ukuran berkas harus di atas 0 dan maksimal 30 MB.';
            } elseif (!is_uploaded_file($f['tmp_name'])) {
                $galat = 'Berkas tidak sah.';
            } else {
                $ext  = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
                if ($ext !== 'pdf' || $mime !== 'application/pdf') {
                    $galat = 'Hanya berkas PDF yang diterima.';
                } else {
                    $dir = DIR_UNGGAH . '/' . $mk;
                    if (!is_dir($dir)) @mkdir($dir, 0755, true);
                    if (!is_dir($dir) || !is_writable($dir)) {
                        $galat = 'Folder unggahan belum bisa ditulis di server. Cek izin folder unggahan.';
                    } else {
                        $dasar  = slugkan($judul);
                        if ($dasar === '') $dasar = 'materi';
                        $berkas = $dasar . '-' . date('Ymd-His') . '.pdf';
                        $tujuan = $dir . '/' . $berkas;
                        if (@move_uploaded_file($f['tmp_name'], $tujuan)) {
                            @chmod($tujuan, 0644);

                            /* Gambar sampul opsional: gambar slide halaman 1. */
                            $sampul = '';
                            $cf = $_FILES['sampul'] ?? null;
                            if ($cf && is_array($cf)
                                && ($cf['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
                                && is_uploaded_file($cf['tmp_name'])
                                && ($cf['size'] ?? 0) > 0 && $cf['size'] <= MAKS_SAMPUL) {
                                $cmime = (new finfo(FILEINFO_MIME_TYPE))->file($cf['tmp_name']);
                                if (isset(SAMPUL_MIME[$cmime])) {
                                    /* Coba kecilkan jadi WebP dulu supaya ringan; kalau GD tak
                                       tersedia, simpan gambar aslinya apa adanya. */
                                    $sampul = sampul_ke_webp($cf['tmp_name'], $cmime, $dir, $dasar);
                                    if ($sampul !== '') {
                                        @chmod($dir . '/' . $sampul, 0644);
                                    } else {
                                        $cnama = $dasar . '-' . date('Ymd-His') . '-sampul.' . SAMPUL_MIME[$cmime];
                                        if (@move_uploaded_file($cf['tmp_name'], $dir . '/' . $cnama)) {
                                            @chmod($dir . '/' . $cnama, 0644);
                                            $sampul = $cnama;
                                        }
                                    }
                                }
                            }

                            tambah_materi([
                                'mk'        => $mk,
                                'judul'     => $judul,
                                'deskripsi' => $deskripsi,
                                'semester'  => $semester,
                                'pertemuan' => $pertemuan,
                                'berkas'    => $berkas,
                                'sampul'    => $sampul,
                                'ukuran'    => (int) filesize($tujuan),
                                'tanggal'   => date('Y-m-d'),
                                'oleh'      => $pengguna['nama'],
                            ]);
                            $_SESSION['pesan_materi'] = 'Materi "' . $judul . '" berhasil diunggah.';
                            header('Location: /admin-materi.php');
                            exit;
                        }
                        $galat = 'Gagal menyimpan berkas di server.';
                    }
                }
            }
        }
    }
}

$csrf   = ee(token_csrf());
$materi = muat_materi();
/* kelompokkan per mata kuliah, urut terbaru dulu */
$per_mk = [];
foreach (array_reverse($materi) as $m) {
    $per_mk[$m['mk']][] = $m;
}
$jml = count($materi);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Kelola Materi, Portal Dr. Despinur Dara</title>
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
      &middot; <a href="materi.php">Lihat publik</a>
      &middot; <a href="keluar.php">Keluar</a></span>
  </div>
</header>

<div class="admin-band">
  <div class="admin-band-isi">
    <p class="admin-lencana">Panel admin</p>
    <h1>Kelola materi kuliah</h1>
    <p class="admin-band-lead">Unggah berkas PDF materi. Begitu tersimpan, materi
    langsung muncul di halaman <a href="materi.php">Materi kuliah</a> untuk diunduh
    mahasiswa. Saat ini ada <?= $jml ?> materi terunggah.</p>
  </div>
</div>

<main class="ak-utama admin-utama" id="konten">
  <nav class="admin-menu" aria-label="Menu panel admin">
    <a href="admin.php">&larr; Panel admin</a>
    <a href="akun.php">Bimbingan &amp; akun</a>
    <a href="admin-materi.php" class="active">Materi kuliah</a>
    <a href="admin-bedah.php">Bedah paper</a>
    <a href="admin-buku.php">Buku</a>
    <a href="admin-statistik.php">Statistik</a>
  <a href="admin-komentar.php">Tanya jawab</a>
  <a href="admin-cadangan.php">Cadangan</a>
  </nav>

  <?php if ($pesan): ?>
    <p class="admin-pesan" role="status"><?= ee($pesan) ?></p>
  <?php endif; ?>
  <?php if ($galat): ?>
    <p class="masuk-galat" role="alert"><?= ee($galat) ?></p>
  <?php endif; ?>

  <section class="admin-kartu">
    <h2>Mata kuliah</h2>
    <p class="admin-sub">Tujuh mata kuliah bawaan sudah punya halaman lengkap. Tambah mata kuliah baru di sini kalau ada yang di luar itu, lalu isi materinya lewat form di bawah.</p>
    <form method="post" action="admin-materi.php" class="mk-tambah-form">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="aksi" value="tambah_mk">
      <label class="masuk-label" for="nama_mk">Nama mata kuliah baru</label>
      <div class="mk-tambah-baris">
        <input class="masuk-input" id="nama_mk" name="nama_mk" type="text" maxlength="100" placeholder="Misalnya: Manajemen Talenta" required>
        <button class="masuk-tombol" type="submit">Tambah</button>
      </div>
    </form>
    <?php $mk_tambahan = muat_matkul(); ?>
    <?php if ($mk_tambahan): ?>
      <ul class="mk-daftar">
        <?php foreach ($mk_tambahan as $mt): $s = (string) ($mt['slug'] ?? ''); ?>
          <li>
            <span><b><?= ee($mt['nama'] ?? $s) ?></b> &middot; <a href="mata-kuliah.php?mk=<?= ee($s) ?>" target="_blank" rel="noopener">lihat halaman</a></span>
            <form method="post" action="admin-materi.php" onsubmit="return confirm('Hapus mata kuliah ini? Hanya bisa kalau materinya sudah kosong.');">
              <input type="hidden" name="csrf" value="<?= $csrf ?>">
              <input type="hidden" name="aksi" value="hapus_mk">
              <input type="hidden" name="slug_mk" value="<?= ee($s) ?>">
              <button type="submit" class="mk-hapus">Hapus</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="admin-kosong">Belum ada mata kuliah tambahan. Yang ada baru tujuh mata kuliah bawaan.</p>
    <?php endif; ?>
  </section>

  <section class="admin-kartu">
    <h2>Unggah materi baru</h2>
    <form method="post" action="admin-materi.php" enctype="multipart/form-data" class="materi-form">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="aksi" value="unggah">

      <label class="masuk-label" for="mk">Mata kuliah</label>
      <select class="masuk-input" id="mk" name="mk" required>
        <?php foreach ($MK as $slug => $nama): ?>
          <option value="<?= ee($slug) ?>"><?= ee($nama) ?></option>
        <?php endforeach; ?>
      </select>

      <div class="materi-form-baris">
        <div>
          <label class="masuk-label" for="semester">Semester</label>
          <select class="masuk-input" id="semester" name="semester" required>
            <?php foreach (semester_pilihan() as $s): ?>
              <option value="<?= ee($s) ?>"><?= ee($s . ' — ' . label_semester($s)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="masuk-label" for="pertemuan">Pertemuan ke-</label>
          <input class="masuk-input" id="pertemuan" name="pertemuan" type="number" min="1" max="16" step="1" placeholder="Misal: 1" required>
        </div>
      </div>

      <label class="masuk-label" for="judul">Judul materi</label>
      <input class="masuk-input" id="judul" name="judul" type="text" maxlength="140"
             placeholder="Misalnya: Pertemuan 8 - Kompensasi Berbasis Kinerja" required>

      <label class="masuk-label" for="deskripsi">Keterangan singkat (opsional)</label>
      <input class="masuk-input" id="deskripsi" name="deskripsi" type="text" maxlength="200"
             placeholder="Ringkasan isi materi, boleh dikosongkan">

      <label class="masuk-label" for="berkas">Berkas PDF (maks. 30 MB)</label>
      <input class="masuk-input" id="berkas" name="berkas" type="file" accept="application/pdf,.pdf" required>

      <label class="masuk-label" for="sampul">Gambar sampul (opsional, maks. 5 MB)</label>
      <input class="masuk-input" id="sampul" name="sampul" type="file" accept="image/jpeg,image/png,image/webp">
      <p class="masuk-bantu">Untuk sekarang pakai gambar slide halaman 1. JPG, PNG, atau WebP. Kalau dikosongkan, dipakai ikon dokumen.</p>

      <button class="masuk-tombol" type="submit">Unggah materi</button>
    </form>
  </section>

  <section class="admin-kartu">
    <h2>Materi terunggah</h2>
    <?php if ($jml === 0): ?>
      <p class="admin-kosong">Belum ada materi yang diunggah.</p>
    <?php else: ?>
      <?php foreach ($MK as $slug => $nama): ?>
        <?php if (empty($per_mk[$slug])) continue; ?>
        <h3 class="materi-mk-judul"><?= ee($nama) ?> <span>(<?= count($per_mk[$slug]) ?>)</span></h3>
        <ul class="materi-kelola">
          <?php foreach ($per_mk[$slug] as $m): ?>
            <li>
              <div class="materi-kelola-teks">
                <b><?= ee($m['judul']) ?></b>
                <?php if (!empty($m['deskripsi'])): ?><span><?= ee($m['deskripsi']) ?></span><?php endif; ?>
                <span class="materi-kelola-meta"><?php if (!empty($m['semester'])): ?>Semester <?= ee($m['semester']) ?> &middot; P<?= (int) ($m['pertemuan'] ?? 0) ?> &middot; <?php endif; ?><?= ee($m['tanggal']) ?> &middot; <?= ukuran_manusia((int) ($m['ukuran'] ?? 0)) ?>
                  &middot; <a href="unggahan/<?= ee($m['mk']) ?>/<?= ee($m['berkas']) ?>" target="_blank" rel="noopener">buka PDF</a></span>
              </div>
              <form method="post" action="admin-materi.php" onsubmit="return confirm('Hapus materi ini beserta berkasnya?');">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                <button class="materi-hapus" type="submit">Hapus</button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <p class="admin-pulang"><a href="akun.php">&larr; Kembali ke panel akun</a></p>
</main>
</body>
</html>
