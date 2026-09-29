<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Dashboard.
 *
 * Isi dashboard berbeda per role:
 *   admin          -> seluruh ruangan
 *   kepala_ruangan -> hanya ruangannya
 *   pelapor        -> laporan miliknya sendiri, tanpa statistik antar-ruangan
 *
 * Setiap angka dihitung di server memakai filter role. Jangan pernah
 * menghitung agregat tanpa filter.
 */
class Dashboard extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        // Semua role boleh melihat dashboard
        $this->require_role(array('admin', 'kepala_ruangan', 'pelapor'));
        $this->load->model(array('status_model', 'risk_band_model'));
    }

    public function index()
    {
        $this->load->model('incident_report_model');

        $data['title'] = 'Dashboard';
        $data['role']  = $this->user['role'];

        if ($this->user['role'] === 'pelapor') {
            $data['mode'] = 'pelapor';
            $data['ringkasan'] = $this->_ringkasan_pelapor();
        } else {
            $data['mode'] = 'internal';
            $data['ringkasan'] = $this->_ringkasan_internal();
            $data['per_jenis'] = $this->_per_jenis();
            $data['per_ruangan'] = $this->_per_ruangan();
        }

        $data['status_list'] = $this->status_model->options_by_jenis('laporan');

        $this->render('dashboard/index', $data);
    }

    // ------------------------------------------------------------ Data

    /**
     * Query dasar tanpa select, sudah dibatasi role.
     * Pemanggil yang menentukan sendiri select-nya, karena query agregat
     * TIDAK boleh memakai select ir.* (bertentangan dengan only_full_group_by).
     */
    private function _scope($alias = 'ir')
    {
        $q = $this->db->from('incident_reports ir')
            ->where('ir.is_deleted', 0);
        $this->apply_role_scope($q, 'ir');
        return $q;
    }

    private function _ringkasan_internal()
    {
        $row = $this->_scope()
            ->select('
                COUNT(*) AS total,
                SUM(CASE WHEN ir.status_id = 1 THEN 1 ELSE 0 END) AS draft,
                SUM(CASE WHEN ir.status_id = 3 THEN 1 ELSE 0 END) AS menunggu_grading,
                SUM(CASE WHEN ir.status_id = 4 THEN 1 ELSE 0 END) AS sudah_grading,
                SUM(CASE WHEN ir.status_id = 7 THEN 1 ELSE 0 END) AS selesai,
                SUM(CASE WHEN ir.tanggal_lapor >= CURDATE() THEN 1 ELSE 0 END) AS hari_ini
            ')
            ->get()
            ->row_array();

        $row = is_array($row) ? $row : array();

        return array(
            'total'            => (int) (isset($row['total']) ? $row['total'] : 0),
            'draft'            => (int) (isset($row['draft']) ? $row['draft'] : 0),
            'menunggu_grading' => (int) (isset($row['menunggu_grading']) ? $row['menunggu_grading'] : 0),
            'sudah_grading'    => (int) (isset($row['sudah_grading']) ? $row['sudah_grading'] : 0),
            'selesai'          => (int) (isset($row['selesai']) ? $row['selesai'] : 0),
            'hari_ini'         => (int) (isset($row['hari_ini']) ? $row['hari_ini'] : 0),
        );
    }

    private function _ringkasan_pelapor()
    {
        $row = $this->_scope()
            ->select('
                COUNT(*) AS total,
                SUM(CASE WHEN ir.status_id = 1 THEN 1 ELSE 0 END) AS draft,
                SUM(CASE WHEN ir.status_id NOT IN (1,7) THEN 1 ELSE 0 END) AS diproses,
                SUM(CASE WHEN ir.tanggal_lapor >= CURDATE() THEN 1 ELSE 0 END) AS hari_ini
            ')
            ->get()
            ->row_array();

        $row = is_array($row) ? $row : array();

        return array(
            'total'    => (int) (isset($row['total']) ? $row['total'] : 0),
            'draft'    => (int) (isset($row['draft']) ? $row['draft'] : 0),
            'diproses' => (int) (isset($row['diproses']) ? $row['diproses'] : 0),
            'hari_ini' => (int) (isset($row['hari_ini']) ? $row['hari_ini'] : 0),
        );
    }

    private function _per_jenis()
    {
        return $this->_scope()
            ->select('it.kode, it.nama, COUNT(ir.id) AS jumlah')
            ->join('incident_types it', 'it.id = ir.jenis_insiden_id')
            ->group_by('it.id, it.kode, it.nama')
            ->order_by('it.urutan', 'ASC')
            ->get()
            ->result_array();
    }

    private function _per_ruangan()
    {
        return $this->_scope()
            ->select('r.nama, COUNT(ir.id) AS jumlah')
            ->join('ruangan r', 'r.id = ir.ruangan_id')
            ->group_by('r.id, r.nama')
            ->order_by('jumlah', 'DESC')
            ->limit(10)
            ->get()
            ->result_array();
    }

    /**
     * Laporan terbaru, sudah dibatasi oleh role.
     */
    public function terbaru($limit = 10)
    {
        $this->json_ok('OK', $this->_scope()
            ->select('ir.id, ir.nomor, ir.tanggal_insiden, ir.ruangan_id, ir.jenis_insiden_id, ir.status_id')
            ->order_by('ir.created_at', 'DESC')
            ->limit((int) $limit)
            ->get()
            ->result_array());
    }}
