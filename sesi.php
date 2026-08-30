<?php
/* ------------------------------------------------------------------
   Mesin sesi dan lapisan data area bimbingan.

   Lapisan data punya dua moda. Selama berkas data/konfig.php belum
   ada, semuanya dibaca-tulis ke berkas JSON di folder data yang
   diblokir dari web. Begitu konfig.php berisi kredensial MySQL
   terpasang dan migrasi dijalankan, seluruh baca-tulis pindah ke
   MySQL lewat PDO tanpa satu pun halaman perlu diubah, karena semua
   halaman hanya bicara lewat fungsi-fungsi di berkas ini.

   PostgreSQL tidak tersedia di shared hosting Hostinger; MySQL yang
   disediakan, maka MySQL yang digunakan. Kata sandi selalu disimpan
   sebagai hash bcrypt di kedua moda.
   ------------------------------------------------------------------ */

const BERKAS_PENGGUNA = __DIR__ . '/data/pengguna.json';
const BERKAS_GAGAL    = __DIR__ . '/data/gagal-masuk.json';
const BERKAS_SETUP    = __DIR__ . '/data/kode-setup.txt';
const BERKAS_KONFIG   = __DIR__ . '/data/konfig.php';
const BERKAS_BIMBINGAN = __DIR__ . '/data/bimbingan.json';
const BERKAS_ANTREAN   = __DIR__ . '/data/pendaftaran.json';

/* ================= sesi ================= */

function mulai_sesi(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('dara_sesi');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function pengguna_sekarang(): ?array {
    mulai_sesi();
    return $_SESSION['pengguna'] ?? null;
}

function wajib_masuk(): array {
    $p = pengguna_sekarang();
    if ($p === null) {
        header('Location: /masuk.php');
        exit;
    }
    return $p;
}

function wajib_masuk_segar(): array {
    $p = wajib_masuk();
    if (!empty($p['wajib_ganti'])) {
        header('Location: /ganti-sandi.php');
        exit;
    }
    return $p;
}

function token_csrf(): string {
    mulai_sesi();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function csrf_sah(): bool {
    mulai_sesi();
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], (string) $_POST['csrf']);
}

/* ================= sambungan database ================= */

/* PDO ke MySQL, atau null bila masih moda JSON. Migrasi ditandai lewat
   tabel meta, sehingga konfig yang sudah terpasang tetapi belum
   dimigrasi tidak membuat situs membaca database kosong. */
function db(): ?PDO {
    static $pdo = null, $dicoba = false;
    if ($dicoba) return $pdo;
    $dicoba = true;
    if (!is_file(BERKAS_KONFIG)) return null;
    $k = require BERKAS_KONFIG;
    try {
        $pdo = new PDO(
            'mysql:host=' . $k['host'] . ';dbname=' . $k['nama_db'] . ';charset=utf8mb4',
            $k['pengguna_db'], $k['sandi_db'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $ada = $pdo->query("SHOW TABLES LIKE 'meta'")->fetch();
        if (!$ada) { $pdo = null; return null; }
        $m = $pdo->query("SELECT nilai FROM meta WHERE kunci = 'migrasi'")->fetch();
        if (!$m || $m['nilai'] !== 'selesai') { $pdo = null; }
    } catch (Throwable $e) {
        $pdo = null;                       /* database bermasalah: moda JSON */
    }
    return $pdo;
}

/* ================= pengguna ================= */

function muat_pengguna(): array {
    if ($d = db()) {
        return $d->query('SELECT email, nama, peran, sandi, wajib_ganti, dibuat FROM pengguna ORDER BY id')->fetchAll();
    }
    if (!is_file(BERKAS_PENGGUNA)) return [];
    $j = json_decode((string) file_get_contents(BERKAS_PENGGUNA), true);
    return $j['pengguna'] ?? [];
}

function tambah_pengguna(array $p): void {
    if ($d = db()) {
        $d->prepare('INSERT INTO pengguna (email, nama, peran, sandi, wajib_ganti, dibuat) VALUES (?,?,?,?,?,?)')
          ->execute([$p['email'], $p['nama'], $p['peran'], $p['sandi'],
                     (int) !empty($p['wajib_ganti']), $p['dibuat'] ?? date('Y-m-d')]);
        return;
    }
    $semua = muat_pengguna();
    $semua[] = $p;
    simpan_pengguna_json($semua);
}

function perbarui_pengguna(string $email, array $ubah): void {
    if ($d = db()) {
        $set = []; $nilai = [];
        foreach (['sandi', 'peran', 'nama'] as $k) {
            if (array_key_exists($k, $ubah)) { $set[] = "$k = ?"; $nilai[] = $ubah[$k]; }
        }
        if (array_key_exists('wajib_ganti', $ubah)) {
            $set[] = 'wajib_ganti = ?'; $nilai[] = (int) $ubah['wajib_ganti'];
        }
        if (!$set) return;
        $nilai[] = $email;
        $d->prepare('UPDATE pengguna SET ' . implode(', ', $set) . ' WHERE email = ?')->execute($nilai);
        return;
    }
    $semua = muat_pengguna();
    foreach ($semua as $i => $p) {
        if ($p['email'] === $email) { $semua[$i] = array_merge($p, $ubah); break; }
    }
    simpan_pengguna_json($semua);
}

function hapus_pengguna(string $email): void {
    if ($d = db()) {
        $d->prepare('DELETE FROM pengguna WHERE email = ?')->execute([$email]);
        return;
    }
    simpan_pengguna_json(array_values(array_filter(muat_pengguna(),
        fn($p) => $p['email'] !== $email)));
}

function simpan_pengguna_json(array $daftar): void {
    file_put_contents(BERKAS_PENGGUNA,
        json_encode(['pengguna' => $daftar],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX);
}

/* Dipakai halaman setup pertama, saat belum ada satu pun akun. */
function ada_pengguna(): bool {
    if ($d = db()) {
        return (bool) $d->query('SELECT 1 FROM pengguna LIMIT 1')->fetch();
    }
    return is_file(BERKAS_PENGGUNA);
}

/* ================= rekap bimbingan ================= */

function muat_bimbingan(): ?array {
    if ($d = db()) {
        $m = $d->query('SELECT nama, kelompok, peran, tahap, keterangan FROM bimbingan ORDER BY id')->fetchAll();
        $t = $d->query("SELECT nilai FROM meta WHERE kunci = 'diperbarui'")->fetch();
        return ['diperbarui' => $t['nilai'] ?? '-', 'mahasiswa' => $m];
    }
    if (!is_file(BERKAS_BIMBINGAN)) return null;
    return json_decode((string) file_get_contents(BERKAS_BIMBINGAN), true);
}

function tambah_mahasiswa_bimbingan(array $m): void {
    if ($d = db()) {
        $d->prepare('INSERT INTO bimbingan (nama, kelompok, peran, tahap, keterangan) VALUES (?,?,?,?,?)')
          ->execute([$m['nama'], $m['kelompok'], $m['peran'], $m['tahap'], $m['keterangan']]);
        return;
    }
    $data = muat_bimbingan() ?? ['diperbarui' => date('Y-m-d'), 'mahasiswa' => []];
    $data['mahasiswa'][] = $m;
    file_put_contents(BERKAS_BIMBINGAN,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/* ================= antrean pendaftaran ================= */

/* Tiap baris pulang membawa id: nomor baris di database, atau indeks
   larik di moda JSON, supaya halaman tidak perlu tahu bedanya. */
function muat_antrean(): array {
    if ($d = db()) {
        return $d->query('SELECT id, nama, kelompok, kontak, waktu FROM pendaftaran ORDER BY id')->fetchAll();
    }
    if (!is_file(BERKAS_ANTREAN)) return [];
    $a = json_decode((string) file_get_contents(BERKAS_ANTREAN), true) ?: [];
    foreach ($a as $i => $x) $a[$i]['id'] = $i;
    return $a;
}

function tambah_antrean(array $a): void {
    if ($d = db()) {
        $d->prepare('INSERT INTO pendaftaran (nama, kelompok, kontak, waktu) VALUES (?,?,?,?)')
          ->execute([$a['nama'], $a['kelompok'], $a['kontak'], $a['waktu']]);
        return;
    }
    $antre = muat_antrean();
    foreach ($antre as $i => $x) unset($antre[$i]['id']);
    $antre[] = $a;
    file_put_contents(BERKAS_ANTREAN,
        json_encode(array_values($antre), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX);
}

function ambil_antrean(int $id): ?array {
    if ($d = db()) {
        $s = $d->prepare('SELECT id, nama, kelompok, kontak, waktu FROM pendaftaran WHERE id = ?');
        $s->execute([$id]);
        $baris = $s->fetch() ?: null;
        if ($baris) $d->prepare('DELETE FROM pendaftaran WHERE id = ?')->execute([$id]);
        return $baris;
    }
    $antre = muat_antrean();
    if (!isset($antre[$id])) return null;
    $baris = $antre[$id];
    array_splice($antre, $id, 1);
    foreach ($antre as $i => $x) unset($antre[$i]['id']);
    file_put_contents(BERKAS_ANTREAN,
        json_encode(array_values($antre), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX);
    return $baris;
}

/* ================= pembatas percobaan ================= */

/* Tetap berkas: umurnya 15 menit dan hilang pun tidak ada ruginya. */
function boleh_mencoba(string $kunci): bool {
    $data = is_file(BERKAS_GAGAL)
        ? (json_decode((string) file_get_contents(BERKAS_GAGAL), true) ?: [])
        : [];
    $riwayat = array_filter($data[$kunci] ?? [], fn($t) => $t > time() - 900);
    return count($riwayat) < 5;
}

function catat_gagal(string $kunci): void {
    $data = is_file(BERKAS_GAGAL)
        ? (json_decode((string) file_get_contents(BERKAS_GAGAL), true) ?: [])
        : [];
    foreach ($data as $k => $riwayat) {
        $data[$k] = array_values(array_filter($riwayat, fn($t) => $t > time() - 900));
        if (!$data[$k]) unset($data[$k]);
    }
    $data[$kunci][] = time();
    file_put_contents(BERKAS_GAGAL, json_encode($data), LOCK_EX);
}

/* Kode akses acak untuk akun baru dan reset sandi. Huruf yang mudah
   tertukar (l, 1, o, 0, i) sengaja tidak digunakan karena kode ini akan
   dibacakan dan diketik ulang orang. */
function kode_akses(int $n = 10): string {
    $a = 'abcdefghjkmnpqrstuvwxyz23456789';
    $s = '';
    for ($i = 0; $i < $n; $i++) $s .= $a[random_int(0, strlen($a) - 1)];
    return $s;
}
