<?php
/* ------------------------------------------------------------------
   Monitoring Pelaksanaan Bimbingan Tugas Akhir Mahasiswa. Terbuka penuh.

   Yang tampil publik hanya nama, judul penelitian, jenjang, dan status
   (ketetapan Dr. Dara). NIM, surel, dan catatan tidak pernah dikirim ke
   halaman ini. Status mengikuti urutan per jenjang di sesi.php:
   S1 lima tahap, S2 lima tahap, S3 sembilan tahap. Pendaftar yang belum
   disetujui ikut tampil dengan status menunggu.
   ------------------------------------------------------------------ */
require __DIR__ . '/../bimbingan-inti.php';

$mhs = muat_mhs();
$tunggu = [];
foreach (muat_daftar_akun() as $a) $tunggu[] = ['nama' => $a['nama'], 'jenjang' => $a['jenjang'], 'kelompok' => kelompok_bawaan($a['jenjang'], $a['angkatan']), 'judul' => ''];
foreach (muat_antrean() as $a) {
    $k = (string) $a['kelompok'];
    $j = mb_stripos($k, 'Disertasi') !== false ? 'S3' : (mb_stripos($k, 'Tesis') !== false ? 'S2' : 'S1');
    $tunggu[] = ['nama' => $a['nama'], 'jenjang' => $j, 'kelompok' => $k, 'judul' => ''];
}

$total = count($mhs);
$n_lulus = count(array_filter($mhs, 'sudah_lulus'));
$per_j = [];
foreach (JENJANG as $j => $_) {
    $anggota = array_filter($mhs, fn($m) => $m['jenjang'] === $j);
    $hit = [];
    foreach (status_jenjang($j) as $k => $_l) $hit[$k] = count(array_filter($anggota, fn($m) => $m['status'] === $k));
    $per_j[$j] = ['n' => count($anggota), 'hit' => $hit];
}
usort($mhs, fn($a, $b) => [$a['jenjang'], $a['kelompok'], $a['nama']] <=> [$b['jenjang'], $b['kelompok'], $b['nama']]);

function e($s): string { return htmlspecialchars((string) $s, ENT_QUOTES); }
function inisial(string $nama): string {
    $k = preg_split('/\s+/', trim($nama));
    return mb_strtoupper(mb_substr($k[0], 0, 1) . (count($k) > 1 ? mb_substr($k[1], 0, 1) : ''));
}
function kelas_status(array $m): string {
    if (sudah_lulus($m)) return 'lulus';
    return urut_status($m['jenjang'], $m['status']) <= 0 ? 'awal' : 'tengah';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Monitoring Pelaksanaan Bimbingan Tugas Akhir Mahasiswa, Dr. Despinur Dara</title>
<meta name="description" content="Monitoring bimbingan tugas akhir <?= $total ?> mahasiswa S1, S2, dan S3 Dr. Despinur Dara: <?= $n_lulus ?> lulus. Nama, judul, dan status diperbarui langsung.">
<link rel="canonical" href="https://despinurdara.id/bimbingan/progres.php">
<link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="../assets/style.css?v=<?= filemtime(__DIR__ . '/../assets/style.css') ?>">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600..800&family=Figtree:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="ak" data-grup="mengajar">
<header class="ak-bar">
  <div class="ak-bar-isi">
    <a class="ak-nama" href="../index.html"><svg class="ak-logo" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="8" fill="#e9b949"/><path d="M7.5 10.2c3-.9 5.9-.6 8.5 1.3v12.3c-2.6-1.9-5.5-2.2-8.5-1.3z" fill="#0f4c5c"/><path d="M24.5 10.2c-3-.9-5.9-.6-8.5 1.3v12.3c2.6-1.9 5.5-2.2 8.5-1.3z" fill="#0f4c5c" opacity=".72"/></svg><span>Belajar Bersama Dara</span></a>
    <span class="rekap-siapa"><a href="index.html">Bimbingan</a>
      &middot; <a href="lulus.php">Lulusan</a>
      &middot; <a href="panduan.html">Panduan</a>
      &middot; <a href="daftar.php">Daftar</a>
      &middot; <a href="../masuk.php">Masuk</a></span>
  </div>
</header>

<div class="prog-band">
  <div class="prog-band-isi">
    <p class="prog-band-kicker">Monitoring bimbingan <span class="langsung"><i></i>Data langsung</span></p>
    <h1>Monitoring Pelaksanaan Bimbingan Tugas Akhir Mahasiswa</h1>
    <p class="prog-band-lead">Status bimbingan mahasiswa S1, S2, dan S3, dari pengajuan topik sampai lulus.</p>
<?php if ($mhs): ?>
    <div class="prog-band-angka">
      <div><b class="hitung" data-akhir="<?= $total ?>"><?= $total ?></b><span>mahasiswa</span></div>
      <div><b class="hitung" data-akhir="<?= $total - $n_lulus ?>"><?= $total - $n_lulus ?></b><span>sedang berjalan</span></div>
      <div><b class="hitung" data-akhir="<?= $n_lulus ?>"><?= $n_lulus ?></b><span>lulus</span></div>
      <div><b><?= $per_j['S1']['n'] ?> / <?= $per_j['S2']['n'] ?> / <?= $per_j['S3']['n'] ?></b><span>S1 / S2 / S3</span></div>
    </div>
<?php endif; ?>
  </div>
</div>

<div class="ak-halaman bim-lebar">
<main class="ak-utama" id="konten">
  <nav class="remah" aria-label="Jejak lokasi"><a href="../index.html">Beranda</a><span class="remah-pisah">&rsaquo;</span><a href="index.html">Bimbingan karya ilmiah</a><span class="remah-pisah">&rsaquo;</span><span class="remah-kini">Monitoring bimbingan</span></nav>

  <div class="container">
<?php if (!$mhs): ?>
    <p class="masuk-galat">Data bimbingan belum tersedia.</p>
<?php else: ?>

    <h2>Tahapan per jenjang</h2>
<?php foreach (JENJANG as $j => $t): if (!$per_j[$j]['n']) continue; $akhir = status_akhir($j); ?>
    <div class="bim-alur">
      <p class="bim-alur-judul"><b><?= $j ?> &middot; <?= $t ?></b> <span><?= $per_j[$j]['n'] ?> mahasiswa</span></p>
      <ol class="bim-alur-tahap" style="--n: <?= count(status_jenjang($j)) ?>">
<?php foreach (status_jenjang($j) as $k => $lbl): ?>
        <li class="<?= $k === $akhir ? 'akhir' : '' ?><?= $per_j[$j]['hit'][$k] ? ' isi' : '' ?>"><b><?= $per_j[$j]['hit'][$k] ?></b><span><?= e($lbl) ?></span></li>
<?php endforeach; ?>
      </ol>
    </div>
<?php endforeach; ?>

    <h2>Daftar mahasiswa</h2>
    <div class="prog-kendali">
      <label class="sr-only" for="prog-cari">Cari nama atau judul</label>
      <input class="prog-cari" id="prog-cari" type="search" placeholder="Cari nama atau judul" autocomplete="off">
      <nav class="mk-saring" aria-label="Saring menurut status">
        <button class="mk-chip aktif" data-saring="semua" aria-pressed="true">Semua</button>
        <button class="mk-chip" data-saring="berjalan" aria-pressed="false">Sedang berjalan</button>
        <button class="mk-chip" data-saring="lulus" aria-pressed="false">Lulus</button>
<?php if ($tunggu): ?>
        <button class="mk-chip" data-saring="tunggu" aria-pressed="false">Menunggu persetujuan</button>
<?php endif; ?>
      </nav>
      <nav class="mk-saring mk-saring-jenjang" aria-label="Saring menurut jenjang">
        <span class="mk-saring-label">Jenjang</span>
        <button class="mk-chip aktif" data-jenjang="semua" aria-pressed="true">Semua</button>
<?php foreach (JENJANG as $j => $t): ?>
        <button class="mk-chip" data-jenjang="<?= $j ?>" aria-pressed="false"><?= $j ?></button>
<?php endforeach; ?>
      </nav>
      <span class="mk-saring-hasil" role="status"></span>
    </div>

    <div class="rekap-gulir">
      <table class="rekap-tabel mon-tabel">
        <thead><tr><th>Mahasiswa</th><th>Judul penelitian</th><th>Perjalanan</th><th>Status</th></tr></thead>
        <tbody id="mon-badan">
<?php foreach ($mhs as $m): $ks = kelas_status($m); $urut = urut_status($m['jenjang'], $m['status']); ?>
          <tr class="mon-baris" data-status="<?= $ks === 'lulus' ? 'lulus' : 'berjalan' ?>" data-jenjang="<?= e($m['jenjang']) ?>" data-nama="<?= e(mb_strtolower($m['nama'] . ' ' . $m['judul'])) ?>">
            <td class="mon-nama"><span class="prog-avatar bim-av-<?= $ks ?>" aria-hidden="true"><?= e(inisial($m['nama'])) ?></span><span><?= e($m['nama']) ?><small class="bim-kecil"><?= e($m['jenjang']) ?> &middot; <?= e($m['kelompok']) ?></small></span></td>
            <td class="mon-judul"><?= e($m['judul'] ?: '-') ?></td>
            <td><span class="alur-mini alur-datar" role="img" aria-label="Tahap <?= $urut + 1 ?> dari <?= count(status_jenjang($m['jenjang'])) ?>">
<?php foreach (array_keys(status_jenjang($m['jenjang'])) as $i => $_k): ?>
              <span class="alur-titik <?= $i <= $urut ? 'sudah' : '' ?> <?= $i === $urut + 1 ? 'kini' : '' ?>"><i></i></span>
<?php endforeach; ?>
            </span></td>
            <td><span class="bim-st bim-st-<?= $ks ?>"><?= e(label_status($m['jenjang'], $m['status'])) ?></span></td>
          </tr>
<?php endforeach; ?>
<?php foreach ($tunggu as $m): ?>
          <tr class="mon-baris" data-status="tunggu" data-jenjang="<?= e($m['jenjang']) ?>" data-nama="<?= e(mb_strtolower($m['nama'])) ?>">
            <td class="mon-nama"><span class="prog-avatar bim-av-tunggu" aria-hidden="true"><?= e(inisial($m['nama'])) ?></span><span><?= e($m['nama']) ?><small class="bim-kecil"><?= e($m['jenjang']) ?> &middot; <?= e($m['kelompok']) ?></small></span></td>
            <td class="mon-judul">-</td>
            <td></td>
            <td><span class="bim-st bim-st-tunggu">Menunggu persetujuan</span></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <nav class="mon-pager" aria-label="Halaman daftar mahasiswa">
      <button type="button" id="mon-mundur">&larr; Sebelumnya</button>
      <span id="mon-halaman"></span>
      <button type="button" id="mon-maju">Berikutnya &rarr;</button>
    </nav>

    <div class="prog-ajak">
      <div>
        <b>Mahasiswa bimbingan baru</b>
        <p>Baca alur bimbingan dan aturannya, lalu buat akun dengan NIM Anda.</p>
      </div>
      <div class="prog-ajak-tombol">
        <a class="btn primary" href="daftar.php">Daftar akun</a>
        <a class="btn" href="panduan.html">Panduan mahasiswa</a>
      </div>
    </div>
    <p class="bim-catatan">Diperbarui <?= e(tanggal_data_mhs()) ?>. Yang ditampilkan hanya nama,
    judul, dan status bimbingan.</p>
<?php endif; ?>
  </div>
</main>
</div>
<script>
(function () {
  var PER = 10;
  var chipStatus = document.querySelectorAll('.mk-saring:not(.mk-saring-jenjang) .mk-chip');
  var chipJenjang = document.querySelectorAll('.mk-saring-jenjang .mk-chip');
  var baris = Array.prototype.slice.call(document.querySelectorAll('.mon-baris'));
  var hasil = document.querySelector('.mk-saring-hasil');
  var cari = document.getElementById('prog-cari');
  var tHalaman = document.getElementById('mon-halaman');
  var bMundur = document.getElementById('mon-mundur');
  var bMaju = document.getElementById('mon-maju');
  if (!cari) return;
  var status = 'semua', jenjang = 'semua', hal = 1;

  function terapkan() {
    var q = (cari.value || '').toLowerCase().trim();
    var lolos = baris.filter(function (b) {
      return (status === 'semua' || b.dataset.status === status)
          && (jenjang === 'semua' || b.dataset.jenjang === jenjang)
          && (!q || b.dataset.nama.indexOf(q) !== -1);
    });
    var total = Math.max(1, Math.ceil(lolos.length / PER));
    if (hal > total) hal = total;
    var awal = (hal - 1) * PER;
    baris.forEach(function (b) { b.hidden = true; });
    lolos.slice(awal, awal + PER).forEach(function (b) { b.hidden = false; });
    if (hasil) hasil.textContent = lolos.length
      ? (awal + 1) + '–' + Math.min(awal + PER, lolos.length) + ' dari ' + lolos.length + ' mahasiswa'
      : 'Tidak ada yang cocok';
    tHalaman.textContent = 'Halaman ' + hal + ' dari ' + total;
    bMundur.disabled = hal <= 1;
    bMaju.disabled = hal >= total;
  }
  function pasangChip(grup, saatKlik) {
    grup.forEach(function (b) {
      b.addEventListener('click', function () {
        saatKlik(b); hal = 1;
        grup.forEach(function (x) {
          x.classList.toggle('aktif', x === b);
          x.setAttribute('aria-pressed', x === b ? 'true' : 'false');
        });
        terapkan();
      });
    });
  }
  pasangChip(chipStatus, function (b) { status = b.dataset.saring; });
  pasangChip(chipJenjang, function (b) { jenjang = b.dataset.jenjang; });
  cari.addEventListener('input', function () { hal = 1; terapkan(); });
  bMundur.addEventListener('click', function () { hal--; terapkan(); });
  bMaju.addEventListener('click', function () { hal++; terapkan(); });
  terapkan();

  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.querySelectorAll('.hitung').forEach(function (el) {
      var akhir = parseInt(el.dataset.akhir, 10) || 0;
      var t0 = null;
      function tik(t) {
        if (!t0) t0 = t;
        var p = Math.min((t - t0) / 800, 1);
        el.textContent = Math.round(akhir * (1 - Math.pow(1 - p, 3)));
        if (p < 1) requestAnimationFrame(tik);
      }
      el.textContent = '0';
      requestAnimationFrame(tik);
    });
  }
})();
</script>
</body>
</html>
