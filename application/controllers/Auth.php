<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Autentikasi.
 *
 * Catatan kenapa kelas ini extends CI_Controller, bukan MY_Controller:
 * MY_Controller memaksa login di constructor. Auth justru harus bisa
 * diakses tanpa login.
 */
class Auth extends CI_Controller
{
    /** Jumlah kegagalan sebelum dikunci. */
    const MAX_ATTEMPTS = 5;

    /** Durasi kunci dalam detik (15 menit). */
    const LOCKOUT_SECONDS = 900;

    public function __construct()
    {
        parent::__construct();
        $this->load->library(array('session', 'form_validation'));
        $this->load->helper(array('url', 'form', 'security'));
        $this->load->model(array('user_model', 'audit_log_model'));
        $this->load->database();
    }

    // -------------------------------------------------------------- Login

    public function index()
    {
        if ($this->session->userdata('logged_in')) {
            redirect('dashboard');
        }
        redirect('auth/login');
    }

    public function login()
    {
        if ($this->session->userdata('logged_in')) {
            redirect('dashboard');
        }

        $data['title'] = 'Login';
        $this->load->view('auth/login', $data);
    }

    public function proses_login()
    {
        $this->form_validation->set_rules('username', 'Username', 'required|trim|max_length[50]');
        $this->form_validation->set_rules('password', 'Password', 'required|max_length[255]');

        if ($this->form_validation->run() === FALSE) {
            $this->session->set_flashdata('error', 'Username dan password wajib diisi.');
            redirect('auth/login');
        }

        $username = $this->input->post('username', true);
        $password = $this->input->post('password');
        $ip       = $this->input->ip_address();

        // 1. Cek kunci
        if ($this->_is_locked_out($ip, $username)) {
            $sisa = $this->_lockout_remaining($ip, $username);
            $this->session->set_flashdata(
                'error',
                'Terlalu banyak percobaan gagal. Coba lagi dalam ' . ceil($sisa / 60) . ' menit.'
            );
            redirect('auth/login');
        }

        $user = $this->user_model->find_for_login($username);

        // 2. Verifikasi. Pesan error sengaja sama agar tidak membocorkan
        //    apakah username itu ada.
        $valid = $user
            && (int) $user['is_active'] === 1
            && (int) $user['is_deleted'] === 0
            && password_verify($password, $user['password']);

        if (! $valid) {
            $this->_record_attempt($ip, $username, 0);

            if ($user && (int) $user['is_deleted'] === 1) {
                $this->session->set_flashdata('error', 'Akun tidak aktif. Hubungi administrator.');
                $this->_audit_safe('Login Gagal (Akun Nonaktif)', 'users', $user['id'], $username);
                redirect('auth/login');
            }
            if ($user && (int) $user['is_active'] === 0) {
                $this->session->set_flashdata('error', 'Akun dinonaktifkan. Hubungi administrator.');
                $this->_audit_safe('Login Gagal (Akun Nonaktif)', 'users', $user['id'], $username);
                redirect('auth/login');
            }

            $this->session->set_flashdata('error', 'Username atau password salah.');
            $this->_audit_safe('Login Gagal', 'users', $user ? $user['id'] : null, $username);
            redirect('auth/login');
        }

        // 3. Sukses
        $this->_record_attempt($ip, $username, 1);
        $this->_clear_attempts($ip, $username);

        // 4. Anti session fixation: ID sesi wajib berubah setelah login
        $this->session->sess_regenerate(TRUE);

        $this->session->set_userdata(array(
            'user_id'    => (int) $user['id'],
            'username'   => $user['username'],
            'user_nama'  => $user['nama'],
            'role'       => $user['role'],
            'ruangan_id' => $user['ruangan_id'] !== NULL ? (int) $user['ruangan_id'] : NULL,
            'logged_in'  => TRUE,
        ));

        $this->user_model->update_last_login($user['id']);
        $this->_audit_safe('Login Berhasil', 'users', $user['id'], $user['username']);

        redirect('dashboard');
    }

    // ------------------------------------------------------------- Logout

    /**
     * WAJIB POST. Menerima GET akan membiarkan logout dipicu diam-diam
     * lewat prefetch browser, gambar, atau tautan.
     */
    public function logout()
    {
        if ($this->input->method() !== 'post') {
            show_error('Logout harus melalui POST.', 405, 'Metode Tidak Diizinkan');
            return;
        }

        $user_id   = $this->session->userdata('user_id');
        $username  = $this->session->userdata('username');

        if ($user_id) {
            $this->_audit_safe('Logout', 'users', $user_id, $username);
        }

        $this->session->unset_userdata(array(
            'user_id', 'username', 'user_nama', 'role', 'ruangan_id', 'logged_in',
        ));
        $this->session->sess_destroy();

        redirect('auth/login');
    }

    // -------------------------------------------------- Lockout (login_attempts)

    private function _is_locked_out($ip, $username)
    {
        $since = time() - self::LOCKOUT_SECONDS;
        $this->db->where('time >', $since);
        $this->db->where('ip_address', $ip);
        $this->db->where('login', $username);
        $this->db->where('success', 0);
        return (int) $this->db->count_all_results('login_attempts') >= self::MAX_ATTEMPTS;
    }

    private function _lockout_remaining($ip, $username)
    {
        $since = time() - self::LOCKOUT_SECONDS;
        $this->db->where('time >', $since);
        $this->db->where('ip_address', $ip);
        $this->db->where('login', $username);
        $this->db->where('success', 0);
        $this->db->order_by('time', 'DESC');
        $last = $this->db->get('login_attempts')->row_array();

        if (! $last) {
            return 0;
        }
        return max(0, ((int) $last['time'] + self::LOCKOUT_SECONDS) - time());
    }

    private function _record_attempt($ip, $username, $success)
    {
        $this->db->insert('login_attempts', array(
            'login'      => $username,
            'ip_address' => $ip,
            'time'       => time(),
            'success'    => $success ? 1 : 0,
        ));
    }

    private function _clear_attempts($ip, $username)
    {
        $this->db->where('ip_address', $ip);
        $this->db->where('login', $username);
        $this->db->delete('login_attempts');
    }

    /**
     * Audit sebelum user login. setelah = null karena belum ada $this->user.
     * Jangan pernah menulis password ke log.
     */
    private function _audit_safe($aktivitas, $entitas, $entitas_id, $username)
    {
        try {
            $this->audit_log_model->log(array(
                'user_id'    => $entitas_id ? (int) $entitas_id : null,
                'username'   => $username,
                'aktivitas'  => $aktivitas,
                'entitas'    => $entitas,
                'entitas_id' => $entitas_id,
                'sebelum'    => null,
                'sesudah'    => null,
                'ip'         => $this->input->ip_address(),
            ));
        } catch (Exception $e) {
            // Jangan sampai kegagalan audit memblokir login
            log_message('error', 'Gagal menulis audit log: ' . $e->getMessage());
        }
    }
}
