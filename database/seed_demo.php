<?php
/**
 * Seed data demo yang realistis untuk menguji dashboard, rekap, dan statistik.
 *
 *   php database/seed_demo.php --reset    hapus data demo lama, lalu buat ulang
 *   php database/seed_demo.php --append    tambah data demo saja
 *   php database/seed_demo.php --laporan   ringkasan saja, tidak mengubah apa pun
 *
 * HANYA untuk development. Skrip menolak jalan kalau ENVIRONMENT = production.
 *
 * BAND YANG DIPAKAI HANYA BIRU & HIJAU
 * --------------------------------------
 * KUNING dan MERAH sengaja TIDAK dipakai. Grading ke dua band itu ditolak
 * selama kolom `kriteria`-nya kosong, dan kriteria WAJIB berasal dari dokumen
 * resmi fasilitas kesehatan - sistem tidak boleh mengarangnya. Jadi data demo
 * ini belum mewakili distribusi 4 band. Begitu klien memberi kriteria, ubah
 * $PAKAI_KUNING_MERAH di bawah menjadi true lalu jalankan ulang.
 *
 * PENANDA DATA DEMO
 * ------------------
 * Semua pasien demo bernomor MR berawalan "MR-DEMO-". Itu yang dipakai
 * --reset untuk membersihkan, jadi data asli tidak pernah tersentuh.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Forbidden: hanya untuk CLI.');
}

$root   = dirname(__DIR__);
$isProd = strpos(@file_get_contents($root . '/index.php'), "define('ENVIRONMENT', 'production')") !== false;
if ($isProd) {
    die('DITOLAK: ENVIRONMENT production. Skrip ini hanya untuk development.' . PHP_EOL);
}

$PAKAI_KUNING_MERAH = false;   // lihat catatan di header

// ------------------------------------------------------------------ argumen
$mode = 'append';
foreach (array_slice($_SERVER['argv'], 1) as $a) {
    if ($a === '--reset')   { $mode = 'reset'; }
    if ($a === '--append')  { $mode = 'append'; }
    if ($a === '--laporan') { $mode = 'laporan'; }
}

// Jumlah laporan demo per bulan (Feb - Ags 2026). Tren naik supaya grafik
// rekap punya bentuk yang masuk akal, bukan garis datar.
$PER_BULAN = array(
    '2026-02' => 4,
    '2026-03' => 5,
    '2026-04' => 6,
    '2026-05' => 7,
    '2026-06' => 8,
    '2026-07' => 9,
    '2026-08' => 10,
);

/** Porsi laporan BIRU/HIJAU yang dibuatkan lembar investigasi. */
$RASIO_INVESTIGASI = 0.50;

/** Every Nth report is archived, so the archive page is not empty. */
$ARSIP_SETIAP = 17;

/**
 * Porsi jenis insiden. KNC paling banyak karena itu yang paling sering
 * terjadi di lapangan dan justru sering tidak dilaporkan kalau tidak ada
 * sistem. KTD dan Sentinel jelas paling jarang.
 */
$PEMBAGIAN_JENIS = array(
    array('KNC', 45),
    array('KTC', 30),
    array('KTD', 18),
    array('ST',  7),
);

// ------------------------------------------------------------------ koneksi
$envFile = $root . DIRECTORY_SEPARATOR . '.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') { continue; }
        $p = explode('=', $line, 2);
        if (count($p) !== 2) { continue; }
        $k = trim($p[0]);
        $v = trim($p[1]);
        if (strlen($v) > 1 && (($v[0] === '"' && substr($v, -1) === '"') || ($v[0] === "'" && substr($v, -1) === "'"))) {
            $v = substr($v, 1, -1);
        }
        if (getenv($k) === false) { putenv($k . '=' . $v); }
    }
}

$db = @new mysqli(
    getenv('DB_HOST') ?: '127.0.0.1',
    getenv('DB_USER') ?: 'root',
    getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
    getenv('DB_NAME') ?: 'ikp'
);
if ($db->connect_errno) {
    die('GAGAL koneksi: ' . $db->connect_error . PHP_EOL);
}
$db->set_charset('utf8mb4');

// ------------------------------------------------------------------ utilitas
function out($m = '') { echo $m . PHP_EOL; }

function pilih($db, $sql, array $args = array())
{
    $st = $db->prepare($sql);
    if ($args) { $st->bind_param(str_repeat('s', count($args)), ...$args); }
    $st->execute();
    $r  = $st->get_result();
    $rs = $r ? $r->fetch_all(MYSQLI_ASSOC) : array();
    $st->close();
    return $rs;
}

function hitung($db, $sql, array $args = array())
{
    $st = $db->prepare($sql);
    if ($args) { $st->bind_param(str_repeat('s', count($args)), ...$args); }
    $st->execute();
    $r = $st->get_result()->fetch_row();
    $st->close();
    return (int) $r[0];
}

function ins($db, $sql, array $args)
{
    $st = $db->prepare($sql);
    $st->bind_param(str_repeat('s', count($args)), ...$args);
    if (! $st->execute()) {
        die('GAGAL INSERT: ' . $st->error . PHP_EOL . $sql . PHP_EOL);
    }
    $id = (int) $st->insert_id;
    $st->close();
    return $id;
}

function jalankan($db, $sql) { return $db->query($sql); }

// Deterministik: hasil seed bisa diulang persis, memudahkan membandingkan.
mt_srand(20260927);
function acak($min, $max)    { return $min + mt_rand(0, $max - $min); }
function pilihAcak(array $a) { return $a[mt_rand(0, count($a) - 1)]; }
function peluang($p)         { return mt_rand(1, 100) <= $p; }
function kutip($s) { return "'" . str_replace("'", "''", $s) . "'"; }

/** Pilih satu item dari daftar {nama, bobot} sesuai bobotnya. */
function pilihBerbobot(array $daftar)
{
    $total = 0;
    foreach ($daftar as $d) { $total += $d[1]; }
    $x = mt_rand(1, max(1, $total));
    $akum = 0;
    foreach ($daftar as $d) {
        $akum += $d[1];
        if ($x <= $akum) { return $d[0]; }
    }
    return $daftar[0][0];
}

// ------------------------------------------------------------------ master
function master($db, $tabel)
{
    return pilih($db, "SELECT id, kode, nama FROM {$tabel} WHERE is_active = 1 ORDER BY id");
}

$ruangan = array();
foreach (pilih($db, 'SELECT id, kode, nama FROM ruangan WHERE is_active = 1') as $r) {
    $ruangan[$r['kode']] = $r;
}
foreach (array('IGD', 'RAWATINAP') as $k) {
    if (! isset($ruangan[$k])) {
        die('GAGAL: ruangan ' . $k . ' tidak ada. Jalankan seed_test.php dulu.' . PHP_EOL);
    }
}

function petaStatus($db, $untuk)
{
    $p = array();
    foreach (pilih($db, "SELECT id, kode FROM statuses WHERE untuk = '{$untuk}'") as $r) {
        $p[$r['kode']] = (int) $r['id'];
    }
    return $p;
}
$S  = petaStatus($db, 'laporan');
$SI = petaStatus($db, 'investigasi');
$ST = petaStatus($db, 'tindakan');

$band = array();
foreach (pilih($db, 'SELECT id, kode FROM risk_bands WHERE is_active = 1') as $r) {
    $band[$r['kode']] = (int) $r['id'];
}
$bandSimple = array();
foreach (pilih($db, 'SELECT id, kode FROM risk_bands
                     WHERE is_active = 1 AND perlu_investigasi_sederhana = 1') as $r) {
    $bandSimple[$r['kode']] = (int) $r['id'];
}
if (! isset($bandSimple['BIRU']) || ! isset($bandSimple['HIJAU'])) {
    die('GAGAL: band BIRU / HIJAU tidak ditemukan.' . PHP_EOL);
}
if ($PAKAI_KUNING_MERAH && (! isset($band['KUNING']) || ! isset($band['MERAH']))) {
    die('GAGAL: band KUNING / MERAH tidak ditemukan.' . PHP_EOL);
}

$jenis     = master($db, 'incident_types');
$akibat    = master($db, 'incident_impacts');
$kategori  = master($db, 'incident_categories');
$spes      = master($db, 'incident_specializations');
$umur      = master($db, 'age_groups');
$perawatan = master($db, 'care_types');
$tim       = master($db, 'action_teams');
$pj        = master($db, 'patient_responsibles');
$pelapor0  = master($db, 'first_reporters');

$userPelapor = pilih($db, "SELECT id, username, nama, ruangan_id FROM users
                           WHERE role = 'pelapor' AND is_active = 1 AND is_deleted = 0 ORDER BY id");
$userKepala  = pilih($db, "SELECT id, username, nama, ruangan_id FROM users
                           WHERE role = 'kepala_ruangan' AND is_active = 1 AND is_deleted = 0 ORDER BY id");
if (! $userPelapor || ! $userKepala) {
    die('GAGAL: user uji belum ada. Jalankan seed_test.php dulu.' . PHP_EOL);
}

$adminId = hitung($db, "SELECT id FROM users WHERE username = 'admin' AND is_deleted = 0");
if (! $adminId) { $adminId = (int) $userPelapor[0]['id']; }

$pelaporRuang = array();
$kepalaRuang  = array();
foreach (array('IGD', 'RAWATINAP') as $k) {
    $rid = (int) $ruangan[$k]['id'];
    $pelaporRuang[$k] = array();
    foreach ($userPelapor as $u) {
        if ((int) $u['ruangan_id'] === $rid) { $pelaporRuang[$k][] = $u; }
    }
    $kepalaRuang[$k] = null;
    foreach ($userKepala as $u) {
        if ((int) $u['ruangan_id'] === $rid) { $kepalaRuang[$k] = $u; }
    }
    if (! $pelaporRuang[$k]) {
        die('GAGAL: ruangan ' . $k . ' harus punya minimal satu akun pelapor.' . PHP_EOL);
    }
}

// ------------------------------------------------------------------ teks demo
$NAMA = array(
    'Tn. Budi Santoso', 'Siti Aminah', 'Ahmad Fauzi', 'Dewi Lestari', 'Rudi Hartono',
    'Nurul Hidayah', 'Agus Setiawan', 'Rina Marlina', 'Bambang Suryanto', 'Indah Permatasari',
    'Slamet Riyadi', 'Maya Sari', 'Joko Prasetyo', 'Fitri Handayani', 'Hendra Gunawan',
    'Wulandari', 'Eko Purnomo', 'Lina Marlina', 'Yantournawan', 'Ratna Kumala',
    'Supardi', 'Ani Rahmawati', 'Dedi Kurniawan', 'Sri Wahyuni', 'Rudi Hartana',
    'Tatik Supiani', 'Asep Saepudin', 'Nina Rochman', 'Ujang Solihin', 'Mimin Sudarmawan',
);

/**
 * Narasi insiden, dipisah per jenis supaya kronologi terasa nyata dan bukan
 * kalimat yang sama berulang. Sengaja tidak menyebut nama pasien: data pasien
 * tidak boleh masuk ke teks bebas yang menyimpang dari form.
 */
function kronologi($jenisKode, $kategoriNama, $akibatNama, $ruangNama)
{
    $pola = array(
        'KNC' => array(
            'Pemberian obat tidak sesuai dengan instruksi. Label obat tidak diperiksa dan kemasan dua jenis obat mirip sehingga tertukar. Tidak menimbulkan cidera; baru disadari saat penulisan catatan resume.',
            'Pasean mendapat obat lebih cepat dari jadwal karena jadwal pada grafik tidak diperbarui. Ketidaksesuaian terungkap saat konfirmasi dengan Apoteker, pasien tidak mengeluh.',
            'Pemberian obat tanpa verifikasi ulang oleh perawat kedua. Tidak ada cidera, tetapi prosedur keselamatan obat tidak diikuti secara lengkap.',
        ),
        'KTC' => array(
            'Obat yang diberikan tidak sesuai dengan dosis pada rekam medis. Dosis ganda tidak menimbulkan keluhan, sehingga baru terungkap saat peninjauan rekam medis.',
            'Pemeriksaan identitas tidak lengkap sebelum pemberian obat. Data pada gelang pasien sudah usang sehingga tidak terbaca jelas.',
            'Prosedur kecil dilakukan tanpa menunggu anjuran dokter. Pasien tidak mengalami cidera, tetapi prosedur dimulai lebih awal dari seharusnya.',
        ),
        'KTD' => array(
            'Pasean terjatuh saat turun dari tempat tidur karena pengaman tidak terpasang. Cidera ringan pada lutut, perlu observasi 24 jam.',
            'Dosis obat melebihi aturan karena berat badan yang dipakai adalah berat estimasi, bukan berat aktual. Memerlukan pemberian cairan 12 jam.',
            'Pasean menerima cairan melebihi resep karena tidak ada konfirmasi ulang. Perlu perawatan lanjutan selama dua hari.',
        ),
        'ST' => array(
            'Kejadian nyaris averted pada pasien kritis dengan catatan DNR. Respons cepat membuat kejadian dapat dicegah, dibahas pada bulletin keselamatan pasien.',
        ),
    );

    $opsi = isset($pola[$jenisKode])
        ? $pola[$jenisKode]
        : array('Kejadian tercatat di ' . $ruangNama . ' dengan kategori ' . $kategoriNama
            . ' dan akibat ' . $akibatNama . '.');

    return pilihAcak($opsi);
}

$SEBAB_LANGSUNG = array(
    'Label obat tidak diperiksa sebelum pemberian, dan kemasan dua jenis obat diletakkan berdampingan.',
    'Prosedur dimulai lebih dulu daripada anjuran dokter karena tim mengira anjuran sudah turun.',
    'Komunikasi antar shift tidak mencatat bahwa pasien sudah disiapkan untuk prosedur.',
    'Data identitas pada gelang pasien tidak diperbarui setelah pasien pindah dari ruang rawat.',
    'Alat tidak diperiksa sebelum dipakai karena tidak ada daftar periksa harian.',
    'Pemilihan obat mengacu pada kebiasaan, bukan pada rekam medis pasien.',
);
$AKAR = array(
    'SOP penyimpanan obat belum mengatur pemisahan label dan penataan rak untuk obat yang kemiripannya tinggi.',
    'Belum ada daftar periksa wajib sebelum prosedur yang melibatkan alat invasif.',
    'Alur serah terima antar shift belum memuat konfirmasi ulang atas persiapan pasien.',
    'Prosedur verifikasi identitas belum punya langkah ganda yang mengikat.',
    'Jadwal pemeliharaan alat tidak mencakup kalibrasi berkala.',
    'Formulir pencatatan tidak mewajibkan kolom waktu, sehingga kronologi sulit divalidasi.',
);
$REKOMENDASI = array(
    'Briefing ulang seluruh tim tentang alur verifikasi identitas.',
    'Penataan ulang rak obat dengan pemisahan warna untuk obat yang mirip.',
    'Menambahkan daftar periksa harian alat ukur ke jadwal perawat.',
    'Pelatihan singkat tentang pencatatan waktu pada formulir.',
    'Revisi SOP serah terima antar shift.',
    'Pemberian label tambahan pada obat berisiko tinggi.',
);
$CATATAN_PMKP = array(
    'Perlu langkah perbaikan yang berkelanjutan, bukan sekali jalan.',
    'Komite menganggap perlu monitoring lanjutan selama 30 hari.',
    'Diteruskan ke tim mutu untuk pemantauan berikutnya.',
    'Sudah ada perbaikan, tetapi tetap perlu evaluasi pada kuartal berikutnya.',
);
$TEMPAT = array(
    'Ruang triage', 'Ruang tindakan observasi', 'Ruang perawatan inap lantai dua',
    'Ruang persiapan operasi', 'Instalasi farmasi', 'Ruang gawat darurat',
);
$PENANGGUBAT = array(
    'Perawat jaga', 'Perawat penanggung jawab', 'Dokterarrer', 'Kepala perawat',
    'Koordinator ruang', 'Penanggung jawab shift',
);
$TINDAKAN = array(
    'Menghentikan pemberian obat dan memonitor kondisi pasien.',
    'Memvalidasi ulang resep bersama Apoteker.',
    'Melaporkan kejadian ke kepala ruang untuk ditindaklanjuti.',
    'Melakukan asesmen ulang dan menambah observasi 24 jam.',
    'Mengganti alat dengan unit cadangan yang sudah terkalibrasi.',
    'Melengkapi pencatatan dan meminta verifikasi supervisor.',
);
$HASIL_TINDAKAN = array(
    'Pasien dalam kondisi stabil dan dapat dipulangkan.',
    'Kondisi pasien membaik setelah tindakan langsung.',
    'Pasien memerlukan observasi lanjutan selama 24 jam.',
    'Tidak ada keluhan lanjutan dari pasien atau keluarga.',
);
$PENYETUJU = array('Kepala Bagian Keperawatan', 'Kepala Bagian Medik', 'Manajer IOSRS', 'Kepala Unit');

$counterPasien = 0;

/** Buat satu pasien demo baru. Nomor MR selalu berawalan MR-DEMO-. */
function buatPasien($db, $nama, $ruanganId, $umurId, $jk, $pjId, $tgl, $jam)
{
    global $counterPasien, $ruangan;
    $counterPasien++;
    $kodeRuang = ((int) $ruanganId === (int) $ruangan['IGD']['id']) ? 'IGD' : 'RAW';
    $mr = sprintf('MR-DEMO-%s-%04d', $kodeRuang, $counterPasien);

    return ins($db,
        'INSERT INTO patients (nama_pasien, nomor_mr, ruangan_id, kelompok_umur_id,
            jenis_kelamin, penanggung_jawab_id, tanggal_masuk, jam_masuk, is_deleted)
         VALUES (?,?,?,?,?,?,?,?,0)',
        array($nama, $mr, $ruanganId, $umurId, $jk, $pjId, $tgl, $jam)
    );
}

// ------------------------------------------------------------------ reset
function resetDemo($db)
{
    out('Menghapus data demo lama ...');

    $lap = array();
    foreach (pilih($db, "SELECT ir.id FROM incident_reports ir
                         JOIN patients p ON p.id = ir.patient_id
                         WHERE p.nomor_mr LIKE 'MR-DEMO-%'") as $r) {
        $lap[] = (int) $r['id'];
    }

    if ($lap) {
        $in = implode(',', $lap);
        jalankan($db, "DELETE FROM investigation_recommendations WHERE investigation_id IN
            (SELECT id FROM investigations WHERE incident_report_id IN ($in))");
        jalankan($db, "DELETE FROM investigation_actions WHERE investigation_id IN
            (SELECT id FROM investigations WHERE incident_report_id IN ($in))");
        jalankan($db, "DELETE FROM audit_logs WHERE entitas = 'investigations' AND entitas_id IN
            (SELECT id FROM investigations WHERE incident_report_id IN ($in))");
        jalankan($db, "DELETE FROM investigations WHERE incident_report_id IN ($in)");

        jalankan($db, "DELETE FROM incident_report_history WHERE incident_report_id IN ($in)");
        jalankan($db, "DELETE FROM incident_report_actions WHERE incident_report_id IN ($in)");
        jalankan($db, "DELETE FROM audit_logs WHERE entitas = 'incident_reports' AND entitas_id IN ($in)");
        jalankan($db, "DELETE FROM incident_reports WHERE id IN ($in)");
        out('  ' . count($lap) . ' laporan demo dihapus');
    }

    // Catatan: mysqli::query() untuk DELETE mengembalikan TRUE (bukan objek
    // result), jadi jumlah baris Taken harus dibaca dari $db->affected_rows.
    jalankan($db, "DELETE FROM patients WHERE nomor_mr LIKE 'MR-DEMO-%'");
    out('  ' . $db->affected_rows . ' pasien demo dihapus');
}

/**
 * Beri nomor dokumen berurutan tanpa celah, lalu setel document_counters
 * supaya nomor berikutnya lanjut dari angka terakhir.
 *
 * Perlunya: smoke test sebelumnya sudah memakai INV-2026-00001 sampai
 * 00015 lalu menghapusnya. Tanpa perataan ini, lembar investigasi pertama
 * yang muncul di data demonumbered INV-2026-00016, yang terlihat aneh.
 */
function rapikanNomor($db)
{
    foreach (array('INS' => 'incident_reports', 'INV' => 'investigations') as $jenisDok => $tabel) {
        $baris = pilih($db, "SELECT id, nomor FROM {$tabel} ORDER BY id ASC");
        $perTahun = array();
        $n = 0;

        foreach ($baris as $b) {
            $tahun = preg_match('/-(\d{4})-\d{5}$/', $b['nomor'], $m) ? (int) $m[1] : (int) date('Y');
            if (! isset($perTahun[$tahun])) { $perTahun[$tahun] = 0; }
            $perTahun[$tahun]++;
            $n++;
            $baru = sprintf('%s-%d-%05d', $jenisDok, $tahun, $perTahun[$tahun]);
            if ($baru !== $b['nomor']) {
                jalankan($db, 'UPDATE ' . $tabel . ' SET nomor = ' . kutip($baru)
                    . ' WHERE id = ' . (int) $b['id']);
            }
        }

        foreach ($perTahun as $tahun => $terbesar) {
            $ada = hitung($db, 'SELECT COUNT(*) FROM document_counters
                                WHERE jenis = ? AND tahun = ?', array($jenisDok, (string) $tahun));
            if ($ada) {
                jalankan($db, 'UPDATE document_counters SET counter = ' . (int) $terbesar
                    . ' WHERE jenis = ' . kutip($jenisDok) . ' AND tahun = ' . (int) $tahun);
            } else {
                jalankan($db, 'INSERT INTO document_counters (jenis, tahun, counter) VALUES ('
                    . kutip($jenisDok) . ', ' . (int) $tahun . ', ' . (int) $terbesar . ')');
            }
            out("  {$jenisDok} {$tahun}: {$n} nomor, counter = {$terbesar}");
        }
    }
}

// ------------------------------------------------------------------ ringkasan
function baris($n) { return str_repeat('#', (int) $n); }

function tampilkanRingkasan($db)
{
    out('');
    out('RINGKASAN DATA');
    out('--------------');

    out('');
    out('Laporan per bulan:');
    foreach (pilih($db, "SELECT DATE_FORMAT(tanggal_lapor, '%Y-%m') AS bulan, COUNT(*) AS n
                         FROM incident_reports WHERE is_deleted = 0
                         GROUP BY bulan ORDER BY bulan") as $r) {
        out(sprintf('  %s  %2d  %s', $r['bulan'], $r['n'], baris($r['n'])));
    }

    out('');
    out('Laporan per status:');
    foreach (pilih($db, 'SELECT s.nama, COUNT(*) AS n FROM incident_reports ir
                         JOIN statuses s ON s.id = ir.status_id
                         WHERE ir.is_deleted = 0 GROUP BY s.id ORDER BY s.urutan') as $r) {
        out(sprintf('  %-18s %2d  %s', $r['nama'], $r['n'], baris($r['n'])));
    }

    out('');
    out('Laporan per band:');
    foreach (pilih($db, 'SELECT COALESCE(rb.kode, "(belum di-grade)") AS kode, COUNT(*) AS n
                         FROM incident_reports ir
                         LEFT JOIN risk_bands rb ON rb.id = ir.risk_band_id
                         WHERE ir.is_deleted = 0 GROUP BY rb.id ORDER BY n DESC') as $r) {
        out(sprintf('  %-18s %2d  %s', $r['kode'], $r['n'], baris($r['n'])));
    }

    out('');
    out('Laporan per ruangan:');
    foreach (pilih($db, 'SELECT ru.nama, COUNT(*) AS n FROM incident_reports ir
                         JOIN ruangan ru ON ru.id = ir.ruangan_id
                         WHERE ir.is_deleted = 0 GROUP BY ru.id ORDER BY n DESC') as $r) {
        out(sprintf('  %-26s %2d  %s', $r['nama'], $r['n'], baris($r['n'])));
    }

    out('');
    out('Laporan per jenis insiden:');
    foreach (pilih($db, 'SELECT it.kode, it.nama, COUNT(*) AS n FROM incident_reports ir
                         JOIN incident_types it ON it.id = ir.jenis_insiden_id
                         WHERE ir.is_deleted = 0 GROUP BY it.id ORDER BY n DESC') as $r) {
        out(sprintf('  %-5s %-32s %2d', $r['kode'], $r['nama'], $r['n']));
    }

    out('');
    out('Investigasi per status:');
    foreach (pilih($db, 'SELECT s.nama, COUNT(*) AS n FROM investigations i
                         JOIN statuses s ON s.id = i.status_id
                         WHERE i.is_deleted = 0 GROUP BY s.id ORDER BY s.urutan') as $r) {
        out(sprintf('  %-24s %2d  %s', $r['nama'], $r['n'], baris($r['n'])));
    }

    out('');
    out('Turunan:');
    out('  rekomendasi       : ' . hitung($db, 'SELECT COUNT(*) FROM investigation_recommendations'));
    out('  tindakan          : ' . hitung($db, 'SELECT COUNT(*) FROM investigation_actions'));
    out('  laporan terarsip  : ' . hitung($db, 'SELECT COUNT(*) FROM incident_reports WHERE is_deleted = 1'));
    out('  investigasi arsip : ' . hitung($db, 'SELECT COUNT(*) FROM investigations WHERE is_deleted = 1'));
    out('  pasien demo       : ' . hitung($db, "SELECT COUNT(*) FROM patients WHERE nomor_mr LIKE 'MR-DEMO-%'"));
    out('  data asli tersisa : ' . hitung($db, "SELECT COUNT(*) FROM patients WHERE nomor_mr NOT LIKE 'MR-DEMO-%'")
        . ' pasien, ' . hitung($db, 'SELECT COUNT(*) FROM patients p
            WHERE p.nomor_mr NOT LIKE \'MR-DEMO-%\'
              AND NOT EXISTS (SELECT 1 FROM incident_reports ir WHERE ir.patient_id = p.id)') . ' tanpa laporan');
}

// ================================================================== JALAN
if ($mode === 'laporan') {
    tampilkanRingkasan($db);
    exit(0);
}

if ($mode === 'reset') {
    resetDemo($db);
}

$adaDemo = hitung($db, "SELECT COUNT(*) FROM patients WHERE nomor_mr LIKE 'MR-DEMO-%'");
if ($mode === 'append' && $adaDemo > 0) {
    out('Data demo sudah ada (' . $adaDemo . ' pasien). Pakai --reset untuk membuat ulang.');
    out('');
    tampilkanRingkasan($db);
    exit(0);
}

out('=== SEED DATA DEMO ===');
out('Mode    : ' . $mode);
out('Rencana : ' . array_sum($PER_BULAN) . ' laporan di ' . count($PER_BULAN) . ' bulan (Feb - Ags 2026)');
out('');

$counterINS = hitung($db, "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(nomor, '-', -1) AS UNSIGNED)), 0)
                            FROM incident_reports
                            WHERE nomor REGEXP '^[A-Z]+-[0-9]{4}-[0-9]{5}$'");
$counterINV = hitung($db, "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(nomor, '-', -1) AS UNSIGNED)), 0)
                            FROM investigations
                            WHERE nomor REGEXP '^[A-Z]+-[0-9]{4}-[0-9]{5}$'");

$jumlahLap = 0;
$jumlahInv = 0;
$pasienCache = array();

foreach ($PER_BULAN as $bulan => $target) {
    $tahun = (int) substr($bulan, 0, 4);
    $bln   = (int) substr($bulan, 5, 2);
    $hariTertinggi = 0;

    for ($i = 0; $i < $target; $i++) {
        $ruangKode = peluang(60) ? 'IGD' : 'RAWATINAP';
        $ruang     = $ruangan[$ruangKode];
        $ruangId   = (int) $ruang['id'];
        $ruangNama = $ruang['nama'];

        $hari = acak(1, $hariTertinggi === 0 ? 3 : 28);
        $hariTertinggi = max($hariTertinggi, $hari);
        $tgl    = sprintf('%04d-%02d-%02d', $tahun, $bln, $hari);
        $jamIns = sprintf('%02d:%02d:00', acak(0, 23), acak(0, 59));
        $jamRep = sprintf('%02d:%02d:00', acak(0, 23), acak(0, 59));

        // Sebagian pasien punya lebih dari satu insiden, supaya data realistis.
        // Kuncinya per bulan per ruangan, BUKAN per tanggal: kalau memakai
        // tanggal, hampir tidak pernah kena sehingga tiap laporan selalu
        // membuat pasien baru.
        $kunciCache = $ruangKode;
        if (! empty($pasienCache[$kunciCache]) && peluang(30)) {
            $pasienId = pilihAcak($pasienCache[$kunciCache]);
        } else {
            $pasienId = buatPasien(
                $db,
                pilihAcak($NAMA),
                $ruangId,
                (int) pilihAcak($umur)['id'],
                peluang(55) ? 'P' : 'L',
                (int) pilihAcak($pj)['id'],
                $tgl,
                sprintf('%02d:%02d:00', acak(0, 22), acak(0, 59))
            );
            $pasienCache[$kunciCache][] = $pasienId;
        }

        $jenisKode = pilihBerbobot($PEMBAGIAN_JENIS);
        $j = null;
        foreach ($jenis as $x) { if ($x['kode'] === $jenisKode) { $j = $x; break; } }
        if (! $j) { $j = pilihAcak($jenis); }

        $a = pilihAcak($akibat);
        $k = pilihAcak($kategori);
        $s = pilihAcak($spes);
        $kron = kronologi($j['kode'], $k['nama'], $a['nama'], $ruangNama);

        // Pembuat laporan: biasanya pelapor, tapi sesekali kepala ruang yang
        // membuat laporannya sendiri. Data seperti ini dibutuhkan untuk
        // menguji alur "kepala ruang mengirim laporan miliknya sendiri".
        $kepala = $kepalaRuang[$ruangKode];
        $kepalaId = $kepala ? (int) $kepala['id'] : $adminId;
        $pelapor = ($kepala && peluang(15))
            ? $kepala
            : pilihAcak($pelaporRuang[$ruangKode]);

        // Status diacak supaya semua tahap terisi pada halaman rekap.
        $u = mt_rand(1, 100);
        if     ($u <= 8)  { $statusKode = 'draft'; }
        elseif ($u <= 20) { $statusKode = 'menunggu_grading'; }
        elseif ($u <= 38) { $statusKode = 'sudah_grading'; }
        elseif ($u <= 56) { $statusKode = 'investigasi'; }
        elseif ($u <= 78) { $statusKode = 'tindak_lanjut'; }
        else              { $statusKode = 'selesai'; }

        $counterINS++;
        $nomor = sprintf('INS-%d-%05d', $tahun, $counterINS);

        // Band hanya diisi kalau laporan sudah lewat tahap grading.
        $bandId    = null;
        $gradedBy  = null;
        $gradedAt  = null;
        if (! in_array($statusKode, array('draft', 'menunggu_grading'), TRUE)) {
            if ($PAKAI_KUNING_MERAH) {
                $x = mt_rand(1, 100);
                $akum = 0;
                $pilihBand = 'HIJAU';
                foreach (array(array('BIRU', 40), array('HIJAU', 35), array('KUNING', 18), array('MERAH', 7)) as $pil) {
                    $akum += $pil[1];
                    if ($x <= $akum) { $pilihBand = $pil[0]; break; }
                }
                $bandId = (int) $band[$pilihBand];
            } else {
                $bandId = peluang(45) ? $bandSimple['BIRU'] : $bandSimple['HIJAU'];
            }
            $gradedBy = $kepalaId;
            $gradedAt = $tgl . ' ' . $jamRep;
        }

        $laporanId = ins($db,
            'INSERT INTO incident_reports
                (nomor, tanggal_lapor, jam_lapor, nama_pembuat, paraf_pembuat,
                 patient_id, ruangan_id, tanggal_insiden, jam_insiden,
                 insiden, kronologi_insiden,
                 jenis_insiden_id, akibat_insiden_id, incident_category_id, specialization_id,
                 first_reporter_id, insiden_pada, care_type_id, tempat_insiden,
                 unit_terkait_id, tindakan_segera, hasil_tindakan, action_team_id,
                 pernah_terjadi_di_unit_lain, tindakan_pencegahan_sebelumnya,
                 risk_band_id, graded_by, graded_at, status_id,
                 is_deleted, created_by, created_at, updated_at, updated_by)
             VALUES (?,?,?,?,?, ?,?,?,?, ?,?, ?,?,?,?, ?,?,?,?, ?,?,?,?, ?,?, ?,?,?,?, 0,?,?,?,?)',
            array(
                $nomor, $tgl, $jamRep, $pelapor['nama'], $pelapor['nama'],
                $pasienId, $ruangId, $tgl, $jamIns,
                $kron, $kron,
                (int) $j['id'], (int) $a['id'], (int) $k['id'], (int) $s['id'],
                (int) pilihAcak($pelapor0)['id'], 'PASIEN', (int) pilihAcak($perawatan)['id'],
                pilihAcak($TEMPAT), $ruangId,
                pilihAcak($TINDAKAN), pilihAcak($HASIL_TINDAKAN), (int) pilihAcak($tim)['id'],
                peluang(6) ? 1 : 0,
                peluang(30) ? 'Sudah dilakukan briefing pada tim tentang pencegahan serupa.' : null,
                $bandId, $gradedBy, $gradedAt, (int) $S[$statusKode],
                (int) $pelapor['id'], $tgl . ' ' . $jamRep, $tgl . ' ' . $jamRep, (int) $pelapor['id'],
            )
        );
        $jumlahLap++;

        // Riwayat: create, submit, grading. Tanpa data pasien.
        ins($db, 'INSERT INTO incident_report_history
                    (incident_report_id, action, sebelum, sesudah, user_id, waktu, ip)
                  VALUES (?,?,?,?,?,?,?)',
            array($laporanId, 'create', NULL,
                json_encode(array('status' => 'Draft', 'nomor' => $nomor)),
                (int) $pelapor['id'], $tgl . ' ' . $jamRep, '127.0.0.1'));

        if ($statusKode !== 'draft') {
            ins($db, 'INSERT INTO incident_report_history
                        (incident_report_id, action, sebelum, sesudah, user_id, waktu, ip)
                      VALUES (?,?,?,?,?,?,?)',
                array($laporanId, 'submit',
                    json_encode(array('status' => 'Draft')),
                    json_encode(array('status' => 'Menunggu Grading')),
                    (int) $pelapor['id'], $tgl . ' ' . $jamRep, '127.0.0.1'));
        }
        if ($bandId !== null) {
            ins($db, 'INSERT INTO incident_report_history
                        (incident_report_id, action, sebelum, sesudah, user_id, waktu, ip)
                      VALUES (?,?,?,?,?,?,?)',
                array($laporanId, 'grading',
                    json_encode(array('status' => 'Menunggu Grading')),
                    json_encode(array('status' => 'Sudah Grading')),
                    $kepalaId, $tgl . ' ' . $jamRep, '127.0.0.1'));
        }

        // Sebagian laporan diarsipkan supaya halaman Arsip terisi.
        if ($jumlahLap % $ARSIP_SETIAP === 0) {
            jalankan($db, 'UPDATE incident_reports SET is_deleted = 1, deleted_at = '
                . kutip($tgl . ' ' . $jamRep) . ', deleted_by = ' . $adminId
                . ' WHERE id = ' . $laporanId);
        }

        // ---- Lembar investigasi, hanya untuk band BIRU / HIJAU
        $bolehInv = in_array($statusKode, array('investigasi', 'tindak_lanjut', 'selesai'), TRUE)
            && $bandId !== null
            && in_array($bandId, array((int) $bandSimple['BIRU'], (int) $bandSimple['HIJAU']), TRUE)
            && peluang((int) ($RASIO_INVESTIGASI * 100));

        if (! $bolehInv) { continue; }

        $counterINV++;
        $nomorInv = sprintf('INV-%d-%05d', $tahun, $counterINV);

        if ($statusKode === 'investigasi') {
            $u2 = mt_rand(1, 100);
            if     ($u2 <= 35) { $statusInv = 'inv_draft'; }
            elseif ($u2 <= 60) { $statusInv = 'dalam_investigasi'; }
            elseif ($u2 <= 80) { $statusInv = 'menunggu_tindak_lanjut'; }
            else               { $statusInv = 'dalam_pelaksanaan'; }
        } else {
            $statusInv = 'inv_selesai';
        }

        $investId = ins($db,
            'INSERT INTO investigations
                (nomor, incident_report_id, tanggal_investigasi, ruangan_id, jenis_insiden_id,
                 risk_band_id, status_id, penyebab_langsung, akar_masalah,
                 nama_penyetuju, tanggal_mulai_investigasi, tanggal_selesai_investigasi,
                 investigasi_lanjut, tanggal_keputusan_pmkp, catatan_pmkp,
                 is_deleted, created_by, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?,?)',
            array(
                $nomorInv, $laporanId, $tgl, $ruangId, (int) $j['id'],
                $bandId, (int) $SI[$statusInv],
                pilihAcak($SEBAB_LANGSUNG), pilihAcak($AKAR),
                pilihAcak($PENYETUJU), $tgl,
                $statusInv === 'inv_selesai' ? $tgl : null,
                NULL, NULL, NULL,
                $adminId, $tgl . ' ' . $jamRep,
            )
        );
        $jumlahInv++;

        for ($r = 0, $n = acak(1, 3); $r < $n; $r++) {
            ins($db, 'INSERT INTO investigation_recommendations
                        (investigation_id, rekomendasi, penanggung_jawab, tanggal_rekomendasi)
                      VALUES (?,?,?,?)',
                array($investId, pilihAcak($REKOMENDASI), pilihAcak($PENANGGUBAT),
                    date('Y-m-d', strtotime($tgl . ' +' . acak(3, 21) . ' days'))));
        }

        for ($r = 0, $n = acak(1, 3); $r < $n; $r++) {
            $stT = $statusInv === 'inv_selesai'
                ? $ST['tuntas']
                : $ST[pilihAcak(array('belum', 'proses'))];
            ins($db, 'INSERT INTO investigation_actions
                        (investigation_id, tindakan, penanggung_jawab, tanggal_tindakan, status_id)
                      VALUES (?,?,?,?,?)',
                array($investId, pilihAcak($TINDAKAN), pilihAcak($PENANGGUBAT),
                    date('Y-m-d', strtotime($tgl . ' +' . acak(1, 14) . ' days')), (int) $stT));
        }

        if ($statusInv === 'inv_selesai' && peluang(70)) {
            $lanjut = peluang(30) ? 1 : 0;
            jalankan($db, 'UPDATE investigations SET
                investigasi_lanjut = ' . $lanjut . ',
                tanggal_keputusan_pmkp = '
                    . kutip(date('Y-m-d', strtotime($tgl . ' +' . acak(5, 30) . ' days'))) . ',
                catatan_pmkp = ' . kutip(pilihAcak($CATATAN_PMKP)) . '
                WHERE id = ' . $investId);
        }
    }

    out(sprintf('  %s  %2d laporan', $bulan, $target));
}

out('');
out('Dibuat : ' . $jumlahLap . ' laporan, ' . $jumlahInv . ' lembar investigasi');
out('');
out('Menyelaraskan nomor dokumen ...');
rapikanNomor($db);
out('');
tampilkanRingkasan($db);
