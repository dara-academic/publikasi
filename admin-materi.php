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

const MK = [
    'pengantar-manajemen'          => 'Pengantar Manajemen',
    'pengadaan-sdm-aparatur'       => 'Pengadaan SDM Aparatur',
    'kompensasi-perlindungan-sdm'  => 'Kompensasi dan Perlindungan SDM Aparatur',
    'pelatihan-dan-pengembangan'   => 'Pelatihan dan Pengembangan',
    'manajemen-kinerja'            => 'Manajemen Kinerja',
    'simulasi-bisnis'              => 'Simulasi Bisnis',
    'management-information-system'=> 'Management Information System',
];
const MAKS_UKURAN = 31457280; /* 30 MB */
const DIR_UNGGAH  = __DIR__ . '/unggahan';

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

$pesan = $_SESSION['pesan_materi'] ?? '';
unset($_SESSION['pesan_materi']);
$galat = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_sah()) {
        $galat = 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } else {
        $aksi = (string) ($_POST['aksi'] ?? '');

        if ($aksi === 'hapus') {
            $baris = hapus_materi((int) ($_POST['id'] ?? -1));
            if ($baris !== null && !empty($baris['berkas']) && array_key_exists($baris['mk'], MK)) {
                $path = DIR_UNGGAH . '/' . $baris['mk'] . '/' . basename((string) $baris['berkas']);
                if (is_file($path)) @unlink($path);
            }
            $_SESSION['pesan_materi'] = 'Materi dihapus.';
            header('Location: /admin-materi.php');
            exit;
        }

        if ($aksi === 'unggah') {
            $mk        = (string) ($_POST['mk'] ?? '');
            $judul     = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['judul'] ?? ''))));
            $deskripsi = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['deskripsi'] ?? ''))));
            $f         = $_FILES['berkas'] ?? null;

            if (!array_key_exists($mk, MK)) {
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
                            tambah_materi([
                                'mk'        => $mk,
                                'judul'     => $judul,
                                'deskripsi' => $deskripsi,
                                'berkas'    => $berkas,
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
  </nav>

  <?php if ($pesan): ?>
    <p class="admin-pesan" role="status"><?= ee($pesan) ?></p>
  <?php endif; ?>
  <?php if ($galat): ?>
    <p class="masuk-galat" role="alert"><?= ee($galat) ?></p>
  <?php endif; ?>

  <section class="admin-kartu">
    <h2>Unggah materi baru</h2>
    <form method="post" action="admin-materi.php" enctype="multipart/form-data" class="materi-form">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="aksi" value="unggah">

      <label class="masuk-label" for="mk">Mata kuliah</label>
      <select class="masuk-input" id="mk" name="mk" required>
        <?php foreach (MK as $slug => $nama): ?>
          <option value="<?= ee($slug) ?>"><?= ee($nama) ?></option>
        <?php endforeach; ?>
      </select>

      <label class="masuk-label" for="judul">Judul materi</label>
      <input class="masuk-input" id="judul" name="judul" type="text" maxlength="140"
             placeholder="Misalnya: Pertemuan 8 - Kompensasi Berbasis Kinerja" required>

      <label class="masuk-label" for="deskripsi">Keterangan singkat (opsional)</label>
      <input class="masuk-input" id="deskripsi" name="deskripsi" type="text" maxlength="200"
             placeholder="Ringkasan isi materi, boleh dikosongkan">

      <label class="masuk-label" for="berkas">Berkas PDF (maks. 30 MB)</label>
      <input class="masuk-input" id="berkas" name="berkas" type="file" accept="application/pdf,.pdf" required>

      <button class="masuk-tombol" type="submit">Unggah materi</button>
    </form>
  </section>

  <section class="admin-kartu">
    <h2>Materi terunggah</h2>
    <?php if ($jml === 0): ?>
      <p class="admin-kosong">Belum ada materi yang diunggah.</p>
    <?php else: ?>
      <?php foreach (MK as $slug => $nama): ?>
        <?php if (empty($per_mk[$slug])) continue; ?>
        <h3 class="materi-mk-judul"><?= ee($nama) ?> <span>(<?= count($per_mk[$slug]) ?>)</span></h3>
        <ul class="materi-kelola">
          <?php foreach ($per_mk[$slug] as $m): ?>
            <li>
              <div class="materi-kelola-teks">
                <b><?= ee($m['judul']) ?></b>
                <?php if (!empty($m['deskripsi'])): ?><span><?= ee($m['deskripsi']) ?></span><?php endif; ?>
                <span class="materi-kelola-meta"><?= ee($m['tanggal']) ?> &middot; <?= ukuran_manusia((int) ($m['ukuran'] ?? 0)) ?>
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
