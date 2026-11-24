<?php
/* ------------------------------------------------------------------
   Unggah berkas per potongan kecil.

   Safari di Mac bisa gagal mengunggah berkas dengan galat "request body
   stream exhausted" (NSURLErrorDomain -1021) bila koneksi terputus atau
   diulang di tengah unggahan: isi berkas tidak bisa dikirim ulang.
   Karena itu formulir bertanda data-potong mengirim berkas per potongan
   2 MB lewat fetch (assets/unggah-potong.js), dengan pengulangan per
   potongan. Potongan ditampung di data/potongan/<id>/ (folder data
   diblokir dari web), lalu dirakit saat formulir utama dikirim.
   Tanpa JavaScript, formulir tetap terkirim biasa lewat $_FILES.
   ------------------------------------------------------------------ */
const DIR_POTONG     = __DIR__ . '/data/potongan';
const POTONG_UKURAN  = 2097152;  /* 2 MB, sama dengan di unggah-potong.js */
const POTONG_MAKS    = 2621440;  /* toleransi per potongan */

function potong_id_sah(string $id): bool {
    return (bool) preg_match('/^[a-f0-9]{32}$/', $id);
}

function potong_batas(int $maks_total): int {
    return (int) ceil($maks_total / POTONG_UKURAN) + 1;
}

/* Buang sisa potongan dan rakitan yang lebih tua dari sehari. */
function potong_bersihkan(): void {
    foreach (glob(DIR_POTONG . '/*') ?: [] as $p) {
        if ((int) @filemtime($p) > time() - 86400) continue;
        if (is_dir($p)) {
            foreach (glob($p . '/*') ?: [] as $f) @unlink($f);
            @rmdir($p);
        } else {
            @unlink($p);
        }
    }
}

/* Terima satu potongan. Mengembalikan '' bila berhasil, atau sebab gagal. */
function potong_terima(int $maks_total): string {
    $id     = (string) ($_POST['id'] ?? '');
    $indeks = (int) ($_POST['indeks'] ?? -1);
    $total  = (int) ($_POST['total'] ?? 0);
    $f      = $_FILES['potong'] ?? null;
    if (!potong_id_sah($id) || $total < 1 || $total > potong_batas($maks_total) || $indeks < 0 || $indeks >= $total) {
        return 'data potongan tidak sah';
    }
    if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        return 'potongan tidak diterima utuh';
    }
    if (($f['size'] ?? 0) <= 0 || $f['size'] > POTONG_MAKS) return 'ukuran potongan tidak sah';
    $dir = DIR_POTONG . '/' . $id;
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) return 'folder sementara tidak bisa dibuat';
    if (!@move_uploaded_file($f['tmp_name'], $dir . '/' . sprintf('%04d', $indeks))) return 'potongan gagal disimpan';
    if (mt_rand(1, 20) === 1) potong_bersihkan();
    return '';
}

/* Rakit potongan jadi satu berkas. Hasilnya berbentuk seperti satu entri
   $_FILES dengan tanda 'rakitan', atau null bila potongan tidak lengkap. */
function potong_rakit(string $id, int $total, string $nama, int $maks_total): ?array {
    if (!potong_id_sah($id) || $total < 1 || $total > potong_batas($maks_total)) return null;
    $dir   = DIR_POTONG . '/' . $id;
    $hasil = DIR_POTONG . '/' . $id . '.berkas';
    $out = @fopen($hasil, 'wb');
    if (!$out) return null;
    $ukuran = 0;
    for ($i = 0; $i < $total; $i++) {
        $p  = $dir . '/' . sprintf('%04d', $i);
        $in = is_file($p) ? @fopen($p, 'rb') : false;
        if (!$in) { fclose($out); @unlink($hasil); return null; }
        $ukuran += (int) stream_copy_to_stream($in, $out);
        fclose($in);
        if ($ukuran > $maks_total) { fclose($out); @unlink($hasil); return null; }
    }
    fclose($out);
    foreach (glob($dir . '/*') ?: [] as $p) @unlink($p);
    @rmdir($dir);
    /* Rakitan yang ditolak atau tidak dipindah (bukan PDF, sampul jadi WebP)
       dibuang di akhir permintaan; yang sudah dipindah tidak ada lagi di sini. */
    register_shutdown_function(static function () use ($hasil) { if (is_file($hasil)) @unlink($hasil); });
    return ['name' => basename(str_replace('\\', '/', $nama)), 'type' => '', 'tmp_name' => $hasil,
            'error' => UPLOAD_ERR_OK, 'size' => $ukuran, 'rakitan' => true];
}

/* Pengganti is_uploaded_file dan move_uploaded_file yang juga menerima rakitan. */
function berkas_sah(array $f): bool {
    if (!empty($f['rakitan'])) {
        return is_file((string) $f['tmp_name']) && strpos((string) $f['tmp_name'], DIR_POTONG . '/') === 0;
    }
    return is_uploaded_file((string) ($f['tmp_name'] ?? ''));
}

function berkas_pindah(array $f, string $tujuan): bool {
    return !empty($f['rakitan']) ? @rename($f['tmp_name'], $tujuan) : @move_uploaded_file($f['tmp_name'], $tujuan);
}

/* Berkas tunggal dari formulir: rakitan potongan bila ada, kalau tidak $_FILES. */
function berkas_form(string $nama, int $maks): ?array {
    $id = (string) ($_POST[$nama . '_potongan'] ?? '');
    if ($id !== '') {
        $r = potong_rakit($id, (int) ($_POST[$nama . '_total'] ?? 0), (string) ($_POST[$nama . '_nama'] ?? ''), $maks);
        return $r ?? ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_PARTIAL, 'size' => 0];
    }
    $f = $_FILES[$nama] ?? null;
    return is_array($f) ? $f : null;
}

/* Banyak berkas dari satu masukan multiple, sebagai daftar entri. */
function berkas_form_banyak(string $nama, int $maks): array {
    $hasil = [];
    $ids = $_POST[$nama . '_potongan'] ?? null;
    if (is_array($ids)) {
        $tot = (array) ($_POST[$nama . '_total'] ?? []);
        $nm  = (array) ($_POST[$nama . '_nama'] ?? []);
        foreach (array_values($ids) as $i => $id) {
            $r = potong_rakit((string) $id, (int) ($tot[$i] ?? 0), (string) ($nm[$i] ?? ''), $maks);
            $hasil[] = $r ?? ['name' => (string) ($nm[$i] ?? 'berkas'), 'type' => '', 'tmp_name' => '',
                              'error' => UPLOAD_ERR_PARTIAL, 'size' => 0];
        }
        return $hasil;
    }
    $ff = $_FILES[$nama] ?? null;
    if (!is_array($ff) || !is_array($ff['name'] ?? null)) return [];
    foreach (array_keys($ff['name']) as $i) {
        if (($ff['name'][$i] ?? '') === '') continue;
        $hasil[] = ['name' => $ff['name'][$i], 'type' => $ff['type'][$i] ?? '', 'tmp_name' => $ff['tmp_name'][$i] ?? '',
                    'error' => $ff['error'][$i] ?? UPLOAD_ERR_NO_FILE, 'size' => $ff['size'][$i] ?? 0];
    }
    return $hasil;
}

/* Jawab permintaan potongan dalam JSON lalu berhenti. */
function potong_layani(int $maks_total): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $sebab = csrf_sah() ? potong_terima($maks_total) : 'sesi formulir kedaluwarsa, muat ulang halaman';
    http_response_code($sebab === '' ? 200 : 400);
    echo json_encode(['ok' => $sebab === '', 'galat' => $sebab]);
    exit;
}
