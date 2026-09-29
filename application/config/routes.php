<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| URI Routing
|--------------------------------------------------------------------------
| Ringkas saja. Modul utama: Auth, Dashboard, Pelaporan, Investigasi, Laporan_rekap.
| Semua controller di bawah kecuali Auth mewarisi MY_Controller, yang
| sudah menjaga login secara otomatis.
|
*/

$route['default_controller'] = 'dashboard';
$route['404_override'] = 'notfound';
$route['translate_uri_dashes'] = FALSE;

/*
| Rute khusus (harus sebelum rute default CI3).
|
| PENTING: rute yang hanya wanna pas dengan satu segment HARUS diakhiri
| tanda $ (anchor). Tanpa itu, $route['dashboard'] akan juga menelan
| dashboard/terbaru dan;sub-rute tidak akan pernah tercapai.
*/

// Landing
$route['beranda$'] = 'dashboard/index';

// Auth
$route['login$']        = 'auth/login';
$route['proses-login$'] = 'auth/proses_login';
$route['logout$']       = 'auth/logout';

// Dashboard
$route['dashboard$'] = 'dashboard/index';

// Laporan insiden
$route['laporan$']                        = 'pelaporan/index';
$route['laporan/input$']                  = 'pelaporan/create';
$route['laporan/arsip$']                  = 'pelaporan/archived';
$route['laporan/store$']                  = 'pelaporan/store';
$route['laporan/update/(:num)']            = 'pelaporan/update/$1';
$route['laporan/detail/(:num)']          = 'pelaporan/detail/$1';
$route['laporan/edit/(:num)']            = 'pelaporan/edit/$1';
$route['laporan/hapus/(:num)']           = 'pelaporan/delete/$1';
$route['laporan/kirim/(:num)']           = 'pelaporan/submit/$1';
$route['laporan/grading/(:num)']          = 'pelaporan/grading/$1';

// Export PDF & Excel (Fase 5) BELUM diimplementasikan, jadi rutenya sengaja
// TIDAK diaktifkan. Method-nya juga belum ada, jadi kalau rutenya aktif akan
// jatuh ke "Method not found". PDF juga masih terblokir: tata letaknya harus
// mengikuti formulir fisik, yang belum ada.
// TODO aktifkan bersama implementasi:
// $route['laporan/export/pdf/(:num)']      = 'pelaporan/export_pdf/$1';
// $route['laporan/export/excel$']          = 'pelaporan/export_excel';

// Investigasi
$route['investigasi$']                    = 'investigasi/index';
$route['investigasi/arsip$']              = 'investigasi/archived';
$route['investigasi/input/(:num)']        = 'investigasi/create/$1';
$route['investigasi/store/(:num)']        = 'investigasi/store/$1';
$route['investigasi/detail/(:num)']       = 'investigasi/detail/$1';
$route['investigasi/edit/(:num)']         = 'investigasi/edit/$1';
$route['investigasi/update/(:num)']       = 'investigasi/update/$1';
$route['investigasi/hapus/(:num)']        = 'investigasi/delete/$1';
$route['investigasi/status/(:num)']       = 'investigasi/ubah_status/$1';
$route['investigasi/pmkp/(:num)']         = 'investigasi/pmkp/$1';
$route['investigasi/rekomendasi/tambah/(:num)'] = 'investigasi/tambah_rekomendasi/$1';
$route['investigasi/rekomendasi/hapus/(:num)/(:num)'] = 'investigasi/hapus_rekomendasi/$1/$2';
$route['investigasi/tindakan/tambah/(:num)']   = 'investigasi/tambah_tindakan/$1';
$route['investigasi/tindakan/hapus/(:num)/(:num)'] = 'investigasi/hapus_tindakan/$1/$2';
$route['investigasi/tindakan/status/(:num)/(:num)'] = 'investigasi/set_status_tindakan/$1/$2';
// Export PDF investigasi (Fase 5) belum diimplementasikan.
// TODO aktifkan bersama implementasi:
// $route['investigasi/export/pdf/(:num)']  = 'investigasi/export_pdf/$1';

// Rekap & Statistik. Controller Laporan_rekap BELUM ada, jadi rutenya
// sengaja tidak diaktifkan. Menu di sidebar juga disembunyikan lewat flag
// fitur (config/features.php, kunci 'rekap'), jadi tidak ada tombol mati.
// TODO aktifkan bersama pembuatan application/controllers/Laporan_rekap.php
// $route['rekap$']           = 'laporan_rekap/index';
// $route['rekap/harian']     = 'laporan_rekap/harian';
// $route['rekap/mingguan']   = 'laporan_rekap/mingguan';
// $route['rekap/bulanan']    = 'laporan_rekap/bulanan';
// $route['rekap/statistik']  = 'laporan_rekap/statistik';

// Profil
$route['profil$']                        = 'profile/index';
$route['profil/ubah$']                   = 'profile/update';
$route['profil/unggah-ttd$']             = 'profile/upload_ttd';
$route['profil/ubah-sandi']             = 'profile/change_password';

// Audit log
$route['audit-log$']             = 'audit_log/index';
$route['audit-log/export-csv$']  = 'audit_log/export_csv';

// Master data (khusus admin)
$route['master/user$']                   = 'admin/user';
$route['master/user/form$']             = 'admin/user/form';
$route['master/user/form/(:num)']       = 'admin/user/form/$1';
$route['master/user/save']              = 'admin/user/save';
$route['master/user/save/(:num)']       = 'admin/user/save/$1';
$route['master/user/hapus/(:num)']      = 'admin/user/delete/$1';
$route['master/user/toggle/(:num)']     = 'admin/user/toggle/$1';
$route['master/user/reset-sandi/(:num)'] = 'admin/user/reset_password/$1';

$route['master/ruangan$']               = 'admin/ruang';
$route['master/ruangan/form$']          = 'admin/ruang/form';
$route['master/ruangan/form/(:num)']    = 'admin/ruang/form/$1';
$route['master/ruangan/save']           = 'admin/ruang/save';
$route['master/ruangan/save/(:num)']    = 'admin/ruang/save/$1';
$route['master/ruangan/hapus/(:num)']   = 'admin/ruang/delete/$1';
$route['master/ruangan/toggle/(:num)']  = 'admin/ruang/toggle/$1';

$route['master/band-risiko$']           = 'admin/band_risiko';
$route['master/band-risiko/form$']      = 'admin/band_risiko/form';
$route['master/band-risiko/form/(:num)'] = 'admin/band_risiko/form/$1';
$route['master/band-risiko/save']       = 'admin/band_risiko/save';
$route['master/band-risiko/save/(:num)'] = 'admin/band_risiko/save/$1';

// Master generik. Semua punya pola rute yang sama karena controller-nya
// mewarisi MY_Master_controller.
$route['master/jenis-insiden$']           = 'admin/jenis_insiden';
$route['master/jenis-insiden/form$']      = 'admin/jenis_insiden/form';
$route['master/jenis-insiden/form/(:num)'] = 'admin/jenis_insiden/form/$1';
$route['master/jenis-insiden/save']       = 'admin/jenis_insiden/save';
$route['master/jenis-insiden/save/(:num)'] = 'admin/jenis_insiden/save/$1';
$route['master/jenis-insiden/hapus/(:num)'] = 'admin/jenis_insiden/delete/$1';
$route['master/jenis-insiden/toggle/(:num)'] = 'admin/jenis_insiden/toggle/$1';

$route['master/kategori$']                = 'admin/kategori';
$route['master/kategori/form$']           = 'admin/kategori/form';
$route['master/kategori/form/(:num)']     = 'admin/kategori/form/$1';
$route['master/kategori/save']            = 'admin/kategori/save';
$route['master/kategori/save/(:num)']     = 'admin/kategori/save/$1';
$route['master/kategori/hapus/(:num)']    = 'admin/kategori/delete/$1';
$route['master/kategori/toggle/(:num)']   = 'admin/kategori/toggle/$1';

$route['master/spesialisasi$']            = 'admin/spesialisasi';
$route['master/spesialisasi/form$']       = 'admin/spesialisasi/form';
$route['master/spesialisasi/form/(:num)'] = 'admin/spesialisasi/form/$1';
$route['master/spesialisasi/save']        = 'admin/spesialisasi/save';
$route['master/spesialisasi/save/(:num)'] = 'admin/spesialisasi/save/$1';
$route['master/spesialisasi/hapus/(:num)'] = 'admin/spesialisasi/delete/$1';
$route['master/spesialisasi/toggle/(:num)'] = 'admin/spesialisasi/toggle/$1';

$route['master/akibat$']                  = 'admin/akibat';
$route['master/akibat/form$']             = 'admin/akibat/form';
$route['master/akibat/form/(:num)']       = 'admin/akibat/form/$1';
$route['master/akibat/save']              = 'admin/akibat/save';
$route['master/akibat/save/(:num)']       = 'admin/akibat/save/$1';
$route['master/akibat/hapus/(:num)']      = 'admin/akibat/delete/$1';
$route['master/akibat/toggle/(:num)']     = 'admin/akibat/toggle/$1';

// Master Status. Kode status tidak unik antar jenis, jadi controller memakai
// pasangan (kode, untuk) - tidak bisa memakai MY_Master_controller.
$route['master/status$']                  = 'admin/status';
$route['master/status/form$']             = 'admin/status/form';
$route['master/status/form/(:num)']       = 'admin/status/form/$1';
$route['master/status/save']              = 'admin/status/save';
$route['master/status/save/(:num)']       = 'admin/status/save/$1';
$route['master/status/hapus/(:num)']      = 'admin/status/delete/$1';
$route['master/status/toggle/(:num)']     = 'admin/status/toggle/$1';
