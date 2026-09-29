<?php
/**
 * Seed satu akun admin awal dengan password acak.
 *
 * Jalankan dari CLI saja:
 *   php database/seed_admin.php [username] [nama]
 *
 * Password di-generate acak dan DICETAK SEKALI di terminal.
 * Password TIDAK pernah disimpan di file mana pun, dan tidak masuk log.
 * Segera ganti setelah login pertama.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Forbidden: skrip ini hanya boleh dijalankan via CLI.');
}

$root = dirname(__DIR__);

// --- Konfigurasi koneksi (baca .env bila ada) -----------------------
$envFile = $root . DIRECTORY_SEPARATOR . '.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) continue;
        $k = trim($parts[0]);
        $v = trim($parts[1]);
        if (strlen($v) > 1 && (($v[0] === '"' && substr($v, -1) === '"') || ($v[0] === "'" && substr($v, -1) === "'"))) {
            $v = substr($v, 1, -1);
        }
        if (getenv($k) === false) {
            putenv($k . '=' . $v);
        }
    }
}

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$dbName = getenv('DB_NAME') ?: 'ikp';

$username = isset($argv[1]) && $argv[1] !== '' ? $argv[1] : 'admin';
$nama    = isset($argv[2]) && $argv[2] !== '' ? $argv[2] : 'Administrator';

// --- Buat password acak yang memenuhi aturan ------------------------
// min 8, ada huruf besar, huruf kecil, angka
function buat_password($panjang = 16)
{
    $besar   = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $kecil   = 'abcdefghijkmnopqrstuvwxyz';
    $angka   = '23456789';
    $simbol  = '!@#$%&*';
    $semua   = $besar . $kecil . $angka . $simbol;

    $karakter = array(
        $besar[random_int(0, strlen($besar) - 1)],
        $kecil[random_int(0, strlen($kecil) - 1)],
        $angka[random_int(0, strlen($angka) - 1)],
        $simbol[random_int(0, strlen($simbol) - 1)],
    );

    for ($i = count($karakter); $i < $panjang; $i++) {
        $karakter[] = $semua[random_int(0, strlen($semua) - 1)];
    }

    // Acak urutannya supaya posisi karakter wajib tidak selalu di depan
    for ($i = count($karakter) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        $tmp = $karakter[$i];
        $karakter[$i] = $karakter[$j];
        $karakter[$j] = $tmp;
    }

    return implode('', $karakter);
}

$password = buat_password(16);

echo "=== Seed Admin IKP ===\n";
echo "Host     : {$dbHost}\n";
echo "Database : {$dbName}\n";
echo "Username : {$username}\n";

$mysqli = @new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($mysqli->connect_errno) {
    echo "\nGAGAL koneksi: " . $mysqli->connect_error . "\n";
    echo "Pastikan MySQL Laragon berjalan dan .env benar.\n";
    exit(1);
}

$mysqli->set_charset('utf8mb4');

// Jangan buat dua kali
$stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param('s', $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo "\nUsername '{$username}' sudah ada. Dibatalkan.\n";
    $stmt->close();
    $mysqli->close();
    exit(1);
}
$stmt->close();

$hash = password_hash($password, PASSWORD_DEFAULT);
$now  = date('Y-m-d H:i:s');

$stmt = $mysqli->prepare(
    "INSERT INTO users (nama, username, password, role, is_active, created_at, updated_at)
     VALUES (?, ?, ?, 'admin', 1, ?, ?)"
);
$stmt->bind_param('sssss', $nama, $username, $hash, $now, $now);

if (! $stmt->execute()) {
    echo "\nGAGAL insert: " . $stmt->error . "\n";
    $stmt->close();
    $mysqli->close();
    exit(1);
}

$userId = $stmt->insert_id;
$stmt->close();

// Catat di audit log
$ip  = 'cli';
$log = $mysqli->prepare(
    "INSERT INTO audit_logs (user_id, username, aktivitas, entitas, entitas_id, ip, waktu)
     VALUES (?, ?, 'Seed Admin', 'users', ?, ?, ?)"
);
$idStr = (string) $userId;
$log->bind_param('issss', $userId, $username, $idStr, $ip, $now);
$log->execute();
$log->close();

$mysqli->close();

echo "\n";
echo "--------------------------------------------------------------\n";
echo "  Akun admin berhasil dibuat.\n";
echo "\n";
echo "  Username : " . $username . "\n";
echo "  Password : " . $password . "\n";
echo "\n";
echo "  SIMPAN SEKARANG. Password ini hanya ditampilkan sekali\n";
echo "  dan tidak disimpan di berkas mana pun.\n";
echo "--------------------------------------------------------------\n";
echo "\n";
echo "URL login: http://localhost/ikp/login\n";
echo "Ganti password setelah login pertama lewat menu Profil.\n";
