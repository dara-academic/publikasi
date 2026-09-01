<?php
/* ------------------------------------------------------------------
   Panel bedah paper. Hanya admin.

   Setiap kali Dara menerbitkan paper baru, entrinya ditambahkan di sini:
   judul, jurnal, indeks (kuartil Scopus atau peringkat SINTA), tahun,
   tautan resmi ke paper yang sudah terbit, ringkasan singkat, dan sampul
   opsional. Entri tampil sebagai kartu di halaman publik paper.php dan
   menautkan ke paper aslinya.

   Keamanan sama seperti unggah materi: hanya admin, CSRF wajib, tautan
   divalidasi sebagai URL http(s), sampul hanya gambar (cek MIME), ukuran
   dibatasi, nama berkas dibuat ulang.
   ------------------------------------------------------------------ */
require __DIR__ . '/sesi.php';
$pengguna = wajib_masuk_segar();
if ($pengguna['peran'] !== 'admin') {
    header('Location: /bimbingan/rekap.php');
    exit;
}

const INDEKS = [
    'Scopus Q1', 'Scopus Q2', 'Scopus Q3', 'Scopus Q4',
    'SINTA 1', 'SINTA 2', 'SINTA 3', 'SINTA 4', 'SINTA 5', 'SINTA 6', 'Lainnya',
];
const MAKS_SAMPUL = 5242880; /* 5 MB */
const DIR_UNGGAH  = __DIR__ . '/unggahan';
const EXT_GAMBAR  = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

function ee($s): string { return htmlspecialchars((string) $s, ENT_QUOTES); }
function slugkan(string $s): string {
    $t = strtolower(trim($s));
    $t = preg_replace('/[^a-z0-9]+/', '-', $t);
    return trim((string) $t, '-');
}
function badge_kelas(string $indeks): string {
    if ($indeks === 'Scopus Q1') return 'k-q1';
    if ($indeks === 'Scopus Q2') return 'k-q2';
    if ($indeks === 'Scopus Q3' || $indeks === 'Scopus Q4') return 'k-q3';
    if (strncmp($indeks, 'SINTA', 5) === 0) return 'k-sinta';
    return 'k-q2';
}

$pesan = $_SESSION['pesan_paper'] ?? '';
unset($_SESSION['pesan_paper']);
$galat = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_sah()) {
        $galat = 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } else {
        $aksi = (string) ($_POST['aksi'] ?? '');

        if ($aksi === 'hapus') {
            $baris = hapus_paper((int) ($_POST['id'] ?? -1));
            if ($baris !== null && !empty($baris['sampul'])) {
                $path = DIR_UNGGAH . '/paper/' . basename((string) $baris['sampul']);
                if (is_file($path)) @unlink($path);
            }
            $_SESSION['pesan_paper'] = 'Paper dihapus.';
            header('Location: /admin-bedah.php');
            exit;
        }

        if ($aksi === 'tambah') {
            $judul    = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['judul'] ?? ''))));
            $jurnal   = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['jurnal'] ?? ''))));
            $penerbit = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['penerbit'] ?? ''))));
            $indeks   = (string) ($_POST['indeks'] ?? '');
            $tahun    = (string) ($_POST['tahun'] ?? '');
            $tautan   = trim((string) ($_POST['tautan'] ?? ''));
            $ringkas  = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['ringkasan'] ?? ''))));

            if ($judul === '' || $jurnal === '') {
                $galat = 'Judul dan nama jurnal wajib diisi.';
            } elseif (!in_array($indeks, INDEKS, true)) {
                $galat = 'Indeks tidak sah.';
            } elseif (!preg_match('/^(19|20)\d{2}$/', $tahun)) {
                $galat = 'Tahun terbit tidak sah.';
            } elseif (!filter_var($tautan, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $tautan)) {
                $galat = 'Tautan resmi harus berupa alamat web yang sah (diawali http:// atau https://).';
            } else {
                /* sampul opsional */
                $sampul = '';
                $f = $_FILES['sampul'] ?? null;
                $ada_sampul = $f && is_array($f) && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
                if ($ada_sampul && ($f['error'] ?? 0) !== UPLOAD_ERR_OK) {
                    $galat = 'Sampul gagal diunggah. Coba berkas lain atau kosongkan.';
                } elseif ($ada_sampul) {
                    $ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
                    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
                    if (($f['size'] ?? 0) > MAKS_SAMPUL) {
                        $galat = 'Ukuran sampul maksimal 5 MB.';
                    } elseif (!isset(EXT_GAMBAR[$ext]) || EXT_GAMBAR[$ext] !== $mime || !is_uploaded_file($f['tmp_name'])) {
                        $galat = 'Sampul harus berupa gambar JPG, PNG, atau WEBP.';
                    } else {
                        $dir = DIR_UNGGAH . '/paper';
                        if (!is_dir($dir)) @mkdir($dir, 0755, true);
                        if (!is_dir($dir) || !is_writable($dir)) {
                            $galat = 'Folder unggahan belum bisa ditulis. Cek izin folder unggahan.';
                        } else {
                            $nama = (slugkan($judul) ?: 'paper') . '-' . date('Ymd-His') . '.' . $ext;
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
                    tambah_paper([
                        'judul'     => $judul,
                        'jurnal'    => $jurnal,
                        'penerbit'  => $penerbit,
                        'indeks'    => $indeks,
                        'tahun'     => $tahun,
                        'tautan'    => $tautan,
                        'ringkasan' => $ringkas,
                        'sampul'    => $sampul,
                        'tanggal'   => date('Y-m-d'),
                        'oleh'      => $pengguna['nama'],
                    ]);
                    $_SESSION['pesan_paper'] = 'Paper "' . $judul . '" ditambahkan.';
                    header('Location: /admin-bedah.php');
                    exit;
                }
            }
        }
    }
}

$csrf  = ee(token_csrf());
$paper = array_reverse(muat_paper());
$jml   = count($paper);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Kelola Bedah Paper, Portal Dr. Despinur Dara</title>
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
      &middot; <a href="paper.php">Lihat publik</a>
      &middot; <a href="keluar.php">Keluar</a></span>
  </div>
</header>

<div class="admin-band">
  <div class="admin-band-isi">
    <p class="admin-lencana">Panel admin</p>
    <h1>Kelola bedah paper</h1>
    <p class="admin-band-lead">Tambahkan paper Dara yang sudah terbit. Entri langsung
    tampil di halaman <a href="paper.php">Paper terbit</a> sebagai kartu yang menautkan
    ke paper aslinya. Saat ini ada <?= $jml ?> paper.</p>
  </div>
</div>

<nav class="admin-menu" aria-label="Menu panel admin">
  <a href="admin.php">&larr; Panel admin</a>
  <a href="akun.php">Bimbingan &amp; akun</a>
  <a href="admin-materi.php">Materi kuliah</a>
  <a href="admin-bedah.php" class="active">Bedah paper</a>
</nav>

<main class="ak-utama admin-utama" id="konten">

  <?php if ($pesan): ?>
    <p class="admin-pesan" role="status"><?= ee($pesan) ?></p>
  <?php endif; ?>
  <?php if ($galat): ?>
    <p class="masuk-galat" role="alert"><?= ee($galat) ?></p>
  <?php endif; ?>

  <section class="admin-kartu">
    <h2>Tambah paper terbit</h2>
    <form method="post" action="admin-bedah.php" enctype="multipart/form-data" class="materi-form">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="aksi" value="tambah">

      <label class="masuk-label" for="judul">Judul paper</label>
      <input class="masuk-input" id="judul" name="judul" type="text" maxlength="220" required>

      <label class="masuk-label" for="jurnal">Nama jurnal</label>
      <input class="masuk-input" id="jurnal" name="jurnal" type="text" maxlength="160" required>

      <div class="materi-form-baris">
        <div>
          <label class="masuk-label" for="penerbit">Penerbit (opsional)</label>
          <input class="masuk-input" id="penerbit" name="penerbit" type="text" maxlength="120">
        </div>
        <div>
          <label class="masuk-label" for="tahun">Tahun terbit</label>
          <input class="masuk-input" id="tahun" name="tahun" type="text" inputmode="numeric" pattern="(19|20)\d{2}" placeholder="2026" required>
        </div>
        <div>
          <label class="masuk-label" for="indeks">Indeks</label>
          <select class="masuk-input" id="indeks" name="indeks" required>
            <?php foreach (INDEKS as $x): ?>
              <option value="<?= ee($x) ?>"><?= ee($x) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <label class="masuk-label" for="tautan">Tautan resmi ke paper (DOI atau URL penerbit)</label>
      <input class="masuk-input" id="tautan" name="tautan" type="url" placeholder="https://doi.org/..." required>

      <label class="masuk-label" for="ringkasan">Ringkasan singkat (opsional)</label>
      <input class="masuk-input" id="ringkasan" name="ringkasan" type="text" maxlength="240"
             placeholder="Satu kalimat isi paper dengan bahasa sederhana">

      <label class="masuk-label" for="sampul">Sampul jurnal (opsional, JPG/PNG/WEBP maks. 5 MB)</label>
      <input class="masuk-input" id="sampul" name="sampul" type="file" accept="image/jpeg,image/png,image/webp">

      <button class="masuk-tombol" type="submit">Tambah paper</button>
    </form>
  </section>

  <section class="admin-kartu">
    <h2>Paper terdaftar</h2>
    <?php if ($jml === 0): ?>
      <p class="admin-kosong">Belum ada paper yang ditambahkan.</p>
    <?php else: ?>
      <ul class="materi-kelola">
        <?php foreach ($paper as $p): ?>
          <li>
            <div class="materi-kelola-teks">
              <b><?= ee($p['judul']) ?></b>
              <span><?= ee($p['jurnal']) ?><?= $p['penerbit'] ? ' &middot; ' . ee($p['penerbit']) : '' ?></span>
              <span class="materi-kelola-meta"><?= ee($p['indeks']) ?> &middot; <?= ee($p['tahun']) ?>
                &middot; <a href="<?= ee($p['tautan']) ?>" target="_blank" rel="noopener noreferrer">tautan</a>
                <?= $p['sampul'] ? '&middot; ada sampul' : '&middot; tanpa sampul' ?></span>
            </div>
            <form method="post" action="admin-bedah.php" onsubmit="return confirm('Hapus paper ini?');">
              <input type="hidden" name="csrf" value="<?= $csrf ?>">
              <input type="hidden" name="aksi" value="hapus">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button class="materi-hapus" type="submit">Hapus</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <p class="admin-pulang"><a href="akun.php">&larr; Kembali ke panel akun</a></p>
</main>
</body>
</html>
