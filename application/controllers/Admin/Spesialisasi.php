<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// MY_Master_controller tidak di-auto-load CI3. Wajib require manual.
require_once APPPATH . 'core/MY_Master_controller.php';

/**
 * Master Spesialisasi Kejadian. KHUSUS admin.
 *
 * Tabel ini tidak punya kolom `urutan`, jadi urutannya mengikuti `kode`
 * (sudah diatur di Specialization_model).
 *
 * Catatan: MODUL-LAPORAN menyebut "Lokasi Kejadian" di dalam daftar
 * spesialisasi, padahal itu bukan spesialisasi. Kemungkinan besar salah salin
 * dari formulir. Lihat 06 - Keputusan & Pertanyaan Tertunda item 3.
 */
class Spesialisasi extends MY_Master_controller
{
	protected $spec = array(
		'judul'        => 'Master Spesialisasi Kejadian',
		'slug'         => 'spesialisasi',
		'model'        => 'specialization_model',
		'tabel'        => 'incident_specializations',
		'view'         => 'admin/master',
		'nama_tunggal' => 'spesialisasi',
	);

	protected function _fields()
	{
		return array(
			array(
				'nama'=> 'kode',
				'label' => 'Kode',
				'jenis' => 'text',
				'wajib' => TRUE,
				'max'   => 5,
			),
			array(
				'nama'=> 'nama',
				'label' => 'Nama Spesialisasi',
				'jenis' => 'text',
				'wajib' => TRUE,
				'max'   => 100,
			),
			array(
				'nama'=> 'is_active',
				'label' => 'Aktif dan bisa dipilih saat isi laporan',
				'jenis' => 'switch',
			),
		);
	}

	protected function _references()
	{
		return array(
			array('tabel' => 'incident_reports', 'kolom' => 'specialization_id', 'kecuali_soft_delete' => TRUE),
		);
	}
}
