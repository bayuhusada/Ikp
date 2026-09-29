<?php
/**
 * Cek cepat: apakah website masih benar setelah diisi data demo.
 *
 * Berbeda dari smoke test, skrip ini tidak menghapus apa pun dan tidak punya
 * asersi. Gunanya untuk melihat cepat apakah halaman-halaman utama masih
 * terbuka dan terisi setelah data demo dibuat.
 *
 *   php tests/cek_demo.php
 *
 * HANYA untuk development (butuh server lokal yang hidup).
 */

define('BASE', 'http://localhost/ikp');
$GLOBALS['jar'] = tempnam(sys_get_temp_dir(), 'ikpjar');
function http($m, $u, $b = null) {
    $ch = curl_init($u);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $GLOBALS['jar'], CURLOPT_COOKIEFILE => $GLOBALS['jar'],
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30));
    if ($m === 'POST') { curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($b) ? http_build_query($b) : $b);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded')); }
    $raw = curl_exec($ch); $st = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE); curl_close($ch);
    return array('code' => $st, 'body' => substr($raw, $hs), 'head' => substr($raw, 0, $hs));
}
function token($p) { $r = http('GET', BASE . $p);
    return preg_match('/name="csrf_token"\s+value="([^"]+)"/', $r['body'], $m) ? $m[1] : NULL; }
function login($u, $p) { @unlink($GLOBALS['jar']);
    return http('POST', BASE . '/proses-login', array('username' => $u, 'password' => $p, 'csrf_token' => token('/login'))); }
function get($p) { return http('GET', BASE . $p); }

function cek($nama, $r, $harus = 200) {
    $ok = $r['code'] === $harus;
    printf("  [%s] %-46s HTTP %d\n", $ok ? 'OK  ' : 'GAGAL', $nama, $r['code']);
    return $ok;
}

$pdo = new PDO('mysql:host=127.0.0.1;dbname=ikp;charset=utf8mb4', 'root', '', array(PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC));

echo "=== ADMIN ===\n";
login('admin', 'aeM*2uDZ2z%#VEki');
foreach (array('/dashboard', '/laporan', '/laporan/arsip', '/investigasi',
               '/investigasi/arsip', '/audit-log', '/master/status') as $p) {
    cek($p, get($p));
}

// Dashboard harus berisi angka, bukan nol semua
$d = get('/dashboard');
$adaAngka = preg_match('/\b(4[0-9]|3[0-9])\b/', $d['body']);
printf("  [%s] dashboard menampilkan angka non-zero\n", $adaAngka ? 'OK  ' : 'GAGAL');

// Daftar laporan harus terisi
$l = get('/laporan');
$jumlahINS = preg_match_all('/INS-2026-\d{5}/', $l['body']);
printf("  [%s] daftar laporan memuat nomor INS          (%d nomor terlihat)\n",
    $jumlahINS > 0 ? 'OK  ' : 'GAGAL', $jumlahINS);

// Detail laporan harus terbuka & menampilkan data pasien
$lap = $pdo->query("SELECT ir.id, ir.nomor FROM incident_reports ir
                    JOIN patients p ON p.id = ir.patient_id
                    WHERE p.nomor_mr LIKE 'MR-DEMO-%' AND ir.is_deleted = 0
                    ORDER BY ir.id LIMIT 1")->fetch();
$r = get('/laporan/detail/' . $lap['id']);
cek('detail ' . $lap['nomor'], $r);
$pasien = $pdo->query("SELECT nama_pasien, nomor_mr FROM patients WHERE id =
                        (SELECT patient_id FROM incident_reports WHERE id = " . $lap['id'] . ")")->fetch();
printf("  [%s] detail menampilkan nama & MR pasien\n",
    (strpos($r['body'], $pasien['nama_pasien']) !== FALSE && strpos($r['body'], $pasien['nomor_mr']) !== FALSE) ? 'OK  ' : 'GAGAL');

// Detail investigasi
$inv = $pdo->query("SELECT id, nomor FROM investigations WHERE is_deleted = 0 ORDER BY id LIMIT 1")->fetch();
if ($inv) { cek('detail ' . $inv['nomor'], get('/investigasi/detail/' . $inv['id'])); }

echo "\n=== FILTER DAFTAR LAPORAN ===\n";
$halaman = array(
    'filter status'    => '/laporan?search=',
    'filter bulan'     => '/laporan?date_from=2026-06-01&date_to=2026-06-30',
    'filter ruang'     => '/laporan?ruangan_id=3',
    'halaman 2'        => '/laporan?page=2',
    'halaman 3'        => '/laporan?page=3',
);
foreach ($halaman as $n => $p) { cek($n . ' ' . $p, get($p)); }

echo "\n=== KEPALA RUANGAN (hanya ruangannya) ===\n";
login('kepala_igd', 'Zx2zww8Nxztb');
cek('/dashboard', get('/dashboard'));
cek('/laporan', get('/laporan'));
cek('/investigasi', get('/investigasi'));
$l = get('/laporan');
$igd = $pdo->query("SELECT COUNT(*) c FROM incident_reports ir
                    JOIN ruangan ru ON ru.id=ir.ruangan_id
                    WHERE ru.kode='IGD' AND ir.is_deleted=0")->fetch();
$rawat = $pdo->query("SELECT COUNT(*) c FROM incident_reports ir
                      JOIN ruangan ru ON ru.id=ir.ruangan_id
                      WHERE ru.kode='RAWATINAP' AND ir.is_deleted=0")->fetch();
printf("  DB: IGD=%d, Rawat=%d\n", $igd['c'], $rawat['c']);

echo "\n=== PELAPOR (hanya laporannya sendiri) ===\n";
login('pelapor_igd', 'Zx2zww8Nxztb');
cek('/dashboard', get('/dashboard'));
cek('/laporan', get('/laporan'));
$cek = get('/laporan');
$jml = preg_match_all('/INS-2026-\d{5}/', $cek['body']);
printf("  daftar memuat %d nomor laporan (punya laporan sendiri) \n", $jml);

echo "\n=== LOGOUT (wajib POST) ===\n";
login('pelapor_igd', 'Zx2zww8Nxztb');
cek('GET /auth/logout harus ditolak', get('/auth/logout'), 405);
