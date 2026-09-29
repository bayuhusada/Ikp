<?php
/**
 * Audit target form, tautan sidebar, dan rute terhadap kode yang ada.
 *
 * Menangkap kelas bug "tombol kelihatan normal tapi tidak terjadi apa-apa":
 *   - form menunjuk ke URL yang tidak ada route-nya
 *   - route yang menunjuk ke method yang tidak pernah ditulis
 *   - <form> mentah tanpa form_open() -> tanpa csrf_token -> CI3 balas 403
 *
 * Sudah menemukan 3 bug nyata di project ini.
 *
 * Jalankan: php tests/audit_rute.php
 */

define('IKP', 'C:/laragon/www/ikp');
$APP = IKP . '/application';

// ------------------------------------------------- 1. KUMPULKAN RUTE
$src = file_get_contents($APP . '/config/routes.php');

// Buang komentar baris. Tanpa ini, rute yang sengaja dinonaktifkan (TODO)
// ikut terbaca seolah-olah aktif.
$src = preg_replace('#//[^\n]*#', '', $src);
// Buang blok komentar /* ... */
$src = preg_replace('#/\*.*?\*/#s', '', $src);

preg_match_all('/\$route\[\s*[\'"]([^\'"]+)[\'"]\s*\]\s*=\s*[\'"]([^\'"]+)[\'"]/', $src, $m);

$rute = array();
foreach ($m[1] as $i => $key) {
    $rute[$key] = $m[2][$i];
}
echo "Rute AKTIF terdaftar: " . count($rute) . " (komentar sudah diabaikan)\n";

// --------------------------------------------- 2. PETA CONTROLLER
/** file => array('path' => ..., 'cls' => 'User') */
$ctrl = array();
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($APP . '/controllers')) as $f) {
    if ($f->isDir() OR $f->getExtension() !== 'php') { continue; }
    $path     = str_replace('\\', '/', $f->getPathname());
    $cls      = $f->getBasename('.php');
    $sub      = strpos($path, '/controllers/Admin/') !== FALSE ? 'admin' : '';
    $ctrl[strtolower($cls)] = array(
        'path'   => $f->getPathname(),
        'sub'    => $sub,
        'source' => file_get_contents($f->getPathname()),
    );
}

function methodAda($cls, $metode)
{
    global $ctrl;
    global $APP;
    $cls = strtolower($cls);
    if (! isset($ctrl[$cls])) { return FALSE; }
    return (bool) preg_match('/function\s+' . preg_quote($metode, '/') . '\s*\(/i', sumberLengkap($cls));
}

/**
 * Sumber kelas beserta seluruh kelas induknya.
 * PENTING: banyak controller hanya mengisi $spec, sedangkan index/form/save/
 * delete/toggle diwarisi dari MY_Master_controller. Kalau hanya membaca
 * berkas child, semua method itu akan dianggap tidak ada.
 */
function sumberLengkap($cls, $tingkat = 0)
{
    global $ctrl, $APP;
    $cls = strtolower($cls);
    if (! isset($ctrl[$cls]) OR $tingkat > 5) { return ''; }

    $sumber = $ctrl[$cls]['source'];

    if (preg_match('/^\s*(?:abstract\s+)?class\s+\w+\s+extends\s+(\w+)/mi', $sumber, $m)) {
        $induk = strtolower($m[1]);
        if (isset($ctrl[$induk])) {
            return $sumber . "\n" . sumberLengkap($induk, $tingkat + 1);
        }
        // induk ada di core/
        foreach (array($APP . '/core', $APP . '/libraries') as $dir) {
            $p = $dir . '/' . $m[1] . '.php';
            if (is_file($p)) { return $sumber . "\n" . file_get_contents($p); }
        }
    }
    return $sumber;
}

/** Ubah kunci rute jadi regex yang bisa dicocokkan. */
function ruteKeRegex($key)
{
    $p = preg_quote($key, '#');
    $p = str_replace(['\(\:num\)', '\(\:any\)', '\(\:segment\)', '\(\:alpha\)'],
                     ['[0-9]+', '.+', '[^/]+', '[a-zA-Z]+'], $p);
    // anchor di ujung $route[...] berarti hanya cocok persis
    if (substr($key, -1) === '$') {
        return '#^' . substr($p, 0, -2) . '$#';
    }
    return '#^' . $p . '#';
}

/** @return string 'rute' | 'default' | 'tidak' */
function bisaDicapai($target)
{
    global $rute, $ctrl;

    $t = trim((string) $target);
    if ($t === '' OR stripos($t, 'base_url') !== FALSE) { return 'default'; }
    $t = ltrim($t, '/');
    // buang query string
    $t = explode('?', $t)[0];
    if ($t === '') { return 'default'; }

    foreach (array_keys($rute) as $key) {
        if (preg_match(ruteKeRegex($key), $t)) { return 'rute'; }
    }

    // Default routing CI3
    $bag = explode('/', $t);
    if (count($bag) === 1) {
        return methodAda($bag[0], 'index') ? 'default' : 'tidak';
    }
    if (count($bag) === 2) {
        // Maybe admin/user -> file Admin/User.php class User
        if (methodAda($bag[1], 'index')) { return 'default'; }
        return methodAda($bag[0], $bag[1]) ? 'default' : 'tidak';
    }
    if (count($bag) === 3) {
        return methodAda($bag[1], $bag[2]) ? 'default' : 'tidak';
    }
    return 'tidak';
}

$views = function () {
    global $APP;
    return new RecursiveIteratorIterator(new RecursiveDirectoryIterator($APP . '/views'));
};

// ------------------------------------------------- 3. TARGET FORM
echo "\n=== 1. TARGET form_open() DI SEMUA VIEW ===\n";
$statis = 0; $dinamis = 0; $gagal = 0;
foreach ($views() as $f) {
    if ($f->isDir() OR $f->getExtension() !== 'php') { continue; }
    $src = file_get_contents($f->getPathname());
    $rel = str_replace('C:/laragon/www/ikp/application/views/', '',
        str_replace('\\', '/', $f->getPathname()));

    preg_match_all("/form_open(?:_multipart)?\(\s*(.+?)\s*,\s*array\(/s", $src, $mm, PREG_SET_ORDER);
    foreach ($mm as $hit) {
        $expr = trim($hit[1]);
        if (strpos($expr, '.') !== FALSE) {
            $dinamis++;
            printf("  [dinamis] %-30s %s\n", $rel, $expr);
            continue;
        }
        if (! preg_match("/^'([^']+)'/", $expr, $t2)) {
            $dinamis++;
            printf("  [periksa] %-30s %s\n", $rel, $expr);
            continue;
        }
        $statis++;
        if (bisaDicapai($t2[1]) === 'tidak') {
            $gagal++;
            printf("  [GAGAL]  %-30s -> %s  (tidak ada route / method)\n", $rel, $t2[1]);
        }
    }
}
printf("  %d target statis, %d tidak terjangkau | %d target dinamis (periksa manual)\n\n", $statis, $gagal, $dinamis);

// ------------------------------------- 4. FORM MENTAH
echo "=== 2. <form> MENTAH (tanpa form_open -> tanpa csrf_token) ===\n";
$mentah = 0;
foreach ($views() as $f) {
    if ($f->isDir() OR $f->getExtension() !== 'php') { continue; }
    foreach (file($f->getPathname()) as $i => $ln) {
        if (stripos($ln, '<form') !== FALSE && stripos($ln, 'form_open') === FALSE) {
            $mentah++;
            printf("  [GAGAL] %s:%d  %s\n",
                str_replace('C:/laragon/www/ikp/application/views/', '',
                    str_replace('\\', '/', $f->getPathname())), $i + 1, trim($ln));
        }
    }
}
if ($mentah === 0) { echo "  (bersih)\n"; }

// ------------------------------------- 5. RUTE -> METHOD HILANG
echo "\n=== 3. RUTE YANG MENUJU KE METHOD YANG TIDAK ADA ===\n";
$rusak = 0;
foreach ($rute as $key => $tujuan) {
    if (strpos($tujuan, '/') === FALSE) { continue; }

    // Buang segmen placeholder ($1, $2, ...)
    $seg = array_values(array_filter(
        explode('/', $tujuan),
        function ($s) { return $s !== '' && strpos($s, '$') !== 0; }
    ));
    if (count($seg) === 0) { continue; }

    // admin/user/form -> subfolder admin, file User.php, method form
    if ($seg[0] === 'admin') {
        $cls    = $seg[1] ?? '';
        $metode = $seg[2] ?? 'index';
    } else {
        $cls    = $seg[0];
        $metode = $seg[1] ?? 'index';
    }

    if ($cls === '' OR ! methodAda($cls, $metode)) {
        $rusak++;
        printf("  [GAGAL] %-32s -> %s   (%s::%s tidak ada)\n", $key, $tujuan, $cls, $metode);
    }
}
if ($rusak === 0) { echo "  (bersih)\n"; }

// ------------------------------------- 6. METHOD PUBLIK TANPA RUTE
echo "\n=== 4. METHOD PUBLIK YANG TIDAK BISA DICAPAI LEWAT URL ===\n";
$yatim = 0;
foreach ($ctrl as $nama => $c) {
    preg_match_all('/^\s*public function (\w+)\s*\(/m', $c['source'], $mm);
    foreach ($mm[1] as $metode) {
        if (in_array($metode, array('__construct', 'index'), TRUE)) { continue; }
        $prefix = ($c['sub'] !== '' ? $c['sub'] . '/' : '') . strtolower($nama) . '/' . strtolower($metode);
        if (bisaDicapai($prefix) === 'tidak') {
            $yatim++;
            printf("  [cek] %s::%s()  (coba /%s)\n", $c['path'], $metode, $prefix);
        }
    }
}
if ($yatim === 0) { echo "  (bersih)\n"; }

$total = $gagal + $mentah + $rusak;
printf("\n=== TOTAL MASALAH: %d (form tak terjangkau %d, form mentah %d, rute rusak %d) ===\n",
    $total, $gagal, $mentah, $rusak);
printf("    (yatim %d = metode tanpa route, boleh sah bila memang dipanggil internal)\n", $yatim);
exit($total === 0 ? 0 : 1);
