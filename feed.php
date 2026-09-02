<?php
/* ------------------------------------------------------------------
   Umpan RSS dinamis: materi kuliah, paper, dan buku terbaru dari manifes,
   diurutkan dari yang paling baru. Menggantikan feed.xml statis supaya
   tiap unggahan baru otomatis sampai ke pelanggan RSS tanpa disunting
   tangan. Materi menaut ke halaman mata kuliahnya.
   ------------------------------------------------------------------ */
require __DIR__ . '/sesi.php';

header('Content-Type: application/rss+xml; charset=utf-8');
header('Cache-Control: max-age=600');

const SITUS = 'https://despinurdara.id/';

function x($s): string { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_XML1, 'UTF-8'); }
function rfc822(string $tgl): string {
    $t = $tgl !== '' ? strtotime($tgl . ' 07:00:00 UTC') : false;
    return gmdate('r', $t ?: time());
}

$nama = mk_nama();
$items = [];

foreach (muat_materi() as $m) {
    $mk = (string) ($m['mk'] ?? '');
    $desk = (string) ($m['deskripsi'] ?? '');
    $items[] = [
        't'   => 'Materi baru: ' . (string) ($m['judul'] ?? ''),
        'l'   => SITUS . (isset($nama[$mk]) ? 'mata-kuliah/' . $mk . '.html' : 'mengajar.html'),
        'd'   => $desk !== '' ? $desk : (($nama[$mk] ?? 'Materi kuliah') . ', bisa diunduh.'),
        'g'   => 'materi-' . (string) ($m['berkas'] ?? ''),
        'tgl' => (string) ($m['tanggal'] ?? ''),
    ];
}
foreach (muat_paper() as $p) {
    $items[] = [
        't'   => 'Paper: ' . (string) ($p['judul'] ?? ''),
        'l'   => SITUS . 'paper.php',
        'd'   => trim((string) ($p['jurnal'] ?? '') . ' ' . (string) ($p['indeks'] ?? '')),
        'g'   => 'paper-' . (string) ($p['id'] ?? ''),
        'tgl' => (string) ($p['tanggal'] ?? ''),
    ];
}
foreach (muat_buku() as $b) {
    $items[] = [
        't'   => 'Buku: ' . (string) ($b['judul'] ?? ''),
        'l'   => SITUS . 'buku.php',
        'd'   => (string) ($b['penerbit'] ?? '') ?: 'Buku terbit.',
        'g'   => 'buku-' . (string) ($b['id'] ?? ''),
        'tgl' => (string) ($b['tanggal'] ?? ''),
    ];
}

usort($items, fn($a, $b) => strcmp($b['tgl'], $a['tgl']));
$items = array_slice($items, 0, 25);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss version="2.0"><channel>' . "\n";
echo '<title>Materi &amp; publikasi terbaru, Dr. Despinur Dara</title>' . "\n";
echo '<link>' . SITUS . "</link>\n";
echo '<description>Materi kuliah, paper, dan buku terbaru dari portal belajar Dr. Despinur Dara.</description>' . "\n";
echo "<language>id</language>\n";
foreach ($items as $it) {
    echo "<item>\n";
    echo '  <title>' . x($it['t']) . "</title>\n";
    echo '  <link>' . x($it['l']) . "</link>\n";
    echo '  <guid isPermaLink="false">' . x($it['g']) . "</guid>\n";
    echo '  <pubDate>' . rfc822($it['tgl']) . "</pubDate>\n";
    echo '  <description>' . x($it['d']) . "</description>\n";
    echo "</item>\n";
}
echo "</channel></rss>";
