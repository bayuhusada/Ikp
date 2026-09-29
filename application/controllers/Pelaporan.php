<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Modul Laporan Insiden.
 *
 * Alur status laporan (lihat statuses, untuk = laporan):
 *   1 draft  2 dilaporkan  3 menunggu_grading  4 sudah_grading
 *   5 investigasi  6 tindak_lanjut  7 selesai
 *
 * Filter role diterapkan di model, bukan di sini:
 *   pelapor        -> hanya created_by = user himself
 *   kepala_ruangan -> hanya ruangan_id = ruangannya
 *   admin          -> semua
 */
class Pelaporan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->require_role(array('admin', 'kepala_ruangan', 'pelapor'));
        $this->load->model(array(
            'incident_report_model', 'patient_model', 'ruangan_model',
            'incident_type_model', 'impact_model', 'specialization_model',
            'age_group_model', 'responsible_model', 'first_reporter_model',
            'care_type_model', 'action_team_model', 'risk_band_model',
            'status_model',
        ));
    }

    // ===================================================== DAFTAR LAPORAN

    public function index()
    {
        $data = $this->_filter_dari_query();
        $limit = (int) $this->input->get('limit');
        $limit = ($limit > 0 && $limit <= 100) ? $limit : 25;
        $page = max(1, (int) $this->input->get('page'));

        $result = $this->incident_report_model->paginate_reports(
            $data['filter'],
            $limit,
            ($page - 1) * $limit,
            (string) $this->input->get('sort') ?: 'created_at',
            (string) $this->input->get('dir')
        );

        $data['title']  = $this->user['role'] === 'pelapor' ? 'Laporan Saya' : 'Semua Laporan';
        $data['rows']   = $result['rows'];
        $data['total']  = $result['total'];
        $data['page']   = $page;
        $data['limit']  = $limit;
        $data['pages']  = (int) ceil($result['total'] / max(1, $limit));

        $this->_isi_pilihan($data);

        $this->render('pelaporan/index', $data);
    }

    public function archived()
    {
        $this->require_role('admin');

        $limit = 25;
        $page = max(1, (int) $this->input->get('page'));
        $search = (string) $this->input->get('search');

        $this->db->select('ir.*, r.nama AS ruang_nama, it.nama AS jenis_nama, st.nama AS status_nama, u.nama AS dibuat_oleh')
            ->from('incident_reports ir')
            ->join('ruangan r', 'r.id = ir.ruangan_id')
            ->join('incident_types it', 'it.id = ir.jenis_insiden_id')
            ->join('statuses st', 'st.id = ir.status_id')
            ->join('users u', 'u.id = ir.deleted_by', 'left')
            ->where('ir.is_deleted', 1);

        if ($search !== '') {
            $this->db->group_start()
                ->like('ir.nomor', $search)
                ->or_like('ir.kronologi_insiden', $search)
                ->group_end();
        }

        $rows = $this->db->order_by('ir.deleted_at', 'DESC')
            ->limit($limit, ($page - 1) * $limit)
            ->get()
            ->result_array();

        $this->db->from('incident_reports ir')->where('ir.is_deleted', 1);
        if ($search !== '') {
            $this->db->group_start()
                ->like('ir.nomor', $search)
                ->or_like('ir.kronologi_insiden', $search)
                ->group_end();
        }
        $total = (int) $this->db->count_all_results();

        $data = array(
            'title'  => 'Arsip Laporan',
            'rows'   => $rows,
            'total'  => $total,
            'page'   => $page,
            'limit'  => $limit,
            'pages'  => (int) ceil($total / max(1, $limit)),
            'search' => $search,
        );

        $this->render('pelaporan/archived', $data);
    }

    // ============================================================== FORM

    public function create()
    {
        $data = array(
            'title'    => 'Input Laporan Insiden',
            'row'      => NULL,
            'is_edit'  => FALSE,
            'halaman'  => 'create',
            'tindakan' => array(),
        );
        $this->_isi_pilihan($data);

        $this->render('pelaporan/form', $data);
    }

    public function edit($id)
    {
        $row = $this->incident_report_model->get_detail($id);

        if (! $row) {
            $this->session->set_flashdata('error', 'Laporan tidak ditemukan atau tidak punya hak akses.');
            redirect('laporan');
        }

        // Draft milik sendiri, atau admin
        $this->_cek_boleh_ubah($row, $id);

        $data = array(
            'title'   => 'Ubah Laporan ' . $row['nomor'],
            'row'     => $row,
            'is_edit' => TRUE,
            'halaman' => 'edit',
            'tindakan'=> $this->incident_report_model->get_tindakan($id),
        );
        $this->_isi_pilihan($data);

        $this->render('pelaporan/form', $data);
    }

    // ============================================================== SIMPAN

    public function store()
    {
        if ($this->input->method() !== 'post') {
            $this->_deny();
        }

        $this->_validasi();

        $this->db->trans_begin();

        try {
            $pasien = $this->_simpan_pasien();
            if ($pasien === FALSE) {
                $this->db->trans_rollback();
                $this->session->set_flashdata('error', 'Data pasien gagal disimpan.');
                redirect('laporan/input');
            }

            $laporan = $this->_siapkan_data_laporan($pasien);

            $nomor = $this->incident_report_model->nomor_baru('INS');
            if ($nomor === FALSE) {
                $this->db->trans_rollback();
                $this->session->set_flashdata('error', 'Gagal membuat nomor laporan. Coba lagi.');
                redirect('laporan/input');
            }
            $laporan['nomor'] = $nomor;
            $laporan['status_id'] = $this->_id_status('draft');

            $id = $this->incident_report_model->insert($laporan);

            if (! $id) {
                $this->db->trans_rollback();
                $this->session->set_flashdata('error', 'Laporan gagal disimpan.');
                redirect('laporan/input');
            }

            $this->incident_report_model->catat_history(
                $id, 'create', NULL, $nomor, $this->user['id'], $this->input->ip_address()
            );

            $this->_simpan_tambahan($id);

            $this->db->trans_commit();
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Gagal menyimpan laporan: ' . $e->getMessage());
            $this->session->set_flashdata('error', 'Terjadi kesalahan saat menyimpan laporan.');
            redirect('laporan/input');
        }

        $this->_audit('Buat Laporan Insiden', 'incident_reports', $id, NULL, $nomor);
        $this->session->set_flashdata('success', 'Laporan ' . $nomor . ' tersimpan sebagai Draft. Kirim saat sudah lengkap.');

        redirect('laporan/detail/' . $id);
    }

    public function update($id)
    {
        if ($this->input->method() !== 'post') {
            $this->_deny();
        }

        $row = $this->incident_report_model->get_detail($id);
        if (! $row) {
            $this->session->set_flashdata('error', 'Laporan tidak ditemukan.');
            redirect('laporan');
        }
        $this->_cek_boleh_ubah($row, $id);

        $this->_validasi();

        $this->db->trans_begin();

        try {
            $pasien = $this->_simpan_pasien();
            if ($pasien === FALSE) {
                $this->db->trans_rollback();
                $this->session->set_flashdata('error', 'Data pasien gagal disimpan.');
                redirect('laporan/edit/' . $id);
            }

            $laporan = $this->_siapkan_data_laporan($pasien);

            $this->incident_report_model->update($id, $laporan);

            $this->incident_report_model->catat_history(
                $id, 'update',
                json_encode(array('status' => $row['status_nama'])),
                json_encode(array('status' => $row['status_nama'])),
                $this->user['id'],
                $this->input->ip_address()
            );

            $this->_simpan_tambahan($id);

            $this->db->trans_commit();
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Gagal mengubah laporan: ' . $e->getMessage());
            $this->session->set_flashdata('error', 'Terjadi kesalahan saat menyimpan perubahan.');
            redirect('laporan/edit/' . $id);
        }

        $this->_audit('Ubah Laporan Insiden', 'incident_reports', $id, $row['nomor'], $row['nomor']);
        $this->session->set_flashdata('success', 'Perubahan pada laporan ' . $row['nomor'] . ' tersimpan.');

        redirect('laporan/detail/' . $id);
    }

    // ============================================================== DETAIL

    public function detail($id)
    {
        $row = $this->incident_report_model->get_detail($id);
        if (! $row) {
            $this->session->set_flashdata('error', 'Laporan tidak ditemukan atau tidak punya hak akses.');
            redirect('laporan');
        }

        $data = array(
            'title'     => 'Laporan ' . $row['nomor'],
            'row'       => $row,
            'tindakan'  => $this->incident_report_model->get_tindakan($id),
            'riwayat'   => $this->incident_report_model->get_history($id),
            'boleh_ubah' => $this->_boleh_ubah($row),
            'boleh_grading' => $this->_boleh_grading($row),
            'boleh_kirim' => $this->_boleh_kirim($row),
            'boleh_hapus' => $this->_boleh_hapus($row),
            'investigasi' => $this->_investigasi_terkait($id),
        );

        $this->_isi_pilihan($data);

        $this->render('pelaporan/detail', $data);
    }

    // =============================================================== AKSI

    /** Kirim draft agar masuk antrean grading. */
    public function submit($id)
    {
        if ($this->input->method() !== 'post') {
            $this->_deny();
        }

        $row = $this->incident_report_model->get_detail($id);
        if (! $row) {
            $this->session->set_flashdata('error', 'Laporan tidak ditemukan.');
            redirect('laporan');
        }
        if (! $this->_boleh_kirim($row)) {
            $this->_deny();
        }

        // Pastikan field wajib benar-benar terisi sebelum dikirim
        $kurang = array();
        foreach (array('nama_pasien', 'nomor_mr', 'tanggal_insiden', 'jam_insiden',
                       'kronologi_insiden') as $f) {
            if (empty($row[$f])) {
                $kurang[] = $f;
            }
        }
        if (empty($row['jenis_insiden_id']) OR empty($row['akibat_insiden_id'])) {
            $kurang[] = 'jenis insiden / akibat insiden';
        }
        if (! empty($kurang)) {
            $this->session->set_flashdata('error',
                'Laporan belum lengkap: ' . implode(', ', $kurang) . '. Lengkapi dulu.');
            redirect('laporan/edit/' . $id);
        }

        $dari = $row['status_id'];
        $ke   = $this->_id_status('menunggu_grading');

        $this->incident_report_model->update($id, array('status_id' => $ke));
        $this->incident_report_model->catat_history(
            $id, 'submit',
            json_encode(array('status' => $row['status_nama'])),
            json_encode(array('status' => 'Menunggu Grading')),
            $this->user['id'], $this->input->ip_address()
        );

        $this->_audit('Kirim Laporan', 'incident_reports', $id, $row['nomor'],
            'status: ' . $row['status_nama'] . ' -> Menunggu Grading');

        $this->session->set_flashdata('success',
            'Laporan ' . $row['nomor'] . ' terkirim. Menunggu grading dari Kepala Ruangan.');

        redirect('laporan/detail/' . $id);
    }

    /** Arsipkan (soft delete). */
    public function delete($id)
    {
        if ($this->input->method() !== 'post') {
            $this->_deny();
        }
        $this->require_role('admin');

        $row = $this->incident_report_model->get_detail($id);
        if (! $row) {
            $this->session->set_flashdata('error', 'Laporan tidak ditemukan.');
            redirect('laporan');
        }

        $this->incident_report_model->soft_delete($id);
        $this->_audit('Arsip Laporan Insiden', 'incident_reports', $id, $row['nomor'], 'is_deleted = 1');

        $this->session->set_flashdata('success', 'Laporan ' . $row['nomor'] . ' diarsipkan.');

        redirect('laporan');
    }

    // ============================================================ GRADING

    public function grading($id)
    {
        if ($this->input->method() !== 'post') {
            $this->_deny();
        }
        $this->require_role(array('admin', 'kepala_ruangan'));

        $row = $this->incident_report_model->get_detail($id);
        if (! $row) {
            $this->session->set_flashdata('error', 'Laporan tidak ditemukan.');
            redirect('laporan');
        }
        if (! $this->_boleh_grading($row)) {
            $this->_deny();
        }

        $this->load->library('form_validation');
        $this->form_validation->set_rules('risk_band_id', 'Band Risiko', 'required|numeric');
        $this->form_validation->set_rules('catatan_grading', 'Catatan', 'trim|max_length[1000]');

        if ($this->form_validation->run() === FALSE) {
            $this->session->set_flashdata('error', 'Pilih band risiko.');
            redirect('laporan/detail/' . $id);
        }

        $band = $this->risk_band_model->get((int) $this->input->post('risk_band_id'));
        if (! $band OR (int) $band['is_active'] !== 1) {
            $this->session->set_flashdata('error', 'Band risiko tidak valid atau sedang nonaktif.');
            redirect('laporan/detail/' . $id);
        }

        // Kriteria wajib terisi sebelum grading boleh dipakai
        if (empty(trim((string) $band['kriteria']))) {
            $this->session->set_flashdata('error',
                'Kriteria untuk band ' . $band['kode'] . ' belum diisi. Isi dulu di Master Band Risiko.');
            redirect('laporan/detail/' . $id);
        }

        $update = array(
            'risk_band_id' => (int) $band['id'],
            'graded_by'    => (int) $this->user['id'],
            'graded_at'    => date('Y-m-d H:i:s'),
            'status_id'    => $this->_id_status('sudah_grading'),
        );

        $this->incident_report_model->update($id, $update);

        $catatan = (string) $this->input->post('catatan_grading', true);
        $sebelum = json_encode(array('band' => $row['band_kode'] ?: '-', 'status' => $row['status_nama']));
        $sesudah = json_encode(array('band' => $band['kode'], 'status' => 'Sudah Grading',
                                     'catatan' => $catatan !== '' ? $catatan : null));

        $this->incident_report_model->catat_history(
            $id, 'grading', $sebelum, $sesudah, $this->user['id'], $this->input->ip_address()
        );
        $this->_audit_diff('Grading Risiko', 'incident_reports', $id, array(
            'band'   => array('sebelum' => $row['band_kode'] ?: '-', 'sesudah' => $band['kode']),
            'status' => array('sebelum' => $row['status_nama'], 'sesudah' => 'Sudah Grading'),
        ));

        if ((int) $band['perlu_investigasi_sederhana'] === 1) {
            $this->session->set_flashdata('success',
                'Grading ' . $band['kode'] . ' tercatat. Laporan ini perlu Lembar Kerja Investigasi Sederhana.');
        } else {
            $this->session->set_flashdata('success',
                'Grading ' . $band['kode'] . ' tercatat. Proses dilanjutkan mengikuti SOP di luar sistem.');
        }

        redirect('laporan/detail/' . $id);
    }

    // ============================================================ HELPER

    private function _filter_dari_query()
    {
        return array(
            'filter' => array(
                'search'              => (string) $this->input->get('search'),
                'date_from'           => (string) $this->input->get('date_from'),
                'date_to'             => (string) $this->input->get('date_to'),
                'ruangan_id'          => (int) $this->input->get('ruangan_id'),
                'jenis_insiden_id'    => (int) $this->input->get('jenis_insiden_id'),
                'risk_band_id'        => (int) $this->input->get('risk_band_id'),
                'status_id'           => (int) $this->input->get('status_id'),
            ),
        );
    }

    private function _isi_pilihan(array &$data)
    {
        $data['ruangan_list']     = $this->ruangan_model->options_for_user($this->user);
        $data['jenis_list']       = $this->incident_type_model->options();
        $data['akibat_list']      = $this->impact_model->options();
        $data['spesialisasi_list']= $this->specialization_model->options();
        $data['umur_list']        = $this->age_group_model->options();
        $data['penanggung_list']  = $this->responsible_model->options();
        $data['pelapor_list']     = $this->first_reporter_model->options();
        $data['perawatan_list']   = $this->care_type_model->options();
        $data['tim_list']         = $this->action_team_model->options();
        $data['band_list']        = $this->risk_band_model->options();
        $data['status_list']      = $this->status_model->options_by_jenis('laporan');
    }

    private function _id_status($kode, $untuk = 'laporan')
    {
        $row = $this->status_model->get_by_kode($kode, $untuk);
        if (! $row) {
            log_message('error', 'Status tidak ditemukan: kode=' . $kode . ' untuk=' . $untuk);
            return NULL;
        }
        return (int) $row['id'];
    }

    /**
     * Penjaga untuk edit & update laporan.
     *
     * Dua kondisi dibedakan supaya pesannya tidak membingungkan:
     * - Laporan `Selesai` : user BOLEH melihat, hanya tidak boleh mengubah.
     *   Jadi redirect ke detail dengan penjelasan, bukan 403.
     * - Selain itu        : masalah izin yang sungguhan, balas 403.
     */
    private function _cek_boleh_ubah($row, $id)
    {
        if ($this->_boleh_ubah($row)) {
            return;
        }

        if ((int) $row['status_id'] === $this->_id_status('selesai')) {
            $this->session->set_flashdata('error',
                'Laporan ' . $row['nomor'] . ' sudah berstatus Selesai sehingga isinya dikunci. '
                . 'Kasus sudah ditutup, jadi laporan tidak bisa diubah. '
                . 'Bila ada kekeliruan, buat laporan baru.');
            redirect('laporan/detail/' . (int) $id);
        }

        $this->_deny();
    }

    /**
     * Siapa yang boleh mengubah isi laporan.
     *
     * Aturan:
     * - status `Draft`   : boleh, tapi hanya pembuatnya (atau admin).
     * - sudah dikirim     : hanya admin.
     * - status `Selesai` : DIBEKUkan untuk semua orang, termasuk admin.
     *
     * keputusan user 27 Sep 2026: laporan yang sudah `Selesai` tidak boleh
     * diubah lagi. Kasus sudah ditutup, jadi dokumennya diperlakukan sebagai
     * catatan final. Admin yang perlu memperbaiki isi laporan `Selesai` harus
     * membuat laporan baru.
     *
     * Semua perubahan tetap dicatat di incident_report_history, jadi koreksi
     * masih bisa ditelusuri selama laporan tidak berstatus `Selesai`.
     */
    private function _boleh_ubah($row)
    {
        // Beku: tidak ada yang boleh mengubah, admin sekalipun.
        if ((int) $row['status_id'] === $this->_id_status('selesai')) {
            return FALSE;
        }

        if ($this->user['role'] === 'admin') {
            return TRUE;
        }

        if ((int) $row['status_id'] !== $this->_id_status('draft')) {
            return FALSE;
        }

        if ($this->user['role'] === 'pelapor') {
            return (int) $row['created_by'] === (int) $this->user['id'];
        }

        return FALSE;
    }

    /**
     * Siapa yang boleh menekan "Kirim Laporan".
     *
     * - admin     : laporan Draft mana pun.
     * - pelapor   : hanya laporan MILIKNYA sendiri, selama masih Draft.
     * - kepala_ruangan: sama seperti pelapor, hanya laporan yang dia buat
     *   sendiri. Sebelumnya role ini tidak bisa mengirim sama sekali, padahal
     *   form Input Laporan terbuka untuknya. Akibatnya laporan yang ia buat
     *   tersangkut di Draft selamanya dan hanya admin yang bisa mengirimnya.
     *   Keputusan user 27 Sep 2026: boleh mengirim laporan sendiri.
     *
     * Catatan: kepala_ruangan TIDAK boleh mengirim Draft milik pelapor di
     * ruangannya. Tidak ada perubahan hak akses terhadap data orang lain.
     */
    private function _boleh_kirim($row)
    {
        if ($this->user['role'] === 'admin') {
            return (int) $row['status_id'] === $this->_id_status('draft');
        }

        if ($this->user['role'] === 'pelapor' OR $this->user['role'] === 'kepala_ruangan') {
            return (int) $row['status_id'] === $this->_id_status('draft')
                && (int) $row['created_by'] === (int) $this->user['id'];
        }

        return FALSE;
    }

    private function _boleh_grading($row)
    {
        if ((int) $row['status_id'] !== $this->_id_status('menunggu_grading')) {
            return FALSE;
        }
        if ($this->user['role'] === 'admin') {
            return TRUE;
        }
        return $this->user['role'] === 'kepala_ruangan'
            && (int) $row['ruangan_id'] === (int) $this->user['ruangan_id'];
    }

    private function _boleh_hapus($row)
    {
        return $this->user['role'] === 'admin';
    }

    private function _investigasi_terkait($report_id)
    {
        $row = $this->db->select('i.id, i.nomor, i.status_id, s.nama AS status_nama, rb.kode AS band_kode')
            ->from('investigations i')
            ->join('statuses s', 's.id = i.status_id')
            ->join('risk_bands rb', 'rb.id = i.risk_band_id')
            ->where('i.incident_report_id', (int) $report_id)
            ->where('i.is_deleted', 0)
            ->get()
            ->row_array();

        return $row ? $row : NULL;
    }

    // ---- Validasi ---------------------------------------------------

    private function _validasi()
    {
        $this->load->library('form_validation');

        // Data pasien
        $this->form_validation->set_rules('nama_pasien', 'Nama Pasien', 'required|trim|max_length[150]');
        $this->form_validation->set_rules('nomor_mr', 'Nomor MR', 'required|trim|max_length[50]');
        $this->form_validation->set_rules('ruangan_id', 'Ruangan', 'required|is_natural_no');

        // Rincian kejadian
        $this->form_validation->set_rules('tanggal_insiden', 'Tanggal Insiden', 'required|valid_date');
        $this->form_validation->set_rules('jam_insiden', 'Jam Insiden', 'required|valid_time');
        $this->form_validation->set_rules('kronologi_insiden', 'Kronologi', 'required|trim|max_length[5000]');

        // Klasifikasi
        $this->form_validation->set_rules('jenis_insiden_id', 'Jenis Insiden', 'required|is_natural_no');
        $this->form_validation->set_rules('akibat_insiden_id', 'Akibat Insiden', 'required|is_natural_no');

        // Sisanya opsional
        $this->form_validation->set_rules('insiden', 'Ringkasan Insiden', 'trim|max_length[2000]');
        $this->form_validation->set_rules('kelompok_umur_id', 'Kelompok Umur', 'is_natural_no');
        $this->form_validation->set_rules('jenis_kelamin', 'Jenis Kelamin', 'in_list[L,P]');
        $this->form_validation->set_rules('penanggung_jawab_id', 'Penanggung Jawab', 'is_natural_no');
        $this->form_validation->set_rules('tempat_insiden', 'Tempat Insiden', 'trim|max_length[200]');
        $this->form_validation->set_rules('spesialisasi_id', 'Spesialisasi', 'is_natural_no');
        $this->form_validation->set_rules('unit_terkait_id', 'Unit Terkait', 'is_natural_no');
        $this->form_validation->set_rules('tindakan_segera', 'Tindakan Segera', 'trim|max_length[5000]');
        $this->form_validation->set_rules('hasil_tindakan', 'Hasil Tindakan', 'trim|max_length[5000]');
        $this->form_validation->set_rules('nama_pembuat', 'Nama Pembuat', 'trim|max_length[100]');
        $this->form_validation->set_rules('paraf_pembuat', 'Paraf Pembuat', 'trim|max_length[100]');
        $this->form_validation->set_rules('nama_penerima', 'Nama Penerima', 'trim|max_length[100]');
        $this->form_validation->set_rules('paraf_penerima', 'Paraf Penerima', 'trim|max_length[100]');

        if ($this->form_validation->run() === FALSE) {
            $this->session->set_flashdata('error', $this->_pesan_validasi());
            $back = ($this->input->post('id_laporan') > 0)
                ? 'laporan/edit/' . (int) $this->input->post('id_laporan')
                : 'laporan/input';
            redirect($back);
        }

        // Tanggal insiden tidak boleh di masa depan
        $tgl = $this->input->post('tanggal_insiden');
        if ($tgl && strtotime($tgl) > strtotime(date('Y-m-d'))) {
            $this->session->set_flashdata('error', 'Tanggal insiden tidak boleh lebih dari hari ini.');
            redirect('laporan/input');
        }
    }

    /**
     * Susun pesan validasi yang menyebut field mana yang bermasalah.
     * Tanpa ini user hanya melihat "lengkapi field wajib" tanpa petunjuk.
     */
    private function _pesan_validasi()
    {
        $err = $this->form_validation->error_array();
        if (empty($err)) {
            return 'Lengkapi field yang ditandai wajib.';
        }

        $pesan = array();
        foreach ($err as $field => $m) {
            $label = $this->form_validation->set_value($field);
            $pesan[] = $m;
        }

        return implode(' ', array_slice(array_unique($pesan), 0, 4));
    }

    // ---- Penyimpanan data ------------------------------------------

    private function _simpan_pasien()
    {
        return $this->patient_model->find_or_create(array(
            'nama_pasien'          => $this->input->post('nama_pasien', true),
            'nomor_mr'             => $this->input->post('nomor_mr', true),
            'ruangan_id'           => (int) $this->input->post('ruangan_id'),
            'kelompok_umur_id'     => $this->_int_or_null('kelompok_umur_id'),
            'jenis_kelamin'        => $this->input->post('jenis_kelamin') ?: NULL,
            'penanggung_jawab_id'  => $this->_int_or_null('penanggung_jawab_id'),
            'tanggal_masuk'        => $this->input->post('tanggal_masuk') ?: NULL,
            'jam_masuk'            => $this->input->post('jam_masuk') ?: NULL,
        ));
    }

    private function _siapkan_data_laporan($pasien_id)
    {
        $pernah = $this->input->post('pernah_terjadi_di_unit_lain') ? 1 : 0;

        return array(
            'patient_id'         => (int) $pasien_id,
            'ruangan_id'         => (int) $this->input->post('ruangan_id'),
            'tanggal_insiden'    => $this->input->post('tanggal_insiden'),
            'jam_insiden'        => $this->input->post('jam_insiden'),
            'insiden'            => $this->input->post('insiden', true) ?: NULL,
            'kronologi_insiden'  => $this->input->post('kronologi_insiden', true),
            'jenis_insiden_id'   => (int) $this->input->post('jenis_insiden_id'),
            'akibat_insiden_id'  => (int) $this->input->post('akibat_insiden_id'),
            'first_reporter_id'  => $this->_int_or_null('first_reporter_id'),
            'first_reporter_lainnya' => $this->_lainnya('first_reporter_id'),
            'insiden_pada'       => $this->input->post('insiden_pada') ?: NULL,
            'insiden_pada_lainnya' => $this->_lainnya_kolom('insiden_pada', 'LAIN'),
            'care_type_id'       => $this->_int_or_null('care_type_id'),
            'care_type_lainnya'  => $this->_lainnya('care_type_id'),
            'tempat_insiden'     => $this->input->post('tempat_insiden', true) ?: NULL,
            'specialization_id'  => $this->_int_or_null('specialization_id'),
            'unit_terkait_id'    => $this->_int_or_null('unit_terkait_id'),
            'tindakan_segera'    => $this->input->post('tindakan_segera', true) ?: NULL,
            'hasil_tindakan'     => $this->input->post('hasil_tindakan', true) ?: NULL,
            'action_team_id'     => $this->_int_or_null('action_team_id'),
            'action_team_lainnya'=> $this->_lainnya('action_team_id'),
            'pernah_terjadi_di_unit_lain' => $pernah,
            'tindakan_pencegahan_sebelumnya' => $pernah
                ? $this->input->post('tindakan_pencegahan_sebelumnya', true) : NULL,
            'nama_pembuat'       => $this->input->post('nama_pembuat', true) ?: NULL,
            'paraf_pembuat'      => $this->input->post('paraf_pembuat', true) ?: NULL,
            'nama_penerima'      => $this->input->post('nama_penerima', true) ?: NULL,
            'paraf_penerima'     => $this->input->post('paraf_penerima', true) ?: NULL,
            'tanggal_terima'     => $this->input->post('tanggal_terima') ?: NULL,
            'tanggal_lapor'      => $this->input->post('tanggal_lapor') ?: date('Y-m-d'),
        );
    }

    private function _simpan_tambahan($laporan_id)
    {
        // Baris tindakan setelah kejadian
        $tindo = (array) $this->input->post('tindakan_rows');
        foreach ($tindo as $i => $t) {
            if (empty($t['tindakan'])) {
                continue;
            }
            $this->incident_report_model->tambah_tindakan($laporan_id, array(
                'tindakan'       => $t['tindakan'],
                'hasil'          => isset($t['hasil']) ? $t['hasil'] : NULL,
                'action_team_id' => isset($t['action_team_id']) && $t['action_team_id'] ? (int) $t['action_team_id'] : NULL,
                'petugas'        => isset($t['petugas']) ? $t['petugas'] : NULL,
                'waktu'          => isset($t['waktu']) && $t['waktu'] ? $t['waktu'] : NULL,
            ));
        }
    }

    private function _int_or_null($field)
    {
        $v = $this->input->post($field);
        return ($v === NULL OR $v === '') ? NULL : (int) $v;
    }

    /** Field _lainnya hanya dipakai bila induknya bernilai 99 (Lain-lain). */
    private function _lainnya($field)
    {
        $v = $this->input->post($field);
        if ($v === NULL OR $v === '' OR (int) $v !== 99) {
            return NULL;
        }
        return $this->input->post($field . '_lainnya', true) ?: NULL;
    }

    private function _lainnya_kolom($field, $nilai_lain)
    {
        if ($this->input->post($field) === $nilai_lain) {
            return $this->input->post($field . '_lainnya', true) ?: NULL;
        }
        return NULL;
    }
}
