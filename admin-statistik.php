<?php
/* ------------------------------------------------------------------
   Statistik. Hanya admin.

   Ringkasan kunjungan, unduhan, dan buka paper/buku, dibaca dari
   penghitung sederhana di statistik.json. Tanpa cookie, tanpa pihak
   ketiga: semua angka dari server sendiri.
   ------------------------------------------------------------------ */
require __DIR__ . '/sesi.php';
$pengguna = wajib_masuk_segar();
if ($pengguna['peran'] !== 'admin') {
    header('Location: /bimbingan/rekap.php');
    exit;
}

function ee($s): string { return htmlspecialchars((string) $s, ENT_QUOTES); }

$stat = baca_statistik();
$lihat = $unduh = $buka = [];
foreach ($stat as $k => $v) {
    if (strncmp($k, 'lihat:', 6) === 0)      $lihat[substr($k, 6)] = (int) $v;
    elseif (strncmp($k, 'unduh:', 6) === 0)  $unduh[substr($k, 6)] = (int) $v;
    elseif (strncmp($k, 'buka:', 5) === 0)   $buka[substr($k, 5)]  = (int) $v;
}
arsort($lihat); arsort($unduh); arsort($buka);
$total_lihat = array_sum($lihat);
$total_unduh = array_sum($unduh);
$total_buka  = array_sum($buka);

$judul_paper = $judul_buku = [];
foreach (muat_paper() as $p) $judul_paper[(int) $p['id']] = $p['judul'];
foreach (muat_buku() as $b)  $judul_buku[(int) $b['id']]  = $b['judul'];

function nama_buka(string $k, array $jp, array $jb): string {
    [$t, $id] = array_pad(explode(':', $k, 2), 2, '');
    $id = (int) $id;
    if ($t === 'paper') return $jp[$id] ?? ('Paper #' . $id . ' (dihapus)');
    if ($t === 'buku')  return $jb[$id] ?? ('Buku #' . $id . ' (dihapus)');
    return $k;
}
$kosong = ($total_lihat + $total_unduh + $total_buka) === 0;

/* ------------------------------------------------------------------
   Log kunjungan harian dari catat.php (data/<hari>.tsv): waktu, sidik
   harian, halaman, perujuk, lebar layar. Diringkas jadi tren kunjungan,
   perujuk teratas, dan sebaran perangkat.
   ------------------------------------------------------------------ */
$tsv = glob(__DIR__ . '/data/*.tsv') ?: [];
sort($tsv);
$tsv = array_slice($tsv, -30);
$per_hari = [];
$rujukan = [];
$perangkat = ['Ponsel' => 0, 'Tablet' => 0, 'Desktop' => 0];
foreach ($tsv as $berkas) {
    $hari = basename($berkas, '.tsv');
    $fh = @fopen($berkas, 'r');
    if (!$fh) continue;
    $orang = []; $kunj = 0;
    while (($ln = fgets($fh)) !== false) {
        $c = explode("\t", rtrim($ln, "\r\n"));
        if (count($c) < 5) continue;
        $kunj++;
        $orang[$c[1]] = 1;
        $w = (int) $c[4];
        if ($w > 0) {
            if ($w < 768) $perangkat['Ponsel']++;
            elseif ($w < 1024) $perangkat['Tablet']++;
            else $perangkat['Desktop']++;
        }
        $ruj = trim($c[3]);
        if ($ruj !== '') {
            $host = parse_url($ruj, PHP_URL_HOST) ?: $ruj;
            $host = preg_replace('/^www\./', '', strtolower($host));
            if ($host !== '') $rujukan[$host] = ($rujukan[$host] ?? 0) + 1;
        }
    }
    fclose($fh);
    $per_hari[$hari] = ['kunjungan' => $kunj, 'orang' => count($orang)];
}
arsort($rujukan);
$ada_tsv = !empty($per_hari);
$tren = array_slice($per_hari, -14, null, true);
$maks_tren = $tren ? max(array_map(fn($x) => $x['kunjungan'], $tren)) : 0;
$total_perangkat = array_sum($perangkat);

function hari_pendek(string $ymd): string {
    $t = date_create($ymd);
    return $t ? $t->format('d/m') : $ymd;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Statistik, Portal Dr. Despinur Dara</title>
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
    <h1>Statistik</h1>
    <p class="admin-band-lead">Angka kunjungan, unduhan, dan buka paper atau buku.
    Dihitung sendiri di server, tanpa cookie dan tanpa pihak ketiga.</p>
  </div>
</div>

<nav class="admin-menu" aria-label="Menu panel admin">
  <a href="admin.php">&larr; Panel admin</a>
  <a href="akun.php">Bimbingan &amp; akun</a>
  <a href="admin-materi.php">Materi kuliah</a>
  <a href="admin-bedah.php">Bedah paper</a>
  <a href="admin-buku.php">Buku</a>
  <a href="admin-statistik.php" class="active">Statistik</a>
  <a href="admin-komentar.php">Tanya jawab</a>
  <a href="admin-cadangan.php">Cadangan</a>
</nav>

<main class="ak-utama admin-utama" id="konten">

  <div class="prog-band-angka stat-ringkas">
    <div><b><?= number_format($total_lihat, 0, ',', '.') ?></b><span>kunjungan halaman</span></div>
    <div><b><?= number_format($total_unduh, 0, ',', '.') ?></b><span>unduhan materi</span></div>
    <div><b><?= number_format($total_buka, 0, ',', '.') ?></b><span>buka paper &amp; buku</span></div>
  </div>

  <?php if ($kosong): ?>
    <section class="admin-kartu"><p class="admin-kosong">Belum ada data. Angka akan muncul begitu ada pengunjung, unduhan, atau klik paper/buku.</p></section>
  <?php else: ?>

    <section class="admin-kartu">
      <h2>Halaman terpopuler</h2>
      <?php if (!$lihat): ?><p class="admin-kosong">Belum ada kunjungan tercatat.</p><?php else: ?>
      <table class="stat-tabel">
        <tbody>
          <?php $i=0; foreach ($lihat as $hal => $n): if (++$i > 15) break; ?>
            <tr><td><a href="<?= ee($hal) ?>" target="_blank" rel="noopener"><?= ee($hal) ?></a></td><td class="stat-n"><?= number_format($n,0,',','.') ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </section>

    <section class="admin-kartu">
      <h2>Materi paling banyak diunduh</h2>
      <?php if (!$unduh): ?><p class="admin-kosong">Belum ada unduhan tercatat.</p><?php else: ?>
      <table class="stat-tabel">
        <tbody>
          <?php $i=0; foreach ($unduh as $berkas => $n): if (++$i > 15) break; ?>
            <tr><td><?= ee($berkas) ?></td><td class="stat-n"><?= number_format($n,0,',','.') ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </section>

    <section class="admin-kartu">
      <h2>Paper &amp; buku paling banyak dibuka</h2>
      <?php if (!$buka): ?><p class="admin-kosong">Belum ada klik tercatat.</p><?php else: ?>
      <table class="stat-tabel">
        <tbody>
          <?php $i=0; foreach ($buka as $k => $n): if (++$i > 15) break; ?>
            <tr><td><?= ee(nama_buka($k, $judul_paper, $judul_buku)) ?></td><td class="stat-n"><?= number_format($n,0,',','.') ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </section>

  <?php endif; ?>

  <?php if ($ada_tsv): ?>
    <section class="admin-kartu">
      <h2>Tren kunjungan 14 hari</h2>
      <div class="stat-tren">
        <?php foreach ($tren as $hari => $d): $tinggi = $maks_tren > 0 ? round($d['kunjungan'] / $maks_tren * 100) : 0; ?>
          <div class="stat-tren-kolom" title="<?= ee($hari) ?>: <?= (int) $d['kunjungan'] ?> kunjungan, <?= (int) $d['orang'] ?> pengunjung">
            <span class="stat-tren-bar" style="height: <?= max($tinggi, 2) ?>%"></span>
            <span class="stat-tren-tgl"><?= ee(hari_pendek($hari)) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="stat-catatan">Batang = kunjungan halaman per hari. Arahkan kursor untuk lihat jumlah pengunjung uniknya.</p>
    </section>

    <div class="admin-kolom2">
      <section class="admin-kartu">
        <h2>Datang dari mana</h2>
        <?php if (!$rujukan): ?><p class="admin-kosong">Belum ada perujuk luar. Pengunjung datang langsung atau dari tautan yang tak mengirim perujuk.</p><?php else: ?>
        <table class="stat-tabel">
          <tbody>
            <?php $i = 0; foreach ($rujukan as $host => $n): if (++$i > 10) break; ?>
              <tr><td><?= ee($host) ?></td><td class="stat-n"><?= number_format($n, 0, ',', '.') ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </section>

      <section class="admin-kartu">
        <h2>Perangkat pengunjung</h2>
        <?php if ($total_perangkat === 0): ?><p class="admin-kosong">Belum ada data perangkat.</p><?php else: ?>
        <table class="stat-tabel">
          <tbody>
            <?php foreach ($perangkat as $nama => $n): $pct = round($n / $total_perangkat * 100); ?>
              <tr><td><?= ee($nama) ?></td><td class="stat-n"><?= $pct ?>% <span class="stat-kecil">(<?= number_format($n, 0, ',', '.') ?>)</span></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </section>
    </div>
  <?php endif; ?>

  <p class="admin-pulang"><a href="admin.php">&larr; Kembali ke panel admin</a></p>
</main>
</body>
</html>
