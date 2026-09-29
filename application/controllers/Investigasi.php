<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Modul Lembar Kerja Investigasi Sederhana.
 *
 * Hanya untuk band BIRU dan HIJAU. Band KUNING dan MERAH memakai
 * proses SOP di luar sistem ini, jadi tombol "Buat Investigasi"
 * sengaja tidak muncul untuk band itu.
 *
 * Status investigasi (statuses untuk = investigasi):
 *   inv_draft 8  dalam_investigasi 9  menunggu_tindak_lanjut 10
 *   dalam_pelaksanaan 11  inv_selesai 12
 */
class Investigasi extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->require_role(array('admin', 'kepala_ruangan'));
        $this->load->model(array(
            'investigation_model', 'incident_report_model', 'ruangan_model',
            'risk_band_model', 'status_model',
        ));
    }

    // ============================================================== DAFTAR

    public function index()
    {
        $limit = 25;
        $page = max(1, (int) $this->input->get('page'));

        $filter = array(
            'search'        => (string) $this->input->get('search'),
            'ruangan_id'    => (int) $this->input->get('ruangan_id'),
            'status_id'     => (int) $this->input->get('status_id'),
            'risk_band_id'  => (int) $this->input->get('risk_band_id'),
        );

        $result = $this->investigation_model->paginate_investigasi(
            $filter, $limit, ($page - 1) * $limit,
            (string) $this->input->get('sort') ?: 'created_at',
            (string) $this->input->get('dir')
        );

        $data = array(
            'title'   => $this->user['role'] === 'admin' ? 'Semua Investigasi' : 'Status Investigasi',
            'rows'    => $result['rows'],
            'total'   => $result['total'],
            'page'    => $page,
            'limit'   => $limit,
            'pages'   => (int) ceil($result['total'] / max(1, $limit)),
            'filter'  => $filter,
            'ruangan_list' => $this->ruangan_model->options_for_user($this->user),
            'band_list'    => $this->risk_band_model->options(),
            'status_list'  => $this->status_model->options_by_jenis('investigasi'),
            'tindakan_status' => $this->status_model->options_by_jenis('tindakan'),
        );

        $this->render('investigasi/index', $data);
    }

    public function archived()
    {
        $this->require_role('admin');

        $rows = $this->db->select('i.*, ir.nomor AS nomor_laporan, r.nama AS ruang_nama,
                                    rb.kode AS band_kode, s.nama AS status_nama, u.nama AS dihapus_oleh')
            ->from('investigations i')
            ->join('incident_reports ir', 'ir.id = i.incident_report_id')
            ->join('ruangan r', 'r.id = i.ruangan_id')
            ->join('risk_bands rb', 'rb.id = i.risk_band_id')
            ->join('statuses s', 's.id = i.status_id')
            ->join('users u', 'u.id = i.deleted_by', 'left')
            ->where('i.is_deleted', 1)
            ->order_by('i.deleted_at', 'DESC')
            ->get()
            ->result_array();

        $data = array(
            'title' => 'Arsip Investigasi',
            'rows'  => $rows,
        );

        $this->render('investigasi/archived', $data);
    }

    // ================================================================ BUAT

    /**
     * Buat lembar investigasi dari sebuah laporan.
     * Hanya untuk laporan berstatus "Sudah Grading" dengan band BIRU/HIJAU.
     */
    public function create($report_id)
    {
        $this->require_role('admin');

        $row = $this->incident_report_model->get_detail($report_id);
        if (! $row) {
            $this->session->set_flashdata('error', 'Laporan tidak ditemukan.');
            redirect('laporan');
        }

        // Syarat: sudah di-grading
        if ((int) $row['status_id'] !== $this->_id_status_laporan('sudah_grading')) {
            $this->session->set_flashdata('error',
                'Laporan harus berstatus "Sudah Grading" sebelum dibuatkan investigasi.');
            redirect('laporan/detail/' . $report_id);
        }

        // Syarat: band BIRU atau HIJAU
        if (empty($row['risk_band_id'])) {
            $this->session->set_flashdata('error', 'Laporan belum memiliki grading risiko.');
            redirect('laporan/detail/' . $report_id);
        }

        $band = $this->risk_band_model->get($row['risk_band_id']);
        if ((int) $band['perlu_investigasi_sederhana'] !== 1) {
            $this->session->set_flashdata('error',
                'Band ' . $band['kode'] . ' tidak memakai Investigasi Sederhana. '
                . 'Prosesnya mengikuti SOP di luar sistem ini.');
            redirect('laporan/detail/' . $report_id);
        }

        // Syarat: belum punya lembar investigasi
        if ($this->investigation_model->ada_per_laporan($report_id)) {
            $this->session->set_flashdata('error',
                'Laporan ini sudah punya lembar investigasi.');
            redirect('investigasi');
        }

        $data = array(
            'title'   => 'Buat Lembar Kerja Investigasi',
            'laporan' => $row,
            'row'     => NULL,
            'is_edit' => FALSE,
        );

        $this->render('investigasi/form', $data);
    }

    public function store($report_id)
    {
        if ($this->input->method() !== 'post') {
            $this->_deny();
        }
        $this->require_role('admin');

        $row = $this->incident_report_model->get_detail($report_id);
        if (! $row) {
            $this->session->set_flashdata('error', 'Laporan tidak ditemukan.');
            redirect('laporan');
        }
        if ($this->investigation_model->ada_per_laporan($report_id)) {
            $this->session->set_flashdata('error', 'Laporan ini sudah punya lembar investigasi.');
            redirect('investigasi');
        }

        $this->_validasi();

        $this->db->trans_begin();
        try {
            $nomor = $this->investigation_model->nomor_baru('INV');
            if ($nomor === FALSE) {
                $this->db->trans_rollback();
                $this->session->set_flashdata('error', 'Gagal membuat nomor investigasi.');
                redirect('investigasi/input/' . $report_id);
            }

            $data = $this->_siapkan_data($row);
            $data['nomor'] = $nomor;
            $data['status_id'] = $this->_id_status_inv('inv_draft');

            $id = $this->investigation_model->insert($data);
            if (! $id) {
                $this->db->trans_rollback();
                $this->session->set_flashdata('error', 'Investigasi gagal disimpan.');
                redirect('investigasi/input/' . $report_id);
            }

            $this->_simpan_baris($id);

            // Laporan induk berpindah ke status Investigasi
            $this->incident_report_model->update($report_id, array(
                'status_id' => $this->_id_status_laporan('investigasi'),
            ));
            $this->incident_report_model->catat_history(
                $report_id, 'update',
                json_encode(array('status' => $row['status_nama'])),
                json_encode(array('status' => 'Investigasi', 'investigasi' => $nomor)),
                (int) $this->user['id'], $this->input->ip_address()
            );

            $this->db->trans_commit();
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Gagal menyimpan investigasi: ' . $e->getMessage());
            $this->session->set_flashdata('error', 'Terjadi kesalahan saat menyimpan investigasi.');
            redirect('investigasi/input/' . $report_id);
        }

        $this->_audit('Buat Lembar Investigasi', 'investigations', $id, NULL, $nomor);
        $this->session->set_flashdata('success', 'Lembar investigasi ' . $nomor . ' dibuat.');

        redirect('investigasi/detail/' . $id);
    }

    // ============================================================== DETAIL

    public function detail($id)
    {
        $row = $this->investigation_model->get_detail($id);
        if (! $row) {
            $this->session->set_flashdata('error', 'Investigasi tidak ditemukan atau tidak punya hak akses.');
            redirect('investigasi');
        }

        $data = array(
            'title'      => 'Lembar Investigasi ' . $row['nomor'],
            'row'        => $row,
            'rekomendasi'=> $this->investigation_model->get_rekomendasi($id),
            'tindakan'   => $this->investigation_model->get_tindakan($id),
            'tindakan_status' => $this->status_model->options_by_jenis('tindakan'),
            'status_list'=> $this->status_model->options_by_jenis('investigasi'),
            'status_inv' => $this->status_model->get_by_jenis('investigasi'),
            'band_list'  => $this->risk_band_model->options(),
            'boleh_ubah' => $this->_boleh_ubah($row),
            'boleh_pmkp' => $this->_boleh_pmkp($row),
        );

        $this->render('investigasi/detail', $data);
    }

    public function edit($id)
    {
        $this->require_role('admin');

        $row = $this->investigation_model->get_detail($id);
        if (! $row) {
            $this->session->set_flashdata('error', 'Investigasi tidak ditemukan.');
            redirect('investigasi');
        }
        if (! $this->_boleh_ubah($row)) {
            $this->_deny();
        }

        $data = array(
            'title'   => 'Ubah Lembar Investigasi ' . $row['nomor'],
            'row'     => $row,
            'laporan' => NULL,
            'is_edit' => TRUE,
            'rekomendasi' => $this->investigation_model->get_rekomendasi($id),
            'tindakan'    => $this->investigation_model->get_tindakan($id),
        );

        $this->render('investigasi/form', $data);
    }

    public function update($id)
    {
        if ($this->input->method() !== 'post') {
            $this->_deny();
        }
        $this->require_role('admin');

        $row = $this->investigation_model->get_detail($id);
        if (! $row) {
            $this->session->set_flashdata('error', 'Investigasi tidak ditemukan.');
            redirect('investigasi');
        }
        if (! $this->_boleh_ubah($row)) {
            $this->_deny();
        }

        $this->_validasi();

        $this->db->trans_begin();
        try {
            $this->investigation_model->update($id, $this->_siapkan_data(NULL, $row));
            $this->_simpan_baris($id, TRUE);
            $this->db->trans_commit();
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Gagal mengubah investigasi: ' . $e->getMessage());
            $this->session->set_flashdata('error', 'Terjadi kesalahan saat menyimpan perubahan.');
            redirect('investigasi/edit/' . $id);
        }

        $this->_audit('Ubah Lembar Investigasi', 'investigations', $id, $row['nomor'], $row['nomor']);
        $this->session->set_flashdata('success', 'Perubahan tersimpan.');

        redirect('investigasi/detail/' . $id);
    }

    // ============================================================== STATUS

    public function ubah_status($id)
    {
        if ($this->input->method() !== 'post') {
            $this->_deny();
        }

        $row = $this->investigation_model->get_detail($id);
        if (! $row) {
            $this->session->set_flashdata('error', 'Investigasi tidak ditemukan.');
            redirect('investigasi');
        }
        if ($this->user['role'] !== 'admin' && (int) $row['ruangan_id'] !== (int) $this->user['ruangan_id']) {
            $this->_deny();
        }

        $kode = (string) $this->input->post('status_kode', true);
        $baru = $this->status_model->get_by_kode($kode, 'investigasi');
        if (! $baru) {
            $this->session->set_flashdata('error', 'Status tidak dikenal.');
            redirect('investigasi/detail/' . $id);
        }

        // Tidak boleh Mundur
        if ((int) $baru['urutan'] < (int) $row['status_urutan']) {
            $this->session->set_flashdata('error', 'Status investigasi tidak bisa mundur.');
            redirect('investigasi/detail/' . $id);
        }

        // Selesai hanya bila semua tindakan tuntas
        if ((int) $baru['is_terminal'] === 1
            && ! $this->investigation_model->semua_tindakan_selesai($id)) {
            $this->session->set_flashdata('error',
                'Investigasi belum bisa ditutup: masih ada tindakan yang belum selesai.');
            redirect('investigasi/detail/' . $id);
        }

        $this->investigation_model->update($id, array('status_id' => (int) $baru['id']));

        $this->_audit_diff('Ubah Status Investigasi', 'investigations', $id, array(
            'status' => array('sebelum' => $row['status_nama'], 'sesudah' => $baru['nama']),
        ));

        // Laporan induk ikut progressed
        $this->_sinkron_status_laporan($row, $baru);

        $this->session->set_flashdata('success', 'Status investigasi diubah ke "' . $baru['nama'] . '".');

        redirect('investigasi/detail/' . $id);
    }

    // =============================================================== PMKP

    public function pmkp($id)
    {
        if ($this->input->method() !== 'post') {
            $this->_deny();
        }
        $this->require_role(array('admin', 'kepala_ruangan'));

        $row = $this->investigation_model->get_detail($id);
        if (! $row) {
            $this->session->set_flashdata('error', 'Investigasi tidak ditemukan.');
            redirect('investigasi');
        }
        if ($this->user['role'] === 'kepala_ruangan'
            && (int) $row['ruangan_id'] !== (int) $this->user['ruangan_id']) {
            $this->_deny();
        }

        $lanjut = $this->input->post('investigasi_lanjut') ? 1 : 0;

        $this->investigation_model->update($id, array(
            'investigasi_lanjut'     => $lanjut,
            'tanggal_keputusan_pmkp' => $this->input->post('tanggal_keputusan_pmkp') ?: NULL,
            'catatan_pmkp'           => $this->input->post('catatan_pmkp', true) ?: NULL,
            'grading_ulang'          => $this->input->post('grading_ulang') ?: NULL,
        ));

        $this->_audit_diff('Keputusan Komite PMKP', 'investigations', $id, array(
            'investigasi_lanjut' => array(
                'sebelum' => $row['investigasi_lanjut'] === NULL ? '-' : (string) $row['investigasi_lanjut'],
                'sesudah' => (string) $lanjut,
            ),
        ));

        $this->session->set_flashdata('success', 'Keputusan Komite PMKP tersimpan.');

        redirect('investigasi/detail/' . $id);
    }

    // ======================================================= REKOMENDASI

    public function tambah_rekomendasi($id)
    {
        if ($this->input->method() !== 'post') {
            $this->_deny();
        }
        $this->require_role('admin');

        $row = $this->investigation_model->get_detail($id);
        if (! $row) {
            $this->session->set_flashdata('error', 'Investigasi tidak ditemukan.');
            redirect('investigasi');
        }

        $rek = trim((string) $this->input->post('rekomendasi', true));
        if ($rek === '') {
            $this->session->set_flashdata('error', 'Isi rekomendasi lebih dulu.');
            redirect('investigasi/detail/' . $id);
        }

        $this->investigation_model->tambah_rekomendasi(
            $id, $rek,
            trim((string) $this->input->post('penanggung_jawab', true)),
            $this->input->post('tanggal_rekomendasi') ?: NULL
        );

        $this->_audit('Tambah Rekomendasi', 'investigations', $id, NULL, substr($rek, 0, 60));
        $this->session->set_flashdata('success', 'Rekomendasi ditambahkan.');

        redirect('investigasi/detail/' . $id);
    }

    public function hapus_rekomendasi($id, $rec_id)
    {
        if ($this->input->method() !== 'post') {
            $this->_deny();
        }
        $this->require_role('admin');

        $this->investigation_model->hapus_rekomendasi($rec_id, $id);
        $this->_audit('Hapus Rekomendasi', 'investigations', $id, 'id ' . $rec_id, NULL);

        $this->session->set_flashdata('success', 'Rekomendasi dihapus.');
        redirect('investigasi/detail/' . $id);
    }

    // =========================================================== TINDAKAN

    public function tambah_tindakan($id)
    {
        if ($this->input->method() !== 'post') {
            $this->_deny();
        }
        $this->require_role('admin');

        $row = $this->investigation_model->get_detail($id);
        if (! $row) {
            $this->session->set_flashdata('error', 'Investigasi tidak ditemukan.');
            redirect('investigasi');
        }

        $t = trim((string) $this->input->post('tindakan', true));
        if ($t === '') {
            $this->session->set_flashdata('error', 'Isi tindakan lebih dulu.');
            redirect('investigasi/detail/' . $id);
        }

        $this->investigation_model->tambah_tindakan(
            $id, $t,
            trim((string) $this->input->post('penanggung_jawab', true)),
            $this->input->post('tanggal_tindakan') ?: NULL,
            $this->input->post('status_id') ? (int) $this->input->post('status_id') : NULL
        );

        $this->_audit('Tambah Tindakan', 'investigations', $id, NULL, substr($t, 0, 60));
        $this->session->set_flashdata('success', 'Tindakan ditambahkan.');

        redirect('investigasi/detail/' . $id);
    }

    public function hapus_tindakan($id, $act_id)
    {
        if ($this->input->method() !== 'post') {
            $this->_deny();
        }
        $this->require_role('admin');

        $this->investigation_model->hapus_tindakan($act_id, $id);
        $this->_audit('Hapus Tindakan', 'investigations', $id, 'id ' . $act_id, NULL);

        $this->session->set_flashdata('success', 'Tindakan dihapus.');
        redirect('investigasi/detail/' . $id);
    }

    public function set_status_tindakan($id, $act_id)
    {
        if ($this->input->method() !== 'post') {
            $this->_deny();
        }
        $this->require_role('admin');

        $baru = (int) $this->input->post('status_id');
        $cek = $this->status_model->get($baru);
        if (! $cek || $cek['untuk'] !== 'tindakan') {
            $this->session->set_flashdata('error', 'Status tindakan tidak valid.');
            redirect('investigasi/detail/' . $id);
        }

        $this->db->where('id', (int) $act_id);
        $this->db->where('investigation_id', (int) $id);
        $this->db->update('investigation_actions', array('status_id' => $baru));

        $this->_audit('Ubah Status Tindakan', 'investigations', $id, NULL, $cek['nama']);
        redirect('investigasi/detail/' . $id);
    }

    // ============================================================== HAPUS

    public function delete($id)
    {
        if ($this->input->method() !== 'post') {
            $this->_deny();
        }
        $this->require_role('admin');

        $row = $this->investigation_model->get_detail($id);
        if (! $row) {
            $this->session->set_flashdata('error', 'Investigasi tidak ditemukan.');
            redirect('investigasi');
        }

        $this->investigation_model->soft_delete($id);
        $this->_audit('Arsip Lembar Investigasi', 'investigations', $id, $row['nomor'], 'is_deleted = 1');

        $this->session->set_flashdata('success', 'Lembar investigasi ' . $row['nomor'] . ' diarsipkan.');

        redirect('investigasi');
    }

    // ============================================================ HELPER

    private function _id_status_laporan($kode)
    {
        $r = $this->status_model->get_by_kode($kode, 'laporan');
        return $r ? (int) $r['id'] : NULL;
    }

    private function _id_status_inv($kode)
    {
        $r = $this->status_model->get_by_kode($kode, 'investigasi');
        return $r ? (int) $r['id'] : NULL;
    }

    private function _boleh_ubah($row)
    {
        if ($this->user['role'] !== 'admin') {
            return FALSE;
        }
        return (int) $row['status_urutan'] < 5; // belum Selesai
    }

    private function _boleh_pmkp($row)
    {
        if (in_array($this->user['role'], array('admin', 'kepala_ruangan'), TRUE)) {
            return (int) $row['status_urutan'] >= 2;
        }
        return FALSE;
    }

    /** Status laporan induk mengikuti tahap investigasi. */
    private function _sinkron_status_laporan($row, $status_inv_baru)
    {
        $report_id = (int) $row['incident_report_id'];
        $urutan = (int) $status_inv_baru['urutan'];

        if ($urutan >= 5) {
            // Investigasi ditutup -> kasus selesai, laporan ikut Selesai
            $tujuan = 'selesai';
        } elseif ($urutan >= 3) {
            // Menunggu Tindak Lanjut / Dalam Pelaksanaan -> ada koreksi yang harus dikejar
            $tujuan = 'tindak_lanjut';
        } else {
            return; // masih Draft / Dalam Investigasi, laporan tetap "Investigasi"
        }

        $id_tujuan = $this->_id_status_laporan($tujuan);
        if (! $id_tujuan) {
            return;
        }

        $laporan = $this->incident_report_model->get_detail($report_id);
        if (! $laporan OR (int) $laporan['status_id'] === $id_tujuan) {
            return;
        }

        $this->incident_report_model->update($report_id, array('status_id' => $id_tujuan));
        $this->incident_report_model->catat_history(
            $report_id, 'status_change',
            json_encode(array('status' => $laporan['status_nama'])),
            json_encode(array('status' => ucfirst(str_replace('_', ' ', $tujuan)))),
            (int) $this->user['id'], $this->input->ip_address()
        );
    }

    private function _validasi()
    {
        $this->load->library('form_validation');
        $this->form_validation->set_rules('tanggal_investigasi', 'Tanggal Investigasi', 'required|valid_date');
        $this->form_validation->set_rules('penyebab_langsung', 'Penyebab Langsung', 'required|trim|max_length[5000]');
        $this->form_validation->set_rules('akar_masalah', 'Akar Masalah', 'required|trim|max_length[5000]');
        $this->form_validation->set_rules('nama_penyetuju', 'Nama Penyetuju', 'trim|max_length[100]');
        $this->form_validation->set_rules('tanggal_mulai_investigasi', 'Tanggal Mulai', 'valid_date');
        $this->form_validation->set_rules('tanggal_selesai_investigasi', 'Tanggal Selesai', 'valid_date');
        $this->form_validation->set_rules('catatan_pmkp', 'Catatan PMKP', 'trim|max_length[2000]');

        if ($this->form_validation->run() === FALSE) {
            $this->session->set_flashdata('error', 'Lengkapi field yang ditandai wajib.');
            $back = $this->input->post('id_investigasi')
                ? 'investigasi/edit/' . (int) $this->input->post('id_investigasi')
                : 'investigasi';
            redirect($back);
        }
    }

    private function _siapkan_data($laporan = NULL, $row = NULL)
    {
        $data = array(
            'tanggal_investigasi' => $this->input->post('tanggal_investigasi'),
            'ruangan_id'          => (int) $this->input->post('ruangan_id')
                ?: ($row ? (int) $row['ruangan_id'] : NULL),
            'jenis_insiden_id'    => (int) $this->input->post('jenis_insiden_id')
                ?: ($row ? (int) $row['jenis_insiden_id'] : NULL),
            'risk_band_id'        => (int) $this->input->post('risk_band_id')
                ?: ($row ? (int) $row['risk_band_id'] : NULL),
            'penyebab_langsung'   => $this->input->post('penyebab_langsung', true),
            'akar_masalah'        => $this->input->post('akar_masalah', true),
            'nama_penyetuju'      => $this->input->post('nama_penyetuju', true) ?: NULL,
            'tanda_tangan'        => $row && ! empty($row['tanda_tangan']) ? $row['tanda_tangan'] : NULL,
            'tanggal_mulai_investigasi'  => $this->input->post('tanggal_mulai_investigasi') ?: NULL,
            'tanggal_selesai_investigasi' => $this->input->post('tanggal_selesai_investigasi') ?: NULL,
        );

        // Hanya saat pembuatan; saat update induk laporan tidak boleh berubah.
        if ($laporan && ! empty($laporan['id'])) {
            $data['incident_report_id'] = (int) $laporan['id'];
        }

        return $data;
    }

    /**
     * Simpan baris rekomendasi dan tindakan yang dikirim dari form.
     *
     * @param bool $ganti TRUE saat form edit: baris yang ada di DB dihapus
     *                    dulu karena form sudah merender seluruh baris lama.
     *                    Kalau tidak, setiap penyimpanan akan menambah duplikat.
     */
    private function _simpan_baris($investigation_id, $ganti = FALSE)
    {
        if ($ganti) {
            $this->investigation_model->hapus_semua_rekomendasi($investigation_id);
            $this->investigation_model->hapus_semua_tindakan($investigation_id);
        }

        foreach ((array) $this->input->post('rekomendasi_rows') as $r) {
            if (empty($r['rekomendasi'])) {
                continue;
            }
            $this->investigation_model->tambah_rekomendasi(
                $investigation_id,
                $r['rekomendasi'],
                isset($r['penanggung_jawab']) ? $r['penanggung_jawab'] : NULL,
                isset($r['tanggal_rekomendasi']) && $r['tanggal_rekomendasi'] ? $r['tanggal_rekomendasi'] : NULL
            );
        }

        foreach ((array) $this->input->post('tindakan_rows') as $t) {
            if (empty($t['tindakan'])) {
                continue;
            }
            $this->investigation_model->tambah_tindakan(
                $investigation_id,
                $t['tindakan'],
                isset($t['penanggung_jawab']) ? $t['penanggung_jawab'] : NULL,
                isset($t['tanggal_tindakan']) && $t['tanggal_tindakan'] ? $t['tanggal_tindakan'] : NULL,
                isset($t['status_id']) && $t['status_id'] ? (int) $t['status_id'] : NULL
            );
        }
    }
}
