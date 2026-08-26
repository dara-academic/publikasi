<?php
/* ------------------------------------------------------------------
   Mesin sesi bersama untuk halaman yang butuh masuk.

   Data pengguna hidup di data/pengguna.json, folder yang diblokir dari
   akses web lewat .htaccess dan tidak pernah masuk git. Kata sandi
   disimpan sebagai hash bcrypt lewat password_hash; tidak ada sandi
   polos di mana pun, termasuk di berkas ini.
   ------------------------------------------------------------------ */

const BERKAS_PENGGUNA = __DIR__ . '/data/pengguna.json';
const BERKAS_GAGAL    = __DIR__ . '/data/gagal-masuk.json';
const BERKAS_SETUP    = __DIR__ . '/data/kode-setup.txt';

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

function muat_pengguna(): array {
    if (!is_file(BERKAS_PENGGUNA)) return [];
    $j = json_decode((string) file_get_contents(BERKAS_PENGGUNA), true);
    return $j['pengguna'] ?? [];
}

function simpan_pengguna(array $daftar): void {
    file_put_contents(BERKAS_PENGGUNA,
        json_encode(['pengguna' => $daftar],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX);
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

/* Pembatas percobaan: lima kegagalan per alamat dalam 15 menit.
   Tanpa ini, formulir masuk adalah undangan menebak sandi sepanjang malam. */
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
   tertukar (l, 1, o, 0, i) sengaja tidak dipakai karena kode ini akan
   dibacakan dan diketik ulang orang. */
function kode_akses(int $n = 10): string {
    $a = 'abcdefghjkmnpqrstuvwxyz23456789';
    $s = '';
    for ($i = 0; $i < $n; $i++) $s .= $a[random_int(0, strlen($a) - 1)];
    return $s;
}

/* Halaman berkunci memanggil ini alih-alih wajib_masuk bila pengguna yang
   masih memakai sandi bawaan harus dipaksa menggantinya lebih dulu. */
function wajib_masuk_segar(): array {
    $p = wajib_masuk();
    if (!empty($p['wajib_ganti'])) {
        header('Location: /ganti-sandi.php');
        exit;
    }
    return $p;
}
