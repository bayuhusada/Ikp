<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Master data Ruangan. KHUSUS admin.
 */
class Ruang extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->require_role('admin');
        $this->load->model('ruangan_model');
    }

    public function index()
    {
        $limit = (int) $this->input->get('limit');
        $limit = ($limit > 0 && $limit <= 100) ? $limit : 25;
        $page = max(1, (int) $this->input->get('page'));

        $search = (string) $this->input->get('search');

        $this->db->from('ruangan')->where('is_deleted', 0);
        if ($search !== '') {
            $this->db->group_start()
                ->like('nama', $search)
                ->or_like('kode', $search)
                ->group_end();
        }
        if ($this->input->get('jenis')) {
            $this->db->where('jenis', $this->input->get('jenis'));
        }
        $rows = $this->db->order_by('nama', 'ASC')
            ->limit($limit, ($page - 1) * $limit)
            ->get()
            ->result_array();

        $this->db->from('ruangan')->where('is_deleted', 0);
        if ($search !== '') {
            $this->db->group_start()
                ->like('nama', $search)
                ->or_like('kode', $search)
                ->group_end();
        }
        if ($this->input->get('jenis')) {
            $this->db->where('jenis', $this->input->get('jenis'));
        }
        $total = (int) $this->db->count_all_results();

        $data = array(
            'title'  => 'Master Ruangan',
            'rows'   => $rows,
            'total'  => $total,
            'page'   => $page,
            'limit'  => $limit,
            'pages'  => (int) ceil($total / max(1, $limit)),
            'search' => $search,
            'jenis'  => (string) $this->input->get('jenis'),
        );

        $this->render('admin/ruang/index', $data);
    }

    public function form($id = NULL)
    {
        $data = array(
            'title'  => $id ? 'Ubah Ruangan' : 'Tambah Ruangan',
            'row'    => NULL,
            'is_edit'=> (bool) $id,
        );

        if ($id) {
            $row = $this->ruangan_model->get($id, FALSE);
            if (! $row) {
                $this->session->set_flashdata('error', 'Ruangan tidak ditemukan.');
                redirect('master/ruangan');
            }
            $data['row'] = $row;
        }

        $this->render('admin/ruang/form', $data);
    }

    public function save($id = NULL)
    {
        if ($this->input->method() !== 'post') {
            $this->_deny();
        }

        $this->load->library('form_validation');
        $this->form_validation->set_rules('kode', 'Kode', 'required|trim|max_length[20]');
        $this->form_validation->set_rules('nama', 'Nama Ruangan', 'required|trim|max_length[100]');
        $this->form_validation->set_rules('jenis', 'Jenis', 'required|in_list[unit,departemen]');

        if ($this->form_validation->run() === FALSE) {
            $this->session->set_flashdata('error', 'Data ruangan belum lengkap.');
            redirect($id ? 'master/ruangan/form/' . $id : 'master/ruangan/form');
        }

        $kode  = strtoupper($this->input->post('kode', true));
        $nama  = $this->input->post('nama', true);
        $jenis = $this->input->post('jenis', true);

        // Kode harus unik
        $this->db->from('ruangan')->where('kode', $kode)->where('is_deleted', 0);
        if ($id) {
            $this->db->where('id !=', (int) $id);
        }
        if ($this->db->count_all_results() > 0) {
            $this->session->set_flashdata('error', 'Kode ruangan "' . $kode . '" sudah dipakai.');
            redirect($id ? 'master/ruangan/form/' . $id : 'master/ruangan/form');
        }

        $data = array('kode' => $kode, 'nama' => $nama, 'jenis' => $jenis);

        if ($id) {
            $lama = $this->ruangan_model->get($id, FALSE);
            $this->ruangan_model->update($id, $data);
            $this->_audit('Ubah Ruangan', 'ruangan', $id, json_encode($lama), json_encode($data));
            $this->session->set_flashdata('success', 'Ruangan "' . $nama . '" diperbarui.');
        } else {
            $newId = $this->ruangan_model->insert($data);
            $this->_audit('Tambah Ruangan', 'ruangan', $newId, null, json_encode($data));
            $this->session->set_flashdata('success', 'Ruangan "' . $nama . '" ditambahkan.');
        }

        redirect('master/ruangan');
    }

    public function delete($id)
    {
        if ($this->input->method() !== 'post') {
            $this->_deny();
        }

        $row = $this->ruangan_model->get($id, FALSE);
        if (! $row) {
            $this->session->set_flashdata('error', 'Ruangan tidak ditemukan.');
            redirect('master/ruangan');
        }

        // Jangan boleh dihapus bila sudah dipakai
        $dipakai = (int) $this->db->from('incident_reports')
            ->where('ruangan_id', (int) $id)
            ->where('is_deleted', 0)
            ->count_all_results();
        $dipakai += (int) $this->db->from('users')
            ->where('ruangan_id', (int) $id)
            ->where('is_deleted', 0)
            ->count_all_results();

        if ($dipakai > 0) {
            $this->session->set_flashdata('error',
                'Ruangan "' . $row['nama'] . '" masih dipakai pada ' . $dipakai
                . ' data, jadi tidak bisa dihapus. Nonaktifkan saja.');
            redirect('master/ruangan');
        }

        $this->ruangan_model->soft_delete($id);
        $this->_audit('Hapus Ruangan', 'ruangan', $id, json_encode($row), 'is_deleted = 1');
        $this->session->set_flashdata('success', 'Ruangan "' . $row['nama'] . '" dihapus.');

        redirect('master/ruangan');
    }

    public function toggle($id)
    {
        if ($this->input->method() !== 'post') {
            $this->_deny();
        }

        $row = $this->ruangan_model->get($id, FALSE);
        if (! $row) {
            $this->session->set_flashdata('error', 'Ruangan tidak ditemukan.');
            redirect('master/ruangan');
        }

        $baru = (int) $row['is_active'] === 1 ? 0 : 1;
        $this->ruangan_model->update($id, array('is_active' => $baru));
        $this->_audit('Aktifkan Ruangan', 'ruangan', $id,
            'is_active = ' . (int) $row['is_active'], 'is_active = ' . $baru);

        $this->session->set_flashdata('success',
            'Ruangan "' . $row['nama'] . '" ' . ($baru ? 'diaktifkan' : 'dinonaktifkan') . '.');

        redirect('master/ruangan');
    }
}
