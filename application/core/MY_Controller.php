<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Base controller untuk aplikasi IKP.
 *
 * Contract:
 *  - Semua controller KECUALI Auth WAJIB extend kelas ini.
 *  - Login dijaga otomatis oleh constructor. Tidak perlu cek_login() manual.
 *  - Authorization per halaman wajib lewat require_role() di __construct.
 *  - Semua perubahan data WAJIB lewat _audit().
 */
class MY_Controller extends CI_Controller
{
    protected $data = array();
    protected $user = null;

    /** Role yang boleh meng accesses halaman ini. */
    protected $allowed_roles = array();

    public function __construct()
    {
        parent::__construct();

        $this->load->library('session');
        $this->load->helper(array('url', 'form', 'security', 'text'));
        $this->load->model('user_model');

        $this->_load_user();

        // Penjaga otomatis: semua controller yang extend MY_Controller harus login
        if (! $this->user) {
            $this->_redirect_to_login();
        }
    }

    /**
     * Ambil user dari session. Hancurkan session bila user tidak ada / nonaktif.
     */
    protected function _load_user()
    {
        $user_id = $this->session->userdata('user_id');
        if (! $user_id) {
            return;
        }

        $user = $this->user_model->get_by_id($user_id);
        if ($user && (int) $user['is_active'] === 1) {
            $this->user = $user;
            $this->data['user'] = $user;
        } else {
            $this->session->unset_userdata(array('user_id', 'username', 'role', 'ruangan_id', 'logged_in'));
            $this->session->sess_destroy();
        }
    }

    protected function _redirect_to_login()
    {
        if (is_cli()) {
            show_error('Akses CLI ditolak: session tidak tersedia.', 403);
        }
        $this->session->set_flashdata('error', 'Silakan login terlebih dahulu.');
        redirect('auth');
        exit;
    }

    // ---------------------------------------------------------------- RBAC

    /**
     * Dipanggil di __construct() tiap controller.
     * Contoh: $this->require_role(array('admin', 'kepala_ruangan'));
     */
    protected function require_role($roles)
    {
        $roles = is_array($roles) ? $roles : array($roles);

        if (! in_array($this->user['role'], $roles, true)) {
            $this->_deny();
        }
    }

    protected function _deny()
    {
        if (is_cli()) {
            show_error('Akses ditolak.', 403);
        }

        // Jangan bocorkan apakah resource itu ada atau tidak
        if ($this->is_ajax_request()) {
            $this->json_fail('Akses ditolak.', 403);
        }

        show_error('Akses ditolak. Anda tidak memiliki izin untuk halaman ini.', 403, 'Akses Ditolak');
        exit;
    }

    protected function is_ajax_request()
    {
        return $this->input->is_ajax_request()
            OR strtolower($this->input->get_request_header('X-Requested-With')) === 'xmlhttprequest';
    }

    protected function has_role($roles)
    {
        $roles = is_array($roles) ? $roles : array($roles);
        return in_array($this->user['role'], $roles, true);
    }

    protected function is_admin()
    {
        return $this->user['role'] === 'admin';
    }

    // ------------------------------------------------- Pembatasan data

    /**
     * Terapkan filter data sesuai role. WAJIB dipakai di setiap query laporan.
     *
     *  pelapor        -> hanya laporan miliknya sendiri
     *  kepala_ruangan -> hanya laporan ruangannya
     *  admin          -> semua
     *
     * @param  CI_DB_query_builder $query
     * @param  string              $alias  nama tabel/alias pada query
     * @return CI_DB_query_builder
     */
    protected function apply_role_scope($query, $alias = '')
    {
        $prefix = ($alias !== '') ? $alias . '.' : '';

        switch ($this->user['role']) {
            case 'pelapor':
                $query->where($prefix . 'created_by', (int) $this->user['id']);
                break;

            case 'kepala_ruangan':
                $query->where($prefix . 'ruangan_id', (int) $this->user['ruangan_id']);
                break;

            case 'admin':
            default:
                // tanpa filter
                break;
        }

        return $query;
    }

    /**
     * True bila user boleh mengakses data milik ruangan tertentu.
     */
    protected function may_access_room($ruangan_id)
    {
        switch ($this->user['role']) {
            case 'admin':
                return true;
            case 'kepala_ruangan':
                return (int) $this->user['ruangan_id'] === (int) $ruangan_id;
            case 'pelapor':
                return (int) $this->user['ruangan_id'] === (int) $ruangan_id;
        }
        return false;
    }

    // -------------------------------------------------------- Validasi

    /**
     * Callback untuk rule `callback_password_check`.
     *
     * WAJIB berada di controller, bukan di MY_Form_validation. CI3 mencari
     * method callback dengan `method_exists($this->CI, $rule)` di
     * system/libraries/Form_validation.php baris 716 — $this->CI adalah
     * controller, bukan objek form_validation. Kalau methodnya tidak ada,
     * validasi gagal dengan pesan "Unable to find callback validation rule"
     * dan password tidak pernah bisa diganti.
     *
     * Syarat: minimal 8 karakter, ada huruf besar, huruf kecil, dan angka.
     *
     * Kunci pesan error TANPA prefix `callback_`. CI3 memotong prefix itu
     * sebelum mencari pesan:
     *
     *     system/libraries/Form_validation.php:676
     *     $rule = substr($rule, 9);   // "callback_password_check" -> "password_check"
     *
     * Kalau pesan di-set dengan kunci `callback_password_check`, CI3 tidak
     * menemukannya dan jatuh ke pencarian language line
     * `form_validation_password_check` yang tidak ada -> pesan error generik
     * plus ERROR di log.
     *
     * @param  string $str
     * @return bool
     */
    public function password_check($str)
    {
        $pesan = '';
        if (is_array($str) OR $str === NULL OR $str === '') {
            $pesan = 'Password wajib diisi.';
        } elseif (strlen($str) < 8) {
            $pesan = 'Password minimal 8 karakter.';
        } elseif (preg_match('/[A-Z]/', $str) !== 1) {
            $pesan = 'Password harus punya minimal satu huruf besar (A-Z).';
        } elseif (preg_match('/[a-z]/', $str) !== 1) {
            $pesan = 'Password harus punya minimal satu huruf kecil (a-z).';
        } elseif (preg_match('/[0-9]/', $str) !== 1) {
            $pesan = 'Password harus punya minimal satu angka (0-9).';
        }

        if ($pesan !== '') {
            $this->form_validation->set_message('password_check', $pesan);
            return FALSE;
        }

        return TRUE;
    }

    // ------------------------------------------------------------- Audit

    /**
     * Catat aktivitas ke audit_logs. Tabel ini append-only.
     *
     * @param string $aktivitas contoh: "Grading Risiko"
     * @param string $entitas   nama tabel, contoh: "incident_reports"
     * @param mixed  $entitas_id
     * @param string $sebelum  nilai lama (nullable)
     * @param string $sesudah  nilai baru (nullable)
     */
    protected function _audit($aktivitas, $entitas = null, $entitas_id = null, $sebelum = null, $sesudah = null)
    {
        // Jangan pernah menulis data pasien ke log
        $this->load->model('audit_log_model');

        $this->audit_log_model->log(array(
            'user_id'    => (int) $this->user['id'],
            'username'   => $this->user['username'],
            'aktivitas'  => $aktivitas,
            'entitas'    => $entitas,
            'entitas_id' => $entitas_id,
            'sebelum'    => $sebelum,
            'sesudah'    => $sesudah,
            'ip'         => $this->input->ip_address(),
        ));
    }

    /**
     * Audit khusus perubahan status / nilai field.
     * $before_after = array('grading_risiko' => array('sebelum' => '-', 'sesudah' => 'HIJAU'))
     */
    protected function _audit_diff($aktivitas, $entitas, $entitas_id, array $before_after)
    {
        $sebelum = array();
        $sesudah = array();
        foreach ($before_after as $field => $pair) {
            $sebelum[$field] = isset($pair['sebelum']) ? $pair['sebelum'] : null;
            $sesudah[$field] = isset($pair['sesudah']) ? $pair['sesudah'] : null;
        }

        $this->_audit(
            $aktivitas,
            $entitas,
            $entitas_id,
            json_encode($sebelum, JSON_UNESCAPED_UNICODE),
            json_encode($sesudah, JSON_UNESCAPED_UNICODE)
        );
    }

    // ------------------------------------------------------------ Helper

    /**
     * Render halaman dengan layout admin 5 bagian.
     * WAJIB dipakai controller, jangan load template manual satu per satu.
     *
     * @param string $view  contoh: 'pelaporan/index'
     * @param array  $data
     */
    protected function render($view, array $data = array())
    {
        // Pastikan user selalu tersedia di view
        if (! isset($data['user'])) {
            $data['user'] = $this->user;
        }
        // Flash message opsional
        if (! isset($data['title'])) {
            $data['title'] = '';
        }

        $this->load->view('template/header', $data);
        $this->load->view('template/aside', $data);
        $this->load->view($view, $data);
        $this->load->view('template/footers', $data);
        $this->load->view('template/js', $data);
    }

    /**
     * Balas JSON lalu hentikan.
     *
     * PENTING: set_output() hanya menampung output. CI3 baru mengirimkannya
     * setelah controller selesai. Karena method ini memanggil exit, _display()
     * HARUS dipanggil manual, kalau tidak responsnya kosong (Content-Length: 0).
     */
    protected function json_response($payload, $status = 200)
    {
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $this->output->_display();
        exit;
    }

    protected function json_ok($message = 'OK', $data = null)
    {
        $this->json_response(array('ok' => true, 'message' => $message, 'data' => $data));
    }

    protected function json_fail($message, $status = 400, $errors = null)
    {
        $this->json_response(array('ok' => false, 'message' => $message, 'errors' => $errors), $status);
    }

    // Format tanggal untuk tampilan ada di helper ikp_helper.php
    // (fungsi tgl(), waktu(), jam()), BUKAN di sini, karena di dalam
    // view CI3 $this adalah CI_Loader, bukan controller.
}
