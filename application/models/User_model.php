<?php
defined('BASEPATH') OR exit('No direct script access allowed');


/**
 * Tabel: users
 * Kolom soft delete + ruang_klien: is_deleted, deleted_at, deleted_by, ruangan_id
 */
class User_model extends MY_Model
{
    protected $table = 'users';
    protected $primary_key = 'id';

    public function __construct()
    {
        parent::__construct();
    }

    public function get_by_id($id)
    {
        return $this->db->from($this->table)
            ->where('id', (int) $id)
            ->where('is_deleted', 0)
            ->get()
            ->row_array();
    }

    public function get_by_username($username)
    {
        return $this->db->from($this->table)
            ->where('username', $username)
            ->where('is_deleted', 0)
            ->get()
            ->row_array();
    }

    /** Dipakai Auth::login(). Tidak memfilter is_deleted. */
    public function find_for_login($username)
    {
        return $this->db->from($this->table)
            ->where('username', $username)
            ->get()
            ->row_array();
    }

    public function username_exists($username, $except_id = null)
    {
        $this->db->from($this->table)->where('username', $username);
        if ($except_id) {
            $this->db->where('id !=', (int) $except_id);
        }
        return $this->db->count_all_results() > 0;
    }

    public function insert_user(array $data)
    {
        if (! empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        return $this->insert($data);
    }

    public function update_user($id, array $data)
    {
        if (! empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } else {
            unset($data['password']);
        }
        return $this->update($id, $data);
    }

    public function update_last_login($id)
    {
        $this->db->where('id', (int) $id);
        return $this->db->update($this->table, array('last_login' => date('Y-m-d H:i:s')));
    }

    /** Daftar user + nama ruangan, untuk layar admin. */
    public function paginate_users(array $options = array())
    {
        $search = isset($options['search']) ? trim($options['search']) : '';
        $limit  = isset($options['limit']) ? (int) $options['limit'] : 25;
        $offset = isset($options['offset']) ? (int) $options['offset'] : 0;

        $build = function () use ($search, $options) {
            $this->db->select('users.*, ruangan.nama AS ruang_nama')
                ->from('users')
                ->join('ruangan', 'ruangan.id = users.ruangan_id', 'left')
                ->where('users.is_deleted', 0);

            if ($search !== '') {
                $this->db->group_start()
                    ->like('users.nama', $search)
                    ->or_like('users.username', $search)
                    ->group_end();
            }
            if (! empty($options['role'])) {
                $this->db->where('users.role', $options['role']);
            }
            if (! empty($options['ruangan_id'])) {
                $this->db->where('users.ruangan_id', (int) $options['ruangan_id']);
            }
            return $this->db;
        };

        // Baris dulu, baru total. count_all_results() mereset query builder,
        // jadi query kedua harus dibangun ulang dari awal.
        $rows = $build()->order_by('users.nama', 'ASC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        $total = (int) $build()->count_all_results();

        return array('rows' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset);
    }

    /** Ganti password tanpa mengirim kolom lain. */
    public function set_password($id, $plain)
    {
        $this->db->where('id', (int) $id);
        return $this->db->update($this->table, array(
            'password'   => password_hash($plain, PASSWORD_DEFAULT),
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => isset($this->user) && $this->user ? (int) $this->user['id'] : null,
        ));
    }
}
