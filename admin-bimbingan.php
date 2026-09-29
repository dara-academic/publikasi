<?php
/* ------------------------------------------------------------------
   Manajemen bimbingan. Hanya admin (Dr. Dara).

   1. Pemindahan data lama: rekap lama dipetakan ke struktur baru,
      ditampilkan dulu sebagai pratinjau, baru disimpan setelah
      tombol Terapkan ditekan.
   2. Pendaftar: mahasiswa membuat akun sendiri (NIM + sandi), di sini
      disetujui dan ditautkan ke data bimbingannya.
   3. Tambah mahasiswa S1, S2, S3: satu per satu atau massal (tempel
      dari Excel).
   4. Daftar mahasiswa: ubah status per orang atau sekaligus, ubah
      data, lihat riwayat, hapus.
   ------------------------------------------------------------------ */
require __DIR__ . '/bimbingan-inti.php';
$pengguna = wajib_masuk_segar();
if ($pengguna['peran'] !== 'admin') {
    header('Location: /bimbingan/rekap.php');
    exit;
}

function e($s): string { return htmlspecialchars((string) $s, ENT_QUOTES); }
function pesan(string $t): void { $_SESSION['pesan_bim'] = $t; }
$HARI = date('Y-m-d');

/* Baca satu baris tempelan massal: Nama | NIM | Jenjang | Angkatan | Judul.
   Pemisah boleh tab (tempel dari Excel), garis tegak, atau titik koma. */
function baca_baris_massal(string $baris): ?array {
    $kol = array_map('trim', preg_split('/\t|\||;/', $baris));
    if (count($kol) < 3 || $kol[0] === '' || mb_stripos($kol[0], 'nama') === 0) return null;
    $jt = strtoupper(str_replace(' ', '', $kol[2]));
    $peta = ['S1' => 'S1', 'SKRIPSI' => 'S1', 'S2' => 'S2', 'TESIS' => 'S2', 'THESIS' => 'S2', 'S3' => 'S3', 'DISERTASI' => 'S3'];
    if (!isset($peta[$jt])) return null;
    return ['nama' => $kol[0], 'nim' => $kol[1] ?? '', 'jenjang' => $peta[$jt],
            'angkatan' => $kol[3] ?? '', 'judul' => $kol[4] ?? ''];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_sah()) {
        pesan('Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.');
        header('Location: /admin-bimbingan.php'); exit;
    }
    $aksi = (string) ($_POST['aksi'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($aksi === 'migrasi') {
        $n = terapkan_migrasi();
        pesan($n ? "$n mahasiswa dipindahkan ke data bimbingan baru. Periksa status tiap orang di daftar di bawah."
                 : 'Tidak ada data lama yang perlu dipindahkan.');
    } elseif (perlu_migrasi()) {
        pesan('Pindahkan data lama lebih dulu sebelum mengubah data bimbingan.');
    } elseif ($aksi === 'tambah') {
        $in = $_POST;
        if (nim_dipakai(preg_replace('/[^0-9A-Za-z]/', '', (string) ($in['nim'] ?? '')))) {
            pesan('NIM itu sudah terdaftar.');
        } else {
            $baru = tambah_mhs($in);
            pesan($baru ? 'Ditambahkan: ' . _teks_bersih($in['nama'] ?? '', 100) : 'Nama (minimal 3 huruf) dan jenjang wajib diisi.');
        }
    } elseif ($aksi === 'massal') {
        $ok = 0; $lewat = [];
        foreach (preg_split('/\r\n|\n|\r/', (string) ($_POST['daftar'] ?? '')) as $no => $baris) {
            if (trim($baris) === '') continue;
            $b = baca_baris_massal($baris);
            if ($b === null) { $lewat[] = 'baris ' . ($no + 1); continue; }
            $nim = preg_replace('/[^0-9A-Za-z]/', '', $b['nim']);
            if (nim_dipakai($nim)) { $lewat[] = 'baris ' . ($no + 1) . ' (NIM sudah ada)'; continue; }
            if (tambah_mhs($b, 'Ditambahkan massal')) $ok++; else $lewat[] = 'baris ' . ($no + 1);
        }
        pesan("$ok mahasiswa ditambahkan." . ($lewat ? ' Dilewati: ' . implode(', ', array_slice($lewat, 0, 12)) . '.' : ''));
    } elseif ($aksi === 'status') {
        $n = ubah_status([$id], (string) ($_POST['status'] ?? ''), (string) ($_POST['tgl'] ?? ''), (string) ($_POST['catatan'] ?? ''));
        $m = ambil_mhs($id);
        pesan($n ? 'Status ' . ($m['nama'] ?? '') . ' diperbarui.' : 'Tidak ada perubahan.');
    } elseif ($aksi === 'status_massal') {
        [$j, $st] = array_pad(explode(':', (string) ($_POST['status'] ?? ''), 2), 2, '');
        $ids = array_map('intval', (array) ($_POST['pilih'] ?? []));
        if (!$ids || !isset(status_jenjang($j)[$st])) {
            pesan('Centang mahasiswa dan pilih statusnya dulu.');
        } else {
            $n = ubah_status($ids, $st, (string) ($_POST['tgl'] ?? ''), (string) ($_POST['catatan'] ?? ''), $j);
            $sisa = count($ids) - $n;
            pesan("$n mahasiswa diperbarui ke " . label_status($j, $st) . '.'
                . ($sisa ? " $sisa dilewati karena jenjangnya bukan $j atau statusnya sudah sama." : ''));
        }
    } elseif ($aksi === 'ubah') {
        $nim = preg_replace('/[^0-9A-Za-z]/', '', (string) ($_POST['nim'] ?? ''));
        if (nim_dipakai($nim, $id)) pesan('NIM itu sudah dipakai mahasiswa lain.');
        else pesan(ubah_mhs($id, $_POST) ? 'Data diperbarui.' : 'Nama dan jenjang wajib diisi.');
    } elseif ($aksi === 'hapus') {
        $m = hapus_mhs($id);
        pesan($m ? 'Dihapus dari daftar bimbingan: ' . $m['nama'] . '. Akunnya (bila ada) tetap ada di menu Akun.' : 'Data tidak ditemukan.');
    } elseif ($aksi === 'setujui_akun') {
        $semua_daftar = muat_daftar_akun(); $calon = null;
        foreach ($semua_daftar as $a) if ((int) $a['id'] === $id) $calon = $a;
        $tujuan = (string) ($_POST['tujuan'] ?? 'baru');
        if (!$calon) {
            pesan('Pendaftaran tidak ditemukan.');
        } elseif (nama_pengguna_ada($calon['nim'])) {
            pesan('Akun dengan NIM ' . $calon['nim'] . ' sudah ada. Tolak pendaftaran ini atau hapus akun lama di menu Akun.');
        } else {
            ambil_daftar_akun($id);
            tambah_pengguna(['email' => $calon['nim'], 'nama' => $calon['nama'], 'peran' => 'mahasiswa',
                'sandi' => $calon['sandi'], 'wajib_ganti' => false, 'dibuat' => $HARI]);
            if ($tujuan === 'baru') {
                tambah_mhs(['nama' => $calon['nama'], 'nim' => $calon['nim'], 'jenjang' => $calon['jenjang'],
                    'angkatan' => $calon['angkatan'], 'judul' => $calon['judul'], 'kontak' => $calon['kontak'],
                    'akun' => $calon['nim']], 'Akun disetujui');
            } else {
                $m = ambil_mhs((int) $tujuan);
                if ($m) {
                    ubah_mhs((int) $tujuan, [
                        'akun' => $calon['nim'],
                        'nim' => $m['nim'] !== '' ? $m['nim'] : $calon['nim'],
                        'kontak' => $m['kontak'] !== '' ? $m['kontak'] : $calon['kontak'],
                        'judul' => $m['judul'] !== '' ? $m['judul'] : $calon['judul'],
                    ]);
                }
            }
            pesan('Disetujui: ' . $calon['nama'] . '. Mahasiswa sudah bisa masuk dengan NIM ' . $calon['nim'] . ' dan sandi yang ia buat.');
        }
    } elseif ($aksi === 'tolak_akun') {
        $a = ambil_daftar_akun($id);
        pesan($a ? 'Pendaftaran ditolak: ' . $a['nama'] : 'Pendaftaran tidak ditemukan.');
    } elseif ($aksi === 'setujui_lama' || $aksi === 'tolak_lama') {
        /* antrean dari formulir lama (tanpa sandi): akun dibuat dengan kode sekali pakai */
        $calon = ambil_antrean($id);
        if ($calon && $aksi === 'setujui_lama') {
            $k = (string) $calon['kelompok'];
            $j = mb_stripos($k, 'Disertasi') !== false ? 'S3' : (mb_stripos($k, 'Tesis') !== false ? 'S2' : 'S1');
            $u = preg_replace('/[^a-z0-9]+/', '-', strtolower($calon['nama']));
            $u = trim($u, '-'); if (nama_pengguna_ada($u)) $u .= '2';
            tambah_mhs(['nama' => $calon['nama'], 'jenjang' => $j, 'kelompok' => $k,
                        'kontak' => $calon['kontak'], 'akun' => $u], 'Pendaftaran lama disetujui');
            $kode = kode_akses();
            tambah_pengguna(['email' => $u, 'nama' => $calon['nama'], 'peran' => 'mahasiswa',
                'sandi' => password_hash($kode, PASSWORD_DEFAULT), 'wajib_ganti' => true, 'dibuat' => $HARI]);
            pesan('DISETUJUI: ' . $calon['nama'] . ' | pengguna: ' . $u . ' | kode akses: ' . $kode . ' | kirim ke: ' . $calon['kontak']);
        } else {
            pesan($calon ? 'Ditolak: ' . $calon['nama'] : 'Pendaftaran tidak ditemukan.');
        }
    }
    header('Location: /admin-bimbingan.php' . ($id && in_array($aksi, ['status', 'ubah'], true) ? '#m' . $id : ''));
    exit;
}

$pesan = $_SESSION['pesan_bim'] ?? '';
unset($_SESSION['pesan_bim']);
$csrf = e(token_csrf());
$migrasi = perlu_migrasi();
$mhs = muat_mhs();
$daftar_akun = muat_daftar_akun();
$antre_lama = muat_antrean();
$tanpa_akun = array_values(array_filter($mhs, fn($m) => ($m['akun'] ?? '') === ''));

$n_lulus = count(array_filter($mhs, 'sudah_lulus'));
$n_kurang = count(array_filter($mhs, fn($m) => $m['nim'] === '' || $m['judul'] === ''));
$per_j = [];
foreach (JENJANG as $j => $_) $per_j[$j] = count(array_filter($mhs, fn($m) => $m['jenjang'] === $j));
usort($mhs, fn($a, $b) => [$a['jenjang'], $a['kelompok'], $a['nama']] <=> [$b['jenjang'], $b['kelompok'], $b['nama']]);

function opsi_status(string $j, string $pilih): string {
    $h = '';
    foreach (status_jenjang($j) as $k => $t) {
        $h .= '<option value="' . e($k) . '"' . ($k === $pilih ? ' selected' : '') . '>' . e($t) . '</option>';
    }
    return $h;
}
function tebak_tujuan(array $calon, array $kandidat): int {
    foreach ($kandidat as $m) if ($calon['nim'] !== '' && $m['nim'] === $calon['nim']) return (int) $m['id'];
    foreach ($kandidat as $m) if (strcasecmp(trim($m['nama']), trim($calon['nama'])) === 0) return (int) $m['id'];
    return 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Manajemen Bimbingan, Portal Dr. Despinur Dara</title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Figtree:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="ak" data-grup="mengajar">
<header class="ak-bar">
  <div class="ak-bar-isi">
    <a class="ak-nama" href="index.html"><svg class="ak-logo" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="8" fill="#e9b949"/><path d="M7.5 10.2c3-.9 5.9-.6 8.5 1.3v12.3c-2.6-1.9-5.5-2.2-8.5-1.3z" fill="#0f4c5c"/><path d="M24.5 10.2c-3-.9-5.9-.6-8.5 1.3v12.3c2.6-1.9 5.5-2.2 8.5-1.3z" fill="#0f4c5c" opacity=".72"/></svg><span>Belajar Bersama Dara</span></a>
    <span class="rekap-siapa"><b><?= e($pengguna['nama']) ?></b>
      &middot; <a href="bimbingan/progres.php">Papan monitoring</a>
      &middot; <a href="ganti-sandi.php">Ganti sandi</a>
      &middot; <a href="keluar.php">Keluar</a></span>
  </div>
</header>
<div class="admin-band">
  <div class="admin-band-isi">
    <p class="admin-lencana">Panel admin</p>
    <h1>Manajemen bimbingan</h1>
    <p class="admin-band-lead">Tambah mahasiswa S1, S2, S3, perbarui status, dan setujui pendaftaran akun.</p>
    <div class="prog-band-angka">
      <div class="<?= ($daftar_akun || $antre_lama) ? 'admin-menyala' : '' ?>"><b><?= count($daftar_akun) + count($antre_lama) ?></b><span>menunggu persetujuan</span></div>
      <div><b><?= count($mhs) ?></b><span>mahasiswa</span></div>
      <div><b><?= $n_lulus ?></b><span>lulus</span></div>
      <div><b><?= $per_j['S1'] ?> / <?= $per_j['S2'] ?> / <?= $per_j['S3'] ?></b><span>S1 / S2 / S3</span></div>
    </div>
  </div>
</div>
<div class="ak-halaman bim-halaman">
<main class="ak-utama" id="konten">
  <nav class="admin-menu" aria-label="Menu panel admin">
    <a href="admin.php">&larr; Panel admin</a>
    <a href="admin-bimbingan.php" class="active">Bimbingan</a>
    <a href="akun.php">Akun</a>
    <a href="admin-materi.php">Materi kuliah</a>
    <a href="admin-bedah.php">Bedah paper</a>
    <a href="admin-buku.php">Buku</a>
    <a href="admin-statistik.php">Statistik</a>
    <a href="admin-komentar.php">Tanya jawab</a>
    <a href="admin-cadangan.php">Cadangan</a>
  </nav>
  <div class="container">
<?php if ($pesan): ?>
    <p class="akun-pesan" role="status"><?= e($pesan) ?></p>
<?php endif; ?>

<?php if ($migrasi): ?>
    <section class="bim-migrasi">
      <h2>Pindahkan data lama</h2>
      <p>Rekap lama memakai empat tahap (belum, judul, sempro, lulus). Berikut pemetaannya ke
        status baru. Periksa dulu, lalu tekan <b>Terapkan</b>. Status tiap orang tetap bisa
        diubah sesudahnya. Rekap lama tidak dihapus, ia tinggal sebagai arsip.</p>
      <p class="bim-aturan"><b>Aturan:</b> belum dan judul &rarr; Pengajuan Topik &middot;
        sempro &rarr; Lulus Proposal &middot; lulus &rarr; status akhir jenjangnya &middot;
        jenjang dibaca dari nama kelompok (Tesis = S2, Disertasi = S3, selain itu S1).</p>
      <div class="rekap-gulir"><table class="rekap-tabel">
        <thead><tr><th>Nama</th><th>Kelompok lama</th><th>Jenjang</th><th>Tahap lama</th><th>Status baru</th><th>Akun</th></tr></thead>
        <tbody>
<?php foreach ($mhs as $m): ?>
          <tr><td><?= e($m['nama']) ?></td><td><?= e($m['kelompok']) ?></td><td><?= e($m['jenjang']) ?></td>
            <td><?= e($m['tahap_lama'] ?? '') ?></td><td><?= e(label_status($m['jenjang'], $m['status'])) ?></td>
            <td><?= $m['akun'] !== '' ? '<code>' . e($m['akun']) . '</code>' : '&mdash;' ?></td></tr>
<?php endforeach; ?>
        </tbody>
      </table></div>
      <form method="post" onsubmit="return confirm('Pindahkan <?= count($mhs) ?> mahasiswa ke data bimbingan baru?')">
        <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="aksi" value="migrasi">
        <button class="masuk-tombol bim-tombol">Terapkan pemindahan</button>
      </form>
    </section>
<?php else: ?>

    <h2>Menunggu persetujuan <span class="rekap-jumlah"><?= count($daftar_akun) + count($antre_lama) ?></span></h2>
<?php if (!$daftar_akun && !$antre_lama): ?>
    <p>Tidak ada pendaftar baru. Mahasiswa mendaftar lewat <a href="bimbingan/daftar.php">halaman pendaftaran</a>.</p>
<?php else: ?>
    <div class="rekap-gulir"><table class="rekap-tabel">
      <thead><tr><th>Pendaftar</th><th>Jenjang</th><th>Judul</th><th>Tautkan ke</th><th>Tindakan</th></tr></thead>
      <tbody>
<?php foreach ($daftar_akun as $a): $tebak = tebak_tujuan($a, $tanpa_akun); $fid = 'setuju-' . (int) $a['id']; ?>
        <tr>
          <td><b><?= e($a['nama']) ?></b><small class="bim-kecil">NIM <?= e($a['nim']) ?> &middot; <?= e($a['kontak']) ?> &middot; <?= e(substr($a['waktu'], 0, 10)) ?></small></td>
          <td><?= e($a['jenjang']) ?><?= $a['angkatan'] !== '' ? ' &middot; ' . e($a['angkatan']) : '' ?></td>
          <td class="rekap-ket"><?= e($a['judul'] ?: '-') ?></td>
          <td>
            <select class="masuk-input bim-pilih" name="tujuan" form="<?= $fid ?>">
              <option value="baru">Data bimbingan baru</option>
<?php foreach ($tanpa_akun as $m): ?>
              <option value="<?= (int) $m['id'] ?>"<?= $tebak === (int) $m['id'] ? ' selected' : '' ?>><?= e($m['nama']) ?> (<?= e($m['jenjang']) ?>)</option>
<?php endforeach; ?>
            </select>
          </td>
          <td class="akun-aksi">
            <form method="post" id="<?= $fid ?>"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="aksi" value="setujui_akun"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="akun-tombol setuju">Setujui</button></form>
            <form method="post" onsubmit="return confirm('Tolak pendaftaran ini?')"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="aksi" value="tolak_akun"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="akun-tombol bahaya">Tolak</button></form>
          </td>
        </tr>
<?php endforeach; ?>
<?php foreach ($antre_lama as $a): ?>
        <tr>
          <td><b><?= e($a['nama']) ?></b><small class="bim-kecil"><?= e($a['kontak']) ?> &middot; formulir lama, akun dibuat dengan kode akses</small></td>
          <td colspan="3"><?= e($a['kelompok']) ?></td>
          <td class="akun-aksi">
            <form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="aksi" value="setujui_lama"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="akun-tombol setuju">Setujui</button></form>
            <form method="post" onsubmit="return confirm('Tolak pendaftaran ini?')"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="aksi" value="tolak_lama"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="akun-tombol bahaya">Tolak</button></form>
          </td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table></div>
<?php endif; ?>

    <h2>Tambah mahasiswa</h2>
    <div class="bim-dua">
      <form method="post" class="bim-form">
        <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="aksi" value="tambah">
        <label class="masuk-label">Nama lengkap<input class="masuk-input" name="nama" required minlength="3" maxlength="100"></label>
        <div class="bim-baris">
          <label class="masuk-label">NIM<input class="masuk-input" name="nim" maxlength="20" inputmode="numeric"></label>
          <label class="masuk-label">Jenjang
            <select class="masuk-input" name="jenjang" required data-jenjang>
<?php foreach (JENJANG as $j => $t): ?>
              <option value="<?= $j ?>"><?= $j ?> &middot; <?= $t ?></option>
<?php endforeach; ?>
            </select></label>
          <label class="masuk-label">Angkatan<input class="masuk-input" name="angkatan" maxlength="4" inputmode="numeric" placeholder="2025"></label>
        </div>
        <div class="bim-baris">
          <label class="masuk-label">Peran Dr. Dara
            <select class="masuk-input" name="peran">
<?php foreach (PERAN_PEMBIMBING as $p): ?>
              <option><?= e($p) ?></option>
<?php endforeach; ?>
            </select></label>
          <label class="masuk-label">Status awal
            <select class="masuk-input" name="status" data-status-untuk></select></label>
        </div>
        <label class="masuk-label">Judul penelitian<input class="masuk-input" name="judul" maxlength="300"></label>
        <label class="masuk-label">Surel<input class="masuk-input" name="kontak" type="email" maxlength="120"></label>
        <button class="masuk-tombol bim-tombol" type="submit">Tambah mahasiswa</button>
      </form>
      <form method="post" class="bim-form">
        <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="aksi" value="massal">
        <label class="masuk-label">Tambah banyak sekaligus
          <textarea class="masuk-input bim-massal" name="daftar" rows="9" required
            placeholder="Nama | NIM | Jenjang | Angkatan | Judul&#10;Siti Aminah | 1706123456 | S1 | 2023 | Pengaruh ...&#10;Budi Santoso | 9906111222 | S2 | 2024 |"></textarea></label>
        <p class="bim-bantu">Satu mahasiswa per baris. Bisa ditempel langsung dari Excel (kolom:
          Nama, NIM, Jenjang, Angkatan, Judul). Jenjang ditulis S1, S2, S3, atau Skripsi, Tesis,
          Disertasi. Semua mulai di status Pengajuan Topik.</p>
        <button class="masuk-tombol bim-tombol" type="submit">Tambah semua</button>
      </form>
    </div>

    <h2 id="daftar">Daftar mahasiswa <span class="rekap-jumlah"><?= count($mhs) ?></span></h2>
<?php if ($n_kurang): ?>
    <p class="bim-bantu"><?= $n_kurang ?> mahasiswa belum punya NIM atau judul. Pilih saringan <b>Belum lengkap</b>, lalu tekan <b>Ubah</b> di tiap baris.</p>
<?php endif; ?>
    <div class="bim-saring">
      <input class="masuk-input" type="search" id="bim-cari" placeholder="Cari nama, NIM, atau judul">
      <select class="masuk-input" id="bim-jenjang">
        <option value="">Semua jenjang</option>
<?php foreach (JENJANG as $j => $t): ?>
        <option value="<?= $j ?>"><?= $j ?> &middot; <?= $t ?></option>
<?php endforeach; ?>
      </select>
      <select class="masuk-input" id="bim-tahap">
        <option value="">Semua status</option>
        <option value="aktif">Belum lulus</option>
        <option value="lulus">Sudah lulus</option>
        <option value="kurang">Belum lengkap (tanpa NIM atau judul)</option>
      </select>
      <span class="bim-hitung" id="bim-hitung"></span>
    </div>

    <form method="post" id="form-massal" class="bim-massal-bar">
      <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="aksi" value="status_massal">
      <b id="bim-terpilih">0 dicentang</b>
      <select class="masuk-input" name="status" required>
        <option value="">Ubah status ke&hellip;</option>
<?php foreach (JENJANG as $j => $t): ?>
        <optgroup label="<?= $j ?> &middot; <?= $t ?>">
<?php foreach (status_jenjang($j) as $k => $lbl): ?>
          <option value="<?= $j ?>:<?= $k ?>"><?= e($lbl) ?></option>
<?php endforeach; ?>
        </optgroup>
<?php endforeach; ?>
      </select>
      <input class="masuk-input" type="date" name="tgl" value="<?= $HARI ?>">
      <input class="masuk-input" name="catatan" maxlength="300" placeholder="Catatan (opsional)">
      <button class="akun-tombol setuju" type="submit">Terapkan ke yang dicentang</button>
    </form>

    <div class="rekap-gulir"><table class="rekap-tabel bim-tabel">
      <thead><tr><th><input type="checkbox" id="bim-semua" aria-label="Centang semua yang tampil"></th><th>Mahasiswa</th><th>Judul</th><th>Status</th><th></th></tr></thead>
      <tbody>
<?php foreach ($mhs as $m): $mid = (int) $m['id']; $lulus = sudah_lulus($m); $kurang = $m['nim'] === '' || $m['judul'] === '';
      $cari = mb_strtolower($m['nama'] . ' ' . $m['nim'] . ' ' . $m['judul']); ?>
        <tr class="bim-baris-m" id="m<?= $mid ?>" data-jenjang="<?= e($m['jenjang']) ?>" data-lulus="<?= $lulus ? '1' : '0' ?>" data-kurang="<?= $kurang ? '1' : '0' ?>" data-cari="<?= e($cari) ?>">
          <td><input type="checkbox" name="pilih[]" value="<?= $mid ?>" form="form-massal" aria-label="Pilih <?= e($m['nama']) ?>"></td>
          <td><b><?= e($m['nama']) ?></b>
            <small class="bim-kecil"><?= e($m['jenjang']) ?> &middot; <?= e($m['kelompok']) ?><?= $m['nim'] !== '' ? ' &middot; NIM ' . e($m['nim']) : '' ?><?= $m['akun'] !== '' ? ' &middot; akun &#10003;' : '' ?></small><?php if ($kurang): ?><span class="bim-kurang">data belum lengkap</span><?php endif; ?></td>
          <td class="rekap-ket"><?= e($m['judul'] ?: '-') ?></td>
          <td>
            <form method="post" class="bim-status-form">
              <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="aksi" value="status"><input type="hidden" name="id" value="<?= $mid ?>">
              <select class="masuk-input" name="status" aria-label="Status <?= e($m['nama']) ?>"><?= opsi_status($m['jenjang'], $m['status']) ?></select>
              <input class="masuk-input" type="date" name="tgl" value="<?= $HARI ?>" aria-label="Tanggal">
              <input class="masuk-input" name="catatan" maxlength="300" placeholder="Catatan" aria-label="Catatan">
              <button class="akun-tombol setuju" type="submit">Simpan</button>
            </form>
            <small class="bim-kecil"><?= $lulus ? '&#127891; ' : '' ?><?= e(label_status($m['jenjang'], $m['status'])) ?><?= $m['tgl_status'] !== '' ? ' sejak ' . e($m['tgl_status']) : '' ?></small>
          </td>
          <td><button type="button" class="akun-tombol" data-buka="d<?= $mid ?>">Ubah</button></td>
        </tr>
        <tr class="bim-detail" id="d<?= $mid ?>" hidden>
          <td colspan="5">
            <div class="bim-dua">
              <form method="post" class="bim-form">
                <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="aksi" value="ubah"><input type="hidden" name="id" value="<?= $mid ?>">
                <label class="masuk-label">Nama<input class="masuk-input" name="nama" value="<?= e($m['nama']) ?>" required maxlength="100"></label>
                <div class="bim-baris">
                  <label class="masuk-label">NIM<input class="masuk-input" name="nim" value="<?= e($m['nim']) ?>" maxlength="20"></label>
                  <label class="masuk-label">Jenjang<select class="masuk-input" name="jenjang">
<?php foreach (JENJANG as $j => $t): ?>
                    <option value="<?= $j ?>"<?= $j === $m['jenjang'] ? ' selected' : '' ?>><?= $j ?></option>
<?php endforeach; ?>
                  </select></label>
                  <label class="masuk-label">Angkatan<input class="masuk-input" name="angkatan" value="<?= e($m['angkatan']) ?>" maxlength="4"></label>
                </div>
                <div class="bim-baris">
                  <label class="masuk-label">Kelompok<input class="masuk-input" name="kelompok" value="<?= e($m['kelompok']) ?>" maxlength="80"></label>
                  <label class="masuk-label">Peran Dr. Dara<select class="masuk-input" name="peran">
<?php foreach (PERAN_PEMBIMBING as $p): ?>
                    <option<?= $p === $m['peran'] ? ' selected' : '' ?>><?= e($p) ?></option>
<?php endforeach; ?>
                  </select></label>
                </div>
                <label class="masuk-label">Judul penelitian<input class="masuk-input" name="judul" value="<?= e($m['judul']) ?>" maxlength="300"></label>
                <div class="bim-baris">
                  <label class="masuk-label">Surel<input class="masuk-input" name="kontak" type="email" value="<?= e($m['kontak']) ?>" maxlength="120"></label>
                  <label class="masuk-label">Akun (nama pengguna)<input class="masuk-input" name="akun" value="<?= e($m['akun']) ?>" maxlength="60"></label>
                </div>
                <label class="masuk-label">Catatan internal<textarea class="masuk-input" name="catatan" rows="2" maxlength="1000"><?= e($m['catatan']) ?></textarea></label>
                <button class="akun-tombol setuju" type="submit">Simpan data</button>
              </form>
              <div>
                <p class="masuk-label">Riwayat status</p>
                <ol class="bim-riwayat">
<?php foreach (array_reverse($m['riwayat'] ?? []) as $r): ?>
                  <li><b><?= e(label_status($m['jenjang'], $r['ke'])) ?></b> <span><?= e($r['tgl']) ?><?= $r['catatan'] !== '' ? ' &middot; ' . e($r['catatan']) : '' ?></span></li>
<?php endforeach; ?>
<?php if (empty($m['riwayat'])): ?>
                  <li><span>Belum ada riwayat.</span></li>
<?php endif; ?>
                </ol>
                <form method="post" onsubmit="return confirm('Hapus mahasiswa ini dari daftar bimbingan? Riwayatnya ikut terhapus.')">
                  <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= $mid ?>">
                  <button class="akun-tombol bahaya" type="submit">Hapus dari daftar</button>
                </form>
              </div>
            </div>
          </td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table></div>
<?php endif; ?>
  </div>
</main>
</div>
<script>
(function () {
  var STATUS = <?= json_encode(STATUS_BIMBINGAN, JSON_UNESCAPED_UNICODE) ?>;
  /* status awal di formulir tambah ikut jenjang yang dipilih */
  document.querySelectorAll('[data-jenjang]').forEach(function (sel) {
    var tujuan = sel.form && sel.form.querySelector('[data-status-untuk]');
    if (!tujuan) return;
    function isi() {
      tujuan.innerHTML = '';
      Object.keys(STATUS[sel.value] || {}).forEach(function (k) {
        var o = document.createElement('option'); o.value = k; o.textContent = STATUS[sel.value][k]; tujuan.appendChild(o);
      });
    }
    sel.addEventListener('change', isi); isi();
  });
  /* buka-tutup baris ubah data */
  document.querySelectorAll('[data-buka]').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = document.getElementById(b.getAttribute('data-buka'));
      d.hidden = !d.hidden; b.textContent = d.hidden ? 'Ubah' : 'Tutup';
    });
  });
  /* saring daftar */
  var baris = [].slice.call(document.querySelectorAll('.bim-baris-m'));
  var cari = document.getElementById('bim-cari'), fj = document.getElementById('bim-jenjang'), ft = document.getElementById('bim-tahap');
  var hitung = document.getElementById('bim-hitung');
  function saring() {
    if (!cari) return;
    var q = cari.value.toLowerCase().trim(), n = 0;
    baris.forEach(function (b) {
      var ok = (!q || b.dataset.cari.indexOf(q) !== -1) && (!fj.value || b.dataset.jenjang === fj.value)
            && (!ft.value || (ft.value === 'kurang' ? b.dataset.kurang === '1' : (ft.value === 'lulus') === (b.dataset.lulus === '1')));
      b.hidden = !ok; if (!ok) { var d = b.nextElementSibling; if (d) d.hidden = true; }
      if (ok) n++;
    });
    hitung.textContent = n + ' tampil';
  }
  [cari, fj, ft].forEach(function (x) { if (x) x.addEventListener('input', saring); });
  saring();
  /* centang massal */
  var semua = document.getElementById('bim-semua'), terpilih = document.getElementById('bim-terpilih');
  function perbarui() {
    if (!terpilih) return;
    terpilih.textContent = document.querySelectorAll('input[name="pilih[]"]:checked').length + ' dicentang';
  }
  if (semua) semua.addEventListener('change', function () {
    baris.forEach(function (b) { if (!b.hidden) b.querySelector('input[type=checkbox]').checked = semua.checked; });
    perbarui();
  });
  document.querySelectorAll('input[name="pilih[]"]').forEach(function (c) { c.addEventListener('change', perbarui); });
})();
</script>
</body>
</html>
