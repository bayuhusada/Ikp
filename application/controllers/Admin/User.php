<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Master data User. KHUSUS admin.
 *
 * Tiga role: admin, kepala_ruangan, pelapor.
 * kepala_ruangan dan pelapor WAJIB punya ruangan_id karena filter data
 * keduanya bergantung pada itu.
 */
class User extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->require_role('admin');
        $this->load->model(array('user_model', 'ruangan_model'));
    }

    public function index()
    {
        $limit = (int) $this->input->get('limit');
        $limit = ($limit > 0 && $limit <= 100) ? $limit : 25;
        $page = max(1, (int) $this->input->get('page'));

        $result = $this->user_model->paginate_users(array(
            'search'     => (string) $this->input->get('search'),
            'role'       => (string) $this->input->get('role'),
            'ruangan_id' => (int) $this->input->get('ruangan_id'),
            'limit'      => $limit,
            'offset'     => ($page - 1) * $limit,
        ));

        $data = array(
            'title'   => 'Master User',
            'rows'    => $result['rows'],
            'total'   => $result['total'],
            'page'    => $page,
            'limit'   => $limit,
            'pages'   => (int) ceil($result['total'] / max(1, $limit)),
            'search'  => (string) $this->input->get('search'),
            'role'    => (string) $this->input->get('role'),
            'ruangan' => (int) $this->input->get('ruangan_id'),
            'ruangan_list' => $this->ruangan_model->options_for_user($this->user),
            'total_admin'  => (int) $this->db->from('users')
                ->where('role', 'admin')->where('is_active', 1)
                ->where('is_deleted', 0)->count_all_results(),
        );

        $this->render('admin/user/index', $data);
    }

    public function form($id = NULL)
    {
        $row = NULL;
        if ($id) {
            $row = $this->user_model->get_by_id($id);
            if (! $row) {
                $this->session->set_flashdata('error', 'User tidak ditemukan.');
                redirect('master/user');
            }
        }

        $data = array(
            'title'   => $id ? 'Ubah User' : 'Tambah User',
            'row'     => $row,
            'is_edit' => (bool) $id,
            'ruangan_list' => $this->ruangan_model->options_for_user($this->user),
        );

        $this->render('admin/user/form', $data);
    }

    public function save($id = NULL)
    {
        if ($this->input->method() !== 'post') {
            $this->_deny();
        }

        $this->load->library('form_validation');

        $this->form_validation->set_rules('nama', 'Nama', 'required|trim|max_length[100]');
        $this->form_validation->set_rules('username', 'Username', 'required|trim|min_length[3]|max_length[50]');
        $this->form_validation->set_rules('role', 'Role', 'required|in_list[admin,kepala_ruangan,pelapor]');
        $this->form_validation->set_rules('ruangan_id', 'Ruangan', 'required|numeric');
        $this->form_validation->set_rules('nip', 'NIP', 'trim|max_length[50]');

        if (! $id) {
            $this->form_validation->set_rules('password', 'Password', 'required|min_length[8]|callback_password_check');
        } else {
            // Saat ubah, password opsional. Bila diisi harus tetap lolos aturan.
            $this->form_validation->set_rules('password', 'Password', 'min_length[8]|callback_password_check');
        }

        if ($this->form_validation->run() === FALSE) {
            $this->session->set_flashdata('error', 'Data user belum benar.');
            redirect($id ? 'master/user/form/' . $id : 'master/user/form');
        }

        $nama     = $this->input->post('nama', true);
        $username = strtolower($this->input->post('username', true));
        $role     = $this->input->post('role', true);
        $ruangan  = (int) $this->input->post('ruangan_id');
        $nip      = $this->input->post('nip', true) ?: NULL;
        $password = $this->input->post('password');

        // Username unik
        if ($this->user_model->username_exists($username, $id)) {
            $this->session->set_flashdata('error', 'Username "' . $username . '" sudah dipakai.');
            redirect($id ? 'master/user/form/' . $id : 'master/user/form');
        }

        // Ruangan wajib ada
        $ruang = $this->ruangan_model->get($ruangan, FALSE);
        if (! $ruang) {
            $this->session->set_flashdata('error', 'Ruangan tidak ditemukan. Buat ruangan dulu di Master Ruangan.');
            redirect($id ? 'master/user/form/' . $id : 'master/user/form');
        }

        // Jangan sampai admin aktif terakhir kehilangan akses
        $ubahRole = ($role !== 'admin' OR ! empty($password));
        if ($id && ! $ubahRole) {
            $lama = $this->user_model->get_by_id($id);
            $demote = (isset($lama['role']) && $lama['role'] === 'admin' && $role !== 'admin');
            $nonAktifkan = (isset($lama['is_active']) && (int) $lama['is_active'] === 1 && ! (bool) $this->input->post('is_active'));
            if ($demote OR $nonAktifkan) {
                $sisa = (int) $this->db->from('users')
                    ->where('role', 'admin')->where('is_active', 1)
                    ->where('is_deleted', 0)
                    ->where('id !=', (int) $id)
                    ->count_all_results();
                if ($sisa === 0) {
                    $this->session->set_flashdata('error',
                        'Ini admin aktif terakhir. Minimal harus ada satu admin aktif.');
                    redirect('master/user/form/' . $id);
                }
            }
        }

        $data = array(
            'nama'       => $nama,
            'username'   => $username,
            'role'       => $role,
            'ruangan_id' => $ruangan,
            'nip'        => $nip,
        );
        if ($this->input->post('is_active') !== NULL OR $id) {
            $data['is_active'] = $this->input->post('is_active') ? 1 : 0;
        } else {
            $data['is_active'] = 1;
        }
        if ($password !== NULL && $password !== '') {
            $data['password'] = $password;
        }

        if ($id) {
            $lama = $this->user_model->get_by_id($id);
            $sembunyi = $lama;
            unset($sembunyi['password']);
            $this->user_model->update_user($id, $data);
            $baru = $this->user_model->get_by_id($id);
            unset($baru['password']);
            $ket = 'Ubah User';
            if (isset($data['password'])) {
                $ket = 'Ubah User (termasuk reset password)';
            }
            $this->_audit($ket, 'users', $id, json_encode($sembunyi), json_encode($baru));
            $this->session->set_flashdata('success', 'User "' . $username . '" diperbarui.');
        } else {
            $newId = $this->user_model->insert_user($data);
            $this->_audit('Tambah User', 'users', $newId, null,
                json_encode(array('username' => $username, 'role' => $role, 'ruangan_id' => $ruangan)));
            $this->session->set_flashdata('success', 'User "' . $username . '" dibuat.');
        }

        redirect('master/user');
    }

    public function reset_password($id)
    {
        if ($this->input->method() !== 'post') {
            $this->_deny();
        }

        $row = $this->user_model->get_by_id($id);
        if (! $row) {
            $this->session->set_flashdata('error', 'User tidak ditemukan.');
            redirect('master/user');
        }

        $this->load->library('form_validation');
        $this->form_validation->set_rules('password_baru', 'Password Baru', 'required|min_length[8]|callback_password_check');
        $this->form_validation->set_rules('password_konfirmasi', 'Ulangi Password', 'required|matches[password_baru]');

        if ($this->form_validation->run() === FALSE) {
            $this->session->set_flashdata('error', 'Password baru belum memenuhi syarat.');
            redirect('master/user/form/' . $id);
        }

        $baru = $this->input->post('password_baru');
        if (password_verify($baru, $row['password'])) {
            $this->session->set_flashdata('error', 'Password baru harus berbeda dari password lama.');
            redirect('master/user/form/' . $id);
        }

        $this->user_model->set_password($id, $baru);

        // Hapus riwayat gagal login supaya tidak langsung terkunci
        $this->db->where('login', $row['username'])->delete('login_attempts');

        $this->_audit('Reset Password User', 'users', $id, null, '(disembunyikan)');
        $this->session->set_flashdata('success', 'Password "' . $row['username'] . '" berhasil diganti.');

        redirect('master/user');
    }

    public function toggle($id)
    {
        if ($this->input->method() !== 'post') {
            $this->_deny();
        }

        $row = $this->user_model->get_by_id($id);
        if (! $row) {
            $this->session->set_flashdata('error', 'User tidak ditemukan.');
            redirect('master/user');
        }

        $baru = (int) $row['is_active'] === 1 ? 0 : 1;

        // Jangan biarkan tanpa admin aktif
        if ($baru === 0 && $row['role'] === 'admin') {
            $sisa = (int) $this->db->from('users')
                ->where('role', 'admin')->where('is_active', 1)
                ->where('is_deleted', 0)->where('id !=', (int) $id)
                ->count_all_results();
            if ($sisa === 0) {
                $this->session->set_flashdata('error',
                    'Ini admin aktif terakhir. Minimal harus ada satu admin aktif.');
                redirect('master/user');
            }
        }

        $this->user_model->update($id, array('is_active' => $baru));
        $this->_audit('Aktifkan User', 'users', $id,
            'is_active = ' . (int) $row['is_active'], 'is_active = ' . $baru);

        $this->session->set_flashdata('success',
            'User "' . $row['username'] . '" ' . ($baru ? 'diaktifkan' : 'dinonaktifkan') . '.');

        redirect('master/user');
    }

    public function delete($id)
    {
        if ($this->input->method() !== 'post') {
            $this->_deny();
        }

        $row = $this->user_model->get_by_id($id);
        if (! $row) {
            $this->session->set_flashdata('error', 'User tidak ditemukan.');
            redirect('master/user');
        }

        // Jangan biarkan menghapus diri sendiri
        if ((int) $id === (int) $this->user['id']) {
            $this->session->set_flashdata('error', 'Anda tidak bisa menghapus akun sendiri.');
            redirect('master/user');
        }

        // Jangan biarkan admin aktif terakhir hilang
        if ($row['role'] === 'admin' && (int) $row['is_active'] === 1) {
            $sisa = (int) $this->db->from('users')
                ->where('role', 'admin')->where('is_active', 1)
                ->where('is_deleted', 0)->where('id !=', (int) $id)
                ->count_all_results();
            if ($sisa === 0) {
                $this->session->set_flashdata('error',
                    'Ini admin aktif terakhir, jadi tidak bisa dihapus.');
                redirect('master/user');
            }
        }

        $this->user_model->soft_delete($id);
        $this->_audit('Hapus User', 'users', $id,
            json_encode(array('username' => $row['username'])), 'is_deleted = 1');
        $this->session->set_flashdata('success', 'User "' . $row['username'] . '" dihapus.');

        redirect('master/user');
    }
}
