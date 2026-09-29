<?php
/**
 * Smoke test fitur ganti password.
 *
 * Menutup dua jalur:
 *   1. mandiri  — Profile::change_password()  (menu "Ubah Password")
 *   2. admin    — Admin\User::reset_password() (reset password user lain)
 *
 * Jalankan: php tests/smoke_password.php
 * Mengubah password user uji, lalu MENGEMBALIKANNYA ke nilai awal di akhir.
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
    $r = http('POST', BASE . '/proses-login', array(
        'username'   => $user,
        'password'   => $pass,
        'csrf_token' => $t,
    ));
    return $r;
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
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
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

function hash_of($username)
{
    $r = q('SELECT password FROM users WHERE username = ?', array($username));
    return $r ? $r[0]['password'] : NULL;
}

function set_hash($username, $plain)
{
    db()->prepare('UPDATE users SET password = ? WHERE username = ?')
        ->execute(array(password_hash($plain, PASSWORD_DEFAULT), $username));
}

$pass = 0;
$fail = 0;
function ok($label, $cond, $extra = '')
{
    $mark = $cond ? 'OK  ' : 'GAGAL';
    printf("  [%s] %s%s\n", $mark, $label, $extra !== '' ? '  -> ' . $extra : '');
    global $pass, $fail;
    if ($cond) { $pass++; } else { $fail++; }
    return (bool) $cond;
}
function line($t = '') { echo $t . "\n"; }

$ADMIN_PW  = 'aeM*2uDZ2z%#VEki';
$USER      = 'pelapor_igd';
$AWAL      = 'Zx2zww8Nxztb';
$BARU      = 'Baru12345';

// Jaga agar test bisa diulang: kembalikan password ke nilai awal lebih dulu.
set_hash($USER, $AWAL);

line('=== 0. KONDISI AWAL ===');
$r = login($USER, $AWAL);
ok('user uji login dengan password awal', $r['loc'] !== '' && strpos($r['loc'], 'dashboard') !== FALSE, $r['loc']);

// ------------------------------------------------------------------ 1. FORM
line();
line('=== 1. HALAMAN & AKSES ===');
$r = get('/profil');
ok('halaman profil terbuka', $r['code'] === 200, 'HTTP ' . $r['code']);
ok('form ubah password ada di halaman profil', strpos($r['body'], 'name="password_baru"') !== FALSE);
ok('route ubah-sandi terdaftar', strpos($r['body'], 'profil/ubah-sandi') !== FALSE);

$r = get('/profil/ubah-sandi');
ok('GET ke ubah-sandi ditolak (wajib POST)', $r['code'] !== 200, 'HTTP ' . $r['code']);

// ------------------------------------------------------- 2. VALIDASI DITOLAK
line();
line('=== 2. PERMINTAAN HARUS DITOLAK ===');
$req = '/profil';

// 2a. password lama salah
$r = post($req, '/profil/ubah-sandi', array(
    'password_lama' => 'SalahSekali99', 'password_baru' => $BARU, 'password_konfirmasi' => $BARU,
));
ok('password lama salah ditolak', $r['loc'] !== '' && strpos($r['loc'], 'auth/login') === FALSE, $r['loc']);
ok('hash tidak berubah (password lama salah)', password_verify($AWAL, hash_of($USER)));

// 2b. password baru lemah - tanpa huruf besar
$r = post($req, '/profil/ubah-sandi', array(
    'password_lama' => $AWAL, 'password_baru' => 'lemah12345', 'password_konfirmasi' => 'lemah12345',
));
ok('password tanpa huruf besar ditolak', password_verify($AWAL, hash_of($USER)), 'hash masih = password awal');

// Pesan error harus SPESIFIK, bukan kalimat umum. Kalau kunci set_message
// salah (mis. lupa bahwa CI3 men-strip prefix callback_), CI3 jatuh ke
// language line yang tidak ada dan pesan generik ini yang muncul.
$r = get('/profil');
ok('pesan error menyebut aturan yang violated',
    strpos($r['body'], 'minimal satu huruf besar') !== FALSE,
    strpos($r['body'], 'minimal satu huruf besar') !== FALSE
        ? 'pesan benar'
        : 'pesan tidak ditemukan; yang tampil: ' . (preg_match('/text: "([^"]*)"/', $r['body'], $mm) ? $mm[1] : '?'));

// 2c. terlalu pendek
$r = post($req, '/profil/ubah-sandi', array(
    'password_lama' => $AWAL, 'password_baru' => 'Ab1', 'password_konfirmasi' => 'Ab1',
));
ok('password terlalu pendek ditolak', password_verify($AWAL, hash_of($USER)));

// 2d. konfirmasi tidak cocok
$r = post($req, '/profil/ubah-sandi', array(
    'password_lama' => $AWAL, 'password_baru' => $BARU, 'password_konfirmasi' => 'Beda12345',
));
ok('konfirmasi tidak cocok ditolak', password_verify($AWAL, hash_of($USER)));

// 2e. sama dengan password lama
$r = post($req, '/profil/ubah-sandi', array(
    'password_lama' => $AWAL, 'password_baru' => $AWAL, 'password_konfirmasi' => $AWAL,
));
ok('password baru tidak boleh sama dengan lama', password_verify($AWAL, hash_of($USER)));

// 2f. kosong
$r = post($req, '/profil/ubah-sandi', array(
    'password_lama' => '', 'password_baru' => '', 'password_konfirmasi' => '',
));
ok('form kosong ditolak', password_verify($AWAL, hash_of($USER)));

// ------------------------------------------------------------- 3. BERHASIL
line();
line('=== 3. GANTI PASSWORD BERHASIL ===');
$sebelum = hash_of($USER);

$r = post($req, '/profil/ubah-sandi', array(
    'password_lama' => $AWAL, 'password_baru' => $BARU, 'password_konfirmasi' => $BARU,
));
ok('redirect ke halaman login', strpos($r['loc'], 'auth/login') !== FALSE, $r['loc']);
ok('hash berubah', hash_of($USER) !== $sebelum);
ok('password baru tersimpan', password_verify($BARU, hash_of($USER)));
ok('password lama tidak berlaku lagi', ! password_verify($AWAL, hash_of($USER)));
ok('hash baru bukan teks polos', strpos(hash_of($USER), '$2y$') === 0, substr(hash_of($USER), 0, 7));

// 3g. flashdata hanya hidup SATU request berikutnya. Jadi halaman tujuan
//     redirect harus dicek DULU, sebelum request lain menghabiskan flash-nya.
$r = get('/auth/login');
ok('halaman login terbuka setelah ganti password', $r['code'] === 200, 'HTTP ' . $r['code']);
ok('pesan "login kembali" tampil setelah ganti', strpos($r['body'], 'Password berhasil diubah') !== FALSE);

// 3h. sesi harus ikut mati
$r = get('/dashboard');
ok('sesi dihancurkan setelah ganti password', $r['code'] !== 200, 'HTTP ' . $r['code']);

// 3i. password lama ditolak di login
$r = login($USER, $AWAL);
ok('login dengan password lama ditolak', $r['loc'] !== '' && strpos($r['loc'], 'dashboard') === FALSE, $r['loc']);

// 3j. password baru diterima di login
$r = login($USER, $BARU);
ok('login dengan password baru berhasil', strpos($r['loc'], 'dashboard') !== FALSE, $r['loc']);

// ------------------------------------------------------------- 4. AUDIT LOG
line();
line('=== 4. AUDIT LOG ===');
$uid = (int) q('SELECT id FROM users WHERE username = ?', array($USER))[0]['id'];
$aud = q('SELECT aktivitas, sebelum, sesudah FROM audit_logs WHERE entitas = ? AND entitas_id = ? ORDER BY id',
    array('users', (string) $uid));
$AKSI = array();
foreach ($aud as $a) { $AKSI[] = $a['aktivitas']; }
ok('aktivitas "Ubah Password" tercatat', in_array('Ubah Password', $AKSI, TRUE), implode(' | ', $AKSI));
ok('percobaan gagal tercatat', in_array('Gagal Ubah Password (Password lama salah)', $AKSI, TRUE));

$raw = json_encode($aud);
ok('password lama tidak bocor ke audit log', strpos($raw, $AWAL) === FALSE);
ok('password baru tidak bocor ke audit log', strpos($raw, $BARU) === FALSE);
ok('nilai yang disimpan disamarkan', strpos($raw, '(disembunyikan)') !== FALSE);

// ------------------------------------------------- 5. RESET OLEH ADMIN
line();
line('=== 5. RESET PASSWORD OLEH ADMIN ===');

// Non-admin tidak boleh
login('kepala_igd', 'Zx2zww8Nxztb');
$r = get('/master/user');
ok('kepala_ruangan tidak boleh buka master/user', $r['code'] === 403, 'HTTP ' . $r['code']);

login('admin', $ADMIN_PW);
$r = get('/master/user');
ok('admin boleh buka master/user', $r['code'] === 200, 'HTTP ' . $r['code']);

$r = post('/master/user', '/master/user/reset-sandi/' . $uid, array(
    'password_baru' => 'abc', 'password_konfirmasi' => 'abc',
));
ok('reset dengan password lemah ditolak', password_verify($BARU, hash_of($USER)));

$r = post('/master/user', '/master/user/reset-sandi/' . $uid, array(
    'password_baru' => $AWAL, 'password_konfirmasi' => $AWAL,
));
ok('reset password oleh admin berhasil', password_verify($AWAL, hash_of($USER)), 'dikembalikan ke password awal');
$r = login($USER, $AWAL);
ok('user bisa login dengan password hasil reset', strpos($r['loc'], 'dashboard') !== FALSE, $r['loc']);

$aud = q('SELECT aktivitas FROM audit_logs WHERE entitas = ? AND entitas_id = ?',
    array('users', (string) $uid));
$AKSI = array();
foreach ($aud as $a) { $AKSI[] = $a['aktivitas']; }
ok('aktivitas "Reset Password User" tercatat', in_array('Reset Password User', $AKSI, TRUE));

// GET harus ditolak
$r = get('/master/user/reset-sandi/' . $uid);
ok('GET ke reset-sandi ditolak (wajib POST)', $r['code'] !== 200, 'HTTP ' . $r['code']);

// --------------------------------------------- 6. ATURAN DI FORM MASTER USER
line();
line('=== 6. FORM MASTER USER ===');
login('admin', $ADMIN_PW);
$r = get('/master/user/form');
ok('form tambah user terbuka', $r['code'] === 200, 'HTTP ' . $r['code']);
ok('field password ada di form user', strpos($r['body'], 'name="password"') !== FALSE);

$r = post('/master/user/form', '/master/user/save', array(
    'username' => 'uji_password', 'nama' => 'Uji Password', 'role' => 'pelapor',
    'ruangan_id' => 3, 'password' => 'abc', 'password_konfirmasi' => 'abc',
));
$ada = q('SELECT id FROM users WHERE username = ?', array('uji_password'));
ok('user dengan password lemah TIDAK dibuat', count($ada) === 0, count($ada) . ' row');

$r = post('/master/user/form', '/master/user/save', array(
    'username' => 'uji_password', 'nama' => 'Uji Password', 'role' => 'pelapor',
    'ruangan_id' => 3, 'password' => 'Sah12345', 'password_konfirmasi' => 'Sah12345',
));
$ada = q('SELECT id FROM users WHERE username = ?', array('uji_password'));
ok('user dengan password sah berhasil dibuat', count($ada) === 1, count($ada) . ' row');
if ($ada) {
    ok('password user baru bisa diverifikasi', password_verify('Sah12345', hash_of('uji_password')));
    db()->prepare('DELETE FROM users WHERE username = ?')->execute(array('uji_password'));
    line('  (user uji dihapus kembali)');
}

// ------------------------------------------------------------- 7. BERSIH
line();
line('=== 7. KEADAAN AKHIR ===');
set_hash($USER, $AWAL);
$r = login($USER, $AWAL);
ok('password user uji dikembalikan ke nilai awal', strpos($r['loc'], 'dashboard') !== FALSE, $r['loc']);

line();
line('=== RINGKASAN: ' . $pass . ' OK, ' . $fail . ' GAGAL ===');
exit($fail === 0 ? 0 : 1);
