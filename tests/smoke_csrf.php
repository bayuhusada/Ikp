<?php
/**
 * Pastikan SETIAP form POST di halaman punya csrf_token.
 *
 * Ini yang smoke test lama miss: helper test menyuntik token sendiri,
 * jadi form yang salah masih lulus. Yang diuji di sini HTML hasil render.
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
    $hsize  = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return array('code' => $status, 'body' => substr($raw, $hsize));
}

function token($p) { $r = http('GET', BASE . $p);
    return preg_match('/name="csrf_token"\s+value="([^"]+)"/', $r['body'], $m) ? $m[1] : NULL; }

http('POST', BASE . '/proses-login', array(
    'username' => 'admin', 'password' => 'aeM*2uDZ2z%#VEki', 'csrf_token' => token('/login'),
));

$halaman = array(
    '/master/jenis-insiden', '/master/jenis-insiden/form',
    '/master/kategori', '/master/kategori/form',
    '/master/spesialisasi', '/master/spesialisasi/form',
    '/master/akibat', '/master/akibat/form',
    '/master/status', '/master/status/form',
    '/master/user', '/master/ruangan', '/master/band-risiko',
    '/laporan/input', '/profil', '/investigasi', '/investigasi/detail/1',
    '/laporan/detail/4', '/laporan', '/laporan/arsip', '/investigasi/arsip',
    '/dashboard', '/audit-log',
);

$gagal = 0;
$total = 0;

foreach ($halaman as $p) {
    $r = http('GET', BASE . $p);
    if ($r['code'] !== 200) {
        printf("  [lewati] %-32s HTTP %d\n", $p, $r['code']);
        continue;
    }

    // Pecah HTML per <form ...>...</form>
    preg_match_all('#<form\b[^>]*>(.*?)</form>#is', $r['body'], $m, PREG_SET_ORDER);
    $jumlah = count($m);
    $tanpa  = 0;
    foreach ($m as $f) {
        $isPost = stripos($f[0], 'method="post"') !== FALSE
               || stripos($f[0], "method='post'") !== FALSE;
        if (! $isPost) { continue; }
        if (stripos($f[0], 'name="csrf_token"') === FALSE) { $tanpa++; }
    }

    $total += $jumlah;
    if ($tanpa > 0) { $gagal++; }
    printf("  [%s] %-32s form=%2d  tanpa token=%d\n",
        $tanpa > 0 ? 'GAGAL' : 'OK  ', $p, $jumlah, $tanpa);
}

printf("\n=== RINGKASAN: %d form diperiksa, %d halaman bermasalah ===\n", $total, $gagal);
exit($gagal === 0 ? 0 : 1);
