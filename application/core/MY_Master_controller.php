<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Kelas dasar untuk halaman master data generik.
 *
 * Alasan: 4 tabel master (jenis insiden, kategori, spesialisasi, akibat)
 * punya bentuk yang sama: kode, nama, urutan opsional, is_active. Menyalin
 * Admin\Ruang.php ke empat file berarti ~760 baris yang isinya nyaris sama
 * dan pasti lama-lama tidak sinkron. Semua logika umum dikumpulkan di sini,
 * masing-masing child cukup mengisi $spec.
 *
 * CATATAN MUAT: CI3 HANYA auto-load core/MY_Controller.php dan
 * core/MY_Model.php. Berkas ini tidak otomatis dimuat, jadi setiap child WAJIB
 * menambah require_once di baris paling atas:
 *
 *     require_once APPPATH . 'core/MY_Master_controller.php';
 *
 * BENTUK $spec (diisi di child):
 *
 *     $this->spec = array(
 *         'judul'        => 'Master Jenis Insiden',  // judul halaman
 *         'slug'         => 'jenis-insiden',         // untuk URL: master/<slug>
 *         'model'        => 'incident_type_model',
 *         'tabel'        => 'incident_types',       // untuk pesan & audit
 *         'view'         => 'admin/master',         // folder view
 *         'nama_tunggal' => 'jenis insiden',        // untuk kalimat pesan
 *     );
 */
abstract class MY_Master_controller extends MY_Controller
{
	/** Diisi child. */
	protected $spec = array();

	/** Nama alias model yang sudah di-load. */
	protected $model_alias = 'master_model';

	/** Definisi kolom, diisi dari _fields(). */
	protected $fields = array();

	public function __construct()
	{
		parent::__construct();

		// Master data hanya untuk admin.
		$this->require_role('admin');

		$this->load->model($this->spec['model'], $this->model_alias);
		$this->fields = $this->_fields();
	}

	/**
	 * Daftar kolom. WAJIB ditimpa child.
	 *
	 * Kunci per kolom: nama, label, jenis, wajib, max, keterangan.
	 * Jenis kolom menentukan tampilan di view generik:
	 *   text     - input satu baris
	 *   textarea - input beberapa baris
	 *   number   - input angka (urutan)
	 *   switch   - toggle aktif/nonaktif
	 *
	 * @return array
	 */
	abstract protected function _fields();

	/**
	 * Tabel yang mereferensikan master ini. Child yang tabelnya bisa dipakai
	 * WAJIB menimpanya, supaya baris yang sudah terpakai tidak bisa dihapus
	 * permanen.
	 *
	 * @return array  daftar array('tabel' => 'incident_reports', 'kolom' => 'jenis_insiden_id')
	 */
	protected function _references()
	{
		return array();
	}

	// ============================================================== DAFTAR

	public function index()
	{
		$search = trim((string) $this->input->get('search'));

		$result = $this->{$this->model_alias}->paginate(array(
			'search' => $search,
			'limit'  => 50,
		));

		$rows = $result['rows'];

		// Berapa kali tiap baris dipakai, supaya admin tahu dampaknya sebelum
		// menonaktifkan atau menghapus.
		$refs = $this->_references();
		foreach ($rows as &$r) {
			$r['dipakai'] = $refs ? $this->_hitung_dipakai($refs, (int) $r['id']) : 0;
		}
		unset($r);

		$data = array(
			'title'         => $this->spec['judul'],
			'rows'          => $rows,
			'total'         => $result['total'],
			'search'        => $search,
			'fields'        => $this->fields,
			'spec'          => $this->spec,
			'ada_referensi' => ! empty($refs),
		);

		$this->render($this->spec['view'] . '/index', $data);
	}

	// ================================================================ FORM

	public function form($id = NULL)
	{
		$row = NULL;

		if ($id) {
			$row = $this->{$this->model_alias}->get($id);
			if (! $row) {
				$this->session->set_flashdata('error',
					ucfirst($this->spec['nama_tunggal']) . ' tidak ditemukan.');
				redirect('master/' . $this->spec['slug']);
			}
		}

		$data = array(
			'title'   => $id
				? 'Ubah ' . ucfirst($this->spec['nama_tunggal'])
				: 'Tambah ' . ucfirst($this->spec['nama_tunggal']),
			'row'     => $row,
			'is_edit' => (bool) $id,
			'fields'  => $this->fields,
			'spec'    => $this->spec,
		);

		$this->render($this->spec['view'] . '/form', $data);
	}

	// ================================================================ SIMPAN

	public function save($id = NULL)
	{
		if ($this->input->method() !== 'post') {
			$this->_deny();
		}

		$this->load->library('form_validation');

		foreach ($this->fields as $f) {
			$this->form_validation->set_rules($f['nama'], $f['label'], $this->_rule_untuk($f));
		}

		if ($this->form_validation->run() === FALSE) {
			$this->session->set_flashdata('error', 'Periksa lagi data yang diisi.');
			redirect($id ? $this->_url('form/' . (int) $id) : $this->_url('form'));
		}

		// Susun payload hanya dari kolom yang dikenal, supaya POST liar tidak
		// bisa menyuntik kolom lain ke database.
		$data = array();
		foreach ($this->fields as $f) {
			if ($f['jenis'] === 'switch') {
				$data[$f['nama']] = $this->input->post($f['nama']) ? 1 : 0;
				continue;
			}

			$nilai = $this->input->post($f['nama'], TRUE);

			if ($f['jenis'] === 'number') {
				$nilai = (int) $nilai;
			} else {
				$nilai = trim((string) $nilai);
				$nilai = $nilai === '' ? NULL : $nilai;
			}

			// Kode selalu huruf besar supaya isi dropdown konsisten.
			if ($f['nama'] === 'kode' && $nilai !== NULL) {
				$nilai = strtoupper($nilai);
			}

			$data[$f['nama']] = $nilai;
		}

		// Kode tidak boleh sama dengan kode lain.
		$kode = isset($data['kode']) ? $data['kode'] : NULL;
		if ($kode !== NULL AND $this->{$this->model_alias}->kode_exists($kode, $id)) {
			$this->session->set_flashdata('error',
				'Kode "' . $kode . '" sudah dipakai ' . $this->spec['nama_tunggal'] . ' lain.');
			redirect($id ? $this->_url('form/' . (int) $id) : $this->_url('form'));
		}

		if ($id) {
			$lama = $this->{$this->model_alias}->get($id);
			$this->{$this->model_alias}->update_row($id, $data);
			$this->_audit('Ubah ' . ucfirst($this->spec['nama_tunggal']),
				$this->spec['tabel'], $id, json_encode($lama), json_encode($data));
			$this->session->set_flashdata('success',
				ucfirst($this->spec['nama_tunggal']) . ' "' . $data['nama'] . '" diperbarui.');
		} else {
			$newId = $this->{$this->model_alias}->insert_row($data);
			$this->_audit('Tambah ' . ucfirst($this->spec['nama_tunggal']),
				$this->spec['tabel'], $newId, NULL, json_encode($data));
			$this->session->set_flashdata('success',
				ucfirst($this->spec['nama_tunggal']) . ' "' . $data['nama'] . '" ditambahkan.');
		}

		redirect($this->_url());
	}

	// =============================================================== HAPUS

	public function delete($id)
	{
		if ($this->input->method() !== 'post') {
			$this->_deny();
		}

		$row = $this->{$this->model_alias}->get($id);
		if (! $row) {
			$this->session->set_flashdata('error',
				ucfirst($this->spec['nama_tunggal']) . ' tidak ditemukan.');
			redirect($this->_url());
		}

		// WAJIB: master yang sudah dipakai tidak boleh dihapus permanen.
		// Master_model::delete_row() benar-benar menghapus baris, sehingga
		// laporan yang memakainya akan kehilangan referensi.
		$total = $this->_total_dipakai($this->_references(), (int) $id);

		if ($total > 0) {
			$this->session->set_flashdata('error',
				'"' . $row['nama'] . '" masih dipakai pada ' . $total
				. ' data, jadi tidak bisa dihapus. Nonaktifkan saja agar tidak muncul lagi di form.');
			redirect($this->_url());
		}

		$this->{$this->model_alias}->delete_row($id);
		$this->_audit('Hapus ' . ucfirst($this->spec['nama_tunggal']),
			$this->spec['tabel'], $id, json_encode($row), 'dihapus permanen');
		$this->session->set_flashdata('success',
			ucfirst($this->spec['nama_tunggal']) . ' "' . $row['nama'] . '" dihapus.');

		redirect($this->_url());
	}

	// ================================================================ TOGGLE

	public function toggle($id)
	{
		if ($this->input->method() !== 'post') {
			$this->_deny();
		}

		$row = $this->{$this->model_alias}->get($id);
		if (! $row) {
			$this->session->set_flashdata('error',
				ucfirst($this->spec['nama_tunggal']) . ' tidak ditemukan.');
			redirect($this->_url());
		}

		$baru = (int) $row['is_active'] === 1 ? 0 : 1;
		$this->{$this->model_alias}->update_row($id, array('is_active' => $baru));

		$aktivitas = $baru ? 'Aktifkan ' : 'Nonaktifkan ';
		$aktivitas .= ucfirst($this->spec['nama_tunggal']);
		$this->_audit($aktivitas, $this->spec['tabel'], $id,
			'is_active = ' . (int) $row['is_active'], 'is_active = ' . $baru);

		$qs = $this->input->get('search') ? '?search=' . urlencode($this->input->get('search')) : '';

		$this->session->set_flashdata('success',
			'"' . $row['nama'] . '" ' . ($baru ? 'diaktifkan' : 'dinonaktifkan') . '.');

		redirect($this->_url() . $qs);
	}

	// ============================================================== BANTUAN

	/** Susun aturan validasi dari definisi kolom. */
	protected function _rule_untuk(array $f)
	{
		$parts = array();

		if (! empty($f['wajib'])) {
			$parts[] = 'required';
		} else {
			$parts[] = 'trim';
		}

		if ($f['jenis'] === 'number') {
			$parts[] = 'integer';
			$parts[] = 'min_length[0]';
			$parts[] = 'max_length[3]';
		} elseif ($f['jenis'] !== 'switch') {
			$max = isset($f['max']) ? (int) $f['max'] : 100;
			$parts[] = 'max_length[' . $max . ']';
		}

		return implode('|', $parts);
	}

	/**
	 * Total pemakaian id di seluruh tabel referensi.
	 * Tabel yang sama tidak dihitung dua kali walau kolom foreign key-nya banyak.
	 */
	protected function _total_dipakai(array $refs, $id)
	{
		$per_tabel = array();

		foreach ($refs as $r) {
			if (! isset($per_tabel[$r['tabel']])) {
				$per_tabel[$r['tabel']] = $this->_hitung_dipakai($refs, $id, $r['tabel']);
			}
		}

		return array_sum($per_tabel);
	}

	/**
	 * @param array       $refs  daftar referensi
	 * @param int         $id    id master
	 * @param string|null $satu  kalau diisi, hitung hanya tabel ini
	 * @return int
	 */
	protected function _hitung_dipakai(array $refs, $id, $satu = NULL)
	{
		$jumlah = 0;

		foreach ($refs as $r) {
			if ($satu !== NULL && $r['tabel'] !== $satu) {
				continue;
			}
			if (! empty($r['kecuali_soft_delete'])) {
				$this->db->where($r['tabel'] . '.is_deleted', 0);
			}
			$this->db->where($r['tabel'] . '.' . $r['kolom'], (int) $id);
			$jumlah += (int) $this->db->from($r['tabel'])->count_all_results();
		}

		return $jumlah;
	}

	/** Roti ke URL master ini. */
	protected function _url($suffix = '')
	{
		return 'master/' . $this->spec['slug'] . ($suffix !== '' ? '/' . $suffix : '');
	}
}
