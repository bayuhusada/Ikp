<?php
defined('BASEPATH') OR exit('No direct script access allowed');


/**
 * Tabel: incident_reports
 *
 * WAJIB extends MY_Model karena tabel ini punya soft delete DAN kolom
 * created_by yang dipakai filter role.
 *
 * RETENSI DATA - JANGAN diubah: laporan tidak boleh pernah dihapus permanen.
 * Keputusan user 27 Sep 2026: data disimpan selamanya, tidak ada tombol
 * hapus permanen sama sekali. Memakai ini berarti soft_delete() tidak akan
 * diam-diam jatuh ke hard_delete() kalau properti ini di-set false.
 */
class Incident_report_model extends MY_Model
{
    protected $table = 'incident_reports';
    protected $primary_key = 'id';

    /** WAJIB true. Lihat catatan retensi di header kelas. */
    protected $has_soft_delete = true;

    /** Kolom yang boleh dicari lewat kotak search. */
    protected $search_cols = array('nomor', 'insiden', 'kronologi_insiden', 'tempat_insiden');

    /** Kolom yang boleh diurutkan. */
    protected $sortable = array('id', 'nomor', 'tanggal_insiden', 'tanggal_lapor', 'created_at', 'status_id');

    /**
     * Ambil satu laporan lengkap dengan join label yang mudah ditampilkan.
     * Tetap menerapkan filter role.
     *
     * PENTING: filter role WAJIB ada di sini. Halaman detail menampilkan nama
     * dan nomor MR pasien, jadi tanpa filter ini siapa pun yang tahu id-nya
     * bisa membuka laporan ruangan lain. Daftar laporan punya filter, tapi
     * daftar saja tidak cukup - balasannya harus ikut difilter.
     *
     * Pelapor  : hanya laporan miliknya sendiri
     * Kepala   : hanya laporan ruangannya
     * Admin    : semua
     */
    public function get_detail($id)
    {
        $query = $this->db
            ->select('ir.*,
                      p.nama_pasien, p.nomor_mr, p.jenis_kelamin, p.tanggal_masuk, p.jam_masuk,
                      r.nama AS ruang_nama,
                      it.kode AS jenis_kode, it.nama AS jenis_nama,
                      ic.nama AS kategori_nama,
                      sp.nama AS spesialisasi_nama,
                      im.nama AS akibat_nama,
                      rb.kode AS band_kode, rb.nama AS band_nama, rb.warna_hex AS band_warna,
                      st.nama AS status_nama, st.urutan AS status_urutan,
                      ru.nama AS unit_terkait_nama,
                      g.nama AS graded_by_nama,
                      fr.nama AS pelapor_pertama_nama,
                      cr.nama AS care_type_nama,
                      pr.nama AS penanggung_jawab_nama,
                      ag.nama AS kelompok_umur_nama,
                      at.nama AS tim_tindakan_nama')
            ->from('incident_reports ir')
            ->join('patients p', 'p.id = ir.patient_id')
            ->join('ruangan r', 'r.id = ir.ruangan_id')
            ->join('incident_types it', 'it.id = ir.jenis_insiden_id')
            ->join('incident_impacts im', 'im.id = ir.akibat_insiden_id')
            ->join('statuses st', 'st.id = ir.status_id')
            ->join('incident_categories ic', 'ic.id = ir.incident_category_id', 'left')
            ->join('incident_specializations sp', 'sp.id = ir.specialization_id', 'left')
            ->join('risk_bands rb', 'rb.id = ir.risk_band_id', 'left')
            ->join('ruangan ru', 'ru.id = ir.unit_terkait_id', 'left')
            ->join('users g', 'g.id = ir.graded_by', 'left')
            ->join('first_reporters fr', 'fr.id = ir.first_reporter_id', 'left')
            ->join('care_types cr', 'cr.id = ir.care_type_id', 'left')
            ->join('patient_responsibles pr', 'pr.id = p.penanggung_jawab_id', 'left')
            ->join('age_groups ag', 'ag.id = p.kelompok_umur_id', 'left')
            ->join('action_teams at', 'at.id = ir.action_team_id', 'left')
            ->where('ir.id', (int) $id)
            ->where('ir.is_deleted', 0);

        $this->apply_role_scope($query, 'ir');

        return $query->get()->row_array();
    }

    /**
     * Daftar laporan + filter. Selalu menerapkan filter role.
     *
     * @param array $filter
     *   search, date_from, date_to, ruangan_id, jenis_insiden_id,
     *   risk_band_id, status_id, incident_category_id
     * @return array
     */
    public function paginate_reports(array $filter = array(), $limit = 25, $offset = 0, $sort = 'created_at', $dir = 'DESC')
    {
        $this->db->select('ir.id, ir.nomor, ir.tanggal_insiden, ir.tanggal_lapor, ir.created_at,
                           ir.patient_id, ir.ruangan_id, ir.status_id, ir.jenis_insiden_id,
                           ir.risk_band_id, ir.incident_category_id, ir.created_by,
                           r.nama AS ruang_nama,
                           it.kode AS jenis_kode, it.nama AS jenis_nama,
                           im.nama AS akibat_nama,
                           rb.kode AS band_kode, rb.nama AS band_nama, rb.warna_hex AS band_warna,
                           st.nama AS status_nama, st.urutan AS status_urutan')
            ->from('incident_reports ir')
            ->join('ruangan r', 'r.id = ir.ruangan_id')
            ->join('incident_types it', 'it.id = ir.jenis_insiden_id')
            ->join('incident_impacts im', 'im.id = ir.akibat_insiden_id')
            ->join('statuses st', 'st.id = ir.status_id')
            ->join('risk_bands rb', 'rb.id = ir.risk_band_id', 'left')
            ->where('ir.is_deleted', 0);

        $this->apply_role_scope($this->db, 'ir');

        $this->_filter($this->db, $filter);

        if (! in_array($sort, $this->sortable, TRUE)) {
            $sort = 'created_at';
        }
        $dir = (strtoupper($dir) === 'ASC') ? 'ASC' : 'DESC';

        $rows = $this->db->order_by('ir.' . $sort, $dir)
            ->limit((int) $limit, (int) $offset)
            ->get()
            ->result_array();

        return array('rows' => $rows, 'total' => $this->_count($filter));
    }

    /**
     * Terapkan filter yang sama untuk hasil dan penghitungan total.
     * TIDAK boleh join ruangan di sini - pemanggil sudah doing join.
     */
    private function _filter($q, array $filter)
    {
        if (! empty($filter['search'])) {
            $s = $filter['search'];
            $q->group_start();
            foreach ($this->search_cols as $i => $col) {
                if ($i === 0) {
                    $q->like('ir.' . $col, $s);
                } else {
                    $q->or_like('ir.' . $col, $s);
                }
            }
            $q->or_like('ir.nomor', $s);
            $q->group_end();
        }

        if (! empty($filter['date_from'])) {
            $q->where('ir.tanggal_insiden >=', $filter['date_from']);
        }
        if (! empty($filter['date_to'])) {
            $q->where('ir.tanggal_insiden <=', $filter['date_to']);
        }
        if (! empty($filter['ruangan_id'])) {
            $q->where('ir.ruangan_id', (int) $filter['ruangan_id']);
        }
        if (! empty($filter['jenis_insiden_id'])) {
            $q->where('ir.jenis_insiden_id', (int) $filter['jenis_insiden_id']);
        }
        if (! empty($filter['risk_band_id'])) {
            $q->where('ir.risk_band_id', (int) $filter['risk_band_id']);
        }
        if (! empty($filter['status_id'])) {
            $q->where('ir.status_id', (int) $filter['status_id']);
        }
        if (! empty($filter['incident_category_id'])) {
            $q->where('ir.incident_category_id', (int) $filter['incident_category_id']);
        }
    }

    private function _count(array $filter)
    {
        $this->db->select('ir.id')->from('incident_reports ir')
            ->join('ruangan r', 'r.id = ir.ruangan_id')
            ->join('incident_types it', 'it.id = ir.jenis_insiden_id')
            ->join('incident_impacts im', 'im.id = ir.akibat_insiden_id')
            ->join('statuses st', 'st.id = ir.status_id')
            ->join('risk_bands rb', 'rb.id = ir.risk_band_id', 'left')
            ->where('ir.is_deleted', 0);
        $this->apply_role_scope($this->db, 'ir');
        $this->_filter($this->db, $filter);
        return (int) $this->db->count_all_results();
    }

    // nomor_baru() mewarisi MY_Model. Versi di sana memakai
    // UPDATE counter = counter + 1 di dalam satu transaksi (PRIMARY KEY
    // (jenis, tahun) membuat InnoDB mengunci baris, jadi dua proses tidak
    // mendapat angka sama) PLUS self-healing: counter disamakan dulu dengan
    // nomor terbesar yang ada, supaya tidak bentrok setelah ada baris yang
    // dihapus atau diimpor.

    /**
     * Simpan baris ke incident_report_history.
     * Dipakai untuk jejak perubahan, termasuk Grading Ulang.
     */
    public function catat_history($report_id, $action, $sebelum = null, $sesudah = null, $user_id = null, $ip = null)
    {
        return $this->db->insert('incident_report_history', array(
            'incident_report_id' => (int) $report_id,
            'action'             => $action,
            'sebelum'            => $sebelum,
            'sesudah'            => $sesudah,
            'user_id'            => $user_id,
            'waktu'              => date('Y-m-d H:i:s'),
            'ip'                 => $ip,
        ));
    }

    public function get_history($report_id, $limit = 50)
    {
        return $this->db
            ->select('h.*, u.nama AS user_nama')
            ->from('incident_report_history h')
            ->join('users u', 'u.id = h.user_id', 'left')
            ->where('h.incident_report_id', (int) $report_id)
            ->order_by('h.waktu', 'DESC')
            ->limit((int) $limit)
            ->get()
            ->result_array();
    }

    /** Simpan satu baris tindakan setelah kejadian. */
    public function tambah_tindakan($report_id, array $data)
    {
        $data['incident_report_id'] = (int) $report_id;
        return $this->db->insert('incident_report_actions', $data);
    }

    public function get_tindakan($report_id)
    {
        return $this->db
            ->select('ira.*, at.nama AS tim_nama')
            ->from('incident_report_actions ira')
            ->join('action_teams at', 'at.id = ira.action_team_id', 'left')
            ->where('ira.incident_report_id', (int) $report_id)
            ->order_by('ira.id', 'ASC')
            ->get()
            ->result_array();
    }
}
