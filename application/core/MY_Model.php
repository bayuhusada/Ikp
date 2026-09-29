<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Base model: soft delete, kolom audit, dan scope role.
 *
 * Aturan penting:
 *  - Tabel yang extends kelas ini WAJIB punya kolom is_deleted, deleted_at, deleted_by.
 *  - Tabel master (ruangan, users, dst.) TIDAK perlu extends kelas ini.
 *
 * RETENSI DATA - ATURAN KERAS:
 *  incident_reports dan investigations TIDAK BOLEH pernah dihapus permanen.
 *  Keputusan user 27 Sep 2026: data disimpan selamanya, tidak ada fitur hapus
 *  permanen di aplikasi ini. Soft delete pada kedua tabel itu berarti
 *  "diarsipkan", bukan "dihapus".
 *
 *  Karena itu JANGAN set $has_soft_delete = false di kedua model itu - kalau
 *  sampai, soft_delete() akan jatuh ke hard_delete() dan datanya hilang
 *  permanen tanpa jejak. Keduanya sudah mengunci properti itu secara eksplisit.
 *
 *  Pengecualian yang tetap memakai DELETE langsung (bukan melalui kelas ini):
 *  - investigation_recommendations & investigation_actions: baris anak, bukan
 *    record. Dihapus saat baris itu dihapus dari form.
 *  - tabel master (jenis insiden, kategori, dst.): itu konfigurasi, bukan data
 *    pasien. Dihapus permanen kalau belum dipakai, dijaga oleh cek referensi.
 */
class MY_Model extends CI_Model
{
    protected $table = '';
    protected $primary_key = 'id';
    protected $has_soft_delete = true;
    protected $has_audit_columns = true;
    protected $use_timestamps = true;

    /** Cache user aktif untuk satu request. */
    protected $user = null;

    // ------------------------------------------------------------- Query

    /**
     * Query builder dasar: sudah otomatis filter is_deleted = 0.
     * Gunakan ini sebagai titik awal setiap method.
     */
    protected function q($with_role_scope = false, $alias = '')
    {
        $this->db->from($this->table);

        if ($this->has_soft_delete) {
            $this->db->where($this->table . '.is_deleted', 0);
        }

        if ($with_role_scope) {
            $this->apply_role_scope($this->db, $alias);
        }

        return $this->db;
    }

    /**
     * User aktif, diambil langsung dari session.
     *
     * PENTING: session TIDAK berada di CI_Loader. Tidak ada properti
     * $this->load->session di CodeIgniter 3. Session disimpan sebagai
     * properti $session pada instance controller, jadi harus diambil lewat
     * get_instance(). Memakai $this->load->session selalu menghasilkan NULL
     * tanpa error, sehingga created_by diam-diam tidak terisi.
     *
     * Sengaja tidak memakai set_user() dari controller: model bisa dimuat
     * kapan saja, dan bergantung pada pemanggil yang lupa mengaturnya akan
     * membuat filter role gagal diam-diam.
     */
    protected function current_user()
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $CI =& get_instance();
        if ( ! isset($CI->session)) {
            return null;
        }
        if ( ! $CI->session->userdata('logged_in')) {
            return null;
        }

        $uid = $CI->session->userdata('user_id');
        if ( ! $uid) {
            return null;
        }

        $this->user = array(
            'id'         => (int) $uid,
            'role'       => $CI->session->userdata('role'),
            'ruangan_id' => $CI->session->userdata('ruangan_id'),
        );
        return $this->user;
    }

    /**
     * WAJIB: filter data sesuai role. Terapkan di setiap query yang menyentuh
     * tabel laporan atau investigasi.
     */
    public function apply_role_scope($query, $alias = '')
    {
        $user = $this->current_user();

        if (! $user) {
            // Tidak ada user = tidak boleh melihat apa pun. Kunci semua.
            $query->where($alias !== '' ? $alias . '.id' : $this->table . '.id', 0);
            return $query;
        }

        $prefix = ($alias !== '') ? $alias . '.' : '';

        switch ($user['role']) {
            case 'pelapor':
                $query->where($prefix . 'created_by', (int) $user['id']);
                break;

            case 'kepala_ruangan':
                $query->where($prefix . 'ruangan_id', (int) $user['ruangan_id']);
                break;

            case 'admin':
            default:
                break;
        }

        return $query;
    }

    // -------------------------------------------------------------- Read

    public function get($id, $with_role_scope = true)
    {
        $query = $this->db->from($this->table)
            ->where($this->table . '.' . $this->primary_key, $id);

        if ($this->has_soft_delete) {
            $query->where($this->table . '.is_deleted', 0);
        }

        if ($with_role_scope) {
            $this->apply_role_scope($query);
        }

        return $query->get()->row_array();
    }

    public function get_by($column, $value, $with_role_scope = false)
    {
        $query = $this->db->from($this->table)->where($column, $value);

        if ($this->has_soft_delete) {
            $query->where($this->table . '.is_deleted', 0);
        }

        if ($with_role_scope) {
            $this->apply_role_scope($query);
        }

        return $query->get()->row_array();
    }

    /**
     * Daftar + paginasi + search + sort.
     *
     * @param array $options
     *   search      string
     *   search_cols array   kolom yang boleh dicari
     *   sort        string   nama kolom
     *   dir         string   ASC / DESC
     *   limit, offset int
     *   role_scope  bool
     *   alias       string
     * @return array
     */
    public function paginate(array $options = array())
    {
        $search      = isset($options['search']) ? trim($options['search']) : '';
        $search_cols = isset($options['search_cols']) ? $options['search_cols'] : array();
        $sort        = isset($options['sort']) ? $options['sort'] : $this->primary_key;
        $dir         = (isset($options['dir']) && strtoupper($options['dir']) === 'ASC') ? 'ASC' : 'DESC';
        $limit       = isset($options['limit']) ? (int) $options['limit'] : 25;
        $offset      = isset($options['offset']) ? (int) $options['offset'] : 0;
        $role_scope  = isset($options['role_scope']) ? (bool) $options['role_scope'] : false;
        $alias       = isset($options['alias']) ? $options['alias'] : '';

        // Validasi kolom sort terhadap daftar putih
        $sortable = isset($options['sortable']) ? $options['sortable'] : array($this->primary_key, 'created_at');
        if (! in_array($sort, $sortable, true)) {
            $sort = $this->primary_key;
        }

        $build = function () use ($search, $search_cols, $sort, $dir, $role_scope, $alias, $limit, $offset) {
            $this->db->select($this->table . '.*')
                ->from($this->table);

            if ($this->has_soft_delete) {
                $this->db->where($this->table . '.is_deleted', 0);
            }

            if ($role_scope) {
                $this->apply_role_scope($this->db, $alias);
            }

            if ($search !== '' && ! empty($search_cols)) {
                $this->db->group_start();
                foreach ($search_cols as $i => $col) {
                    if ($i === 0) {
                        $this->db->like($col, $search);
                    } else {
                        $this->db->or_like($col, $search);
                    }
                }
                $this->db->group_end();
            }

            return $this->db->order_by($sort, $dir)
                ->limit($limit, $offset)
                ->get()
                ->result_array();
        };

        $rows = $build();

        // Hitung total dengan filter yang sama tanpa limit
        $this->db->from($this->table);
        if ($this->has_soft_delete) {
            $this->db->where($this->table . '.is_deleted', 0);
        }
        if ($role_scope) {
            $this->apply_role_scope($this->db, $alias);
        }
        if ($search !== '' && ! empty($search_cols)) {
            $this->db->group_start();
            foreach ($search_cols as $i => $col) {
                if ($i === 0) {
                    $this->db->like($col, $search);
                } else {
                    $this->db->or_like($col, $search);
                }
            }
            $this->db->group_end();
        }
        $total = (int) $this->db->count_all_results();

        return array(
            'rows'  => $rows,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        );
    }

    // ------------------------------------------------------------- Write

    protected function _timestamps()
    {
        if (! $this->has_audit_columns) {
            return array();
        }
        $now = date('Y-m-d H:i:s');
        $out = array();
        $user = $this->current_user();
        if ($user) {
            $out['created_by'] = (int) $user['id'];
            $out['updated_by'] = (int) $user['id'];
        } else {
            // Insert tanpa created_by akan merusak filter role untuk pelapor.
            log_message('error', 'MY_Model::_timestamps tidak menemukan user di session (tabel ' . $this->table . ')');
        }
        if ($this->use_timestamps) {
            $out['created_at'] = $now;
            $out['updated_at'] = $now;
        }
        return $out;
    }

    /**
     * @return int ID baru, atau FALSE bila gagal
     */
    public function insert(array $data)
    {
        $data = array_merge($data, $this->_timestamps());

        // Cegah overwrite kolom yang harus dibentuk sistem
        unset($data[$this->primary_key]);
        if ($this->has_soft_delete) {
            $data['is_deleted'] = 0;
        }

        if (! $this->db->insert($this->table, $data)) {
            return FALSE;
        }

        return (int) $this->db->insert_id();
    }

    public function update($id, array $data)
    {
        $data = array_merge($data, $this->_timestamps());
        unset($data['id'], $data['created_by'], $data['created_at']);

        // Soft delete tidak boleh diubah lewat update biasa
        if ($this->has_soft_delete) {
            unset($data['is_deleted'], $data['deleted_at'], $data['deleted_by']);
        }

        $this->db->where($this->primary_key, $id);
        return $this->db->update($this->table, $data);
    }

    /**
     * Soft delete. Tidak pernah benar-benar menghapus baris.
     * @return bool
     */
    public function soft_delete($id)
    {
        if (! $this->has_soft_delete) {
            return $this->hard_delete($id);
        }

        $data = array(
            'is_deleted' => 1,
            'deleted_at' => date('Y-m-d H:i:s'),
        );
        $user = $this->current_user();
        if ($user) {
            $data['deleted_by'] = (int) $user['id'];
        }

        $this->db->where($this->primary_key, $id);
        return $this->db->update($this->table, $data);
    }

    /**
     * Hapus permanen. HANYA untuk data master atau data salah input.
     * Jangan pernah dipakai pada data laporan.
     */
    public function hard_delete($id)
    {
        $this->db->where($this->primary_key, $id);
        return $this->db->delete($this->table);
    }

    // ------------------------------------------------------------- Nomor

    /**
     * Nomor dokumen berikutnya, format INS-2026-00001 / INV-2026-00001.
     *
     * Menghitung urut satu tahun. Arsip tidak me-reset nomor, jadi nomor
     * tidak pernah dipakai ulang.
     *
     * PENTING - PERBAIKAN DIRI (self-healing):
     * Counter di document_counters tidak selalu sama dengan nomor terbesar
     * yang benar-benar ada. Dufftractnya bisa tertinggal kalau ada baris yang
     * dihapus, diimpor, atau dinomori ulang di luar aplikasi. Kalau counter
     * tertinggal, nomor baru bisa bentrok dan INSERT ditolak UNIQUE index -
     * user hanya melihat error cryptic.
     *
     * Karena itu sebelum menaikkan counter, nilainya disamakan dulu dengan
     * nomor terbesar yang ada. Query-nya satu kali per pembuatan nomor, jauh
     * lebih murah daripada gagal terus-terusan.
     *
     * @param  string $jenis  INS atau INV
     * @return string|false
     */
    public function nomor_baru($jenis = 'INS')
    {
        $tahun = (int) date('Y');
        $tabel = ($jenis === 'INV') ? 'investigations' : 'incident_reports';
        $pola  = $jenis . '-' . $tahun . '-%';

        $this->db->trans_begin();

        // Pastikan baris counter untuk tahun ini ada.
        $ada = $this->db->select('counter')->from('document_counters')
            ->where('jenis', $jenis)->where('tahun', $tahun)
            ->get()->row_array();

        if (! $ada) {
            $this->db->insert('document_counters', array(
                'jenis' => $jenis, 'tahun' => $tahun, 'counter' => 0,
            ));
        }

        // Self-healing: samakan counter dengan nomor terbesar yang ada.
        // PENTING: pakai row_array() + alias. row() mengembalikan objek dan
        // nama kolomnya ikut nama fungsinya ("COALESCE(...)"), yang tidak
        // bisa dibaca.
        $sql = 'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(nomor, \'-\', -1) AS UNSIGNED)), 0) AS max_no'
            . ' FROM ' . $tabel . ' WHERE nomor LIKE ' . $this->db->escape($pola);
        $hasil = $this->db->query($sql)->row_array();
        $terbesar = isset($hasil['max_no']) ? (int) $hasil['max_no'] : 0;

        if ($terbesar > (int) $ada['counter']) {
            $this->db->where('jenis', $jenis)->where('tahun', $tahun)
                ->update('document_counters', array('counter' => $terbesar));
        }

        // Kenaikan atomik: aman walau dua user menyimpan bersamaan.
        $this->db->set('counter', 'counter + 1', FALSE)
            ->where('jenis', $jenis)->where('tahun', $tahun)
            ->update('document_counters');

        $row = $this->db->select('counter')->from('document_counters')
            ->where('jenis', $jenis)->where('tahun', $tahun)
            ->get()->row_array();

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return FALSE;
        }

        $this->db->trans_commit();

        return sprintf('%s-%d-%05d', $jenis, $tahun, (int) $row['counter']);
    }

    // ---------------------------------------------------------- Master data

    /** Helper untuk tabel master: ambil semua yang aktif, urut. */
    public function options($order_by = 'nama', $asc = true, $extra_where = array())
    {
        $this->db->from($this->table);

        $fields = $this->db->list_fields($this->table);
        if (in_array('is_active', $fields, true)) {
            $this->db->where('is_active', 1);
        }
        if ($this->has_soft_delete) {
            $this->db->where('is_deleted', 0);
        }

        foreach ($extra_where as $k => $v) {
            $this->db->where($k, $v);
        }

        $this->db->order_by($order_by, $asc ? 'ASC' : 'DESC');
        return $this->db->get()->result_array();
    }
}
