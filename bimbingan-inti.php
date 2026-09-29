<?php
/* ------------------------------------------------------------------
   Inti modul bimbingan. Dipisah dari sesi.php supaya kesalahan di modul
   ini tidak ikut merusak halaman lain (masuk, materi, unduhan). Hanya
   halaman bimbingan dan panel admin yang memuatnya.
   ------------------------------------------------------------------ */
require_once __DIR__ . '/sesi.php';
/* ==================================================================
   MANAJEMEN BIMBINGAN

   Menggantikan rekap lama (nama, kelompok, peran, tahap, keterangan)
   yang tahapnya terkunci empat nilai. Tiap mahasiswa kini punya nomor
   id tetap, jenjang eksplisit, NIM, judul, status sesuai jenjangnya,
   dan riwayat perubahan status. Akun mahasiswa ditautkan lewat nama
   pengguna (NIM), bukan kecocokan nama.

   Penyimpanan memakai "dokumen": satu JSON utuh per kunci. Di moda
   MySQL ia disimpan di tabel dokumen, di moda berkas sebagai
   data/<kunci>.json. Satu kode melayani dua moda, dan tabel lama
   bimbingan (ENUM empat tahap) tidak disentuh lagi.
   ================================================================== */

const JENJANG = ['S1' => 'Skripsi', 'S2' => 'Tesis', 'S3' => 'Disertasi'];

/* Urutan status per jenjang, sesuai ketetapan Dr. Dara (29 Sep 2026).
   Status terakhir tiap jenjang berarti lulus. */
const STATUS_BIMBINGAN = [
    'S1' => [
        'pengajuan-topik'      => 'Pengajuan Topik',
        'acc-proposal'         => 'ACC Proposal',
        'lulus-proposal'       => 'Lulus Proposal',
        'acc-skripsi'          => 'ACC Skripsi',
        'lulus-sidang-skripsi' => 'Lulus Sidang Skripsi',
    ],
    'S2' => [
        'pengajuan-topik'     => 'Pengajuan Topik',
        'acc-proposal'        => 'ACC Proposal',
        'lulus-proposal'      => 'Lulus Proposal',
        'acc-thesis'          => 'ACC Thesis',
        'lulus-sidang-thesis' => 'Lulus Sidang Thesis',
    ],
    'S3' => [
        'pengajuan-topik'       => 'Pengajuan Topik',
        'acc-proposal'          => 'ACC Proposal',
        'lulus-proposal'        => 'Lulus Proposal',
        'acc-seminar-hasil'     => 'ACC Seminar Hasil',
        'lulus-seminar-hasil'   => 'Lulus Seminar Hasil',
        'acc-sidang-tertutup'   => 'ACC Sidang Tertutup',
        'lulus-sidang-tertutup' => 'Lulus Sidang Tertutup',
        'acc-sidang-terbuka'    => 'ACC Sidang Terbuka',
        'lulus-sidang-terbuka'  => 'Lulus Sidang Terbuka',
    ],
];

const PERAN_PEMBIMBING = ['Pembimbing 1', 'Pembimbing 2', 'Promotor', 'Ko-promotor', 'Pembimbing Akademik', '-'];

function status_jenjang(string $j): array { return STATUS_BIMBINGAN[$j] ?? []; }
function status_awal(string $j): string { $s = status_jenjang($j); return $s ? (string) array_key_first($s) : ''; }
function status_akhir(string $j): string { $s = status_jenjang($j); return $s ? (string) array_key_last($s) : ''; }
function label_status(string $j, string $s): string { return STATUS_BIMBINGAN[$j][$s] ?? $s; }
function urut_status(string $j, string $s): int {
    $i = array_search($s, array_keys(status_jenjang($j)), true);
    return $i === false ? -1 : (int) $i;
}
function sudah_lulus(array $m): bool { return ($m['status'] ?? '') !== '' && $m['status'] === status_akhir($m['jenjang'] ?? ''); }
function tanggal_sah(string $t): string {
    $t = trim($t);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) && checkdate((int) substr($t, 5, 2), (int) substr($t, 8, 2), (int) substr($t, 0, 4)) ? $t : '';
}
function kelompok_bawaan(string $j, string $angkatan): string {
    $dasar = ['S1' => 'Skripsi', 'S2' => 'Tesis S2', 'S3' => 'Disertasi S3'][$j] ?? 'Bimbingan';
    return $dasar . ($angkatan !== '' ? ' Angkatan ' . $angkatan : '');
}

/* ---------------- penyimpanan dokumen ---------------- */

function _dok_tabel(PDO $d): void {
    static $siap = false;
    if ($siap) return;
    $d->exec('CREATE TABLE IF NOT EXISTS dokumen (kunci VARCHAR(64) PRIMARY KEY, isi LONGTEXT NOT NULL, diubah DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $siap = true;
}
function baca_dok(string $kunci): ?array {
    if ($d = db()) {
        _dok_tabel($d);
        $s = $d->prepare('SELECT isi FROM dokumen WHERE kunci = ?');
        $s->execute([$kunci]);
        $r = $s->fetch();
        return $r ? (json_decode($r['isi'], true) ?: null) : null;
    }
    $f = __DIR__ . '/data/' . $kunci . '.json';
    if (!is_file($f)) return null;
    return json_decode((string) file_get_contents($f), true) ?: null;
}
function tulis_dok(string $kunci, array $isi): void {
    $json = json_encode($isi, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($d = db()) {
        _dok_tabel($d);
        $d->prepare('REPLACE INTO dokumen (kunci, isi, diubah) VALUES (?, ?, NOW())')->execute([$kunci, $json]);
        return;
    }
    file_put_contents(__DIR__ . '/data/' . $kunci . '.json', $json, LOCK_EX);
}

/* ---------------- data mahasiswa ---------------- */

const DOK_MHS = 'mahasiswa';
const KOLOM_MHS = ['nim' => 20, 'nama' => 100, 'jenjang' => 2, 'angkatan' => 4, 'kelompok' => 80,
                   'prodi' => 80, 'peran' => 30, 'judul' => 300, 'kontak' => 120, 'akun' => 60, 'catatan' => 1000];

function _teks_bersih($s, int $max): string {
    $s = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $s)));
    return mb_substr($s, 0, $max);
}
function data_mhs_ada(): bool { return baca_dok(DOK_MHS) !== null; }
/* Data lama masih ada tetapi belum dipindahkan: panel admin wajib memindahkan dulu. */
function perlu_migrasi(): bool { return !data_mhs_ada() && !empty(muat_bimbingan()['mahasiswa'] ?? []); }

function _mhs_dok(): array {
    return baca_dok(DOK_MHS) ?? ['seq' => 0, 'diperbarui' => date('Y-m-d'), 'mahasiswa' => []];
}
function _mhs_simpan(array $dok): void {
    $dok['diperbarui'] = date('Y-m-d');
    $dok['mahasiswa'] = array_values($dok['mahasiswa']);
    tulis_dok(DOK_MHS, $dok);
}

/* Semua mahasiswa. Sebelum data lama dipindahkan, halaman publik tetap
   tampil dengan membaca data lama lewat pemetaan yang sama. */
function muat_mhs(): array {
    $dok = baca_dok(DOK_MHS);
    return $dok !== null ? ($dok['mahasiswa'] ?? []) : mhs_dari_data_lama();
}
function tanggal_data_mhs(): string {
    $dok = baca_dok(DOK_MHS);
    return (string) ($dok['diperbarui'] ?? (muat_bimbingan()['diperbarui'] ?? '-'));
}
function ambil_mhs(int $id): ?array {
    foreach (muat_mhs() as $m) if ((int) $m['id'] === $id) return $m;
    return null;
}
/* Baris milik akun yang sedang masuk: lewat tautan akun, lalu nama sebagai cadangan. */
function mhs_milik(string $akun, string $nama): ?array {
    $semua = muat_mhs();
    foreach ($semua as $m) if (($m['akun'] ?? '') !== '' && $m['akun'] === $akun) return $m;
    foreach ($semua as $m) if (strcasecmp(trim($m['nama']), trim($nama)) === 0) return $m;
    return null;
}

/* Pemetaan data lama ke struktur baru. Tahap "belum" dan "judul" sama-sama
   menjadi Pengajuan Topik, "sempro" menjadi Lulus Proposal, "lulus" menjadi
   status akhir jenjangnya. Akun mahasiswa ditautkan bila namanya sama. */
function mhs_dari_data_lama(): array {
    $lama = muat_bimbingan()['mahasiswa'] ?? [];
    $akun = [];
    foreach (muat_pengguna() as $p) {
        if (($p['peran'] ?? '') === 'mahasiswa') $akun[mb_strtolower(trim($p['nama']))] = $p['email'];
    }
    $out = []; $i = 0;
    foreach ($lama as $m) {
        $k = (string) ($m['kelompok'] ?? '');
        $j = mb_stripos($k, 'Disertasi') !== false ? 'S3' : (mb_stripos($k, 'Tesis') !== false ? 'S2' : 'S1');
        $tahap = (string) ($m['tahap'] ?? 'belum');
        $st = ['belum' => status_awal($j), 'judul' => status_awal($j),
               'sempro' => 'lulus-proposal', 'lulus' => status_akhir($j)][$tahap] ?? status_awal($j);
        $out[] = [
            'id' => ++$i, 'nim' => '', 'nama' => (string) $m['nama'], 'jenjang' => $j,
            'angkatan' => preg_match('/(20\d\d)/', $k, $x) ? $x[1] : '', 'kelompok' => $k, 'prodi' => '',
            'peran' => ((string) ($m['peran'] ?? '-')) ?: '-', 'judul' => '', 'status' => $st, 'tgl_status' => '',
            'kontak' => '', 'akun' => $akun[mb_strtolower(trim((string) $m['nama']))] ?? '',
            'catatan' => (string) ($m['keterangan'] ?? ''), 'dibuat' => date('Y-m-d'), 'riwayat' => [],
            'tahap_lama' => $tahap,
        ];
    }
    return $out;
}
function terapkan_migrasi(): int {
    if (!perlu_migrasi()) return 0;
    $semua = mhs_dari_data_lama();
    $hari = date('Y-m-d');
    foreach ($semua as $i => $m) {
        $semua[$i]['riwayat'] = [['tgl' => $hari, 'dari' => '', 'ke' => $m['status'],
            'catatan' => 'Dipindahkan dari rekap lama (tahap: ' . $m['tahap_lama'] . ')']];
        unset($semua[$i]['tahap_lama']);
    }
    _mhs_simpan(['seq' => count($semua), 'mahasiswa' => $semua]);
    return count($semua);
}

function _mhs_isi(array $in, array $dasar = []): array {
    $m = $dasar;
    foreach (KOLOM_MHS as $k => $max) {
        if (array_key_exists($k, $in)) $m[$k] = _teks_bersih($in[$k], $max);
        elseif (!array_key_exists($k, $m)) $m[$k] = '';
    }
    $m['nim'] = preg_replace('/[^0-9A-Za-z]/', '', $m['nim']);
    $m['kontak'] = strtolower($m['kontak']);
    if (!in_array($m['peran'], PERAN_PEMBIMBING, true)) $m['peran'] = '-';
    if (!preg_match('/^\d{4}$/', $m['angkatan'])) $m['angkatan'] = '';
    return $m;
}
function nim_dipakai(string $nim, int $kecuali = 0): bool {
    if ($nim === '') return false;
    foreach (muat_mhs() as $m) if (($m['nim'] ?? '') === $nim && (int) $m['id'] !== $kecuali) return true;
    return false;
}

/* Tambah satu mahasiswa. Mengembalikan id, atau 0 bila jenjang tidak sah
   atau data lama belum dipindahkan. */
function tambah_mhs(array $in, string $catatan = 'Ditambahkan'): int {
    if (perlu_migrasi()) return 0;
    $m = _mhs_isi($in);
    if (!isset(JENJANG[$m['jenjang']]) || mb_strlen($m['nama']) < 3) return 0;
    $st = (string) ($in['status'] ?? '');
    if (!isset(status_jenjang($m['jenjang'])[$st])) $st = status_awal($m['jenjang']);
    $tgl = tanggal_sah((string) ($in['tgl_status'] ?? '')) ?: date('Y-m-d');
    if ($m['kelompok'] === '') $m['kelompok'] = kelompok_bawaan($m['jenjang'], $m['angkatan']);
    $dok = _mhs_dok();
    $dok['seq'] = (int) $dok['seq'] + 1;
    $m = ['id' => $dok['seq']] + $m + ['status' => $st, 'tgl_status' => $tgl, 'dibuat' => date('Y-m-d'),
          'riwayat' => [['tgl' => $tgl, 'dari' => '', 'ke' => $st, 'catatan' => $catatan]]];
    $dok['mahasiswa'][] = $m;
    _mhs_simpan($dok);
    return (int) $m['id'];
}

/* Ubah data diri dan penempatan. Bila jenjang berganti dan status lama
   tidak berlaku di jenjang baru, status kembali ke tahap pertama. */
function ubah_mhs(int $id, array $in): bool {
    $dok = _mhs_dok();
    foreach ($dok['mahasiswa'] as $i => $m) {
        if ((int) $m['id'] !== $id) continue;
        $baru = _mhs_isi($in, $m);
        if (!isset(JENJANG[$baru['jenjang']]) || mb_strlen($baru['nama']) < 3) return false;
        if (!isset(status_jenjang($baru['jenjang'])[$baru['status']])) {
            $awal = status_awal($baru['jenjang']);
            $baru['riwayat'][] = ['tgl' => date('Y-m-d'), 'dari' => $baru['status'], 'ke' => $awal,
                                  'catatan' => 'Jenjang diubah ke ' . $baru['jenjang']];
            $baru['status'] = $awal; $baru['tgl_status'] = date('Y-m-d');
        }
        if ($baru['kelompok'] === '') $baru['kelompok'] = kelompok_bawaan($baru['jenjang'], $baru['angkatan']);
        $dok['mahasiswa'][$i] = $baru;
        _mhs_simpan($dok);
        return true;
    }
    return false;
}

/* Ubah status satu atau banyak mahasiswa sekaligus; tiap perubahan masuk
   riwayat. Status yang tidak berlaku di jenjang mahasiswanya dilewati.
   Mengembalikan jumlah mahasiswa yang berubah. */
function ubah_status(array $ids, string $status, string $tgl, string $catatan, string $jenjang = ''): int {
    $tgl = tanggal_sah($tgl) ?: date('Y-m-d');
    $catatan = _teks_bersih($catatan, 300);
    $ids = array_map('intval', $ids);
    $dok = _mhs_dok(); $n = 0;
    foreach ($dok['mahasiswa'] as $i => $m) {
        if (!in_array((int) $m['id'], $ids, true)) continue;
        if ($jenjang !== '' && $m['jenjang'] !== $jenjang) continue;
        if (!isset(status_jenjang($m['jenjang'])[$status])) continue;
        if ($m['status'] === $status && $catatan === '' && $m['tgl_status'] === $tgl) continue;
        $dok['mahasiswa'][$i]['riwayat'][] = ['tgl' => $tgl, 'dari' => $m['status'], 'ke' => $status, 'catatan' => $catatan];
        $dok['mahasiswa'][$i]['status'] = $status;
        $dok['mahasiswa'][$i]['tgl_status'] = $tgl;
        $n++;
    }
    if ($n) _mhs_simpan($dok);
    return $n;
}

function hapus_mhs(int $id): ?array {
    $dok = _mhs_dok();
    foreach ($dok['mahasiswa'] as $i => $m) {
        if ((int) $m['id'] === $id) {
            array_splice($dok['mahasiswa'], $i, 1);
            _mhs_simpan($dok);
            return $m;
        }
    }
    return null;
}

/* ---------------- pendaftaran akun mahasiswa ---------------- */

/* Mahasiswa membuat akunnya sendiri (NIM sebagai nama pengguna, sandi
   pilihannya), lalu menunggu persetujuan Dr. Dara. Yang disimpan hanya
   hash sandinya. */
const DOK_DAFTAR = 'pendaftaran-akun';
function muat_daftar_akun(): array { return baca_dok(DOK_DAFTAR)['daftar'] ?? []; }
function tambah_daftar_akun(array $a): int {
    $dok = baca_dok(DOK_DAFTAR) ?? ['seq' => 0, 'daftar' => []];
    $dok['seq'] = (int) $dok['seq'] + 1;
    $a['id'] = $dok['seq'];
    $dok['daftar'][] = $a;
    tulis_dok(DOK_DAFTAR, $dok);
    return $a['id'];
}
function ambil_daftar_akun(int $id): ?array {
    $dok = baca_dok(DOK_DAFTAR);
    if (!$dok) return null;
    foreach ($dok['daftar'] as $i => $a) {
        if ((int) $a['id'] === $id) {
            array_splice($dok['daftar'], $i, 1);
            tulis_dok(DOK_DAFTAR, $dok);
            return $a;
        }
    }
    return null;
}
function nama_pengguna_ada(string $u): bool {
    foreach (muat_pengguna() as $p) if (strcasecmp($p['email'], $u) === 0) return true;
    return false;
}
