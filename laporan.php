<?php
/*
 * Laporan kunjungan.
 *
 * Halaman ini membaca catatan harian yang ditulis catat.php dan merangkumnya.
 * Dilindungi kunci yang harus diganti sendiri sebelum dipakai. Kuncinya bukan
 * pengamanan tingkat tinggi, hanya penghalang agar alamatnya tidak terbuka
 * kalau tertebak, dan itu memadai karena isinya hanya hitungan kunjungan tanpa
 * satu pun data pribadi.
 */

// GANTI kunci di bawah ini dengan kata sandi Anda sendiri sebelum dipakai.
$KUNCI = 'GANTI-KUNCI-INI';

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

if ($KUNCI === 'GANTI-KUNCI-INI') {
    exit('<p style="font:16px system-ui;padding:40px">Kunci laporan belum diganti. '
       . 'Buka berkas laporan.php, ganti nilai $KUNCI, lalu unggah ulang.</p>');
}
if (($_GET['kunci'] ?? '') !== $KUNCI) {
    http_response_code(403);
    exit('<p style="font:16px system-ui;padding:40px">Kunci tidak cocok.</p>');
}

$hari = max(1, min(90, (int) ($_GET['hari'] ?? 30)));
$dir = __DIR__ . '/data';

$halaman = $rujukan = $harian = [];
$kunjungan = 0;
$orang = [];

for ($i = 0; $i < $hari; $i++) {
    $tgl = gmdate('Y-m-d', strtotime("-$i day"));
    $berkas = "$dir/$tgl.tsv";
    if (!is_file($berkas)) { continue; }
    foreach (file($berkas, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $baris) {
        $k = explode("\t", $baris);
        if (count($k) < 3) { continue; }
        [$waktu, $sidik, $hal] = $k;
        $ruj = $k[3] ?? '';
        $kunjungan++;
        $orang["$tgl|$sidik"] = true;
        $halaman[$hal] = ($halaman[$hal] ?? 0) + 1;
        $harian[$tgl] = ($harian[$tgl] ?? 0) + 1;
        if ($ruj !== '') {
            $asal = parse_url($ruj, PHP_URL_HOST) ?: $ruj;
            $rujukan[$asal] = ($rujukan[$asal] ?? 0) + 1;
        }
    }
}
arsort($halaman); arsort($rujukan); ksort($harian);

function tabel($judul, $data, $batas = 25) {
    if (!$data) { return "<h2>$judul</h2><p>Belum ada data.</p>"; }
    $maks = max($data);
    $h = "<h2>$judul</h2><table>";
    $n = 0;
    foreach ($data as $k => $v) {
        if ($n++ >= $batas) { break; }
        $lebar = $maks ? round($v / $maks * 100) : 0;
        $k = htmlspecialchars($k, ENT_QUOTES, 'UTF-8');
        $h .= "<tr><td>$k</td><td class=n>$v</td>"
            . "<td class=b><span style=\"width:{$lebar}%\"></span></td></tr>";
    }
    return $h . '</table>';
}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Laporan kunjungan despinurdara.id</title>
<style>
  body { font: 16px/1.6 "Inter", system-ui, sans-serif; color: #2b3440;
         max-width: 900px; margin: 0 auto; padding: 40px 24px 80px; }
  h1 { font-size: 30px; letter-spacing: -.02em; margin-bottom: 6px; }
  h2 { font-size: 20px; margin: 38px 0 12px; padding-bottom: 8px;
       border-bottom: 1px solid #eae6e0; }
  .ringkas { display: flex; gap: 12px; flex-wrap: wrap; margin: 22px 0; }
  .ringkas div { flex: 1 1 150px; background: #fdfcfa; border: 1px solid #eae6e0;
                 border-radius: 10px; padding: 14px 16px; }
  .ringkas b { display: block; font-size: 28px; color: #2c5580; line-height: 1; }
  .ringkas span { font-size: 12.5px; color: #66717f; }
  table { width: 100%; border-collapse: collapse; font-size: 14.5px; }
  td { padding: 7px 8px; border-bottom: 1px solid #f0ece6; vertical-align: middle; }
  td:first-child { word-break: break-all; }
  .n { text-align: right; width: 64px; font-weight: 600; color: #2c5580; }
  .b { width: 34%; }
  .b span { display: block; height: 8px; background: #d7ebff; border-radius: 99px; }
  p.ket { font-size: 13.5px; color: #66717f; }
</style>
</head>
<body>
<h1>Laporan kunjungan</h1>
<p class="ket">despinurdara.id &middot; <?= $hari ?> hari terakhir &middot;
   dihitung tanpa kuki dan tanpa menyimpan alamat IP</p>

<div class="ringkas">
  <div><b><?= number_format($kunjungan) ?></b><span>halaman dibuka</span></div>
  <div><b><?= number_format(count($orang)) ?></b><span>pengunjung harian</span></div>
  <div><b><?= number_format(count($halaman)) ?></b><span>halaman berbeda</span></div>
  <div><b><?= number_format(count($rujukan)) ?></b><span>situs perujuk</span></div>
</div>

<?= tabel('Halaman paling sering dibuka', $halaman) ?>
<?= tabel('Situs perujuk', $rujukan, 15) ?>
<?= tabel('Kunjungan per hari', array_reverse($harian, true), 30) ?>

<p class="ket">Pengunjung harian dihitung dari sidik yang garamnya berganti tiap
hari, jadi orang yang sama pada dua hari berbeda terhitung dua kali. Itu
disengaja: tanpa penanda yang bertahan lintas hari, tidak ada jejak yang bisa
dirangkai menjadi riwayat seseorang.</p>
</body>
</html>
