<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Lihat audit log. KHUSUS admin.
 *
 * Tabel audit_logs bersifat append-only. Karena itu tidak ada method
 * create, update, maupun delete di controller ini.
 */
class Audit_log extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->require_role('admin');
        $this->load->model('audit_log_model');
    }

    public function index()
    {
        $limit = (int) $this->input->get('limit');
        $limit = ($limit > 0 && $limit <= 200) ? $limit : 50;

        $page = (int) $this->input->get('page');
        $page = $page > 0 ? $page : 1;

        $filter = array(
            'search'    => (string) $this->input->get('search'),
            'user_id'   => (int) $this->input->get('user_id'),
            'aktivitas' => (string) $this->input->get('aktivitas'),
            'date_from' => (string) $this->input->get('date_from'),
            'date_to'   => (string) $this->input->get('date_to'),
        );

        $result = $this->audit_log_model->paginate(array(
            'search'    => $filter['search'],
            'user_id'   => $filter['user_id'],
            'aktivitas' => $filter['aktivitas'],
            'date_from' => $filter['date_from'],
            'date_to'   => $filter['date_to'],
            'limit'     => $limit,
            'offset'    => ($page - 1) * $limit,
        ));

        $data = array(
            'title'    => 'Audit Log',
            'filter'   => $filter,
            'rows'     => $result['rows'],
            'total'    => $result['total'],
            'page'     => $page,
            'limit'    => $limit,
            'pages'    => (int) ceil($result['total'] / max(1, $limit)),
            'aktivitas_list' => $this->audit_log_model->distinct_activities(),
        );

        $this->render('audit_log/index', $data);
    }

    /**
     * Ekspor CSV. Tetap hanya admin.
     * Hanya kolom administratif - tidak ada data pasien.
     */
    public function export_csv()
    {
        $result = $this->audit_log_model->paginate(array(
            'search'  => (string) $this->input->get('search'),
            'limit'   => 5000,
            'offset'  => 0,
        ));

        $this->_audit('Export Audit Log', 'audit_logs', null,
            'jumlah baris', (string) $result['total']);

        $filename = 'audit-log-' . date('Ymd-His') . '.csv';

        $this->output
            ->set_content_type('text/csv; charset=UTF-8')
            ->set_header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        // BOM supaya Excel membaca UTF-8 dengan benar
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array('Waktu', 'Username', 'Aktivitas', 'Entitas', 'Entitas ID', 'Sebelum', 'Sesudah', 'IP'));

        foreach ($result['rows'] as $r) {
            fputcsv($out, array(
                $r['waktu'],
                $r['username'],
                $r['aktivitas'],
                $r['entitas'],
                $r['entitas_id'],
                $r['sebelum'],
                $r['sesudah'],
                $r['ip'],
            ));
        }
        fclose($out);
        $this->output->_display();
        exit;
    }
}
