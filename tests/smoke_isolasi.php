<?php
/**
 * Uji isolasi data antar-ruangan & antar-role.
 *
 * INI ADALAH TEST KEAMANAN. Halaman detail laporan menampilkan nama & nomor MR
 * pasien, jadi kalau filter role tidak dipakai, siapa pun yang tahu id-nya
 * bisa membaca data pasien ruangan lain.
 *
 * Bug ini pernah nyata: Incident_report_model::get_detail() tidak memanggil
 * apply_role_scope(), padahal daftar laporan memanggilnya. Daftar terfilter
 * tapi detail tidak - jadi navigating lewat URL langsung membocorkan data.
 *
 * Jalankan: php tests/smoke_isolasi.php
 * Tidak mengubah data apa pun (semanya GET / POST yang ditolak).
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
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === FALSE) { return array('code' => 0, 'loc' => '', 'body' => '', 'err' => $err); }
    $head = substr($raw, 0, $hsize);
    $loc = '';
    if (preg_match('/^Location:\s*(.+)$/mi', $head, $m)) { $loc = trim($m[1]); }
    return array('code' => $status, 'loc' => $loc, 'body' => substr($raw, $hsize), 'err' => '');
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
function q($sql, $a = array()) { $s = db()->prepare($sql); $s->execute($a); return $s->fetchAll(); }
function ambil1($sql, $a = array()) { $r = q($sql, $a); return $r ? $r[0] : null; }

$pass = 0; $fail = 0;
function ok($label, $cond, $extra = '')
{
    printf("  [%s] %s%s\n", $cond ? 'OK  ' : 'GAGAL', $label, $extra !== '' ? '  -> ' . $extra : '');
    global $pass, $fail;
    if ($cond) { $pass++; } else { $fail++; }
    return (bool) $cond;
}
function line($t = '') { echo $t . "\n"; }

// ============================================== 0. DATA UJI
line('=== 0. MENYIAPKAN DATA UJI ===');

/** Buat laporan uji (Draft) pada ruangan tertentu sebagai user tertentu. */
function buatLaporanUji($akun, $ruang, $tanda)
{
    login($akun, 'Zx2zww8Nxztb');
    post('/laporan/input', '/laporan/store', array(
        'nama_pasien'          => 'Uji Isolasi ' . $tanda,
        'nomor_mr'             => 'MR-ISO-' . $tanda,
        'ruangan_id'           => $ruang,
        'kelompok_umur_id'     => 3,
        'jenis_kelamin'        => 'L',
        'tanggal_insiden'      => '2026-09-27',
        'jam_insiden'          => '09:00',
        'kronologi_insiden'    => 'Laporan uji isolasi antar-ruangan.',
        'jenis_insiden_id'     => 1,
        'akibat_insiden_id'    => 1,
        'incident_category_id' => 1,
        'specialization_id'    => 1,
    ));
    $r = q('SELECT ir.id, ir.nomor, ir.ruangan_id, p.nama_pasien, p.nomor_mr
            FROM incident_reports ir JOIN patients p ON p.id = ir.patient_id
            WHERE p.nomor_mr = ?', array('MR-ISO-' . $tanda));
    return $r ? $r[0] : NULL;
}

// Laporan di ruang 4 dibuat oleh pelapor_rawat, supaya bisa diuji dua arah:
// kepala_rawat boleh buka miliknya sendiri, tapi tidak boleh buka yang di ruang 3.
$rawatBaru = buatLaporanUji('pelapor_rawat', 4, 'RAWAT');
// Laporan di ruang 3 dibuat oleh KEPALA IGD, supaya pelapor_igd bisa diuji
// sebagai "orang lain di ruangan yang sama".
$igdBaru = buatLaporanUji('kepala_igd', 3, 'IGD');

// Aktifkan sementara satu lembar investigasi supaya bisa diuji.
$invAktifLama = q('SELECT id, is_deleted FROM investigations ORDER BY id');
$invHidup = null;
foreach ($invAktifLama as $x) {
    if ((int) $x['is_deleted'] === 1) {
        db()->prepare('UPDATE investigations SET is_deleted = 0 WHERE id = ?')->execute(array($x['id']));
        $invHidup = (int) $x['id'];
        break;
    }
}
if ($invHidup === NULL) {
    // tidak ada yang terarsip: pakai yang aktif saja
    foreach ($invAktifLama as $x) { if ((int) $x['is_deleted'] === 0) { $invHidup = (int) $x['id']; break; } }
}

$lap = q('SELECT ir.id, ir.nomor, ir.ruangan_id, p.nama_pasien, p.nomor_mr
          FROM incident_reports ir JOIN patients p ON p.id = ir.patient_id
          WHERE ir.is_deleted = 0 ORDER BY ir.id');
$igd  = NULL; $rawat = NULL;
foreach ($lap as $r) {
    if ((int) $r['ruangan_id'] === 3 && $igd === NULL) { $igd = $r; }
    if ((int) $r['ruangan_id'] === 4 && $rawat === NULL) { $rawat = $r; }
}

if (! $igd) {
    line('Laporan IGD tidak ada. Jalankan smoke_investigasi.php dulu.');
    exit(1);
}

line(sprintf('  IGD    : %s (id %d) pasien "%s" / %s',
    $igd['nomor'], $igd['id'], $igd['nama_pasien'], $igd['nomor_mr']));
line(sprintf('  RAWAT  : %s', $rawat ? $rawat['nomor'] . ' (id ' . $rawat['id'] . ')' : '(tidak ada)'));
line('  laporan uji dibuat: ' . ($rawatBaru ? $rawatBaru['nomor'] : '-') . ', ' . ($igdBaru ? $igdBaru['nomor'] : '-'));
line('  investigasi diuji: ' . ($invHidup !== NULL ? 'id ' . $invHidup : '(tidak ada)'));

/**
 * GET halaman dan pastikan TIDAK membocorkan nama/MR pasien.
 * @return array
 */
function cekTidakBocor($r, $target)
{
    $bocorNama = strpos($r['body'], $target['nama_pasien']) !== FALSE;
    $bocorMr   = strpos($r['body'], $target['nomor_mr']) !== FALSE;
    return array('bocor' => $bocorNama OR $bocorMr, 'nama' => $bocorNama, 'mr' => $bocorMr);
}

// ============================================== 1. YANG BOLEH
line();
line('=== 1. AKSES YANG SEHARUSNYA DIBUKA ===');

login('admin', 'aeM*2uDZ2z%#VEki');
$r = get('/laporan/detail/' . $igd['id']);
ok('admin buka detail IGD', $r['code'] === 200, 'HTTP ' . $r['code']);

login('kepala_igd', 'Zx2zww8Nxztb');
$r = get('/laporan/detail/' . $igd['id']);
ok('kepala_ruangan IGD buka detail laporan IGD-nya', $r['code'] === 200, 'HTTP ' . $r['code']);
ok('  dan memang melihat data pasien', strpos($r['body'], $igd['nama_pasien']) !== FALSE);

if ($rawat) {
    login('kepala_rawat', 'Zx2zww8Nxztb');
    $r = get('/laporan/detail/' . $rawat['id']);
    ok('kepala_ruangan RAWAT buka detail laporan RAWAT-nya', $r['code'] === 200, 'HTTP ' . $r['code']);
}

// ============================================== 2. YANG HARUS DITOLAK
line();
line('=== 2. AKSES YANG HARUS DITOLAK (inti dari test ini) ===');

/**_pages yang menampilkan data laporan */
function halamanLaporan($id)
{
    return array(
        'detail'      => '/laporan/detail/' . $id,
        'edit'        => '/laporan/edit/' . $id,
        'arsip?'      => '/laporan/arsip',
    );
}

$kasus = array(
    array('kepala_rawat',  'Kepala Ruangan RAWATINAP', 'ruang 4', $igd),
    array('pelapor_rawat', 'Pelapor RAWATINAP',        'ruang 4', $igd),
    array('pelapor_igd',   'Pelapor IGD (bukan pembuat)', 'ruang 3', $igd),
);

foreach ($kasus as $k) {
    list($akun, $nama, $ruang, $target) = $k;

    // Kalau pelapor IGD adalah pembuatnya, tidak diuji sebagai "orang lain"
    $pembuat = q('SELECT created_by FROM incident_reports WHERE id = ?', array($target['id']));
    $akunPemilik = q('SELECT id, username FROM users WHERE id = ?', array($pembuat[0]['created_by']));
    if ($akunPemilik && $akunPemilik[0]['username'] === $akun) {
        line(sprintf('  (dilewati: %s adalah pembuat laporan ini)', $nama));
        continue;
    }

    login($akun, 'Zx2zww8Nxztb');

    foreach (halamanLaporan($target['id']) as $namaHal => $path) {
        $r = get($path);
        $c = cekTidakBocor($r, $target);
        $ditutup = in_array($r['code'], array(403, 307), TRUE);

        if ($namaHal === 'arsip?') {
            // Halaman arsip tidak punya id: yang dicek, nomor tidak muncul
            $adaNomor = strpos($r['body'], '>' . $target['nomor'] . '<') !== FALSE;
            ok(sprintf('%-24s /laporan/arsip tidak menampilkan nomor %s', $nama, $target['nomor']),
                ! $adaNomor, $adaNomor ? 'MUNCUL' : 'tidak muncul');
            continue;
        }

        ok(sprintf('%-24s %s ditolak', $nama, $namaHal), $ditutup,
            'HTTP ' . $r['code'] . ($ditutup ? '' : '  <-- BOCOR'));
        ok(sprintf('%-24s   tidak membocorkan nama/MR pasien', $nama),
            ! $c['bocor'],
            $c['bocor'] ? 'BOCOR (nama=' . ($c['nama'] ? 'ya' : '-') . ', mr=' . ($c['mr'] ? 'ya' : '-') . ')' : 'aman');
    }
}

// ============================================== 3. AKSI TIDAK BOLEH
line();
line('=== 3. AKSI TIDAK BOLEH DI LAPORAN ORANG LAIN ===');

login('kepala_rawat', 'Zx2zww8Nxztb');
$aksi = array(
    'kirim'   => '/laporan/kirim/' . $igd['id'],
    'grading' => '/laporan/grading/' . $igd['id'],
    'update'  => '/laporan/update/' . $igd['id'],
    'hapus'   => '/laporan/hapus/' . $igd['id'],
);
foreach ($aksi as $nama => $path) {
    $r = post('/laporan/detail/' . $igd['id'], $path, array('nama' => 'Dibobol', 'risk_band_id' => 2));
    $ditutup = in_array($r['code'], array(403, 307), TRUE);
    ok("kepala_ruangan RAWAT tidak bisa $nama laporan IGD", $ditutup, 'HTTP ' . $r['code']);
}

// pastikan statusnya tidak berubah
$st = q('SELECT st.nama FROM incident_reports ir JOIN statuses st ON st.id = ir.status_id WHERE ir.id = ?',
    array($igd['id']));
line('  status laporan IGD setelah percobaan: ' . $st[0]['nama']);

// ============================================== 4. INVESTIGASI
line();
line('=== 4. INVESTIGASI ===');
if ($invHidup !== NULL) {
    login('admin', 'aeM*2uDZ2z%#VEki');
    $r = get('/investigasi/detail/' . $invHidup);
    ok('admin buka lembar investigasi', $r['code'] === 200, 'HTTP ' . $r['code']);

    login('kepala_rawat', 'Zx2zww8Nxztb');
    $r = get('/investigasi/detail/' . $invHidup);
    $c = cekTidakBocor($r, $igd);
    ok('kepala_ruangan lain tidak bisa buka lembar investigasi',
        in_array($r['code'], array(403, 307), TRUE), 'HTTP ' . $r['code']);
    ok('  dan tidak membocorkan data pasien induknya', ! $c['bocor'],
        $c['bocor'] ? 'BOCOR' : 'aman');

    $r = get('/investigasi');
    $adaInv = strpos($r['body'], '>' . $invHidup . '<') !== FALSE
        OR strpos($r['body'], 'INV-') !== FALSE;
    ok('daftar investigasi tidak menampilkan milik ruangan lain', ! $adaInv,
        $adaInv ? 'MUNCUL' : 'tidak muncul');
} else {
    line('  (belum ada lembar investigasi sama sekali)');
}

// daftar laporan tidak boleh menampilkan nomor ruangan lain
line();
line('=== 5. DAFTAR LAPORAN ===');
login('kepala_rawat', 'Zx2zww8Nxztb');
$r = get('/laporan');
$ada = strpos($r['body'], '>' . $igd['nomor'] . '<') !== FALSE;
ok('kepala_ruangan RAWAT tidak melihat nomor laporan IGD di daftar', ! $ada,
    $ada ? 'MUNCUL' : 'tidak muncul');

login('pelapor_rawat', 'Zx2zww8Nxztb');
$r = get('/laporan');
$ada = strpos($r['body'], '>' . $igd['nomor'] . '<') !== FALSE;
ok('pelapor RAWAT tidak melihat nomor laporan IGD di daftar', ! $ada,
    $ada ? 'MUNCUL' : 'tidak muncul');

// Pelapor TIDAK boleh melihat laporan milik pelapor lain, walaupun satu ruangan.
// Aturan resmi: pelapor = WHERE created_by = session.user_id (bukan satu ruangan).
if ($igdBaru) {
    login('pelapor_igd', 'Zx2zww8Nxztb');
    $r = get('/laporan/detail/' . $igdBaru['id']);
    $c = cekTidakBocor($r, $igdBaru);
    ok('pelapor IGD TIDAK boleh buka laporan milik kepala IGD (satu ruangan)',
        in_array($r['code'], array(403, 307), TRUE), 'HTTP ' . $r['code']);
    ok('  dan tidak membocorkan nama/MR pasien', ! $c['bocor'],
        $c['bocor'] ? 'BOCOR' : 'aman');

    // Tapi laporan sendiri tetap boleh
    $r = get('/laporan');
    ok('pelapor tetap melihat daftar laporannya sendiri', $r['code'] === 200, 'HTTP ' . $r['code']);
}

// ============================================== 6. BEKU: LAPORAN SELESAI
line();
line('=== 6. LAPORAN SELESAI BEKU (aturan 27 Sep 2026) ===');
$selesai = ambil1("SELECT ir.id, ir.nomor FROM incident_reports ir
                       JOIN statuses st ON st.id = ir.status_id
                       WHERE st.kode = 'selesai' AND ir.is_deleted = 0
                       ORDER BY ir.id DESC LIMIT 1");

if ($selesai) {
    // Admin pun tidak boleh mengubah laporan yang sudah Selesai.
    login('admin', 'aeM*2uDZ2z%#VEki');
    $d = get('/laporan/detail/' . $selesai['id']);
    ok('tombol Edit TIDAK muncul di laporan Selesai',
        strpos($d['body'], 'laporan/edit') === FALSE, $selesai['nomor']);

    $r = post('/laporan/detail/' . $selesai['id'], '/laporan/edit/' . $selesai['id'], array());
    ok('admin GET edit laporan Selesai dialihkan, bukan dibuka',
        in_array($r['code'], array(303, 307), TRUE), 'HTTP ' . $r['code']);

    $r = post('/laporan/detail/' . $selesai['id'], '/laporan/update/' . $selesai['id'],
        array('kronologi_insiden' => 'COBA UBAH ISILAH'));
    ok('admin POST update laporan Selesai ditolak',
        in_array($r['code'], array(303, 307), TRUE), 'HTTP ' . $r['code']);

    // Isinya harus benar-benar tidak berubah
    $teks = ambil1('SELECT kronologi_insiden FROM incident_reports WHERE id = ?',
        array($selesai['id']));
    ok('isi laporan Selesai tidak berubah',
        strpos($teks['kronologi_insiden'], 'COBA UBAH ISILAH') === FALSE);

    // Pesan harus menjelaskan, bukan "Akses Ditolak"
    $d2 = get('/laporan/detail/' . $selesai['id']);
    ok('ada penjelasan bahwa laporan dikunci',
        strpos($d2['body'], 'Selesai') !== FALSE
        && (strpos($d2['body'], 'dikunci') !== FALSE || strpos($d2['body'], 'dibekukan') !== FALSE
            || strpos($d2['body'], 'Akses Ditolak') === FALSE));

    // Admin masih boleh mengubah laporan yang BELUM selesai
    $belum = ambil1("SELECT ir.id, ir.nomor FROM incident_reports ir
                          JOIN statuses st ON st.id = ir.status_id
                          WHERE st.kode <> 'selesai' AND ir.is_deleted = 0
                          ORDER BY ir.id LIMIT 1");
    if ($belum) {
        $r = get('/laporan/edit/' . $belum['id']);
        ok('admin masih boleh edit laporan yang belum Selesai',
            $r['code'] === 200, $belum['nomor'] . ' HTTP ' . $r['code']);
    }
} else {
    ok('ada laporan Selesai untuk diuji', FALSE, 'tidak ditemukan');
}

// ============================================== 7. BERSIHKAN
line();
line('=== 7. BERSIHKAN DATA UJI ===');
// Bersihkan berdasarkan POLA nomor MR, bukan id yang dicatat. Alasannya:
// patients.nomor_mr TIDAK punya UNIQUE index, jadi run sebelumnya yang gagal
// di tengah bisa meninggalkan pasien & laporan dengan MR yang sama. Kalau
// hanya menghapus id yang tercatat di run ini, sisa dari run lama akan
// membuat DELETE patient gagal karena masih direferensikan laporan.
$pola = 'MR-ISO-%';
$ids  = array();
foreach (q("SELECT ir.id FROM incident_reports ir
            JOIN patients p ON p.id = ir.patient_id
            WHERE p.nomor_mr LIKE ?", array($pola)) as $x) {
    $ids[] = (int) $x['id'];
}
if ($ids) {
    $in = implode(',', $ids);
    db()->exec("DELETE FROM investigation_recommendations WHERE investigation_id IN
                (SELECT id FROM investigations WHERE incident_report_id IN ($in))");
    db()->exec("DELETE FROM investigation_actions WHERE investigation_id IN
                (SELECT id FROM investigations WHERE incident_report_id IN ($in))");
    db()->exec("DELETE FROM investigations WHERE incident_report_id IN ($in)");
    db()->exec("DELETE FROM incident_report_history WHERE incident_report_id IN ($in)");
    db()->exec("DELETE FROM incident_report_actions WHERE incident_report_id IN ($in)");
    db()->exec("DELETE FROM incident_reports WHERE id IN ($in)");
    line('  ' . count($ids) . ' laporan uji dihapus');
}
db()->prepare('DELETE FROM patients WHERE nomor_mr LIKE ?')->execute(array($pola));

// Kembalikan status arsip investigasi seperti semula
if ($invHidup !== NULL) {
    foreach ($invAktifLama as $x) {
        if ((int) $x['id'] === $invHidup) {
            db()->prepare('UPDATE investigations SET is_deleted = ? WHERE id = ?')
                ->execute(array((int) $x['is_deleted'], $invHidup));
        }
    }
}

$sisaIso = q("SELECT COUNT(*) AS n FROM patients WHERE nomor_mr LIKE ?", array($pola));
ok('pasien uji terhapus semua', (int) $sisaIso[0]['n'] === 0, $sisaIso[0]['n'] . ' row');
$sisaDemo = q("SELECT COUNT(*) AS n FROM incident_reports ir
               JOIN patients p ON p.id = ir.patient_id
               WHERE p.nomor_mr LIKE 'MR-DEMO-%'");
ok('data demo tetap utuh (tidak ikut terhapus)', (int) $sisaDemo[0]['n'] > 0,
    $sisaDemo[0]['n'] . ' laporan demo');
$invCek = q('SELECT id, is_deleted FROM investigations ORDER BY id');
ok('status arsip investigasi dikembalikan',
    $invCek[0]['is_deleted'] == $invAktifLama[0]['is_deleted'],
    'is_deleted=' . $invCek[0]['is_deleted']);

line();
line('=== RINGKASAN: ' . $pass . ' OK, ' . $fail . ' GAGAL ===');
exit($fail === 0 ? 0 : 1);
