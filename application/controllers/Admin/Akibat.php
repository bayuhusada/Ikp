<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// MY_Master_controller tidak di-auto-load CI3. Wajib require manual.
require_once APPPATH . 'core/MY_Master_controller.php';

/**
 * Master Akibat Insiden. KHUSUS admin.
 *
 * Pilihan ini menentukan seberapa serius insiden bagi pasien, jadi ikut
 * dipakai saat Kepala Ruangan menentukan band risiko.
 */
class Akibat extends MY_Master_controller
{
	protected $spec = array(
		'judul'        => 'Master Akibat Insiden',
		'slug'         => 'akibat',
		'model'        => 'impact_model',
		'tabel'        => 'incident_impacts',
		'view'         => 'admin/master',
		'nama_tunggal' => 'akibat insiden',
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
				'label' => 'Nama Akibat',
				'jenis' => 'text',
				'wajib' => TRUE,
				'max'   => 100,
			),
			array(
				'nama'=> 'urutan',
				'label' => 'Urutan',
				'jenis' => 'number',
				'wajib' => TRUE,
				'keterangan' => '1 = paling ringan. Urutan ini ikut memengaruhi penilaian Kepala Ruangan.',
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
			array('tabel' => 'incident_reports', 'kolom' => 'akibat_insiden_id', 'kecuali_soft_delete' => TRUE),
		);
	}
}
