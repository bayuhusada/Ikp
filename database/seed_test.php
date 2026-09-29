<?php
/**
 * Seed data uji untuk smoke test RBAC.
 *
 *   php database/seed_test.php
 *
 * Membuat 2 ruangan dan 1 akun untuk tiap role, semua dengan password
 * yang sama dan CETAK DI LAYAR. Password di-generate acak lalu disimpan
 * di file .smoke_test_password sementara yang dihapus setelah selesai.
 *
 * HANYA untuk development. Jangan pernah dijalankan di produksi.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Forbidden: hanya untuk CLI.');
}

$root = dirname(__DIR__);
$isProd = strpos(@file_get_contents($root . '/index.php'), "define('ENVIRONMENT', 'production')") !== false;
if ($isProd) {
    die('DITOLAK: ENVIRONMENT production. Skrip ini hanya untuk development.' . PHP_EOL);
}

// --- .env ---------------------------------------------------------
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
        if (getenv($k) === false) putenv($k . '=' . $v);
    }
}

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$dbName = getenv('DB_NAME') ?: 'ikp';

$mysqli = @new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($mysqli->connect_errno) {
    die('GAGAL koneksi: ' . $mysqli->connect_error . PHP_EOL);
}
$mysqli->set_charset('utf8mb4');

function acak_password($panjang = 12)
{
    $besar  = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $kecil  = 'abcdefghijkmnopqrstuvwxyz';
    $angka  = '23456789';
    $semua  = $besar . $kecil . $angka;
    $out = $besar[random_int(0, strlen($besar) - 1)]
         . $kecil[random_int(0, strlen($kecil) - 1)]
         . $angka[random_int(0, strlen($angka) - 1)];
    for ($i = 3; $i < $panjang; $i++) {
        $out .= $semua[random_int(0, strlen($semua) - 1)];
    }
    return $out;
}

$password = acak_password(12);
$hash = password_hash($password, PASSWORD_DEFAULT);
$now  = date('Y-m-d H:i:s');

echo "=== Seed Data Uji IKP (development) ===\n";
echo "Database: {$dbName}\n\n";

// --- Ruangan ------------------------------------------------------
$ruangan = array(
    array('IGD', 'Instalasi Gawat Darurat', 'unit'),
    array('RAWATINAP', 'Rawat Inap', 'unit'),
);
$ruanganId = array();
foreach ($ruangan as $r) {
    $stmt = $mysqli->prepare("SELECT id FROM ruangan WHERE kode = ? LIMIT 1");
    $stmt->bind_param('s', $r[0]);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $ruanganId[$r[0]] = (int) $row['id'];
        echo "ruangan sudah ada: {$r[0]}\n";
    } else {
        $stmt = $mysqli->prepare("INSERT INTO ruangan (kode, nama, jenis, is_active, created_at) VALUES (?, ?, ?, 1, ?)");
        $jenis = $r[2];
        $stmt->bind_param('ssss', $r[0], $r[1], $jenis, $now);
        $stmt->execute();
        $ruanganId[$r[0]] = $stmt->insert_id;
        $stmt->close();
        echo "ruangan dibuat : {$r[0]} (id {$ruanganId[$r[0]]})\n";
    }
}

// --- User uji -----------------------------------------------------
$users = array(
    array('kepala_igd',    'Kepala Ruangan IGD',       'kepala_ruangan', 'IGD'),
    array('pelapor_igd',   'Perawat IGD',              'pelapor',        'IGD'),
    array('kepala_rawat',  'Kepala Ruangan Rawat Inap','kepala_ruangan', 'RAWATINAP'),
    array('pelapor_rawat', 'Perawat Rawat Inap',       'pelapor',        'RAWATINAP'),
);

$hasil = array();
foreach ($users as $u) {
    list($username, $nama, $role, $kodeRuang) = $u;
    $rid = $ruanganId[$kodeRuang];

    $stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $ada = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($ada) {
        // Reset password agar password yang dicetak selalu berlaku
        $stmt = $mysqli->prepare("UPDATE users SET password = ?, nama = ?, role = ?, ruangan_id = ?, is_active = 1, is_deleted = 0 WHERE id = ?");
        $stmt->bind_param('sssii', $hash, $nama, $role, $rid, $ada['id']);
        $stmt->execute();
        $uid = (int) $ada['id'];
        $stmt->close();
        $aksi = 'diperbarui';
    } else {
        $stmt = $mysqli->prepare("INSERT INTO users (nama, username, password, role, ruangan_id, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?)");
        $stmt->bind_param('sssssss', $nama, $username, $hash, $role, $rid, $now, $now);
        $stmt->execute();
        $uid = $stmt->insert_id;
        $stmt->close();
        $aksi = 'dibuat';
    }

    $hasil[] = array('username' => $username, 'role' => $role, 'ruangan' => $kodeRuang, 'id' => $uid);
    echo "user {$aksi}: {$username} ({$role} / {$kodeRuang}) id={$uid}\n";
}

$mysqli->close();

echo "\n";
echo "--------------------------------------------------------------\n";
echo "  Password SEMUA akun uji di bawah ini sama:\n\n";
echo "  " . $password . "\n\n";
echo "  Password ini hanya ditampilkan di layar ini.\n";
echo "  Akun ini HAPUS setelah smoke test selesai:\n";
echo "  DELETE FROM users WHERE username IN ('kepala_igd','pelapor_igd','kepala_rawat','pelapor_rawat');\n";
echo "--------------------------------------------------------------\n";
echo "\n";
echo "Tabel:\n";
printf("  %-16s %-16s %-12s %s\n", 'USERNAME', 'ROLE', 'RUANGAN', 'ID');
foreach ($hasil as $h) {
    printf("  %-16s %-16s %-12s %d\n", $h['username'], $h['role'], $h['ruangan'], $h['id']);
}
echo "\nURL: http://localhost/ikp/login\n";
