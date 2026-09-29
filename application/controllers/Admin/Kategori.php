<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// MY_Master_controller tidak di-auto-load CI3. Wajib require manual.
require_once APPPATH . 'core/MY_Master_controller.php';

/**
 * Master Kategori Kejadian. KHUSUS admin.
 *
 * Tabel ini tidak punya kolom `urutan`, jadi urutannya mengikuti `kode`
 * (sudah diatur di Incident_category_model).
 */
class Kategori extends MY_Master_controller
{
	protected $spec = array(
		'judul'        => 'Master Kategori Kejadian',
		'slug'         => 'kategori',
		'model'        => 'incident_category_model',
		'tabel'        => 'incident_categories',
		'view'         => 'admin/master',
		'nama_tunggal' => 'kategori kejadian',
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
				'label' => 'Nama Kategori',
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
			array('tabel' => 'incident_reports', 'kolom' => 'incident_category_id', 'kecuali_soft_delete' => TRUE),
		);
	}
}
