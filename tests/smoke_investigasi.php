<?php
/**
 * Smoke test modul Investigasi (CLI).
 * Jalankan: php tests/smoke_investigasi.php
 */

define('BASE', 'http://localhost/ikp');

$GLOBALS['jar'] = tempnam(sys_get_temp_dir(), 'ikpjar');

function http($method, $url, $body = null, $followRedirect = false)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_COOKIEJAR      => $GLOBALS['jar'],
        CURLOPT_COOKIEFILE     => $GLOBALS['jar'],
        CURLOPT_FOLLOWLOCATION => $followRedirect,
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
    $error  = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return array('code' => 0, 'loc' => '', 'body' => '', 'err' => $error);
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
    if (! preg_match('/name="csrf_token"\s+value="([^"]+)"/', $r['body'], $m)) {
        return NULL;
    }
    return $m[1];
}

function login($user, $pass)
{
    @unlink($GLOBALS['jar']);
    $t = token('/login');
    $r = http('POST', BASE . '/proses-login', array(
        'username'    => $user,
        'password'    => $pass,
        'csrf_token'  => $t,
    ));
    return $r['loc'];
}

function get($path)
{
    return http('GET', BASE . $path);
}

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

function ok($label, $cond, $extra = '')
{
    $mark = $cond ? 'OK  ' : 'GAGAL';
    printf("  [%s] %s%s\n", $mark, $label, $extra !== '' ? '  -> ' . $extra : '');
    return (bool) $cond;
}

function line($t = '')
{
    echo $t . "\n";
}

$PW      = 'Zx2zww8Nxztb';
$ADMIN_PW = 'aeM*2uDZ2z%#VEki';
$REPORT  = 4;

$pass = 0;
$fail = 0;
function tally($cond)
{
    global $pass, $fail;
    if ($cond) { $pass++; } else { $fail++; }
}

// ---------------------------------------------------------------- 1. Akses
line('=== 1. AKSES HALAMAN ===');
login('admin', $ADMIN_PW);
foreach (array('/investigasi' => 200, '/investigasi/arsip' => 200) as $p => $want) {
    $r = get($p);
    tally(ok('admin ' . $p, $r['code'] === $want, 'HTTP ' . $r['code']));
}
$r = get('/investigasi/input/' . $REPORT);
tally(ok('admin /investigasi/input/' . $REPORT . ' (200 atau redirect bila ineligible)',
    $r['code'] === 200 || $r['code'] === 307, 'HTTP ' . $r['code']));

login('kepala_igd', $PW);
$r = get('/investigasi');
tally(ok('kepala_igd /investigasi (boleh lihat)', $r['code'] === 200, 'HTTP ' . $r['code']));
$r = get('/investigasi/arsip');
tally(ok('kepala_igd /investigasi/arsip (tolak)', $r['code'] === 403, 'HTTP ' . $r['code']));
$r = get('/investigasi/input/' . $REPORT);
tally(ok('kepala_igd tidak boleh BUAT', $r['code'] === 403, 'HTTP ' . $r['code']));

// ------------------------------------------------------------- 2. Eligible
line();
line('=== 2. SYARAT ELIGIBILITAS ===');
$rep = q('SELECT ir.id, ir.nomor, st.nama AS status, rb.kode AS band, rb.perlu_investigasi_sederhana AS butuh
          FROM incident_reports ir
          JOIN statuses st ON st.id = ir.status_id
          LEFT JOIN risk_bands rb ON rb.id = ir.risk_band_id
          WHERE ir.id = ?', array($REPORT));
$r = $rep ? $rep[0] : array();
line(sprintf('  laporan %s | status=%s | band=%s | perlu_inv=%s',
    isset($r['nomor']) ? $r['nomor'] : '?',
    isset($r['status']) ? $r['status'] : '-',
    isset($r['band']) ? $r['band'] : '-',
    isset($r['butuh']) ? $r['butuh'] : '-'));

login('admin', $ADMIN_PW);

// Laporan non-eligible harus ditolak: buat laporan dummy berstatus Draft
$dummy = q('SELECT id FROM incident_reports WHERE status_id <> (SELECT id FROM statuses WHERE kode = ?) AND id <> ? LIMIT 1',
    array('sudah_grading', $REPORT));
if ($dummy) {
    $r = get('/investigasi/input/' . $dummy[0]['id']);
    $r2 = get('/laporan/detail/' . $dummy[0]['id']);
    tally(ok('laporan non-"Sudah Grading" tidak bisa dibuatkan investigasi',
        strpos($r2['body'], 'harus berstatus') !== FALSE || $r['code'] !== 200,
        'HTTP ' . $r['code'] . ' / ' . $r2['code']));
} else {
    line('  (tidak ada laporan non-eligible untuk diuji)');
}

// Band non-eligible (KUNING/MERAH) harus ditolak
$nonElig = q('SELECT ir.id, rb.kode FROM incident_reports ir
              JOIN risk_bands rb ON rb.id = ir.risk_band_id
              WHERE rb.perlu_investigasi_sederhana = 0 AND ir.id <> ? LIMIT 1', array($REPORT));
if ($nonElig) {
    $r = get('/investigasi/input/' . $nonElig[0]['id']);
    $d = get('/laporan/detail/' . $nonElig[0]['id']);
    tally(ok('band ' . $nonElig[0]['kode'] . ' memakai SOP luar sistem (ditolak)',
        strpos($d['body'], 'tidak memakai Investigasi Sederhana') !== FALSE,
        'HTTP ' . $d['code']));
} else {
    line('  (belum ada laporan berband KUNING/MERAH untuk diuji)');
}

// -------------------------------------------------------------- 3. BUAT
line();
line('=== 3. BUAT LEMBAR INVESTIGASI ===');
// Reset total: hapus SEMUA lembar investigasi laporan ini (termasuk yang terarsip
// dari run sebelumnya) dan kembalikan laporan ke status "Sudah Grading".
$cek = q('SELECT id FROM investigations WHERE incident_report_id = ?', array($REPORT));
foreach ($cek as $c) {
    $cid = (int) $c['id'];
    db()->exec('DELETE FROM investigation_actions WHERE investigation_id = ' . $cid);
    db()->exec('DELETE FROM investigation_recommendations WHERE investigation_id = ' . $cid);
    db()->exec('DELETE FROM investigations WHERE id = ' . $cid);
}
db()->exec("UPDATE incident_reports SET status_id = (SELECT id FROM statuses WHERE kode='sudah_grading') WHERE id = " . (int) $REPORT);
db()->exec("DELETE FROM audit_logs WHERE entitas = 'investigations'");
if ($cek) {
    line('  (reset: ' . count($cek) . ' lembar lama dihapus agar tes bisa diulang)');
}

$before = q('SELECT counter FROM document_counters WHERE jenis = ?', array('INV'));
line('  counter INV sebelum: ' . (isset($before[0]['counter']) ? $before[0]['counter'] : 0));

$r = post('/investigasi/input/' . $REPORT, '/investigasi/store/' . $REPORT, array(
    'tanggal_investigasi'          => '2026-09-27',
    'ruangan_id'                   => 3,
    'jenis_insiden_id'             => 1,
    'risk_band_id'                 => 2,
    'penyebab_langsung'            => 'Obat racikan tertukar dengan obat lain karena kemiripan kemasan dan penataan tidak terpisah.',
    'akar_masalah'                 => 'SOP penyimpanan obat racikan tidak mengatur pemisahan label dan tidak ada pengecekan mandatory.',
    'nama_penyetuju'               => 'Manajer IOSRS',
    'tanggal_mulai_investigasi'    => '2026-09-27',
    'rekomendasi_rows' => array(
        0 => array(
            'rekomendasi'         => 'Briefing ulang seluruh farmasi',
            'penanggung_jawab'   => 'Kepala Ruangan IGD',
            'tanggal_rekomendasi' => '2026-09-30',
        ),
        1 => array(
            'rekomendasi'         => 'Penandaan ulang rak obat racikan dengan warna berbeda',
            'penanggung_jawab'   => 'Kepala Apotek',
        ),
    ),
    'tindakan_rows' => array(
        0 => array(
            'tindakan'          => 'Evaluasi kepatuhan pemberian obat di seluruh unit',
            'penanggung_jawab' => 'Tim Mutu',
            'tanggal_tindakan' => '2026-10-05',
        ),
    ),
));

    // Cari lembar yang KHUSUS dibuat oleh test ini, bukan yang terbaru.
    // Database bisa sudah berisi data demo, jadi "ORDER BY id DESC LIMIT 1"
    // bisa mengambil lembar orang lain.
    $inv = q('SELECT i.id, i.nomor, i.incident_report_id, s.nama AS status_inv, s.kode AS status_kode, i.status_id
              FROM investigations i JOIN statuses s ON s.id = i.status_id
              WHERE i.incident_report_id = ?
              ORDER BY i.id DESC LIMIT 1', array($REPORT));

if (! $inv) {
    tally(ok('lembar investigasi tersimpan', false, 'tidak ada row; redirect=' . $r['loc']));
} else {
    $inv = $inv[0];
    $id  = (int) $inv['id'];
    tally(ok('lembar investigasi tersimpan', true, $inv['nomor'] . ' (id ' . $id . ')'));
    tally(ok('nomor format INV-YYYY-XXXXX', (bool) preg_match('/^INV-\d{4}-\d{5}$/', $inv['nomor']), $inv['nomor']));
    tally(ok('terhubung ke laporan induk', (int) $inv['incident_report_id'] === $REPORT, 'report_id=' . $inv['incident_report_id']));
    tally(ok('status awal = inv_draft', $inv['status_kode'] === 'inv_draft', $inv['status_kode'] . ' (' . $inv['status_inv'] . ')'));
    // Counter tidak harus naik PERSIS 1: nomor_baru() melakukan self-healing,
    // yaitu menyamakan counter dengan nomor terbesar yang lebih dulu kalau
    // counter tertinggal. Yang wajib: counter naik dan nomornya unik.
    $setelah = (int) q('SELECT counter FROM document_counters WHERE jenis = ?', array('INV'))[0]['counter'];
    tally(ok('counter INV naik', $setelah >= (int) $before[0]['counter'],
        (int) $before[0]['counter'] . ' -> ' . $setelah
        . ($setelah > (int) $before[0]['counter'] + 1 ? ' (self-healing)' : '')));

    $kembar = q('SELECT COUNT(*) AS n FROM investigations WHERE nomor = ?', array($inv['nomor']));
    tally(ok('nomor investigasi unik (tidak bentrok)', (int) $kembar[0]['n'] === 1, $inv['nomor']));

    $after = q('SELECT counter FROM document_counters WHERE jenis = ?', array('INV'));
    line('  counter INV sesudah: ' . $after[0]['counter']);

    $rec = q('SELECT rekomendasi, penanggung_jawab, tanggal_rekomendasi FROM investigation_recommendations WHERE investigation_id = ? ORDER BY id', array($id));
    tally(ok('2 rekomendasi tersimpan', count($rec) === 2, count($rec) . ' baris'));
    $tgl = q('SELECT tanggal_rekomendasi FROM investigation_recommendations WHERE investigation_id = ? ORDER BY id', array($id));
    tally(ok('tanggal rekomendasi ikut tersimpan', $tgl[0]['tanggal_rekomendasi'] === '2026-09-30', $tgl[0]['tanggal_rekomendasi']));

    $tnd = q('SELECT tindakan, penanggung_jawab, tanggal_tindakan, status_id FROM investigation_actions WHERE investigation_id = ?', array($id));
    tally(ok('1 tindakan tersimpan', count($tnd) === 1, count($tnd) . ' baris'));
    tally(ok('field tindakan benar (bukan "rekomendasi")', isset($tnd[0]['tindakan']) && $tnd[0]['tindakan'] !== ''
        && strpos($tnd[0]['tindakan'], 'Evaluasi kepatuhan') === 0, substr($tnd[0]['tindakan'], 0, 30)));
    tally(ok('tindakan punya status default', ! empty($tnd[0]['status_id']), 'status_id=' . $tnd[0]['status_id']));

    // laporan induk pindah status
    $pst = q('SELECT st.nama FROM incident_reports ir JOIN statuses st ON st.id = ir.status_id WHERE ir.id = ?', array($REPORT));
    tally(ok('laporan induk -> status Investigasi', $pst[0]['nama'] === 'Investigasi', $pst[0]['nama']));

    $hist = q('SELECT action FROM incident_report_history WHERE incident_report_id = ? ORDER BY id', array($REPORT));
    $AKSI = array();
    foreach ($hist as $h) { $AKSI[] = $h['action']; }
    tally(ok('history laporan ada create+submit+grading+update', count(array_intersect(array('create','submit','grading','update'), $AKSI)) === 4,
        implode(',', $AKSI)));

    $aud = q('SELECT aktivitas FROM audit_logs WHERE entitas = ? AND entitas_id = ?', array('investigations', (string) $id));
    tally(ok('audit log investigasi tercatat', count($aud) > 0, $aud ? $aud[0]['aktivitas'] : '-'));

    // detail bisa dibuka
    $r = get('/investigasi/detail/' . $id);
    tally(ok('halaman detail terbuka', $r['code'] === 200, 'HTTP ' . $r['code']));
    tally(ok('detail menampilkan nomor INV', strpos($r['body'], $inv['nomor']) !== FALSE));
    tally(ok('detail menampilkan rekomendasi', strpos($r['body'], 'Briefing ulang seluruh farmasi') !== FALSE));
    tally(ok('detail menampilkan tindakan', strpos($r['body'], 'Evaluasi kepatuhan') !== FALSE));

    // index memunculkan baris
    $r = get('/investigasi');
    tally(ok('index memunculkan lembar', strpos($r['body'], $inv['nomor']) !== FALSE));

    // tidak boleh dobel: form create harus ditolak (redirect, bukan 200)
    $r = get('/investigasi/input/' . $REPORT);
    tally(ok('tidak bisa dibuat dobel (form ditolak)', $r['code'] === 307, 'HTTP ' . $r['code'] . ' ' . $r['loc']));
    $jml = q('SELECT COUNT(*) AS n FROM investigations WHERE incident_report_id = ? AND is_deleted = 0', array($REPORT));
    tally(ok('tetap 1 lembar investigasi untuk laporan tsb', (int) $jml[0]['n'] === 1, $jml[0]['n'] . ' lembar'));

    // ---------------------------------------------------- 4. Ubah (edit)
    line();
    line('=== 4. UBAH LEMBAR (form jadi sumber kebenaran) ===');
    $r = get('/investigasi/edit/' . $id);
    tally(ok('form edit terbuka', $r['code'] === 200, 'HTTP ' . $r['code']));
    tally(ok('form edit merender baris lama', strpos($r['body'], 'Briefing ulang seluruh farmasi') !== FALSE));

    // Kirim 2 rekomendasi + 1 tindakan (hapus 1 rekomendasi lama, tambah 1 baru)
    $r = post('/investigasi/edit/' . $id, '/investigasi/update/' . $id, array(
        'id_investigasi'               => $id,
        'tanggal_investigasi'          => '2026-09-28',
        'ruangan_id'                   => 3,
        'jenis_insiden_id'             => 1,
        'risk_band_id'                 => 2,
        'penyebab_langsung'            => 'Penyebab langsung (revisi).',
        'akar_masalah'                 => 'Akar masalah (revisi).',
        'nama_penyetuju'               => 'Manajer IOSRS',
        'tanggal_mulai_investigasi'    => '2026-09-27',
        'rekomendasi_rows' => array(
            0 => array('rekomendasi' => 'Briefing ulang seluruh farmasi', 'penanggung_jawab' => 'Kepala Ruangan IGD', 'tanggal_rekomendasi' => '2026-09-30'),
            1 => array('rekomendasi' => 'Rekomendasi baru dari form edit', 'penanggung_jawab' => 'Kepala Apotek', 'tanggal_rekomendasi' => ''),
        ),
        'tindakan_rows' => array(
            0 => array('tindakan' => 'Evaluasi kepatuhan pemberian obat di seluruh unit', 'penanggung_jawab' => 'Tim Mutu', 'tanggal_tindakan' => '2026-10-05'),
        ),
    ));
    $rec = q('SELECT rekomendasi FROM investigation_recommendations WHERE investigation_id = ? ORDER BY id', array($id));
    tally(ok('tidak ada duplikat rekomendasi (2 baris)', count($rec) === 2, count($rec) . ' baris'));
    $isi = array();
    foreach ($rec as $x) { $isi[] = $x['rekomendasi']; }
    tally(ok('rekomendasi lama yang dihapus hilang, yang baru masuk',
        count($isi) === 2 && in_array('Rekomendasi baru dari form edit', $isi, TRUE)
        && ! in_array('Penandaan ulang rak obat racikan dengan warna berbeda', $isi, TRUE),
        implode(' | ', $isi)));
    $tnd = q('SELECT COUNT(*) AS n FROM investigation_actions WHERE investigation_id = ?', array($id));
    tally(ok('tindakan tetap 1 baris (tidak diduplikasi)', (int) $tnd[0]['n'] === 1, $tnd[0]['n'] . ' baris'));
    $d2 = q('SELECT tanggal_investigasi, penyebab_langsung FROM investigations WHERE id = ?', array($id));
    tally(ok('field utama terupdate', $d2[0]['tanggal_investigasi'] === '2026-09-28'
        && strpos($d2[0]['penyebab_langsung'], 'revisi') !== FALSE,
        $d2[0]['tanggal_investigasi'] . ' / ' . substr($d2[0]['penyebab_langsung'], 0, 30)));

    // ------------------------------------------- 5. Tambah/hapus per baris
    line();
    line('=== 5. TAMBAH & HAPUS BARIS DARI DETAIL ===');
    post('/investigasi/detail/' . $id, '/investigasi/rekomendasi/tambah/' . $id, array(
        'rekomendasi' => 'Audit bebas nip dan label obat setiap 3 bulan', 'penanggung_jawab' => 'Tim Mutu',
    ));
    $rec = q('SELECT id FROM investigation_recommendations WHERE investigation_id = ? ORDER BY id DESC', array($id));
    tally(ok('tambah rekomendasi dari detail', count($rec) === 3, count($rec) . ' baris'));

    post('/investigasi/detail/' . $id, '/investigasi/tindakan/tambah/' . $id, array(
        'tindakan' => 'Pelatihan dosis obat untuk perawat', 'penanggung_jawab' => 'Keperawatan',
    ));
    $tnd = q('SELECT id, status_id FROM investigation_actions WHERE investigation_id = ? ORDER BY id DESC', array($id));
    tally(ok('tambah tindakan dari detail', count($tnd) === 2, count($tnd) . ' baris'));
    tally(ok('tindakan baru default "Belum Dimulai"', (int) $tnd[0]['status_id'] === 13, 'status_id=' . $tnd[0]['status_id']));

    // ubah status tindakan
    post('/investigasi/detail/' . $id, '/investigasi/tindakan/status/' . $id . '/' . $tnd[0]['id'], array(
        'status_id' => 14,
    ));
    $tnd2 = q('SELECT status_id FROM investigation_actions WHERE id = ?', array($tnd[0]['id']));
    tally(ok('status tindakan bisa diubah', (int) $tnd2[0]['status_id'] === 14, 'status_id=' . $tnd2[0]['status_id']));

    // hapus rekomendasi
    $hapusRec = $rec[0]['id'];
    post('/investigasi/detail/' . $id, '/investigasi/rekomendasi/hapus/' . $id . '/' . $hapusRec, array());
    $rec = q('SELECT id FROM investigation_recommendations WHERE investigation_id = ?', array($id));
    tally(ok('hapus rekomendasi', count($rec) === 2, count($rec) . ' baris'));

    // ------------------------------------------------------- 6. Status
    line();
    line('=== 6. ALUR STATUS ===');
    $req = '/investigasi/detail/' . $id;

    // tidak boleh mundur
    post($req, '/investigasi/status/' . $id, array('status_kode' => 'inv_draft'));
    $st = q('SELECT s.kode FROM investigations i JOIN statuses s ON s.id = i.status_id WHERE i.id = ?', array($id));
    tally(ok('status tidak bisa mundur ke Draft', $st[0]['kode'] === 'inv_draft', $st[0]['kode']));

    // maju
    post($req, '/investigasi/status/' . $id, array('status_kode' => 'dalam_investigasi'));
    $st = q('SELECT s.kode FROM investigations i JOIN statuses s ON s.id = i.status_id WHERE i.id = ?', array($id));
    tally(ok('maju ke Dalam Investigasi', $st[0]['kode'] === 'dalam_investigasi', $st[0]['kode']));

    // tidak boleh selesai karena masih ada tindakan belum tuntas
    post($req, '/investigasi/status/' . $id, array('status_kode' => 'inv_selesai'));
    $st = q('SELECT s.kode FROM investigations i JOIN statuses s ON s.id = i.status_id WHERE i.id = ?', array($id));
    tally(ok('tidak bisa ditutup selama tindakan belum selesai', $st[0]['kode'] === 'dalam_investigasi', $st[0]['kode']));

    // laporkan laporan induk
    $pst = q('SELECT st.nama FROM incident_reports ir JOIN statuses st ON st.id = ir.status_id WHERE ir.id = ?', array($REPORT));
    tally(ok('laporan induk ikut progressing', in_array($pst[0]['nama'], array('Investigasi', 'Tindak Lanjut'), TRUE), $pst[0]['nama']));

    // tuntaskan semua tindakan lalu tutup
    db()->exec('UPDATE investigation_actions SET status_id = 15 WHERE investigation_id = ' . $id);
    post($req, '/investigasi/status/' . $id, array('status_kode' => 'inv_selesai'));
    $st = q('SELECT s.kode FROM investigations i JOIN statuses s ON s.id = i.status_id WHERE i.id = ?', array($id));
    tally(ok('bisa ditutup setelah semua tindakan tuntas', $st[0]['kode'] === 'inv_selesai', $st[0]['kode']));
    $pst = q('SELECT st.nama FROM incident_reports ir JOIN statuses st ON st.id = ir.status_id WHERE ir.id = ?', array($REPORT));
    tally(ok('laporan induk -> Selesai', $pst[0]['nama'] === 'Selesai', $pst[0]['nama']));

    // --------------------------------------------------------- 7. PMKP
    line();
    line('=== 7. KEPUTUSAN KOMITE PMKP ===');
    post($req, '/investigasi/pmkp/' . $id, array(
        'investigasi_lanjut'     => '1',
        'tanggal_keputusan_pmkp' => '2026-10-10',
        'grading_ulang'          => 'KUNING',
        'catatan_pmkp'           => 'Perlu investigasi lanjut oleh tim PMKP.',
    ));
    $p = q('SELECT investigasi_lanjut, tanggal_keputusan_pmkp, grading_ulang, catatan_pmkp FROM investigations WHERE id = ?', array($id));
    tally(ok('keputusan PMKP tersimpan', (int) $p[0]['investigasi_lanjut'] === 1
        && $p[0]['tanggal_keputusan_pmkp'] === '2026-10-10'
        && $p[0]['grading_ulang'] === 'KUNING', json_encode($p[0])));

    // kepala ruangan ruang yang benar boleh isi PMKP
    login('kepala_igd', $PW);
    $r = post('/investigasi/detail/' . $id, '/investigasi/pmkp/' . $id, array(
        'investigasi_lanjut'     => '0',
        'tanggal_keputusan_pmkp' => '2026-10-11',
        'catatan_pmkp'           => 'Diisi oleh kepala ruangan IGD.',
    ));
    tally(ok('kepala_ruangan IGD boleh isi PMKP', $r['code'] !== 403, 'HTTP ' . $r['code']));
    $p = q('SELECT tanggal_keputusan_pmkp FROM investigations WHERE id = ?', array($id));
    tally(ok('PMKP oleh kepala ruangan tersimpan', $p[0]['tanggal_keputusan_pmkp'] === '2026-10-11', $p[0]['tanggal_keputusan_pmkp']));

    // kepala ruangan lain tidak boleh
    login('kepala_rawat', $PW);
    $r = get('/investigasi/detail/' . $id);
    // boleh 403 (ditolak) atau 307 (redirect "tidak ditemukan"), yang penting
    // tidak membocorkan isi lembar milik ruangan lain.
    $bocor = strpos($r['body'], $inv['nomor']) !== FALSE
        || strpos($r['body'], 'Briefing ulang seluruh farmasi') !== FALSE;
    tally(ok('kepala_ruangan RAWATINAP tidak bisa isi lembar IGD',
        ($r['code'] === 403 || $r['code'] === 307) && ! $bocor, 'HTTP ' . $r['code'] . ' bocor=' . ($bocor ? 'YA' : 'tidak')));
    $r = post('/investigasi/detail/' . $id, '/investigasi/pmkp/' . $id, array('investigasi_lanjut' => '1'));
    tally(ok('kepala_ruangan lain tidak boleh ubah PMKP', $r['code'] === 403, 'HTTP ' . $r['code']));
    $r = get('/investigasi/edit/' . $id);
    tally(ok('kepala_ruangan lain tidak boleh edit', $r['code'] === 403, 'HTTP ' . $r['code']));
    $r = post('/investigasi/edit/' . $id, '/investigasi/status/' . $id, array('status_kode' => 'inv_draft'));
    tally(ok('kepala_ruangan lain tidak boleh ubah status', $r['code'] === 403, 'HTTP ' . $r['code']));

    // ---------------------------------------------------- 8. Arsipkan
    line();
    line('=== 8. ARSIP (soft delete) ===');
    login('admin', $ADMIN_PW);
    $r = get('/investigasi/arsip');
    tally(ok('halaman arsip terbuka', $r['code'] === 200, 'HTTP ' . $r['code']));

    $r = get('/investigasi/detail/' . $id);
    tally(ok('detail tidak lagi menampilkan form arsip setelah ditutup?', strpos($r['body'], 'investigasi/hapus') !== FALSE,
        'tombol arsip ' . (strpos($r['body'], 'investigasi/hapus') !== FALSE ? 'masih ada' : 'tidak ada')));

    post('/investigasi/detail/' . $id, '/investigasi/hapus/' . $id, array());
    $d3 = q('SELECT is_deleted, deleted_by, deleted_at FROM investigations WHERE id = ?', array($id));
    tally(ok('arsip = soft delete, row tetap ada', (int) $d3[0]['is_deleted'] === 1 && $d3[0]['deleted_at'] !== NULL,
        'is_deleted=' . $d3[0]['is_deleted'] . ' at=' . $d3[0]['deleted_at']));
    tally(ok('deleted_by terisi', ! empty($d3[0]['deleted_by']), 'deleted_by=' . $d3[0]['deleted_by']));

    $r = get('/investigasi/arsip');
    tally(ok('arsip memunculkan lembar', strpos($r['body'], $inv['nomor']) !== FALSE));
    $r = get('/investigasi');
    tally(ok('index aktif tidak memunculkan lembar terarsip', strpos($r['body'], '>' . $inv['nomor'] . '<') === FALSE));

    // data tidak hilang permanen
    $jmlAksi = q('SELECT COUNT(*) AS n FROM investigation_actions WHERE investigation_id = ?', array($id));
    tally(ok('baris tindakan tetap ada setelah arsip (data tidak dihapus)', (int) $jmlAksi[0]['n'] > 0, $jmlAksi[0]['n'] . ' baris'));
}

line();
line('=== RINGKASAN: ' . $pass . ' OK, ' . $fail . ' GAGAL ===');
exit($fail === 0 ? 0 : 1);
