<?php
/* ------------------------------------------------------------------
   Panel kelola. Hanya admin.

   Dua pekerjaan di sini. Pertama, antrean verifikasi: pendaftar dari
   formulir publik disetujui atau ditolak. Disetujui berarti masuk
   rekap bimbingan resmi dengan tahap belum berjalan, dibuatkan akun,
   dan kode aksesnya ditampilkan sekali untuk dikirim ke surel yang
   didaftarkan. Kedua, kelola akun: tambah, reset kode, hapus.

   Kode akses hasil tambah dan reset hanya ditampilkan sekali, karena
   yang disimpan di server cuma hash-nya.
   ------------------------------------------------------------------ */
require __DIR__ . '/sesi.php';
$pengguna = wajib_masuk_segar();
if ($pengguna['peran'] !== 'admin') {
    header('Location: /bimbingan/rekap.php');
    exit;
}

function slug_nama(string $nama): string {
    $t = strtolower(trim($nama));
    $t = preg_replace('/[^a-z0-9]+/', '-', $t);
    return trim($t, '-');
}

$pesan = $_SESSION['pesan_akun'] ?? '';
unset($_SESSION['pesan_akun']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_sah()) {
    $aksi = (string) ($_POST['aksi'] ?? '');
    $semua = muat_pengguna();

    if ($aksi === 'setujui' || $aksi === 'tolak') {
        $calon = ambil_antrean((int) ($_POST['nomor'] ?? -1));
        if ($calon !== null) {
            if ($aksi === 'setujui') {
                tambah_mahasiswa_bimbingan([
                    'nama' => $calon['nama'], 'kelompok' => $calon['kelompok'],
                    'peran' => '-', 'tahap' => 'belum',
                    'keterangan' => 'Terdaftar lewat formulir, diverifikasi ' . date('Y-m-d'),
                ]);
                $u = slug_nama($calon['nama']);
                foreach ($semua as $p) {
                    if ($p['email'] === $u) { $u .= '2'; break; }
                }
                $kode = kode_akses();
                tambah_pengguna(['email' => $u, 'nama' => $calon['nama'],
                    'peran' => 'mahasiswa',
                    'sandi' => password_hash($kode, PASSWORD_DEFAULT),
                    'wajib_ganti' => true, 'dibuat' => date('Y-m-d')]);
                $_SESSION['pesan_akun'] = 'DISETUJUI: ' . $calon['nama']
                    . ' | pengguna: ' . $u . ' | kode akses: ' . $kode
                    . ' | kirim ke: ' . $calon['kontak'];
            } else {
                $_SESSION['pesan_akun'] = 'Ditolak dan dihapus dari antrean: ' . $calon['nama'];
            }
        }
    } elseif ($aksi === 'tambah') {
        $nama = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['nama'] ?? ''))));
        $u = trim(strtolower((string) ($_POST['pengguna'] ?? ''))) ?: slug_nama($nama);
        $peran = in_array($_POST['peran'] ?? '', ['mahasiswa', 'dosen', 'admin'], true)
            ? $_POST['peran'] : 'mahasiswa';
        $ada = false;
        foreach ($semua as $p) if ($p['email'] === $u) $ada = true;
        if ($nama === '' || $u === '') {
            $_SESSION['pesan_akun'] = 'Nama dan nama pengguna wajib diisi.';
        } elseif ($ada) {
            $_SESSION['pesan_akun'] = 'Nama pengguna ' . $u . ' sudah dipakai.';
        } else {
            $kode = kode_akses();
            tambah_pengguna(['email' => $u, 'nama' => $nama, 'peran' => $peran,
                'sandi' => password_hash($kode, PASSWORD_DEFAULT),
                'wajib_ganti' => true, 'dibuat' => date('Y-m-d')]);
            $_SESSION['pesan_akun'] = 'Akun dibuat: ' . $nama
                . ' | pengguna: ' . $u . ' | kode akses: ' . $kode;
        }
    } elseif ($aksi === 'reset') {
        $u = (string) ($_POST['pengguna'] ?? '');
        foreach ($semua as $p) {
            if ($p['email'] === $u) {
                $kode = kode_akses();
                perbarui_pengguna($u, [
                    'sandi' => password_hash($kode, PASSWORD_DEFAULT),
                    'wajib_ganti' => true,
                ]);
                $_SESSION['pesan_akun'] = 'Kode baru untuk ' . $p['nama'] . ': ' . $kode;
                break;
            }
        }
    } elseif ($aksi === 'hapus') {
        $u = (string) ($_POST['pengguna'] ?? '');
        if ($u === $pengguna['email']) {
            $_SESSION['pesan_akun'] = 'Akun sendiri tidak bisa dihapus.';
        } else {
            hapus_pengguna($u);
            $_SESSION['pesan_akun'] = 'Akun ' . $u . ' dihapus.';
        }
    }
    header('Location: /akun.php');
    exit;
}

$daftar_pengguna = muat_pengguna();
$antre = muat_antrean();
$csrf = htmlspecialchars(token_csrf(), ENT_QUOTES);
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Kelola Akun, Portal Dr. Despinur Dara</title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&family=Source+Serif+4:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="ak" data-grup="mengajar">
<header class="ak-bar">
  <div class="ak-bar-isi">
    <a class="ak-nama" href="index.html">Belajar Bersama Dara</a>
    <span class="rekap-siapa"><b><?= e($pengguna['nama']) ?></b>
      &middot; <a href="bimbingan/rekap.php">Rincian</a>
      &middot; <a href="ganti-sandi.php">Ganti sandi</a>
      &middot; <a href="keluar.php">Keluar</a></span>
  </div>
</header>
<?php
$rekap_b = muat_bimbingan()['mahasiswa'] ?? [];
$n_lulus_b = count(array_filter($rekap_b, fn($m) => $m['tahap'] === 'lulus'));
?>
<div class="admin-band">
  <div class="admin-band-isi">
    <p class="admin-lencana">Panel admin</p>
    <h1>Pusat kendali bimbingan</h1>
    <p class="admin-band-lead">Verifikasi pendaftar, kelola akun, dan pantau
    monitoring bimbingan dari satu tempat, <?= htmlspecialchars($pengguna['nama'], ENT_QUOTES) ?>.</p>
    <div class="prog-band-angka">
      <div class="<?= $antre ? 'admin-menyala' : '' ?>"><b><?= count($antre) ?></b><span>menunggu verifikasi</span></div>
      <div><b><?= count($daftar_pengguna) ?></b><span>akun terdaftar</span></div>
      <div><b><?= count($rekap_b) ?></b><span>mahasiswa di rekap</span></div>
      <div><b><?= $n_lulus_b ?></b><span>lulus</span></div>
    </div>
  </div>
</div>
<div class="ak-halaman">
<main class="ak-utama" id="konten">
  <div class="container">
<?php if ($pesan): ?>
    <p class="akun-pesan" role="status"><?= e($pesan) ?></p>
<?php endif; ?>

    <h2>Menunggu verifikasi <span class="rekap-jumlah"><?= count($antre) ?></span></h2>
<?php if (!$antre): ?>
    <p>Tidak ada pendaftar baru. Formulirnya ada di
      <a href="bimbingan/daftar.php">halaman pendaftaran</a>.</p>
<?php else: ?>
    <div class="rekap-gulir"><table class="rekap-tabel">
      <thead><tr><th>Nama</th><th>Kelompok</th><th>Surel</th><th>Waktu daftar</th><th>Tindakan</th></tr></thead>
      <tbody>
<?php foreach ($antre as $a): $i = $a['id']; ?>
        <tr>
          <td><?= e($a['nama']) ?></td>
          <td><?= e($a['kelompok']) ?></td>
          <td><?= e($a['kontak']) ?></td>
          <td><?= e(substr($a['waktu'], 0, 10)) ?></td>
          <td class="akun-aksi">
            <form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="aksi" value="setujui"><input type="hidden" name="nomor" value="<?= $i ?>"><button class="akun-tombol setuju">Setujui</button></form>
            <form method="post" onsubmit="return confirm('Tolak dan hapus <?= e($a['nama']) ?>?')"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="aksi" value="tolak"><input type="hidden" name="nomor" value="<?= $i ?>"><button class="akun-tombol bahaya">Tolak</button></form>
          </td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table></div>
<?php endif; ?>

    <h2>Tambah akun</h2>
    <form method="post" class="akun-form">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="aksi" value="tambah">
      <input class="masuk-input" name="nama" type="text" placeholder="Nama lengkap" required>
      <input class="masuk-input" name="pengguna" type="text" placeholder="Nama pengguna (kosong = otomatis)">
      <select class="masuk-input" name="peran">
        <option value="mahasiswa">Mahasiswa</option>
        <option value="dosen">Dosen</option>
        <option value="admin">Admin</option>
      </select>
      <button class="masuk-tombol akun-tombol-tambah" type="submit">Buat akun</button>
    </form>

    <h2>Semua akun <span class="rekap-jumlah"><?= count($daftar_pengguna) ?></span></h2>
    <div class="rekap-gulir"><table class="rekap-tabel">
      <thead><tr><th>Nama</th><th>Pengguna</th><th>Peran</th><th>Sandi</th><th>Tindakan</th></tr></thead>
      <tbody>
<?php foreach ($daftar_pengguna as $p): ?>
        <tr>
          <td><?= e($p['nama']) ?></td>
          <td><code><?= e($p['email']) ?></code></td>
          <td><?= e($p['peran']) ?></td>
          <td><?= !empty($p['wajib_ganti']) ? '<span class="rekap-tahap tahap-belum">kode awal</span>' : '<span class="rekap-tahap tahap-sempro">sudah diganti</span>' ?></td>
          <td class="akun-aksi">
            <form method="post" onsubmit="return confirm('Buat kode baru untuk <?= e($p['nama']) ?>? Sandi lamanya hangus.')"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="aksi" value="reset"><input type="hidden" name="pengguna" value="<?= e($p['email']) ?>"><button class="akun-tombol">Reset kode</button></form>
            <form method="post" onsubmit="return confirm('Hapus akun <?= e($p['nama']) ?>?')"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="pengguna" value="<?= e($p['email']) ?>"><button class="akun-tombol bahaya">Hapus</button></form>
          </td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table></div>

    <h2>Akses menu</h2>
    <p>Yang terbuka publik: seluruh situs termasuk
      <a href="bimbingan/progres.php">Monitoring Pelaksanaan Bimbingan Tugas Akhir Mahasiswa</a> (nama dan tahap saja).
      Yang menuntut masuk: <a href="bimbingan/rekap.php">rincian bimbingan</a>
      berikut catatan per mahasiswa, dan panel ini khusus admin. Menu berkunci
      baru tinggal dibuat sebagai halaman PHP yang memanggil penjaga sesi yang
      sama.</p>
  </div>
</main>
</div>
</body>
</html>
