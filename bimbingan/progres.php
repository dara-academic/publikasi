<?php
/* ------------------------------------------------------------------
   Papan progres bimbingan. Terbuka untuk publik.

   Sesuai ketentuan yang disepakati Dr. Dara: nama dan tahap kemajuan
   boleh tampil terbuka, sedangkan catatan bimbingan dan rinciannya
   tetap di balik pintu masuk. Pendaftar yang belum diverifikasi ikut
   tampil dengan warna abu dan label menunggu, supaya mahasiswa yang
   baru mendaftar langsung melihat dirinya di papan.

   Halaman ini dinamis, dibaca langsung dari berkas data di server,
   supaya pendaftar baru dan hasil verifikasi admin muncul seketika
   tanpa menunggu situs dibangun ulang.
   ------------------------------------------------------------------ */
require __DIR__ . '/../sesi.php';

$LABEL = ['lulus' => 'Lulus', 'sempro' => 'Sempro', 'judul' => 'Tahap judul',
          'belum' => 'Belum berjalan', 'tunggu' => 'Menunggu verifikasi'];

$b = __DIR__ . '/../data/bimbingan.json';
$data = is_file($b) ? json_decode((string) file_get_contents($b), true) : null;
$daftar = $data['mahasiswa'] ?? [];

$antre = is_file(__DIR__ . '/../data/pendaftaran.json')
    ? (json_decode((string) file_get_contents(__DIR__ . '/../data/pendaftaran.json'), true) ?: [])
    : [];

$kelompok = [];
foreach ($daftar as $m) {
    $m['status'] = $m['tahap'];
    $kelompok[$m['kelompok']][] = $m;
}
foreach ($antre as $a) {
    $kelompok[$a['kelompok']][] = ['nama' => $a['nama'], 'status' => 'tunggu'];
}

$total = count($daftar);
$n_lulus = count(array_filter($daftar, fn($m) => $m['tahap'] === 'lulus'));
$n_sempro = count(array_filter($daftar, fn($m) => $m['tahap'] === 'sempro'));
$n_tunggu = count($antre);

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Papan Progres Bimbingan Dr. Despinur Dara</title>
<meta name="description" content="Progres <?= $total ?> mahasiswa bimbingan Dr. Despinur Dara dari skripsi sampai disertasi, diperbarui langsung dari rekap bimbingan.">
<link rel="canonical" href="https://despinurdara.id/bimbingan/progres.php">
<link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="../assets/style.css?v=<?= filemtime(__DIR__ . '/../assets/style.css') ?>">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Source+Serif+4:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="ak" data-grup="mengajar">
<header class="ak-bar">
  <div class="ak-bar-isi">
    <a class="ak-nama" href="../index.html">Despinur Dara</a>
    <span class="rekap-siapa"><a href="index.html">Bimbingan</a>
      &middot; <a href="../masuk.php">Masuk</a></span>
  </div>
</header>
<div class="ak-halaman">
<main class="ak-utama" id="konten">
  <nav class="remah" aria-label="Jejak lokasi"><a href="../index.html">Beranda</a><span class="remah-pisah">&rsaquo;</span><a href="index.html">Bimbingan karya ilmiah</a><span class="remah-pisah">&rsaquo;</span><span class="remah-kini">Papan progres</span></nav>

  <section class="hero hero-tipis">
    <div class="container">
      <p class="kicker">Papan progres</p>
      <h1>Progres bimbingan</h1>
      <p class="lead">Perjalanan setiap mahasiswa bimbingan dari topik sampai
      lulus, diperbarui langsung dari rekap. Rincian dan catatan bimbingan
      hanya terbuka setelah masuk.</p>
    </div>
  </section>

  <div class="container">
<?php if (!$daftar): ?>
    <p class="masuk-galat">Data bimbingan belum terunggah di server.</p>
<?php else: ?>
    <div class="ak-angka">
      <div><b><?= $total ?></b><span>mahasiswa bimbingan</span></div>
      <div><b><?= $n_lulus ?></b><span>lulus</span></div>
      <div><b><?= $n_sempro ?></b><span>sampai sempro</span></div>
      <div><b><?= $n_tunggu ?></b><span>menunggu verifikasi</span></div>
    </div>

    <nav class="mk-saring" aria-label="Saring menurut status">
      <span class="mk-saring-label">Status</span>
      <button class="mk-chip aktif" data-saring="semua" aria-pressed="true">Semua</button>
<?php foreach ($LABEL as $k => $t): ?>
      <button class="mk-chip" data-saring="<?= $k ?>" aria-pressed="false"><?= $t ?></button>
<?php endforeach; ?>
      <span class="mk-saring-hasil" role="status"></span>
    </nav>

<?php foreach ($kelompok as $nama_k => $anggota): ?>
    <section class="prog-kelompok">
      <h2><?= e($nama_k) ?> <span class="rekap-jumlah"><?= count($anggota) ?> mahasiswa</span></h2>
      <ul class="prog-rak">
<?php foreach ($anggota as $m): ?>
        <li class="prog-pil status-<?= e($m['status']) ?>" data-status="<?= e($m['status']) ?>">
          <span class="prog-nama"><?= e($m['nama']) ?></span>
          <span class="prog-status"><?= e($LABEL[$m['status']]) ?></span>
        </li>
<?php endforeach; ?>
      </ul>
    </section>
<?php endforeach; ?>

    <div class="prog-ajak">
      <div>
        <b>Mahasiswa bimbingan baru?</b>
        <p>Daftarkan diri Anda; nama Anda tampil di papan ini begitu terkirim,
        dan kode akses dikirim setelah diverifikasi Dr. Dara.</p>
      </div>
      <div class="prog-ajak-tombol">
        <a class="btn primary" href="daftar.php">Daftar bimbingan</a>
        <a class="btn" href="../masuk.php">Masuk area bimbingan</a>
      </div>
    </div>
    <p class="bim-catatan">Rekap diperbarui <?= e($data['diperbarui'] ?? '-') ?>.
    Nama dan tahap tampil sesuai ketentuan program studi; catatan bimbingan
    per mahasiswa hanya terbuka bagi yang bersangkutan dan dosen.</p>
<?php endif; ?>
  </div>
</main>
</div>
<script>
(function () {
  var chip = document.querySelectorAll('.mk-chip');
  var pil = document.querySelectorAll('.prog-pil');
  var hasil = document.querySelector('.mk-saring-hasil');
  function saring(nilai) {
    var tampil = 0;
    pil.forEach(function (p) {
      var cocok = nilai === 'semua' || p.dataset.status === nilai;
      p.hidden = !cocok;
      if (cocok) tampil++;
    });
    document.querySelectorAll('.prog-kelompok').forEach(function (s) {
      s.hidden = ![].some.call(s.querySelectorAll('.prog-pil'), function (p) { return !p.hidden; });
    });
    if (hasil) hasil.textContent = tampil + ' dari ' + pil.length + ' mahasiswa';
  }
  chip.forEach(function (b) {
    b.addEventListener('click', function () {
      chip.forEach(function (x) {
        x.classList.toggle('aktif', x === b);
        x.setAttribute('aria-pressed', x === b ? 'true' : 'false');
      });
      saring(b.dataset.saring);
    });
  });
})();
</script>
</body>
</html>
