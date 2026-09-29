<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Profil dan ubah password milik sendiri.
 */
class Profile extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->require_role(array('admin', 'kepala_ruangan', 'pelapor'));
        $this->load->model(array('user_model', 'ruangan_model', 'audit_log_model'));
    }

    public function index()
    {
        $data['title'] = 'Profil Saya';
        $data['riwayat'] = $this->audit_log_model->paginate(array(
            'user_id' => (int) $this->user['id'],
            'limit'   => 20,
        ));

        $this->render('profile/index', $data);
    }

    public function update()
    {
        if ($this->input->method() !== 'post') {
            $this->_deny();
        }

        $this->load->library('form_validation');
        $this->form_validation->set_rules('nama', 'Nama', 'required|trim|max_length[100]');
        $this->form_validation->set_rules('nip', 'NIP', 'trim|max_length[50]');

        if ($this->form_validation->run() === FALSE) {
            $this->session->set_flashdata('error', 'Periksa kembali data yang diisi.');
            redirect('profil');
        }

        $data = array(
            'nama' => $this->input->post('nama', true),
            'nip'  => $this->input->post('nip', true) ?: null,
        );

        $this->user_model->update($this->user['id'], $data);
        $this->_audit('Ubah Profil', 'users', $this->user['id'], null, json_encode($data));

        $this->session->set_flashdata('success', 'Profil berhasil disimpan.');
        redirect('profil');
    }

    public function change_password()
    {
        if ($this->input->method() !== 'post') {
            $this->_deny();
        }

        $this->load->library('form_validation');
        $this->form_validation->set_rules('password_lama', 'Password lama', 'required');
        $this->form_validation->set_rules('password_baru', 'Password baru', 'required|min_length[8]|callback_password_check');
        $this->form_validation->set_rules('password_konfirmasi', 'Ulangi password baru', 'required|matches[password_baru]');

        if ($this->form_validation->run() === FALSE) {
            // Tampilkan pesan rule yang benar-benar gagal, bukan kalimat umum,
            // supaya user tahu apa yang harus diperbaiki.
            $this->session->set_flashdata('error', $this->_pesan_validasi());
            redirect('profil');
        }

        $lama = $this->input->post('password_lama');
        $baru = $this->input->post('password_baru');

        if (! password_verify($lama, $this->user['password'])) {
            $this->session->set_flashdata('error', 'Password lama salah.');
            $this->_audit('Gagal Ubah Password (Password lama salah)', 'users', $this->user['id']);
            redirect('profil');
        }

        // Cegah memakai password yang sama
        if (password_verify($baru, $this->user['password'])) {
            $this->session->set_flashdata('error', 'Password baru harus berbeda dari password lama.');
            redirect('profil');
        }

        $this->user_model->set_password($this->user['id'], $baru);
        $this->_audit('Ubah Password', 'users', $this->user['id'], null, '(disembunyikan)');

        // Paksa login ulang setelah ganti password.
        //
        // PENTING: pakai sess_regenerate(TRUE), BUKAN sess_destroy().
        // sess_destroy() menghapus baris sesi tapi tetap menyisakan
        // session_id di memori, sehingga penulisan flashdata di akhir request
        // jadi UPDATE ke baris yang sudah hilang -> UPDATE 0 row -> pesan
        // "Password berhasil diubah" tidak pernah tampil. Sess_regenerate()
        // memberi session id baru, jadi flashdata benar-benar tersimpan.
        $this->session->sess_regenerate(TRUE);
        $this->session->unset_userdata(array('user_id', 'username', 'user_nama', 'role', 'ruangan_id', 'logged_in'));
        $this->session->set_flashdata('success', 'Password berhasil diubah. Silakan login kembali.');

        redirect('auth/login');
    }

    /**
     * Unggah / ganti gambar tanda tangan.
     * Hanya png, jpg, webp. Maksimal 1 MB.
     */
    public function upload_ttd()
    {
        if ($this->input->method() !== 'post') {
            $this->_deny();
        }

        $config = array(
            'upload_path'   => FCPATH . 'uploads/ttd/',
            'allowed_types' => 'png|jpg|jpeg|webp',
            'max_size'      => 1024,
            'encrypt_name'  => TRUE,
            'remove_spaces' => TRUE,
        );
        $this->load->library('upload', $config);

        if (! $this->upload->do_upload('ttd')) {
            $this->session->set_flashdata('error', 'Upload gagal: ' . $this->upload->display_errors('', ''));
            redirect('profil');
        }

        $upload = $this->upload->data();

        // Verifikasi bahwa berkas benar-benar gambar, bukan script bernama gambar
        $mime = $this->_detecta_gambar($upload['full_path']);
        if ($mime === FALSE) {
            @unlink($upload['full_path']);
            $this->session->set_flashdata('error', 'Berkas bukan gambar yang valid.');
            redirect('profil');
        }

        // Hapus tanda tangan lama
        if (! empty($this->user['ttd'])) {
            $lama = FCPATH . 'uploads/ttd/' . basename($this->user['ttd']);
            if (is_file($lama)) {
                @unlink($lama);
            }
        }

        $this->user_model->update($this->user['id'], array('ttd' => $upload['file_name']));
        $this->_audit('Upload Tanda Tangan', 'users', $this->user['id'], $this->user['ttd'], $upload['file_name']);

        $this->session->set_flashdata('success', 'Tanda tangan berhasil disimpan.');
        redirect('profil');
    }

    /**
     * Gabungkan semua pesan validasi yang gagal jadi satu kalimat.
     * Dipakai supaya pesan di flashdata benar-benar informatif.
     */
    private function _pesan_validasi()
    {
        $err = $this->form_validation->error_array();
        if (empty($err)) {
            return 'Periksa kembali password yang diisi.';
        }
        return implode(' ', array_map('strval', $err));
    }

    /** Pastikan isi berkas benar-benar gambar. */
    private function _detecta_gambar($path)
    {
        if (! is_file($path)) {
            return FALSE;
        }
        $info = @getimagesize($path);
        $allowed = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        if ($info === FALSE OR ! in_array($info['mime'], $allowed, TRUE)) {
            return FALSE;
        }
        return $info['mime'];
    }
}
