<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Tabel: audit_logs
 *
 * Append-only. TIDAK BOLEH ada method update atau delete di kelas ini.
 * Hanya boleh dihapus lewat operasi administratif di luar aplikasi
 * (dan itu pun harus sesuai kebijakan retensi data).
 */
class Audit_log_model extends CI_Model
{
    protected $table = 'audit_logs';

    public function log(array $data)
    {
        $row = array(
            'user_id'    => isset($data['user_id']) ? $data['user_id'] : null,
            'username'   => isset($data['username']) ? $data['username'] : null,
            'aktivitas'  => isset($data['aktivitas']) ? $data['aktivitas'] : '',
            'entitas'    => isset($data['entitas']) ? $data['entitas'] : null,
            'entitas_id' => isset($data['entitas_id']) ? $data['entitas_id'] : null,
            'sebelum'    => isset($data['sebelum']) ? $data['sebelum'] : null,
            'sesudah'    => isset($data['sesudah']) ? $data['sesudah'] : null,
            'ip'         => isset($data['ip']) ? $data['ip'] : null,
            'waktu'      => date('Y-m-d H:i:s'),
        );

        // Jangan sampai gagal karena overflow kolom
        foreach (array('aktivitas', 'entitas') as $k) {
            if ($row[$k] !== null && strlen($row[$k]) > 100) {
                $row[$k] = substr($row[$k], 0, 100);
            }
        }
        foreach (array('sebelum', 'sesudah') as $k) {
            if ($row[$k] !== null && strlen($row[$k]) > 2000) {
                $row[$k] = substr($row[$k], 0, 2000);
            }
        }

        return $this->db->insert($this->table, $row);
    }

    /** Daftar log. Hanya admin yang boleh memanggil ini. */
    public function paginate(array $options = array())
    {
        $search      = isset($options['search']) ? trim($options['search']) : '';
        $limit       = isset($options['limit']) ? (int) $options['limit'] : 50;
        $offset      = isset($options['offset']) ? (int) $options['offset'] : 0;
        $user_id     = isset($options['user_id']) ? (int) $options['user_id'] : 0;
        $aktivitas   = isset($options['aktivitas']) ? $options['aktivitas'] : '';
        $date_from   = isset($options['date_from']) ? $options['date_from'] : '';
        $date_to     = isset($options['date_to']) ? $options['date_to'] : '';

        // PENTING: ambil baris DULU, baru hitung total.
        // count_all_results() mereset query builder, sehingga query kedua
        // akan kehilangan klausa FROM bila tidak diulang dari awal.
        $this->db->from($this->table);

        if ($search !== '') {
            $this->db->group_start()
                ->like('username', $search)
                ->or_like('aktivitas', $search)
                ->or_like('entitas_id', $search)
                ->group_end();
        }
        if ($user_id) {
            $this->db->where('user_id', $user_id);
        }
        if ($aktivitas !== '') {
            $this->db->where('aktivitas', $aktivitas);
        }
        if ($date_from !== '') {
            $this->db->where('waktu >=', $date_from . ' 00:00:00');
        }
        if ($date_to !== '') {
            $this->db->where('waktu <=', $date_to . ' 23:59:59');
        }

        $rows = $this->db->order_by('waktu', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        // Hitung total dengan filter yang sama, builder baru
        $this->db->from($this->table);
        if ($search !== '') {
            $this->db->group_start()
                ->like('username', $search)
                ->or_like('aktivitas', $search)
                ->or_like('entitas_id', $search)
                ->group_end();
        }
        if ($user_id) {
            $this->db->where('user_id', $user_id);
        }
        if ($aktivitas !== '') {
            $this->db->where('aktivitas', $aktivitas);
        }
        if ($date_from !== '') {
            $this->db->where('waktu >=', $date_from . ' 00:00:00');
        }
        if ($date_to !== '') {
            $this->db->where('waktu <=', $date_to . ' 23:59:59');
        }
        $total = (int) $this->db->count_all_results();

        return array('rows' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset);
    }

    /** Daftar aktivitas unik, untuk filter dropdown. */
    public function distinct_activities()
    {
        // CI3 tidak punya result_column(). Ambil result() lalu ambil kolomnya.
        $rows = $this->db->distinct()
            ->select('aktivitas')
            ->from($this->table)
            ->order_by('aktivitas', 'ASC')
            ->get()
            ->result();

        $out = array();
        foreach ($rows as $r) {
            $out[] = $r->aktivitas;
        }
        return $out;
    }
}
