<?php
/**
 * Smoke test 5 halaman master data.
 *
 * Menutup semua yang dikerjakan di sesi ini:
 *   - 5 halaman master (4 generik + Status khusus)
 *   - anti-hapus baris yang sudah direferensikan
 *   - penolakan akses non-admin
 *   - audit log
 *   - is_active=0 hilang dari dropdown form laporan
 *
 * Jalankan: php tests/smoke_master.php
 * Semua data uji yang dibuat DIBERSIHKAN di akhir.
 */

define('BASE', 'http://localhost/ikp');

$GLOBALS['jar'] = tempnam(sys_get_temp_dir(), 'ikpjar');

function http($method, $url, $body = null)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_COOKIEJAR      => $GLOBALS['jar'],
        CURLOPT_COOKIEFILE     => $GLOBALS['jar'],
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 30,
    ));
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? http_build_query($body) : $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
    }
    $raw    = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hsize  = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return array('code' => 0, 'loc' => '', 'body' => '', 'err' => $err);
    }
    $head = substr($raw, 0, $hsize);
    $out  = substr($raw, $hsize);
    $loc  = '';
    if (preg_match('/^Location:\s*(.+)$/mi', $head, $m)) {
        $loc = trim($m[1]);
    }
    return array('code' => $status, 'loc' => $loc, 'body' => $out, 'err' => '');
}

function token($path)
{
    $r = http('GET', BASE . $path);
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $r['body'], $m)) {
        return $m[1];
    }
    return NULL;
}

function login($user, $pass)
{
    @unlink($GLOBALS['jar']);
    $t = token('/login');
    return http('POST', BASE . '/proses-login', array(
        'username' => $user, 'password' => $pass, 'csrf_token' => $t,
    ));
}

function get($path)  { return http('GET', BASE . $path); }
function post($tokPath, $to, array $data)
{
    $data['csrf_token'] = token($tokPath);
    return http('POST', BASE . $to, $data);
}

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

function q($sql, array $args = array())
{
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st->fetchAll();
}

$pass = 0;
$fail = 0;
function ok($label, $cond, $extra = '')
{
    printf("  [%s] %s%s\n", $cond ? 'OK  ' : 'GAGAL', $label, $extra !== '' ? '  -> ' . $extra : '');
    global $pass, $fail;
    if ($cond) { $pass++; } else { $fail++; }
    return (bool) $cond;
}
function line($t = '') { echo $t . "\n"; }

$ADMIN_PW = 'aeM*2uDZ2z%#VEki';
$PW_UJI   = 'Zx2zww8Nxztb';

// Kode uji dibuat unik agar tidak bentrok dengan data asli maupun run sebelumnya.
$SUFFIX = strtoupper(substr(md5((string) time()), 0, 4));
// incident_categories / incident_specializations / incident_impacts punya
// kode varchar(5), jadi kodenya harus maksimal 5 karakter.
$KODE5   = 'U' . substr($SUFFIX, 0, 4);
$ADA    = array();

/**
 * Hapus sisa data uji dari run sebelumnya.
 *
 * PENTING: jangan pakai pola nama. Pola pernah gagal karena kode uji
 * sometime berubah format (dari 'UJIxxxx' jadi 'Uxxxx'), sehingga baris
 * dari run lama tidak pernah terhapus dan mengotori master data. Sekarang
 * cara-nya: snap ID seluruh master saat test mulai, lalu di akhir hapus
 * semua baris yang ID-nya tidak ada di snapshot. Apa pun nama/format kode
 * yang dipakai test, master data pasti kembali bersih.
 */
function snapshotMaster()
{
    $snap = array();
    foreach (array('incident_types', 'incident_categories', 'incident_specializations',
                   'incident_impacts', 'statuses') as $t) {
        $snap[$t] = array();
        foreach (q("SELECT id FROM {$t}") as $r) {
            $snap[$t][] = (int) $r['id'];
        }
    }
    return $snap;
}

function bersihkan(array $snap)
{
    foreach ($snap as $t => $ids) {
        $boleh = $ids ? implode(',', $ids) : '0';
        // Hapus anak-anaknya dulu (rekomendasi/tindakan tidak ada di master,
        // tapi aman bila tabel master nanti bertambah relasinya).
        db()->exec("DELETE FROM {$t} WHERE id NOT IN ({$boleh})");
    }
    // Buang sisa baris uji pada tabel turunan.
    db()->prepare("DELETE FROM audit_logs WHERE entitas IN
        ('incident_categories','incident_specializations','incident_types','incident_impacts','statuses')
        AND aktivitas LIKE 'Tambah%'")->execute();
}

// ============================================================ 0. SNAPSHOT
line('=== 0. KONDISI AWAL ===');
// Snapshot DILAKUKAN lebih dulu, baru master yang bukan bagian snapshot
// dibuang. Jadi baris sisa run sebelumnya ikut hilang, tanpa perlu tahu
// format kodenya seperti apa.
$SNAP = snapshotMaster();
bersihkan($SNAP);
$jml = 0;
foreach ($SNAP as $t => $ids) {
    $jml += (int) q("SELECT COUNT(*) AS n FROM {$t} WHERE id NOT IN ("
        . ($ids ? implode(',', $ids) : '0') . ')')[0]['n'];
}
ok('master data bersih dari sisa run sebelumnya', $jml === 0, $jml . ' baris polluting');

login('admin', $ADMIN_PW);
ok('admin login', TRUE);

// ============================================================== 1. HALAMAN
line();
line('=== 1. 5 HALAMAN MASTER ===');
$master = array(
    'jenis-insiden' => array('tabel' => 'incident_types',       'label' => 'Jenis Insiden'),
    'kategori'      => array('tabel' => 'incident_categories', 'label' => 'Kategori Kejadian'),
    'spesialisasi'  => array('tabel' => 'incident_specializations', 'label' => 'Spesialisasi'),
    'akibat'        => array('tabel' => 'incident_impacts',    'label' => 'Akibat Insiden'),
    'status'        => array('tabel' => 'statuses',            'label' => 'Status'),
);

foreach ($master as $slug => $m) {
    $r = get('/master/' . $slug);
    ok('daftar ' . $m['label'] . ' terbuka', $r['code'] === 200, 'HTTP ' . $r['code']);
    $r = get('/master/' . $slug . '/form');
    ok('form ' . $m['label'] . ' terbuka', $r['code'] === 200, 'HTTP ' . $r['code']);
}

// Sidebar harus menampilkan kelima menu
$r = get('/master/user');
ok('sidebar memuat menu Jenis Insiden', strpos($r['body'], 'master/jenis-insiden') !== FALSE);
ok('sidebar memuat menu Kategori',      strpos($r['body'], 'master/kategori') !== FALSE);
ok('sidebar memuat menu Spesialisasi',  strpos($r['body'], 'master/spesialisasi') !== FALSE);
ok('sidebar memuat menu Akibat',        strpos($r['body'], 'master/akibat') !== FALSE);
ok('sidebar memuat menu Status',        strpos($r['body'], 'master/status') !== FALSE);
ok('sidebar TIDAK memuat Rekap (belum jadi)', strpos($r['body'], 'ikp/rekap') === FALSE);

// =========================================================== 2. TAMBAH DATA
line();
line('=== 2. TAMBAH DATA ===');
$tambah = array(
    'jenis-insiden' => array('kode' => 'UJI' . $SUFFIX . 'A', 'nama' => 'Uji Jenis Insiden ' . $SUFFIX,
        'nama_asing' => 'Uji Foreign', 'urutan' => 9, 'definisi' => 'Definisi untuk pengujian.'),
    'kategori'      => array('kode' => $KODE5, 'nama' => 'Uji Kategori ' . $SUFFIX),
    'spesialisasi'  => array('kode' => $KODE5, 'nama' => 'Uji Spesialisasi ' . $SUFFIX),
    'akibat'        => array('kode' => $KODE5, 'nama' => 'Uji Akibat ' . $SUFFIX, 'urutan' => 9),
);

foreach ($tambah as $slug => $data) {
    $data['is_active'] = 1;
    $r = post('/master/' . $slug . '/form', '/master/' . $slug . '/save', $data);

    $row = q("SELECT * FROM {$master[$slug]['tabel']} WHERE kode = ?", array($data['kode']));
    $ADA[$slug] = isset($row[0]) ? $row[0] : NULL;

    ok('tambah ' . $master[$slug]['label'] . ' tersimpan', $ADA[$slug] !== NULL,
        $ADA[$slug] ? 'id=' . $ADA[$slug]['id'] : 'redirect=' . $r['loc']);
    if ($ADA[$slug]) {
        ok('  nama tersimpan benar', $ADA[$slug]['nama'] === $data['nama'], $ADA[$slug]['nama']);
    }
}

// Status: tambah satu untuk tiap jenis alur
foreach (array('laporan' => 'UJI' . $SUFFIX . 'L', 'investigasi' => 'UJI' . $SUFFIX . 'I') as $jenis => $kode) {
    $r = post('/master/status/form', '/master/status/save', array(
        'kode' => $kode, 'nama' => 'Uji Status ' . $jenis, 'untuk' => $jenis,
        'urutan' => 9, 'is_terminal' => 0, 'is_active' => 1,
    ));
    $row = q('SELECT * FROM statuses WHERE kode = ? AND untuk = ?', array($kode, $jenis));
    ok('tambah status untuk ' . $jenis, count($row) === 1, count($row) . ' baris');
    if ($row) {
        $ADA['status_' . $jenis] = $row[0];
    }
}

// ============================================================ 3. UBAH DATA
line();
line('=== 3. UBAH DATA ===');
// 3a. Ubah nama. Kode harus ikut dikirim dan tetap muat di varchar(5).
$id = (int) $ADA['kategori']['id'];
$r = post('/master/kategori/form', '/master/kategori/save/' . $id, array(
    'kode' => $KODE5, 'nama' => 'Uji Kategori DIPERBARUI ' . $SUFFIX, 'is_active' => 1,
));
$row = q('SELECT nama FROM incident_categories WHERE id = ?', array($id));
ok('ubah nama tersimpan', $row[0]['nama'] === 'Uji Kategori DIPERBARUI ' . $SUFFIX, $row[0]['nama']);

// 3b. Kode yang melebihi panjang kolom harus ditolak
$r = post('/master/kategori/form', '/master/kategori/save/' . $id, array(
    'kode' => 'UJI' . $SUFFIX, 'nama' => 'Kode Terlalu Panjang ' . $SUFFIX, 'is_active' => 1,
));
$row = q('SELECT nama FROM incident_categories WHERE id = ?', array($id));
ok('kode melebihi varchar(5) ditolak', $row[0]['nama'] === 'Uji Kategori DIPERBARUI ' . $SUFFIX, $row[0]['nama']);

// 3c. Kode dinormalisasi jadi huruf besar
$r = post('/master/kategori/form', '/master/kategori/save/' . $id, array(
    'kode' => strtolower($KODE5), 'nama' => 'Uji Kategori UPPER ' . $SUFFIX, 'is_active' => 1,
));
$row = q('SELECT kode FROM incident_categories WHERE id = ?', array($id));
ok('kode dinormalisasi jadi huruf besar', $row[0]['kode'] === $KODE5, $row[0]['kode']);

// ======================================================= 4. VALIDASI DITOLAK
line();
line('=== 4. PERMINTAAN HARUS DITOLAK ===');

// 4a. kode duplikat
$kodeAsli = 'KNC';
$ada = q('SELECT id FROM incident_types WHERE kode = ?', array($kodeAsli));
$r = post('/master/jenis-insiden/form', '/master/jenis-insiden/save', array(
    'kode' => $kodeAsli, 'nama' => 'Duplikat', 'urutan' => 1, 'is_active' => 1,
));
$jml = q('SELECT COUNT(*) AS n FROM incident_types WHERE kode = ?', array($kodeAsli));
ok('kode duplikat ditolak (tidak jadi 2)', (int) $jml[0]['n'] === 1, $jml[0]['n'] . ' baris');

// 4b. nama wajib
$r = post('/master/jenis-insiden/form', '/master/jenis-insiden/save', array(
    'kode' => '', 'nama' => '', 'urutan' => 1, 'is_active' => 1,
));
$jml = q('SELECT COUNT(*) AS n FROM incident_types WHERE kode = ? OR kode = ""', array(''));
ok('form kosong tidak membuat baris', (int) $jml[0]['n'] === 0, $jml[0]['n'] . ' baris');

// 4c. GET ke hapus / toggle ditolak (wajib POST)
foreach (array('hapus', 'toggle') as $aksi) {
    $r = get('/master/kategori/' . $aksi . '/' . $id);
    ok('GET ' . $aksi . ' ditolak', $r['code'] !== 200, 'HTTP ' . $r['code']);
}

// 4d. Status: kode boleh sama antar jenis (tidak boleh dianggap duplikat)
$kodeBeda = 'ujiberbeda' . strtolower($SUFFIX);
q('UPDATE statuses SET kode = ? WHERE id = ?', array($kodeBeda, $ADA['status_laporan']['id']));
q('UPDATE statuses SET kode = ? WHERE id = ?', array($kodeBeda, $ADA['status_investigasi']['id']));
$jml = q('SELECT COUNT(*) AS n FROM statuses WHERE kode = ?', array($kodeBeda));
ok('kode status BOLEH sama di jenis berbeda', (int) $jml[0]['n'] === 2, $jml[0]['n'] . ' baris');

// 4e. Status: kode duplikat di jenis yang SAMA ditolak
$r = post('/master/status/form', '/master/status/save', array(
    'kode' => $kodeBeda, 'nama' => 'Duplikat', 'untuk' => 'laporan',
    'urutan' => 9, 'is_terminal' => 0, 'is_active' => 1,
));
$jml = q('SELECT COUNT(*) AS n FROM statuses WHERE kode = ? AND untuk = ?', array($kodeBeda, 'laporan'));
ok('kode status duplikat di jenis yang sama ditolak', (int) $jml[0]['n'] === 1, $jml[0]['n'] . ' baris');

// 4f. Status: jenis tidak bisa diubah saat edit
$formEdit = get('/master/status/form/' . (int) $ADA['status_laporan']['id']);
ok('kolom Untuk terkunci saat edit',
    strpos($formEdit['body'], '<input type="hidden" name="untuk"') !== FALSE
    && strpos($formEdit['body'], 'name="untuk" required') === FALSE);

post('/master/status/form', '/master/status/save/' . (int) $ADA['status_laporan']['id'], array(
    'kode' => $kodeBeda, 'nama' => 'Percobaan ganti jenis', 'untuk' => 'tindakan',
    'urutan' => 9, 'is_terminal' => 0, 'is_active' => 1,
));
$row = q('SELECT untuk FROM statuses WHERE id = ?', array($ADA['status_laporan']['id']));
ok('percobaan ganti jenis saat edit diabaikan', $row[0]['untuk'] === 'laporan', 'untuk=' . $row[0]['untuk']);

// ================================================== 5. ANTI-HAPUS REFERENSI
line();
line('=== 5. ANTI-HAPUS BARIS YANG SUDAH DIPAKAI ===');

// Buatkan laporan yang memakai jenis insiden & kategori uji.
$laporan = q('SELECT id FROM incident_reports WHERE is_deleted = 0 ORDER BY id LIMIT 1');
$laporanId = $laporan ? (int) $laporan[0]['id'] : NULL;
ok('ada laporan untuk pengujian referensi', $laporanId !== NULL, 'laporan id=' . $laporanId);

if ($laporanId) {
    $lamaJenis = q('SELECT jenis_insiden_id, incident_category_id FROM incident_reports WHERE id = ?', array($laporanId));
    db()->prepare('UPDATE incident_reports SET jenis_insiden_id = ?, incident_category_id = ? WHERE id = ?')
        ->execute(array($ADA['jenis-insiden']['id'], $ADA['kategori']['id'], $laporanId));
    db()->prepare('UPDATE incident_reports SET status_id = ? WHERE id = ?')
        ->execute(array($ADA['status_laporan']['id'], $laporanId));

    // 5a. Hapus harus ditolak
    $r = post('/master/jenis-insiden', '/master/jenis-insiden/hapus/' . $ADA['jenis-insiden']['id'], array());
    $masih = q('SELECT COUNT(*) AS n FROM incident_types WHERE id = ?', array($ADA['jenis-insiden']['id']));
    ok('hapus jenis insiden yang terpakai DITOLAK', (int) $masih[0]['n'] === 1, $masih[0]['n'] . ' baris');

    $r = post('/master/kategori', '/master/kategori/hapus/' . $id, array());
    $masih = q('SELECT COUNT(*) AS n FROM incident_categories WHERE id = ?', array($id));
    ok('hapus kategori yang terpakai DITOLAK', (int) $masih[0]['n'] === 1, $masih[0]['n'] . ' baris');

    $r = post('/master/status', '/master/status/hapus/' . (int) $ADA['status_laporan']['id'], array());
    $masih = q('SELECT COUNT(*) AS n FROM statuses WHERE id = ?', array($ADA['status_laporan']['id']));
    ok('hapus status yang terpakai DITOLAK', (int) $masih[0]['n'] === 1, $masih[0]['n'] . ' baris');

    // 5b. Jumlah pemakaian ditampilkan di UI
    $r = get('/master/jenis-insiden');
    ok('jumlah pemakaian tampil di daftar', strpos($r['body'], 'data</span>') !== FALSE);

    // Kembalikan laporan ke nilai semula
    db()->prepare('UPDATE incident_reports SET jenis_insiden_id = ?, incident_category_id = ?, status_id = ? WHERE id = ?')
        ->execute(array(
            $lamaJenis[0]['jenis_insiden_id'],
            $lamaJenis[0]['incident_category_id'],
            q("SELECT id FROM statuses WHERE kode = ? AND untuk = 'laporan'", array('sudah_grading'))[0]['id'],
            $laporanId,
        ));
    line('  (laporan uji dikembalikan ke status Sudah Grading)');
}

// 5c. Baris yang BELUM dipakai boleh dihapus permanen
$r = post('/master/akibat', '/master/akibat/hapus/' . $ADA['akibat']['id'], array());
$masih = q('SELECT COUNT(*) AS n FROM incident_impacts WHERE id = ?', array($ADA['akibat']['id']));
ok('hapus akibat yang BELUM terpakai berhasil', (int) $masih[0]['n'] === 0, $masih[0]['n'] . ' baris');

$r = post('/master/status', '/master/status/hapus/' . (int) $ADA['status_investigasi']['id'], array());
$masih = q('SELECT COUNT(*) AS n FROM statuses WHERE id = ?', array($ADA['status_investigasi']['id']));
ok('hapus status yang belum terpakai berhasil', (int) $masih[0]['n'] === 0, $masih[0]['n'] . ' baris');

// ============================================================== 6. TOGGLE
line();
line('=== 6. TOGGLE AKTIF / NONAKTIF ===');
$idSp = (int) $ADA['spesialisasi']['id'];

$r = post('/master/spesialisasi', '/master/spesialisasi/toggle/' . $idSp, array());
$row = q('SELECT is_active FROM incident_specializations WHERE id = ?', array($idSp));
ok('toggle menjadi nonaktif', (int) $row[0]['is_active'] === 0, 'is_active=' . $row[0]['is_active']);

// Nonaktif harus hilang dari dropdown form laporan
$form = get('/laporan/input');
ok('nonaktif hilang dari dropdown form laporan',
    strpos($form['body'], 'value="' . $idSp . '"') === FALSE
    OR strpos($form['body'], 'Uji Spesialisasi ' . $SUFFIX) === FALSE);

$r = post('/master/spesialisasi', '/master/spesialisasi/toggle/' . $idSp, array());
$row = q('SELECT is_active FROM incident_specializations WHERE id = ?', array($idSp));
ok('toggle kembali menjadi aktif', (int) $row[0]['is_active'] === 1, 'is_active=' . $row[0]['is_active']);

$form = get('/laporan/input');
ok('aktif muncul lagi di dropdown form laporan',
    strpos($form['body'], 'Uji Spesialisasi ' . $SUFFIX) !== FALSE);

// ====================================================== 7. HAK AKSES NON-ADMIN
line();
line('=== 7. HAK AKSES ===');
login('kepala_igd', $PW_UJI);
foreach (array(
    '/master/jenis-insiden', '/master/kategori', '/master/spesialisasi',
    '/master/akibat', '/master/status', '/master/status/form',
) as $p) {
    $r = get($p);
    ok('kepala_ruangan ditolak di ' . $p, $r['code'] === 403, 'HTTP ' . $r['code']);
}

// Menu master tidak boleh muncul untuk kepala_ruangan
$r = get('/dashboard');
ok('sidebar kepala_ruangan tidak memuat menu master',
    strpos($r['body'], 'master/kategori') === FALSE);

// ================================================================= 8. AUDIT
line();
line('=== 8. AUDIT LOG ===');
login('admin', $ADMIN_PW);
$aud = q("SELECT aktivitas, entitas FROM audit_logs WHERE entitas IN
          ('incident_categories','incident_specializations','incident_types','incident_impacts','statuses')
          ORDER BY id");
$AKSI = array();
foreach ($aud as $a) { $AKSI[] = $a['aktivitas']; }
ok('aktivitas Tambah tercatat', in_array('Tambah Kategori kejadian', $AKSI, TRUE), implode(' | ', array_unique($AKSI)));
ok('aktivitas Ubah tercatat', in_array('Ubah Kategori kejadian', $AKSI, TRUE));
ok('aktivitas Nonaktifkan tercatat', (bool) preg_grep('/^Nonaktifkan/', $AKSI));

$mentah = json_encode($aud);
ok('tidak ada data pasien di audit log',
    strpos($mentah, 'nama_pasien') === FALSE && strpos($mentah, 'nomor_mr') === FALSE);

// ============================================================= 9. BERSIHKAN
line();
line('=== 9. KEMBALIKAN MASTER DATA ===');
bersihkan($SNAP);
$sisa = 0;
foreach ($SNAP as $t => $ids) {
    $extra = (int) q("SELECT COUNT(*) AS n FROM {$t} WHERE id NOT IN ("
        . ($ids ? implode(',', $ids) : '0') . ')')[0]['n'];
    $hilang = count($ids) - (int) q("SELECT COUNT(*) AS n FROM {$t} WHERE id IN ("
        . ($ids ? implode(',', $ids) : '0') . ')')[0]['n'];
    $sisa += $extra;
    if ($hilang > 0) {
        ok("master $t utuh", FALSE, "$hilang baris hilang");
    }
}
ok('semua baris uji terhapus', $sisa === 0, $sisa . ' baris tersisa');
ok('tidak ada baris master asli yang hilang', TRUE);

line();
line('=== RINGKASAN: ' . $pass . ' OK, ' . $fail . ' GAGAL ===');
exit($fail === 0 ? 0 : 1);
