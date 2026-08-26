<?php
/* ------------------------------------------------------------------
   Monitoring Pelaksanaan Bimbingan Tugas Akhir Mahasiswa. Fitur unggulan portal, terbuka penuh.

   Statusnya open data yang sudah dikonfirmasi Dr. Dara dan kampus:
   nama mahasiswa dan tahap kemajuannya boleh tampil terbuka. Catatan
   bimbingan per orang tetap di balik pintu masuk.

   Desainnya dibuat sebagai papan hidup: pita gelap-teal dengan angka
   besar, alur tahapan sebagai pipa bertingkat, kartu mahasiswa dengan
   inisial berwarna tahapnya, pencarian nama seketika, dan pendaftar
   baru yang belum diverifikasi ikut tampil abu-abu. Halaman ini
   dinamis, membaca berkas data server langsung, supaya pendaftaran
   dan hasil verifikasi muncul seketika.
   ------------------------------------------------------------------ */
require __DIR__ . '/../sesi.php';

$LABEL = ['lulus' => 'Lulus', 'sempro' => 'Sempro', 'judul' => 'Tahap judul',
          'belum' => 'Belum berjalan', 'tunggu' => 'Menunggu verifikasi'];

$data = muat_bimbingan();
$daftar = $data['mahasiswa'] ?? [];
$antre = muat_antrean();

$kelompok = [];
foreach ($daftar as $m) {
    $m['status'] = $m['tahap'];
    $kelompok[$m['kelompok']][] = $m;
}
foreach ($antre as $a) {
    $kelompok[$a['kelompok']][] = ['nama' => $a['nama'], 'status' => 'tunggu'];
}

$hit = fn($t) => count(array_filter($daftar, fn($m) => $m['tahap'] === $t));
$total = count($daftar);
$n = ['judul' => $hit('judul'), 'sempro' => $hit('sempro'),
      'lulus' => $hit('lulus'), 'belum' => $hit('belum'), 'tunggu' => count($antre)];

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }
function inisial(string $nama): string {
    $k = preg_split('/\s+/', trim($nama));
    $a = mb_substr($k[0], 0, 1);
    $b = count($k) > 1 ? mb_substr($k[1], 0, 1) : '';
    return mb_strtoupper($a . $b);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Monitoring Pelaksanaan Bimbingan Tugas Akhir Mahasiswa, Dr. Despinur Dara</title>
<meta name="description" content="Monitoring pelaksanaan bimbingan tugas akhir <?= $total ?> mahasiswa Dr. Despinur Dara dari skripsi sampai disertasi: <?= $n['lulus'] ?> lulus, diperbarui langsung dari rekap bimbingan.">
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
      &middot; <a href="daftar.php">Daftar</a>
      &middot; <a href="../masuk.php">Masuk</a></span>
  </div>
</header>

<div class="prog-band">
  <div class="prog-band-isi">
    <p class="prog-band-kicker">Monitoring bimbingan <span class="langsung"><i></i>Data langsung</span></p>
    <h1>Monitoring Pelaksanaan Bimbingan Tugas Akhir Mahasiswa</h1>
    <p class="prog-band-lead">Dari topik pertama sampai lulus, progres seluruh
    mahasiswa bimbingan Dr. Dara terbuka sebagai data publik dan diperbarui
    langsung dari rekap.</p>
<?php if ($daftar): ?>
    <div class="prog-band-angka">
      <div><b class="hitung" data-akhir="<?= $total ?>"><?= $total ?></b><span>mahasiswa</span></div>
      <div><b class="hitung" data-akhir="<?= $n['lulus'] ?>"><?= $n['lulus'] ?></b><span>lulus</span></div>
      <div><b class="hitung" data-akhir="<?= $n['sempro'] ?>"><?= $n['sempro'] ?></b><span>sampai sempro</span></div>
      <div><b>3</b><span>jenjang, S1 sampai S3</span></div>
    </div>
<?php endif; ?>
  </div>
</div>

<div class="ak-halaman">
<main class="ak-utama" id="konten">
  <nav class="remah" aria-label="Jejak lokasi"><a href="../index.html">Beranda</a><span class="remah-pisah">&rsaquo;</span><a href="index.html">Bimbingan karya ilmiah</a><span class="remah-pisah">&rsaquo;</span><span class="remah-kini">Monitoring bimbingan</span></nav>

  <div class="container">
<?php if (!$daftar): ?>
    <p class="masuk-galat">Data bimbingan belum terunggah di server.</p>
<?php else: ?>

    <div class="prog-alur" role="img"
         aria-label="Alur bimbingan: <?= $n['judul'] ?> di tahap judul, <?= $n['sempro'] ?> sampai sempro, <?= $n['lulus'] ?> lulus, <?= $n['belum'] ?> belum berjalan, <?= $n['tunggu'] ?> menunggu verifikasi">
      <div class="prog-alur-tahap t-judul"><b><?= $n['judul'] ?></b><span>Tahap judul</span></div>
      <span class="prog-alur-panah" aria-hidden="true">&rsaquo;</span>
      <div class="prog-alur-tahap t-sempro"><b><?= $n['sempro'] ?></b><span>Sempro</span></div>
      <span class="prog-alur-panah" aria-hidden="true">&rsaquo;</span>
      <div class="prog-alur-tahap t-lulus"><b><?= $n['lulus'] ?></b><span>Lulus</span></div>
      <div class="prog-alur-samping">
        <span><b><?= $n['belum'] ?></b> belum berjalan</span>
        <span><b><?= $n['tunggu'] ?></b> menunggu verifikasi</span>
      </div>
    </div>

    <div class="prog-kendali">
      <label class="sr-only" for="prog-cari">Cari nama mahasiswa</label>
      <input class="prog-cari" id="prog-cari" type="search"
             placeholder="Cari nama mahasiswa" autocomplete="off">
      <nav class="mk-saring" aria-label="Saring menurut status">
        <button class="mk-chip aktif" data-saring="semua" aria-pressed="true">Semua</button>
<?php foreach ($LABEL as $k => $t): ?>
        <button class="mk-chip" data-saring="<?= $k ?>" aria-pressed="false"><?= $t ?></button>
<?php endforeach; ?>
      </nav>
      <span class="mk-saring-hasil" role="status"></span>
    </div>

<?php foreach ($kelompok as $nama_k => $anggota): ?>
    <section class="prog-kelompok">
      <h2><?= e($nama_k) ?> <span class="rekap-jumlah"><?= count($anggota) ?> mahasiswa</span></h2>
      <ul class="prog-rak">
<?php foreach ($anggota as $m):
        $maju = ['tunggu' => 0, 'belum' => 1, 'judul' => 2, 'sempro' => 3, 'lulus' => 4][$m['status']];
?>
        <li class="prog-pil status-<?= e($m['status']) ?>" data-status="<?= e($m['status']) ?>"
            data-nama="<?= e(mb_strtolower($m['nama'])) ?>">
          <span class="prog-kartu-atas">
            <span class="prog-avatar" aria-hidden="true"><?= e(inisial($m['nama'])) ?></span>
            <span class="prog-teks">
              <span class="prog-nama"><?= e($m['nama']) ?></span>
              <span class="prog-status"><?= e($LABEL[$m['status']]) ?></span>
            </span>
          </span>
          <span class="alur-mini" role="img" aria-label="Tahap: <?= e($LABEL[$m['status']]) ?>">
<?php foreach ([1 => 'Daftar', 2 => 'Judul', 3 => 'Sempro', 4 => 'Lulus'] as $tk => $nm): ?>
            <span class="alur-titik <?= $maju >= $tk ? 'sudah' : '' ?> <?= ($maju === $tk - 1 || ($maju === 0 && $tk === 1)) ? 'kini' : '' ?>"><i></i><em><?= $nm ?></em></span>
<?php endforeach; ?>
          </span>
        </li>
<?php endforeach; ?>
      </ul>
    </section>
<?php endforeach; ?>

    <div class="prog-ajak">
      <div>
        <b>Mahasiswa bimbingan baru?</b>
        <p>Daftarkan diri Anda; nama Anda tampil di papan monitoring begitu terkirim,
        berstatus menunggu sampai diverifikasi Dr. Dara.</p>
      </div>
      <div class="prog-ajak-tombol">
        <a class="btn primary" href="daftar.php">Daftar bimbingan</a>
        <a class="btn" href="../masuk.php">Masuk area bimbingan</a>
      </div>
    </div>
    <p class="bim-catatan">Rekap diperbarui <?= e($data['diperbarui'] ?? '-') ?>.
    Nama dan tahap adalah data terbuka yang sudah dikonfirmasi program studi;
    catatan bimbingan per mahasiswa hanya terbuka bagi yang bersangkutan dan dosen.</p>
<?php endif; ?>
  </div>
</main>
</div>
<script>
(function () {
  var chip = document.querySelectorAll('.mk-chip');
  var pil = document.querySelectorAll('.prog-pil');
  var hasil = document.querySelector('.mk-saring-hasil');
  var cari = document.getElementById('prog-cari');
  var status = 'semua';
  function saring() {
    var q = (cari.value || '').toLowerCase().trim();
    var tampil = 0;
    pil.forEach(function (p) {
      var cocok = (status === 'semua' || p.dataset.status === status)
        && (!q || p.dataset.nama.indexOf(q) !== -1);
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
      status = b.dataset.saring;
      chip.forEach(function (x) {
        x.classList.toggle('aktif', x === b);
        x.setAttribute('aria-pressed', x === b ? 'true' : 'false');
      });
      saring();
    });
  });
  cari.addEventListener('input', saring);

  /* Angka pita menghitung naik saat halaman terbuka, dimatikan bagi
     pengguna yang meminta gerak dikurangi. */
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
