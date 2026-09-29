<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Daftar fitur & status ketersediaannya.
 *
 * TUJUAN: sidebar hanya boleh menampilkan menu yang halamannya sudah jadi.
 * Menu yang controllernya belum ada disembunyikan di sini, bukan dikomentari
 * di dalam view, supaya status fitur tercatat di satu tempat dan mengaktifkan
 * sebuah halaman cukup mengubah satu nilai di sini.
 *
 * CARA PAKAI:
 *   config_item('fitur')['kunci'] === 'siap'  -> tampilkan
 *   config_item('fitur')['kunci'] === 'belum' -> sembunyikan
 *
 * Setelah controllernya selesai dibuat, ubah 'belum' menjadi 'siap' dan
 * menu akan langsung muncul tanpa menyentuh aside.php.
 *
 * PENTING: status di sini hanya mengatur VISIBILITAS MENU. Kontroller tetap
 * wajib require_role() sendiri. Menu tersembunyi bukan pengganti penjaga akses.
 */
$config['fitur'] = array(

	// ---- Master data (khusus admin) ----
	'master_jenis_insiden' => 'siap',
	'master_kategori'      => 'siap',
	'master_spesialisasi'  => 'siap',
	'master_akibat'        => 'siap',
	'master_status'        => 'siap',

	// ---- Laporan & statistik (admin + kepala ruangan) ----
	'rekap'                => 'belum',   // controller Laporan_rekap belum ada

	// ---- Export (Fase 5) ----
	// 'export_pdf'  => 'belum',  // terblokir: menunggu scan formulir fisik
	// 'export_excel'=> 'belum',
);
