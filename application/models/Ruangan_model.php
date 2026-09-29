<?php
defined('BASEPATH') OR exit('No direct script access allowed');


/**
 * Tabel: ruangan
 * Master data dengan soft delete, jadi extend MY_Model.
 */
class Ruangan_model extends MY_Model
{
    protected $table = 'ruangan';
    protected $primary_key = 'id';

    /** Untuk dropdown. Terima hanya ruangannya bila user kepala ruangan. */
    public function options_for_user($user)
    {
        $this->db->from('ruangan')
            ->where('is_deleted', 0)
            ->where('is_active', 1)
            ->order_by('nama', 'ASC');

        if (isset($user['role']) && $user['role'] === 'kepala_ruangan') {
            $this->db->where('id', (int) $user['ruangan_id']);
        }

        return $this->db->get()->result_array();
    }

    public function kode_exists($kode, $except_id = null)
    {
        $this->db->from('ruangan')->where('kode', $kode);
        if ($except_id) {
            $this->db->where('id !=', (int) $except_id);
        }
        return $this->db->count_all_results() > 0;
    }
}
