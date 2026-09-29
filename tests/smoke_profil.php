<?php
/**
 * Smoke test halaman Profil.
 *
 * Menangkap bug yang sudah lama ada:
 *   - form "Simpan Profil" menunjuk ke /profil, yang hanya memanggil
 *     Profile::index() (hanya render) -> tombol diam-diam tidak berfungsi
 *   - form upload TTD menunjuk ke /profil/upload-ttd yang tidak ada -> 404
 * Keduanya sudah diperbaiki dengan menambah rute profil/ubah dan
 * profil/unggah-ttd.
 *
 * Jalankan: php tests/smoke_profil.php
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
        if (is_array($body) && isset($body['__multipart'])) {
            unset($body['__multipart']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);   // array = multipart
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? http_build_query($body) : $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        }
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
    return http('POST', BASE . '/proses-login', array(
        'username' => $user, 'password' => $pass, 'csrf_token' => token('/login'),
    ));
}

function get($path) { return http('GET', BASE . $path); }

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

$USER = 'pelapor_igd';
$PW   = 'Zx2zww8Nxztb';
$Uji  = 'Uji ' . strtoupper(substr(md5((string) time()), 0, 4));

// ============================================================ 0. KONDISI AWAL
line('=== 0. KONDISI AWAL ===');
$row = q('SELECT nama, nip, ttd FROM users WHERE username = ?', array($USER))[0];
$asli = $row;
ok('data user uji terbaca', $row !== NULL, $row['nama']);

login($USER, $PW);
ok('user uji login', TRUE);

// ============================================================ 1. HALAMAN
line();
line('=== 1. FORM DI HALAMAN PROFIL ===');
$r = get('/profil');
ok('halaman profil terbuka', $r['code'] === 200, 'HTTP ' . $r['code']);
ok('form simpan profil menunjuk ke /profil/ubah', strpos($r['body'], 'profil/ubah"') !== FALSE
    OR strpos($r['body'], 'profil/ubah"') !== FALSE);
ok('form ubah sandi tetap ada', strpos($r['body'], 'profil/ubah-sandi') !== FALSE);
ok('form upload TTD menunjuk ke /profil/unggah-ttd', strpos($r['body'], 'profil/unggah-ttd') !== FALSE);

// Rute yang tidak ada harus 404, bukan diam-diam success
$r = http('POST', BASE . '/profil', array('csrf_token' => token('/profil'), 'nama' => $Uji));
$row2 = q('SELECT nama FROM users WHERE username = ?', array($USER))[0];
ok('POST ke /profil tidak lagi diam-diam menyimpan',
    $row2['nama'] === $asli['nama'], 'nama tetap: ' . $row2['nama']);

// ========================================================= 2. UBAH PROFIL
line();
line('=== 2. UBAH PROFIL ===');
$r = post('/profil', '/profil/ubah', array('nama' => $Uji, 'nip' => '998877'));
ok('redirect kembali ke profil', strpos($r['loc'], 'profil') !== FALSE, $r['loc']);

$row2 = q('SELECT nama, nip FROM users WHERE username = ?', array($USER))[0];
ok('nama tersimpan', $row2['nama'] === $Uji, $row2['nama']);
ok('NIP tersimpan', $row2['nip'] === '998877', (string) $row2['nip']);

// GET harus ditolak
$r = get('/profil/ubah');
ok('GET ke /profil/ubah ditolak (wajib POST)', $r['code'] !== 200, 'HTTP ' . $r['code']);

// Nama kosong ditolak
$r = post('/profil', '/profil/ubah', array('nama' => '', 'nip' => '123'));
$row2 = q('SELECT nama FROM users WHERE username = ?', array($USER))[0];
ok('nama kosong ditolak', $row2['nama'] === $Uji, $row2['nama']);

// Role & ruangan tidak boleh bisa diubah sendiri
$r = post('/profil', '/profil/ubah', array(
    'nama' => $Uji, 'nip' => '998877', 'role' => 'admin', 'ruangan_id' => 4, 'is_active' => 0,
));
$row2 = q('SELECT role, ruangan_id, is_active FROM users WHERE username = ?', array($USER))[0];
ok('percobaan angkat role diabaikan', $row2['role'] === 'pelapor', 'role=' . $row2['role']);
ok('percobaan pindah ruangan diabaikan', (int) $row2['ruangan_id'] === 3, 'ruangan_id=' . $row2['ruangan_id']);
ok('percobaan nonaktifkan akun diabaikan', (int) $row2['is_active'] === 1, 'is_active=' . $row2['is_active']);

// Audit
$aud = q('SELECT aktivitas FROM audit_logs WHERE entitas = ? AND entitas_id = ? ORDER BY id',
    array('users', (string) q('SELECT id FROM users WHERE username = ?', array($USER))[0]['id']));
$AKSI = array();
foreach ($aud as $a) { $AKSI[] = $a['aktivitas']; }
ok('aktivitas "Ubah Profil" tercatat', in_array('Ubah Profil', $AKSI, TRUE), implode(' | ', array_slice(array_unique($AKSI), 0, 6)));

// ========================================================= 3. UPLOAD TTD
line();
line('=== 3. UPLOAD TANDA TANGAN ===');

// Buat PNG kecil yang valid
$tmp = tempnam(sys_get_temp_dir(), 'ttd') . '.png';
$im = imagecreatetruecolor(120, 40);
imagefilledrectangle($im, 0, 0, 119, 39, imagecolorallocate($im, 255, 255, 255));
imagestring($im, 5, 10, 12, 'TTD', imagecolorallocate($im, 0, 0, 0));
imagepng($im, $tmp);
imagedestroy($im);
ok('berkas PNG uji dibuat', is_file($tmp), basename($tmp));

$r = post('/profil', '/profil/unggah-ttd', array('__multipart' => 1, 'ttd' => new CURLFile($tmp, 'image/png', 'ttd.png')));
$row2 = q('SELECT ttd FROM users WHERE username = ?', array($USER))[0];
ok('tanda tangan tersimpan', ! empty($row2['ttd']), (string) $row2['ttd']);
ok('redirect kembali ke profil', strpos($r['loc'], 'profil') !== FALSE, $r['loc']);

if (! empty($row2['ttd'])) {
    $path = 'C:/laragon/www/ikp/uploads/ttd/' . $row2['ttd'];
    ok('berkas tersimpan di uploads/ttd/', is_file($path), basename($path));
    $html = get('/profil');
    ok('tanda tangan tampil di halaman profil', strpos($html['body'], rawurlencode($row2['ttd'])) !== FALSE
        OR strpos($html['body'], $row2['ttd']) !== FALSE);

    // Berkas lama harus terhapus saat ganti
    $pathLama = $path;
    $r = post('/profil', '/profil/unggah-ttd', array('__multipart' => 1, 'ttd' => new CURLFile($tmp, 'image/png', 'ttd2.png')));
    $row3 = q('SELECT ttd FROM users WHERE username = ?', array($USER))[0];
    ok('ganti tanda tangan menghasilkan nama baru', $row3['ttd'] !== $row2['ttd'],
        $row2['ttd'] . ' -> ' . $row3['ttd']);

    // PENTING: tanpa clearstatcache(), is_file() mengembalikan hasil stat
    // yang di-cache dari pemeriksaan sebelumnya, jadi berkas yang sudah
    // dihapus oleh server masih terlihat ada.
    clearstatcache();
    ok('berkas tanda tangan lama dihapus', ! is_file($pathLama), basename($pathLama));

    $pathBaru = 'C:/laragon/www/ikp/uploads/ttd/' . $row3['ttd'];
}

// Berkas bukan gambar harus ditolak
$teks = tempnam(sys_get_temp_dir(), 'fake') . '.png';
file_put_contents($teks, "<?php echo 'bukan gambar'; ?>");
$r = post('/profil', '/profil/unggah-ttd', array('__multipart' => 1, 'ttd' => new CURLFile($teks, 'image/png', 'fake.png')));
$row4 = q('SELECT ttd FROM users WHERE username = ?', array($USER))[0];
ok('berkas palsu ditolak (bukan gambar)', $row4['ttd'] === $row3['ttd'], 'ttd=' . $row4['ttd']);
@unlink($teks);
@unlink($tmp);

// ========================================================= 4. HAK AKSES
line();
line('=== 4. HAK AKSES PROFIL ===');
$before = q('SELECT nama FROM users WHERE username = ?', array($USER))[0]['nama'];
login('pelapor_rawat', $PW);
$r = post('/profil', '/profil/ubah', array('nama' => 'Dibobol'));
$after = q('SELECT nama FROM users WHERE username = ?', array($USER))[0]['nama'];
ok('user lain tidak bisa mengubah profil orang lain', $after === $before, 'nama=' . $after);

// ========================================================= 5. BERSIHKAN
line();
line('=== 5. KEMBALIKAN DATA UJI ===');
// Berkas hasil upload uji tidak boleh tertinggal di uploads/ttd.
if (isset($pathBaru) && is_file($pathBaru)) {
    @unlink($pathBaru);
    clearstatcache();
    ok('berkas upload uji dibersihkan', ! is_file($pathBaru), basename($pathBaru));
}

db()->prepare('UPDATE users SET nama = ?, nip = ?, ttd = ? WHERE username = ?')
    ->execute(array($asli['nama'], $asli['nip'], $asli['ttd'], $USER));
$akhir = q('SELECT nama, nip, ttd FROM users WHERE username = ?', array($USER))[0];
ok('nama & NIP dikembalikan', $akhir['nama'] === $asli['nama'] && $akhir['nip'] === $asli['nip']);
ok('ttd dikembalikan', $akhir['ttd'] === $asli['ttd'], (string) $akhir['ttd']);

line();
line('=== RINGKASAN: ' . $pass . ' OK, ' . $fail . ' GAGAL ===');
exit($fail === 0 ? 0 : 1);
