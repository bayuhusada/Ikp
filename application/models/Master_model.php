<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Base untuk tabel master.
 *
 * Berbeda dengan MY_Model:
 *  - tidak ada soft delete, cukup is_active
 *  - tidak ada kolom created_by / updated_by
 *  - tidak ada filter role (master data dibaca semua role)
 */
class Master_model extends CI_Model
{
    protected $table = '';
    protected $primary_key = 'id';
    protected $order_by = 'urutan';
    protected $asc = true;

    /** Apakah tabel ini punya kolom is_active. Tetapkan eksplisit di subclass. */
    protected $has_is_active = true;

    /** Ambil semua yang aktif, untuk isi dropdown. */
    public function options($with_lainnya = false)
    {
        $this->db->from($this->table);

        if ($this->has_is_active) {
            $this->db->where('is_active', 1);
        }

        $this->db->order_by($this->order_by, $this->asc ? 'ASC' : 'DESC');
        $rows = $this->db->get()->result_array();

        if ($with_lainnya) {
            $rows[] = array(
                $this->primary_key => 99,
                'kode'  => '99',
                'nama'  => 'Lain-lain',
            );
        }

        return $rows;
    }

    public function get($id)
    {
        return $this->db->from($this->table)
            ->where($this->primary_key, (int) $id)
            ->get()
            ->row_array();
    }

    public function get_by_kode($kode)
    {
        return $this->db->from($this->table)
            ->where('kode', $kode)
            ->get()
            ->row_array();
    }

    public function kode_exists($kode, $except_id = null)
    {
        $this->db->from($this->table)->where('kode', $kode);
        if ($except_id) {
            $this->db->where($this->primary_key . ' !=', (int) $except_id);
        }
        return $this->db->count_all_results() > 0;
    }

    public function nama_exists($nama, $except_id = null)
    {
        $this->db->from($this->table)->where('nama', $nama);
        if ($except_id) {
            $this->db->where($this->primary_key . ' !=', (int) $except_id);
        }
        return $this->db->count_all_results() > 0;
    }

    public function insert_row(array $data)
    {
        unset($data['id']);
        if (! $this->db->insert($this->table, $data)) {
            return FALSE;
        }
        return (int) $this->db->insert_id();
    }

    public function update_row($id, array $data)
    {
        unset($data['id']);
        $this->db->where($this->primary_key, (int) $id);
        return $this->db->update($this->table, $data);
    }

    /**
     * Hapus permanen. Master data tidak punya historiesi, jadi hard delete
     * aman di sini - TETAPI hanya bila tabel tidak direferensikan baris lain.
     */
    public function delete_row($id)
    {
        $this->db->where($this->primary_key, (int) $id);
        return $this->db->delete($this->table);
    }

    /** Halaman master + search. */
    public function paginate(array $options = array())
    {
        $search = isset($options['search']) ? trim($options['search']) : '';
        $limit  = isset($options['limit']) ? (int) $options['limit'] : 25;
        $offset = isset($options['offset']) ? (int) $options['offset'] : 0;

        $this->db->from($this->table);

        if ($search !== '') {
            $this->db->group_start()
                ->like('nama', $search)
                ->or_like('kode', $search)
                ->group_end();
        }

        // Baris dulu, baru total. count_all_results() mereset query builder.
        $rows = $this->db->order_by($this->order_by, $this->asc ? 'ASC' : 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        $this->db->from($this->table);
        if ($search !== '') {
            $this->db->group_start()
                ->like('nama', $search)
                ->or_like('kode', $search)
                ->group_end();
        }
        $total = (int) $this->db->count_all_results();

        return array('rows' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset);
    }
}
