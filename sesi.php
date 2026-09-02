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
const BERKAS_MATERI    = __DIR__ . '/data/materi.json';
const BERKAS_PAPER     = __DIR__ . '/data/paper.json';
const BERKAS_BUKU      = __DIR__ . '/data/buku.json';
const BERKAS_STATISTIK = __DIR__ . '/data/statistik.json';
const BERKAS_KOMENTAR  = __DIR__ . '/data/komentar.json';

/* ------------------------------------------------------------------
   Semester berjalan. SATU tempat acuan untuk seluruh situs: pilihan di
   form unggah, tab di halaman mata kuliah, dan labelnya. Tiap ganti
   semester cukup ubah dua nilai ini; sisanya ikut sendiri.
   SEM_INI  = semester berjalan (materi diunggah bertahap).
   SEM_LALU = semester sebelumnya (materinya sudah lengkap/statis).
   ------------------------------------------------------------------ */
const SEM_INI  = '125';
const SEM_LALU = '124';

function semester_pilihan(): array { return [SEM_INI, SEM_LALU]; }
function label_semester(string $kode): string {
    if ($kode === SEM_INI)  return 'Semester ini';
    if ($kode === SEM_LALU) return 'Semester lalu';
    return 'Semester ' . $kode;
}

/* Nama tampil tiap mata kuliah, satu acuan untuk seluruh situs. */
function mk_nama(): array {
    return [
        'pengantar-manajemen'           => 'Pengantar Manajemen',
        'pengadaan-sdm-aparatur'        => 'Pengadaan SDM Aparatur',
        'kompensasi-perlindungan-sdm'   => 'Kompensasi dan Perlindungan SDM Aparatur',
        'pelatihan-dan-pengembangan'    => 'Pelatihan dan Pengembangan',
        'manajemen-kinerja'             => 'Manajemen Kinerja',
        'simulasi-bisnis'               => 'Simulasi Bisnis',
        'management-information-system' => 'Management Information System',
    ];
}

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

/* ================= materi unggahan ================= */

/* Manifes materi kuliah yang diunggah dosen lewat panel admin. Selalu
   berkas JSON: ini hanya daftar rujukan, sedangkan PDF-nya tersimpan di
   folder unggahan yang bisa diakses web. Tiap baris pulang membawa id
   berupa indeks larik, supaya penghapusan bisa menunjuk baris tertentu. */
function muat_materi(): array {
    if (!is_file(BERKAS_MATERI)) return [];
    $a = json_decode((string) file_get_contents(BERKAS_MATERI), true) ?: [];
    foreach ($a as $i => $x) $a[$i]['id'] = $i;
    return $a;
}

function tambah_materi(array $m): void {
    $semua = muat_materi();
    foreach ($semua as $i => $x) unset($semua[$i]['id']);
    $semua[] = $m;
    file_put_contents(BERKAS_MATERI,
        json_encode(array_values($semua), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX);
}

function hapus_materi(int $id): ?array {
    $semua = muat_materi();
    if (!isset($semua[$id])) return null;
    $baris = $semua[$id];
    array_splice($semua, $id, 1);
    foreach ($semua as $i => $x) unset($semua[$i]['id']);
    file_put_contents(BERKAS_MATERI,
        json_encode(array_values($semua), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX);
    return $baris;
}

/* ================= bedah paper terbit ================= */

/* Manifes paper Dara yang sudah terbit, ditambah lewat panel admin
   setiap ada publikasi baru. Sama seperti materi: berkas JSON sebagai
   daftar, sampul (opsional) tersimpan di folder unggahan. Tiap baris
   pulang membawa id berupa indeks larik. */
function muat_paper(): array {
    if (!is_file(BERKAS_PAPER)) return [];
    $a = json_decode((string) file_get_contents(BERKAS_PAPER), true) ?: [];
    foreach ($a as $i => $x) $a[$i]['id'] = $i;
    return $a;
}

function tambah_paper(array $p): void {
    $semua = muat_paper();
    foreach ($semua as $i => $x) unset($semua[$i]['id']);
    $semua[] = $p;
    file_put_contents(BERKAS_PAPER,
        json_encode(array_values($semua), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX);
}

function hapus_paper(int $id): ?array {
    $semua = muat_paper();
    if (!isset($semua[$id])) return null;
    $baris = $semua[$id];
    array_splice($semua, $id, 1);
    foreach ($semua as $i => $x) unset($semua[$i]['id']);
    file_put_contents(BERKAS_PAPER,
        json_encode(array_values($semua), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX);
    return $baris;
}

/* ================= buku terbit ================= */

/* Manifes buku Dara yang sudah terbit, ditambah lewat panel admin.
   Sama pola dengan paper: berkas JSON, sampul opsional di folder unggahan. */
function muat_buku(): array {
    if (!is_file(BERKAS_BUKU)) return [];
    $a = json_decode((string) file_get_contents(BERKAS_BUKU), true) ?: [];
    foreach ($a as $i => $x) $a[$i]['id'] = $i;
    return $a;
}

function tambah_buku(array $b): void {
    $semua = muat_buku();
    foreach ($semua as $i => $x) unset($semua[$i]['id']);
    $semua[] = $b;
    file_put_contents(BERKAS_BUKU,
        json_encode(array_values($semua), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX);
}

function hapus_buku(int $id): ?array {
    $semua = muat_buku();
    if (!isset($semua[$id])) return null;
    $baris = $semua[$id];
    array_splice($semua, $id, 1);
    foreach ($semua as $i => $x) unset($semua[$i]['id']);
    file_put_contents(BERKAS_BUKU,
        json_encode(array_values($semua), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX);
    return $baris;
}

/* ================= statistik (baca, unduh, kunjungan) ================= */

/* Penghitung sederhana berbasis JSON. Kunci berupa string, misalnya
   "unduh:pengantar-manajemen/berkas.pdf", "buka:paper:3", atau
   "lihat:/panduan-jurnal.html". Baca-tulis-ulang seluruh berkas; pada
   skala portal ini itu cukup, dan LOCK_EX menahan tulis bersamaan. Sesekali
   ada increment yang lolos saat trafik tinggi, dan itu tidak masalah untuk
   angka yang sifatnya indikatif. */
function baca_statistik(): array {
    if (!is_file(BERKAS_STATISTIK)) return [];
    return json_decode((string) file_get_contents(BERKAS_STATISTIK), true) ?: [];
}

function catat_statistik(string $kunci): int {
    $kunci = substr($kunci, 0, 200);
    $s = baca_statistik();
    $s[$kunci] = (int) ($s[$kunci] ?? 0) + 1;
    file_put_contents(BERKAS_STATISTIK, json_encode($s, JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $s[$kunci];
}

function nilai_statistik(string $kunci): int {
    return (int) (baca_statistik()[$kunci] ?? 0);
}

/* Ubah angka jadi bentuk ringkas: 1200 -> "1,2rb". */
function angka_ringkas(int $n): string {
    if ($n >= 1000000) return rtrim(rtrim(number_format($n / 1000000, 1, ',', ''), '0'), ',') . 'jt';
    if ($n >= 1000)    return rtrim(rtrim(number_format($n / 1000, 1, ',', ''), '0'), ',') . 'rb';
    return (string) $n;
}

/* ================= tanya jawab (komentar) ================= */

/* Komentar disimpan di satu berkas JSON dengan nomor naik yang stabil,
   supaya setujui/hapus/balas menunjuk komentar yang tepat meski daftar
   berubah. Semua komentar berstatus pending sampai admin menyetujui, jadi
   tidak ada yang tampil ke publik tanpa ditinjau. */
function _komentar_raw(): array {
    if (!is_file(BERKAS_KOMENTAR)) return ['berikutnya' => 1, 'daftar' => []];
    $d = json_decode((string) file_get_contents(BERKAS_KOMENTAR), true);
    if (!is_array($d)) return ['berikutnya' => 1, 'daftar' => []];
    return ['berikutnya' => (int) ($d['berikutnya'] ?? 1), 'daftar' => $d['daftar'] ?? []];
}
function _komentar_simpan(array $d): void {
    file_put_contents(BERKAS_KOMENTAR,
        json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}
function tambah_komentar(string $hal, string $nama, string $isi): void {
    $d = _komentar_raw();
    $no = (int) $d['berikutnya'];
    $d['berikutnya'] = $no + 1;
    $d['daftar'][] = [
        'no' => $no, 'hal' => $hal, 'nama' => $nama, 'isi' => $isi,
        'waktu' => date('Y-m-d H:i'), 'status' => 'pending', 'balasan' => '',
    ];
    _komentar_simpan($d);
}
function komentar_disetujui(string $hal): array {
    return array_values(array_filter(_komentar_raw()['daftar'],
        fn($k) => ($k['hal'] ?? '') === $hal && ($k['status'] ?? '') === 'setuju'));
}
function komentar_semua(): array { return _komentar_raw()['daftar']; }
function setujui_komentar(int $no): void {
    $d = _komentar_raw();
    foreach ($d['daftar'] as $i => $k) if ((int) $k['no'] === $no) $d['daftar'][$i]['status'] = 'setuju';
    _komentar_simpan($d);
}
function balas_komentar(int $no, string $teks): void {
    $d = _komentar_raw();
    foreach ($d['daftar'] as $i => $k) if ((int) $k['no'] === $no) {
        $d['daftar'][$i]['balasan'] = $teks;
        $d['daftar'][$i]['status'] = 'setuju';
    }
    _komentar_simpan($d);
}
function hapus_komentar(int $no): void {
    $d = _komentar_raw();
    $d['daftar'] = array_values(array_filter($d['daftar'], fn($k) => (int) $k['no'] !== $no));
    _komentar_simpan($d);
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
