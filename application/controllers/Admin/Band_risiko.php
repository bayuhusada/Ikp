<?php
defined('BASEPATH') OR exit('No direct script access allowed');




/**
 * Master Band Risiko. KHUSUS admin.
 *
 * Kolom `kriteria` adalah bagian penting. MODUL-LAPORAN bag. 1744 melarang
 * sistem menentukan sendiri kriteria grading, jadi kriteria ini harus diisi
 * manual dari kebijakan fasilitas kesehatan. Controller grading akan menandai
 * kalau kriteria masih kosong supaya tidak dipakai sebelum dilengkapi.
 */
class Band_risiko extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->require_role('admin');
        $this->load->model('risk_band_model');
    }

    public function index()
    {
        $rows = $this->risk_band_model->options();

        $kosong = 0;
        foreach ($rows as $r) {
            if (empty(trim((string) $r['kriteria']))) {
                $kosong++;
            }
        }

        $data = array(
            'title'        => 'Master Band Risiko',
            'rows'         => $rows,
            'belum_kriteria'=> $kosong,
        );

        $this->render('admin/band_risiko/index', $data);
    }

    public function form($id = NULL)
    {
        $row = NULL;
        if ($id) {
            $row = $this->risk_band_model->get($id);
            if (! $row) {
                $this->session->set_flashdata('error', 'Band risiko tidak ditemukan.');
                redirect('master/band-risiko');
            }
        }

        $data = array(
            'title'   => $id ? 'Ubah Band Risiko' : 'Tambah Band Risiko',
            'row'     => $row,
            'is_edit' => (bool) $id,
            'kode_list' => array('BIRU', 'HIJAU', 'KUNING', 'MERAH', 'LAIN'),
        );

        $this->render('admin/band_risiko/form', $data);
    }

    public function save($id = NULL)
    {
        if ($this->input->method() !== 'post') {
            $this->_deny();
        }

        $this->load->library('form_validation');
        $this->form_validation->set_rules('kode', 'Kode', 'required|trim|max_length[10]');
        $this->form_validation->set_rules('nama', 'Nama', 'required|trim|max_length[50]');
        $this->form_validation->set_rules('warna_hex', 'Warna', 'trim|regex_match[/^#[0-9a-fA-F]{6}$/]');
        $this->form_validation->set_rules('kriteria', 'Kriteria', 'required|trim|max_length[2000]');
        $this->form_validation->set_rules('urutan', 'Urutan', 'required|integer|min_length[1]');
        $this->form_validation->set_rules(
            'perlu_investigasi_sederhana',
            'Perlu Investigasi Sederhana',
            'required|in_list[0,1]'
        );

        if ($this->form_validation->run() === FALSE) {
            $this->session->set_flashdata('error', 'Periksa lagi data band risiko. Kriteria wajib diisi.');
            redirect($id ? 'master/band-risiko/form/' . $id : 'master/band-risiko/form');
        }

        $kode = strtoupper($this->input->post('kode', true));
        if ($this->risk_band_model->kode_exists($kode, $id)) {
            $this->session->set_flashdata('error', 'Kode "' . $kode . '" sudah dipakai.');
            redirect($id ? 'master/band-risiko/form/' . $id : 'master/band-risiko/form');
        }

        $data = array(
            'kode'      => $kode,
            'nama'      => $this->input->post('nama', true),
            'warna_hex' => $this->input->post('warna_hex', true) ?: NULL,
            'urutan'    => (int) $this->input->post('urutan'),
            'kriteria'  => $this->input->post('kriteria', true),
            'perlu_investigasi_sederhana' => (int) $this->input->post('perlu_investigasi_sederhana'),
            'is_active' => $this->input->post('is_active') ? 1 : 0,
        );

        if ($id) {
            $lama = $this->risk_band_model->get($id);
            $this->risk_band_model->update_row($id, $data);
            $this->_audit('Ubah Band Risiko', 'risk_bands', $id, json_encode($lama), json_encode($data));
            $this->session->set_flashdata('success', 'Band risiko "' . $data['kode'] . '" diperbarui.');
        } else {
            $newId = $this->risk_band_model->insert_row($data);
            $this->_audit('Tambah Band Risiko', 'risk_bands', $newId, null, json_encode($data));
            $this->session->set_flashdata('success', 'Band risiko "' . $data['kode'] . '" ditambahkan.');
        }

        redirect('master/band-risiko');
    }

    /**
     * Cek apakah seluruh band sudah punya kriteria.
     * Dipanggil controller lain sebelum mengizinkan grading.
     */
    public function semua_punya_kriteria()
    {
        $rows = $this->risk_band_model->options();
        foreach ($rows as $r) {
            if (empty(trim((string) $r['kriteria']))) {
                return FALSE;
            }
        }
        return count($rows) > 0;
    }
}
