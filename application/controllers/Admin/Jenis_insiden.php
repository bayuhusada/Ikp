<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// MY_Master_controller tidak di-auto-load CI3. Wajib require manual.
require_once APPPATH . 'core/MY_Master_controller.php';

/**
 * Master Jenis Insiden (KNC / KTC / KTD / Sentinel). KHUSUS admin.
 *
 * Kolom nama_asing dipakai untuk istilah bahasa Inggris (KNC = Near Miss,
 * KTC = No Harm, KTD = Significant Harm, dst) supaya tidak perlu menerjemahkan
 * di tempat lain.
 */
class Jenis_insiden extends MY_Master_controller
{
	protected $spec = array(
		'judul'        => 'Master Jenis Insiden',
		'slug'         => 'jenis-insiden',
		'model'        => 'incident_type_model',
		'tabel'        => 'incident_types',
		'view'         => 'admin/master',
		'nama_tunggal' => 'jenis insiden',
	);

	protected function _fields()
	{
		return array(
			array(
				'nama'=> 'kode',
				'label' => 'Kode',
				'jenis' => 'text',
				'wajib' => TRUE,
				'max'   => 10,
				'keterangan' => 'Kode pendek yang tampil di form, misalnya KNC, KTC, KTD, SENTINEL.',
			),
			array(
				'nama'=> 'nama',
				'label' => 'Nama',
				'jenis' => 'text',
				'wajib' => TRUE,
				'max'   => 100,
			),
			array(
				'nama'=> 'nama_asing',
				'label' => 'Nama Asing',
				'jenis' => 'text',
				'wajib' => FALSE,
				'max'   => 100,
				'keterangan' => 'Opsional. Singer atau istilah bahasa Inggris.',
			),
			array(
				'nama'=> 'urutan',
				'label' => 'Urutan',
				'jenis' => 'number',
				'wajib' => TRUE,
				'keterangan' => 'Menentukan urutan di dropdown. 1 = paling atas.',
			),
			array(
				'nama'=> 'definisi',
				'label' => 'Definisi',
				'jenis' => 'textarea',
				'wajib' => FALSE,
				'max'   => 2000,
				'baris' => 4,
				'keterangan' => 'Penjelasan singkat supaya pelapor tidak salah memilih.',
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
			array('tabel' => 'incident_reports', 'kolom' => 'jenis_insiden_id', 'kecuali_soft_delete' => TRUE),
			array('tabel' => 'investigations',   'kolom' => 'jenis_insiden_id', 'kecuali_soft_delete' => TRUE),
		);
	}
}
