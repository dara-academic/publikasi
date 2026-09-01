<?php
/* ------------------------------------------------------------------
   Titik akhir tanya jawab.

   GET  ?hal=<path>  -> token CSRF + daftar komentar yang sudah disetujui
                        untuk halaman itu (JSON), dipakai skrip di halaman.
   POST              -> kirim pertanyaan baru (masuk antrean pending).

   Pengaman: token CSRF dari sesi, umpan jebakan (honeypot), pembatas
   kiriman per IP, validasi panjang, dan path halaman disaring ketat.
   Semua komentar pending sampai admin menyetujui, jadi tak ada yang
   tampil ke publik tanpa ditinjau.
   ------------------------------------------------------------------ */
require __DIR__ . '/sesi.php';
mulai_sesi();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function _hal_sah(string $p): ?string {
    $p = (string) (parse_url($p, PHP_URL_PATH) ?? '');
    if ($p === '' || strlen($p) > 120 || strpos($p, '..') !== false) return null;
    if (!preg_match('#^/[a-zA-Z0-9/_.\-]*$#', $p)) return null;
    return $p;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip   = (string) ($_SERVER['REMOTE_ADDR'] ?? '?');
    $hal  = _hal_sah((string) ($_POST['hal'] ?? ''));
    $nama = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['nama'] ?? ''))));
    $isi  = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['isi'] ?? ''))));
    $web  = (string) ($_POST['web'] ?? '');
    $csrf = (string) ($_POST['csrf'] ?? '');

    if ($web !== '') { echo json_encode(['ok' => true, 'pesan' => 'Terkirim.']); exit; }
    if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) { echo json_encode(['ok' => false, 'pesan' => 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.']); exit; }
    if ($hal === null) { echo json_encode(['ok' => false, 'pesan' => 'Halaman tidak sah.']); exit; }
    if (!boleh_mencoba('komentar:' . $ip)) { echo json_encode(['ok' => false, 'pesan' => 'Terlalu banyak kiriman. Coba lagi beberapa saat lagi.']); exit; }
    if (mb_strlen($nama) < 2 || mb_strlen($nama) > 60) { echo json_encode(['ok' => false, 'pesan' => 'Nama antara 2 sampai 60 karakter.']); exit; }
    if (mb_strlen($isi) < 5 || mb_strlen($isi) > 1000) { echo json_encode(['ok' => false, 'pesan' => 'Pertanyaan antara 5 sampai 1000 karakter.']); exit; }

    catat_gagal('komentar:' . $ip);
    tambah_komentar($hal, $nama, $isi);
    echo json_encode(['ok' => true, 'pesan' => 'Terima kasih. Pertanyaan Anda akan tampil setelah ditinjau.']);
    exit;
}

$hal = _hal_sah((string) ($_GET['hal'] ?? ''));
$out = ['token' => token_csrf(), 'komentar' => []];
if ($hal !== null) {
    foreach (komentar_disetujui($hal) as $k) {
        $out['komentar'][] = [
            'nama'    => (string) $k['nama'],
            'isi'     => (string) $k['isi'],
            'waktu'   => (string) $k['waktu'],
            'balasan' => (string) ($k['balasan'] ?? ''),
        ];
    }
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
