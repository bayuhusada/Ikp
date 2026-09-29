<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Master Status. KHUSUS admin.
 *
 * TIDAK memakai MY_Master_controller karena tiga hal yang tidak umum:
 *
 * 1. `statuses.kode` TIDAK UNIK. Kode 'draft' dipakai oleh laporan DAN
 *    investigasi sekaligus, dan 'tuntas' dipakai tindakan. Jadi pengecekan
 *    `Master_model::kode_exists()` akan salah menandai kode yang sah sebagai
 *    duplikat. Uniqueness di sini per pasangan (kode, untuk).
 *
 * 2. Kolom `untuk` (laporan / investigasi / tindakan) menyertai seluruh alur
 *    status. Mengubahnya memindahkan status dari satu alur ke alur lain dan
 *    membuat dokumen yang sedang berjalan kehilangan statusnya. Karena itu
 *    kolom ini HANYA bisa diisi saat menambah, dan dikunci saat mengubah.
 *
 * 3. `is_terminal` mengatur boleh/tidaknya sebuah proses ditutup. Status
 *    terminal tidak boleh diubah sembarangan, jadi ditampilkan tapi dengan
 *    peringatan.
 */
class Status extends MY_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->require_role('admin');
		$this->load->model('status_model');
	}

	// ============================================================== DAFTAR

	public function index()
	{
		$search = trim((string) $this->input->get('search'));

		// Kelompokkan per jenis supaya urutannya jelas dan tidak tercampur.
		$kelompok = array(
			'laporan'     => array('label' => 'Status Laporan',     'icon' => 'bx-file'),
			'investigasi' => array('label' => 'Status Investigasi', 'icon' => 'bx-search-alt'),
			'tindakan'    => array('label' => 'Status Tindakan',    'icon' => 'bx-list-check'),
		);

		$blocks = array();
		$total  = 0;

		foreach ($kelompok as $jenis => $meta) {
			$rows = $this->db->from('statuses')->where('untuk', $jenis);

			if ($search !== '') {
				$this->db->group_start()
					->like('kode', $search)
					->or_like('nama', $search)
					->group_end();
			}

			$rows = $this->db->order_by('urutan', 'ASC')->get()->result_array();

			foreach ($rows as &$r) {
				$r['dipakai'] = $this->_hitung_dipakai($jenis, (int) $r['id']);
			}
			unset($r);

			$blocks[$jenis] = array(
				'label' => $meta['label'],
				'icon'  => $meta['icon'],
				'rows'  => $rows,
			);
			$total += count($rows);
		}

		$data = array(
			'title'     => 'Master Status',
			'blocks'    => $blocks,
			'total'     => $total,
			'search'    => $search,
			'untuk_list'=> array('laporan' => 'Laporan', 'investigasi' => 'Investigasi', 'tindakan' => 'Tindakan'),
		);

		$this->render('admin/status/index', $data);
	}

	// ================================================================ FORM

	public function form($id = NULL)
	{
		$row = NULL;

		if ($id) {
			$row = $this->status_model->get($id);
			if (! $row) {
				$this->session->set_flashdata('error', 'Status tidak ditemukan.');
				redirect('master/status');
			}
		}

		$data = array(
			'title'      => $id ? 'Ubah Status' : 'Tambah Status',
			'row'        => $row,
			'is_edit'    => (bool) $id,
			'untuk_list' => array('laporan' => 'Laporan', 'investigasi' => 'Investigasi', 'tindakan' => 'Tindakan'),
		);

		$this->render('admin/status/form', $data);
	}

	// ================================================================ SIMPAN

	public function save($id = NULL)
	{
		if ($this->input->method() !== 'post') {
			$this->_deny();
		}

		$this->load->library('form_validation');
		$this->form_validation->set_rules('kode', 'Kode', 'required|trim|max_length[30]');
		$this->form_validation->set_rules('nama', 'Nama', 'required|trim|max_length[50]');
		$this->form_validation->set_rules('urutan', 'Urutan', 'required|integer|min_length[0]|max_length[3]');
		$this->form_validation->set_rules('untuk', 'Untuk', 'required|in_list[laporan,investigasi,tindakan]');
		$this->form_validation->set_rules('is_terminal', 'Status Terminal', 'required|in_list[0,1]');

		if ($this->form_validation->run() === FALSE) {
			$this->session->set_flashdata('error', 'Periksa lagi data status yang diisi.');
			redirect($id ? 'master/status/form/' . (int) $id : 'master/status/form');
		}

		$kode  = $this->input->post('kode', TRUE);
		$untuk = $this->input->post('untuk', TRUE);

		// Saat mengubah, jenis TIDAK boleh diganti. Lihat catatan di header kelas.
		if ($id) {
			$lama = $this->status_model->get($id);
			if (! $lama) {
				$this->session->set_flashdata('error', 'Status tidak ditemukan.');
				redirect('master/status');
			}
			$untuk = $lama['untuk'];
		}

		$kode = strtolower(trim($kode));

		// Uniqueness per pasangan (kode, untuk) - bukan per kode saja.
		$cek = $this->db->from('statuses')
			->where('kode', $kode)
			->where('untuk', $untuk);
		if ($id) {
			$this->db->where('id !=', (int) $id);
		}
		if ($this->db->count_all_results() > 0) {
			$this->session->set_flashdata('error',
				'Kode "' . $kode . '" sudah dipakai untuk status ' . $untuk . '.');
			redirect($id ? 'master/status/form/' . (int) $id : 'master/status/form');
		}

		$data = array(
			'kode'        => $kode,
			'nama'        => $this->input->post('nama', TRUE),
			'untuk'       => $untuk,
			'urutan'      => (int) $this->input->post('urutan'),
			'is_terminal' => (int) $this->input->post('is_terminal'),
			'is_active'   => $this->input->post('is_active') ? 1 : 0,
		);

		if ($id) {
			$this->status_model->update_row($id, $data);
			$this->_audit('Ubah Status', 'statuses', $id, json_encode($lama), json_encode($data));
			$this->session->set_flashdata('success', 'Status "' . $data['nama'] . '" diperbarui.');
		} else {
			$newId = $this->status_model->insert_row($data);
			$this->_audit('Tambah Status', 'statuses', $newId, NULL, json_encode($data));
			$this->session->set_flashdata('success', 'Status "' . $data['nama'] . '" ditambahkan.');
		}

		redirect('master/status');
	}

	// =============================================================== HAPUS

	public function delete($id)
	{
		if ($this->input->method() !== 'post') {
			$this->_deny();
		}

		$row = $this->status_model->get($id);
		if (! $row) {
			$this->session->set_flashdata('error', 'Status tidak ditemukan.');
			redirect('master/status');
		}

		// Status yang pernah dipakai tidak boleh dihapus, termasuk kalau
		// datanya sudah terarsip - arsip tetap butuh statusnya untuk ditampilkan.
		$total = $this->_hitung_dipakai($row['untuk'], (int) $id, FALSE);

		if ($total > 0) {
			$this->session->set_flashdata('error',
				'Status "' . $row['nama'] . '" masih dipakai pada ' . $total
				. ' data, jadi tidak bisa dihapus. Nonaktifkan saja agar tidak muncul lagi di form.');
			redirect('master/status');
		}

		$this->status_model->delete_row($id);
		$this->_audit('Hapus Status', 'statuses', $id, json_encode($row), 'dihapus permanen');
		$this->session->set_flashdata('success', 'Status "' . $row['nama'] . '" dihapus.');

		redirect('master/status');
	}

	// ================================================================ TOGGLE

	public function toggle($id)
	{
		if ($this->input->method() !== 'post') {
			$this->_deny();
		}

		$row = $this->status_model->get($id);
		if (! $row) {
			$this->session->set_flashdata('error', 'Status tidak ditemukan.');
			redirect('master/status');
		}

		$baru = (int) $row['is_active'] === 1 ? 0 : 1;
		$this->status_model->update_row($id, array('is_active' => $baru));

		$this->_audit(($baru ? 'Aktifkan ' : 'Nonaktifkan ') . 'Status',
			'statuses', $id, 'is_active = ' . (int) $row['is_active'], 'is_active = ' . $baru);

		$this->session->set_flashdata('success',
			'Status "' . $row['nama'] . '" ' . ($baru ? 'diaktifkan' : 'dinonaktifkan') . '.');

		redirect('master/status');
	}

	// ============================================================== BANTUAN

	/**
	 * Berapa kali status ini dipakai di tabel alurnya.
	 *
	 * @param string $untuk            laporan / investigasi / tindakan
	 * @param int    $id
	 * @param bool   $aktif_saja       TRUE = abaikan data terarsip
	 * @return int
	 */
	private function _hitung_dipakai($untuk, $id, $aktif_saja = TRUE)
	{
		$jumlah = 0;

		if ($untuk === 'laporan') {
			$this->db->from('incident_reports')->where('status_id', (int) $id);
			if ($aktif_saja) {
				$this->db->where('is_deleted', 0);
			}
			$jumlah += (int) $this->db->count_all_results();
		} elseif ($untuk === 'investigasi') {
			$this->db->from('investigations')->where('status_id', (int) $id);
			if ($aktif_saja) {
				$this->db->where('is_deleted', 0);
			}
			$jumlah += (int) $this->db->count_all_results();
		} elseif ($untuk === 'tindakan') {
			// Tindakan tidak punya soft delete.
			$jumlah += (int) $this->db->from('investigation_actions')
				->where('status_id', (int) $id)
				->count_all_results();
		}

		return $jumlah;
	}
}
