<?php
/**
 * Uji hak kirim laporan untuk 3 peran.
 *
 * Menangkap gap yang ditemukan waktu menulis panduan uji:
 * kepala_ruangan bisa MEMBUAT laporan tapi tidak bisa MENGIRIM ->
 * laporan tersangkut di Draft selamanya.
 * Keputusan user 27 Sep 2026: boleh mengirim laporan sendiri.
 *
 * Jalankan: php tests/smoke_kirim.php
 */

define('BASE', 'http://localhost/ikp');
$GLOBALS['jar'] = tempnam(sys_get_temp_dir(), 'ikpjar');

function http($method, $url, $body = null)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $GLOBALS['jar'], CURLOPT_COOKIEFILE => $GLOBALS['jar'],
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30,
    ));
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? http_build_query($body) : $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
    }
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hsize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $head = substr($raw, 0, $hsize);
    $out  = substr($raw, $hsize);
    $loc = preg_match('/^Location:\s*(.+)$/mi', $head, $m) ? trim($m[1]) : '';
    return array('code' => $status, 'body' => $out, 'loc' => $loc);
}
function token($p) { $r = http('GET', BASE . $p);
    return preg_match('/name="csrf_token"\s+value="([^"]+)"/', $r['body'], $m) ? $m[1] : NULL; }
function login($u, $p) { @unlink($GLOBALS['jar']);
    return http('POST', BASE . '/proses-login', array('username' => $u, 'password' => $p, 'csrf_token' => token('/login'))); }
function get($p) { return http('GET', BASE . $p); }
function post($tp, $to, array $d) { $d['csrf_token'] = token($tp); return http('POST', BASE . $to, $d); }

function db()
{
    static $pdo = NULL;
    if ($pdo === NULL) {
        $pdo = new PDO('mysql:host=127.0.0.1;dbname=ikp;charset=utf8mb4', 'root', '', array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ));
    }
    return $pdo;
}
function q($sql, array $a = array()) { $s = db()->prepare($sql); $s->execute($a); return $s->fetchAll(); }

$pass = 0; $fail = 0;
function ok($label, $cond, $extra = '')
{
    printf("  [%s] %s%s\n", $cond ? 'OK  ' : 'GAGAL', $label, $extra !== '' ? '  -> ' . $extra : '');
    global $pass, $fail;
    if ($cond) { $pass++; } else { $fail++; }
    return (bool) $cond;
}
function line($t = '') { echo $t . "\n"; }

$PW  = 'Zx2zww8Nxztb';
$SUF = strtoupper(substr(md5((string) time()), 0, 5));
$BUAT = array();   // id laporan yang dibuat test

function buatLaporan($ruang = 3)
{
    global $SUF, $BUAT;
    $r = post('/laporan/input', '/laporan/store', array(
        'nama_pasien'          => 'Uji Kirim ' . $SUF,
        'nomor_mr'             => 'MR-KIRIM-' . $SUF . '-' . count($BUAT),
        'ruangan_id'           => $ruang,
        'kelompok_umur_id'     => 3,
        'jenis_kelamin'        => 'P',
        'tanggal_insiden'      => '2026-09-27',
        'jam_insiden'          => '10:00',
        'kronologi_insiden'    => 'Laporan uji untuk memeriksa hak kirim.',
        'jenis_insiden_id'     => 1,
        'akibat_insiden_id'    => 1,
        'incident_category_id' => 1,
        'specialization_id'    => 1,
    ));
    $row = q('SELECT ir.id, ir.nomor, st.nama AS status, ir.created_by
              FROM incident_reports ir JOIN statuses st ON st.id = ir.status_id
              ORDER BY ir.id DESC LIMIT 1');
    if ($row) { $BUAT[] = (int) $row[0]['id']; return $row[0]; }
    return NULL;
}

function status($id)
{
    $r = q('SELECT st.nama FROM incident_reports ir JOIN statuses st ON st.id = ir.status_id
            WHERE ir.id = ?', array((int) $id));
    return $r ? $r[0]['nama'] : '-';
}

// ============================================================ 1. BUAT
line('=== 1. LAPORAN OLEH KEPALA RUANGAN ===');
login('kepala_igd', $PW);
ok('kepala_ruangan bisa buka form input', get('/laporan/input')['code'] === 200);

$lap = buatLaporan(3);
ok('laporan berhasil dibuat', $lap !== NULL, $lap ? $lap['nomor'] : 'gagal');
$idKR = $lap['id'];
ok('status awal = Draft', $lap['status'] === 'Draft', $lap['status']);
ok('dibuat oleh kepala_igd', (int) $lap['created_by'] === 6, 'created_by=' . $lap['created_by']);

$d = get('/laporan/detail/' . $idKR);
ok('tombol Kirim Laporan muncul', strpos($d['body'], 'laporan/kirim') !== FALSE);

$s = post('/laporan/detail/' . $idKR, '/laporan/kirim/' . $idKR, array());
ok('kepala_ruangan bisa mengirim laporan SENDIRINYA', status($idKR) === 'Menunggu Grading', status($idKR));

// ============================================================ 2. TIDAK BOLEH KIRIM ORANG LAIN
line();
line('=== 2. TIDAK BOLEH MENGIRIM LAPORAN ORANG LAIN ===');

login('pelapor_igd', $PW);
$lap = buatLaporan(3);
$idPelapor = $lap['id'];
ok('pelapor membuat laporan', $lap !== NULL, $lap['nomor']);

// kepala_ruangan tidak boleh mengirim Draft milik pelapor di ruangannya
login('kepala_igd', $PW);
$d = get('/laporan/detail/' . $idPelapor);
ok('tombol Kirim TIDAK muncul untuk Draft milik pelapor', strpos($d['body'], 'laporan/kirim') === FALSE);
$s = post('/laporan/detail/' . $idPelapor, '/laporan/kirim/' . $idPelapor, array());
ok('POST langsung tetap ditolak (403)', $s['code'] === 403, 'HTTP ' . $s['code']);
ok('laporan pelapor tetap Draft', status($idPelapor) === 'Draft', status($idPelapor));

// kepala_ruangan ruang lain tidak boleh sama sekali
login('kepala_rawat', $PW);
$d = get('/laporan/detail/' . $idPelapor);
ok('kepala_ruangan ruang lain tidak bisa buka laporan IGD',
    in_array($d['code'], array(403, 307), TRUE), 'HTTP ' . $d['code']);

// ============================================================ 3. ADMIN
line();
line('=== 3. ADMIN ===');
login('admin', 'aeM*2uDZ2z%#VEki');
$s = post('/laporan/detail/' . $idPelapor, '/laporan/kirim/' . $idPelapor, array());
ok('admin boleh mengirim Draft milik orang lain', status($idPelapor) === 'Menunggu Grading', status($idPelapor));

// kirim dua kali harus ditolak (sudah bukan Draft)
$s = post('/laporan/detail/' . $idPelapor, '/laporan/kirim/' . $idPelapor, array());
ok('tidak bisa mengirim dua kali', status($idPelapor) === 'Menunggu Grading', status($idPelapor));

// ============================================================ 4. WAJIB ISI
line();
line('=== 4. FIELD WAJIB DITOLAK SAAT DISIMPAN ===');
// CATATAN: nama_pasien, nomor_mr, tanggal_insiden, jam_insiden, dan
// kronologi_insiden semuanya sudah `required` di _validasi(), jadi laporan
// dengan field kosong tidak pernah bisa tersimpan sama sekali. Cecek
// "belum lengkap" di submit() hanya pengaman, bukan jalur yang bisa Happens.
//
// Yang diuji di sini adalah aturan yang BENAR-BENAR berlaku: simpan ditolak.
login('pelapor_igd', $PW);
$r = post('/laporan/input', '/laporan/store', array(
    'nama_pasien' => 'Uji Tidak Lengkap ' . $SUF, 'nomor_mr' => 'MR-KOSONG-' . $SUF,
    'ruangan_id' => 3, 'jenis_insiden_id' => 1, 'akibat_insiden_id' => 1,
    'incident_category_id' => 1, 'specialization_id' => 1,
    // tanggal_insiden, jam_insiden, kronologi sengaja dikosongkan
));
$row = q('SELECT ir.id, ir.nomor FROM incident_reports ir
         JOIN patients p ON p.id = ir.patient_id
         WHERE p.nomor_mr = ?', array('MR-KOSONG-' . $SUF));
ok('laporan tanpa tanggal/jam/kronologi TIDAK tersimpan', count($row) === 0,
    count($row) . ' row');
$pasien = q('SELECT id FROM patients WHERE nomor_mr = ?', array('MR-KOSONG-' . $SUF));
ok('pasien ikut tidak tersimpan', count($pasien) === 0, count($pasien) . ' row');

$r = post('/laporan/input', '/laporan/store', array(
    'nomor_mr' => 'MR-KOSONG2-' . $SUF, 'ruangan_id' => 3,
    'jenis_insiden_id' => 1, 'akibat_insiden_id' => 1,
    'incident_category_id' => 1, 'specialization_id' => 1,
    'tanggal_insiden' => '2026-09-27', 'jam_insiden' => '10:00',
    'kronologi_insiden' => 'Uji tanpa nama pasien.',
    // nama_pasien sengaja dikosongkan
));
$row2 = q('SELECT ir.id FROM incident_reports ir JOIN patients p ON p.id = ir.patient_id
          WHERE p.nomor_mr = ?', array('MR-KOSONG2-' . $SUF));
ok('laporan tanpa nama pasien TIDAK tersimpan', count($row2) === 0, count($row2) . ' row');

// ============================================================ 5. BERSIHKAN
line();
line('=== 5. BERSIHKAN DATA UJI ===');
foreach ($BUAT as $id) {
    db()->prepare('DELETE FROM incident_report_history WHERE incident_report_id = ?')->execute(array($id));
    db()->prepare('DELETE FROM incident_report_actions WHERE incident_report_id = ?')->execute(array($id));
    db()->prepare('DELETE FROM incident_reports WHERE id = ?')->execute(array($id));
}

// Bersihkan SEMUA sisa uji, termasuk dari run yang sempat gagal di tengah jalan.
// Patients dihapus lebih dulu karena ada FK dari incident_reports.
foreach (array('MR-KIRIM-%', 'MR-KOSONG%', 'MR-ISO-%', 'MR-UJI-%') as $pola) {
    $ids = q('SELECT ir.id FROM incident_reports ir
              JOIN patients p ON p.id = ir.patient_id
              WHERE p.nomor_mr LIKE ?', array($pola));
    foreach ($ids as $x) {
        db()->prepare('DELETE FROM incident_report_history WHERE incident_report_id = ?')->execute(array($x['id']));
        db()->prepare('DELETE FROM incident_report_actions WHERE incident_report_id = ?')->execute(array($x['id']));
        db()->prepare('DELETE FROM incident_reports WHERE id = ?')->execute(array($x['id']));
    }
    db()->prepare('DELETE FROM patients WHERE nomor_mr LIKE ?')->execute(array($pola));
}

$cek = q('SELECT id, nomor FROM incident_reports ORDER BY id');
// Assertion ini TIDAK boleh mengasumsikan database kosong. Database bisa
// sudah berisi data demo (seed_demo.php) atau laporan asli, jadi yang dicek
// hanya: tidak ada sisa data uji milik test ini.
$sisa = q("SELECT COUNT(*) AS n FROM incident_reports ir
           JOIN patients p ON p.id = ir.patient_id
           WHERE p.nomor_mr LIKE 'MR-KIRIM-%'
              OR p.nomor_mr LIKE 'MR-KOSONG%'
              OR p.nomor_mr LIKE 'MR-ISO-%'");
ok('tidak ada laporan uji yang tersisa', (int) $sisa[0]['n'] === 0, $sisa[0]['n'] . ' row');
line('  laporan yang ada sekarang: ' . count($cek) . ' (tidak harus 1)');

$cekP = q("SELECT COUNT(*) AS n FROM patients
           WHERE nomor_mr LIKE 'MR-KIRIM-%'
              OR nomor_mr LIKE 'MR-KOSONG%'
              OR nomor_mr LIKE 'MR-ISO-%'");
ok('tidak ada pasien uji yang tersisa', (int) $cekP[0]['n'] === 0, $cekP[0]['n'] . ' row');

line();
line('=== RINGKASAN: ' . $pass . ' OK, ' . $fail . ' GAGAL ===');
exit($fail === 0 ? 0 : 1);
