<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Tabel: investigations
 *
 * Hanya untuk band BIRU dan HIJAU (butuh_investigasi_sederhana = 1).
 * Satu laporan punya maksimal satu lembar investigasi (UNIQUE di
 * incident_report_id). Band KUNING dan MERAH memakai proses SOP di
 * luar sistem ini dan tidak pernah sampai ke tabel ini.
 *
 * RETENSI DATA - JANGAN diubah: sama seperti incident_reports, lembar
 * investigasi tidak boleh dihapus permanen. Keputusan user 27 Sep 2026.
 */
class Investigation_model extends MY_Model
{
    protected $table = 'investigations';
    protected $primary_key = 'id';

    /** WAJIB true. Lihat catatan retensi di header kelas. */
    protected $has_soft_delete = true;

    protected $search_cols = array('nomor', 'penyebab_langsung', 'akar_masalah', 'nama_penyetuju');
    protected $sortable = array('id', 'nomor', 'tanggal_investigasi', 'status_id', 'created_at');

    /**
     * Ambil satu investigasi lengkap dengan join label.
     * Tetap menerapkan filter role lewat ruangan_id.
     */
    public function get_detail($id, $with_role_scope = TRUE)
    {
        $query = $this->db
            ->select('i.*,
                      ir.nomor AS nomor_laporan,
                      ir.tanggal_insiden, ir.kronologi_insiden, ir.insiden AS ringkasan_insiden,
                      p.nama_pasien, p.nomor_mr,
                      r.nama AS ruang_nama,
                      it.kode AS jenis_kode, it.nama AS jenis_nama,
                      im.nama AS akibat_nama,
                      rb.kode AS band_kode, rb.nama AS band_nama, rb.warna_hex AS band_warna,
                      s.nama AS status_nama, s.urutan AS status_urutan, s.is_terminal AS status_terminal,
                      gb.nama AS dibuat_oleh')
            ->from('investigations i')
            ->join('incident_reports ir', 'ir.id = i.incident_report_id')
            ->join('patients p', 'p.id = ir.patient_id')
            ->join('ruangan r', 'r.id = i.ruangan_id')
            ->join('incident_types it', 'it.id = i.jenis_insiden_id')
            ->join('incident_impacts im', 'im.id = ir.akibat_insiden_id')
            ->join('risk_bands rb', 'rb.id = i.risk_band_id')
            ->join('statuses s', 's.id = i.status_id')
            ->join('users gb', 'gb.id = i.created_by', 'left')
            ->where('i.id', (int) $id)
            ->where('i.is_deleted', 0);

        if ($with_role_scope) {
            $this->apply_role_scope($query, 'i');
        }

        return $query->get()->row_array();
    }

    /** Daftar investigasi + filter, sudah dibatasi role. */
    public function paginate_investigasi(array $filter = array(), $limit = 25, $offset = 0, $sort = 'created_at', $dir = 'DESC')
    {
        $this->db->select('i.id, i.nomor, i.tanggal_investigasi, i.status_id, i.ruangan_id,
                           i.risk_band_id, i.created_by,
                           ir.nomor AS nomor_laporan,
                           r.nama AS ruang_nama,
                           rb.kode AS band_kode, rb.warna_hex AS band_warna,
                           s.nama AS status_nama')
            ->from('investigations i')
            ->join('incident_reports ir', 'ir.id = i.incident_report_id')
            ->join('ruangan r', 'r.id = i.ruangan_id')
            ->join('risk_bands rb', 'rb.id = i.risk_band_id')
            ->join('statuses s', 's.id = i.status_id')
            ->where('i.is_deleted', 0);

        $this->apply_role_scope($this->db, 'i');
        $this->_filter($this->db, $filter);

        if (! in_array($sort, $this->sortable, TRUE)) {
            $sort = 'created_at';
        }
        $dir = (strtoupper($dir) === 'ASC') ? 'ASC' : 'DESC';

        $rows = $this->db->order_by('i.' . $sort, $dir)
            ->limit((int) $limit, (int) $offset)
            ->get()
            ->result_array();

        return array('rows' => $rows, 'total' => $this->_count($filter));
    }

    private function _filter($q, array $f)
    {
        if (! empty($f['search'])) {
            $q->group_start();
            foreach ($this->search_cols as $i => $col) {
                if ($i === 0) { $q->like('i.' . $col, $f['search']); }
                else { $q->or_like('i.' . $col, $f['search']); }
            }
            $q->or_like('ir.nomor', $f['search']);
            $q->group_end();
        }
        if (! empty($f['ruangan_id'])) {
            $q->where('i.ruangan_id', (int) $f['ruangan_id']);
        }
        if (! empty($f['status_id'])) {
            $q->where('i.status_id', (int) $f['status_id']);
        }
        if (! empty($f['risk_band_id'])) {
            $q->where('i.risk_band_id', (int) $f['risk_band_id']);
        }
    }

    private function _count(array $f)
    {
        $this->db->select('i.id')->from('investigations i')
            ->join('incident_reports ir', 'ir.id = i.incident_report_id')
            ->join('ruangan r', 'r.id = i.ruangan_id')
            ->join('risk_bands rb', 'rb.id = i.risk_band_id')
            ->join('statuses s', 's.id = i.status_id')
            ->where('i.is_deleted', 0);
        $this->apply_role_scope($this->db, 'i');
        $this->_filter($this->db, $f);
        return (int) $this->db->count_all_results();
    }

    // nomor_baru() mewarisi MY_Model (sudah ada self-healing terhadap
    // nomor tertinggal, dan tidak perlu diduplikasi di sini).

    /** Apakah laporan ini sudah punya lembar investigasi? */
    public function ada_per_laporan($report_id)
    {
        return $this->db->from($this->table)
            ->where('incident_report_id', (int) $report_id)
            ->where('is_deleted', 0)
            ->count_all_results() > 0;
    }

    // ---- Rekomendasi (banyak baris) ---------------------------------

    public function get_rekomendasi($investigation_id)
    {
        return $this->db->from('investigation_recommendations')
            ->where('investigation_id', (int) $investigation_id)
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();
    }

    public function tambah_rekomendasi($investigation_id, $rekomendasi, $pic, $tanggal)
    {
        return $this->db->insert('investigation_recommendations', array(
            'investigation_id'  => (int) $investigation_id,
            'rekomendasi'       => $rekomendasi,
            'penanggung_jawab'  => $pic ?: NULL,
            'tanggal_rekomendasi' => $tanggal ?: NULL,
        ));
    }

    public function hapus_rekomendasi($id, $investigation_id)
    {
        $this->db->where('id', (int) $id);
        $this->db->where('investigation_id', (int) $investigation_id);
        return $this->db->delete('investigation_recommendations');
    }

    /** Hapus seluruh rekomendasi. Dipakai saat form edit become sumber kebenaran. */
    public function hapus_semua_rekomendasi($investigation_id)
    {
        $this->db->where('investigation_id', (int) $investigation_id);
        return $this->db->delete('investigation_recommendations');
    }

    // ---- Tindakan (banyak baris) ------------------------------------

    public function get_tindakan($investigation_id)
    {
        return $this->db->select('ia.*, s.nama AS status_nama, s.urutan AS status_urutan')
            ->from('investigation_actions ia')
            ->join('statuses s', 's.id = ia.status_id', 'left')
            ->where('ia.investigation_id', (int) $investigation_id)
            ->order_by('ia.id', 'ASC')
            ->get()
            ->result_array();
    }

    public function tambah_tindakan($investigation_id, $tindakan, $pic, $tanggal, $status_id)
    {
        return $this->db->insert('investigation_actions', array(
            'investigation_id' => (int) $investigation_id,
            'tindakan'        => $tindakan,
            'penanggung_jawab' => $pic ?: NULL,
            'tanggal_tindakan'=> $tanggal ?: NULL,
            'status_id'       => $status_id ? (int) $status_id : $this->_status_id('belum'),
        ));
    }

    /**
     * Resolve id status dari kode. Cache per request supaya tidak query berulang.
     *
     * @return int 0 bila kode tidak ditemukan
     */
    private function _status_id($kode)
    {
        static $cache = array();

        if (isset($cache[$kode])) {
            return $cache[$kode];
        }

        $row = $this->db->select('id')
            ->from('statuses')
            ->where('kode', $kode)
            ->where('untuk', 'tindakan')
            ->limit(1)
            ->get()
            ->row_array();

        $cache[$kode] = empty($row) ? 0 : (int) $row['id'];

        if ($cache[$kode] === 0) {
            log_message('error', 'Investigation_model: kode status tindakan "' . $kode . '" tidak ditemukan');
        }

        return $cache[$kode];
    }

    public function hapus_tindakan($id, $investigation_id)
    {
        $this->db->where('id', (int) $id);
        $this->db->where('investigation_id', (int) $investigation_id);
        return $this->db->delete('investigation_actions');
    }

    /** Hapus seluruh tindakan. Dipakai saat form edit become sumber kebenaran. */
    public function hapus_semua_tindakan($investigation_id)
    {
        $this->db->where('investigation_id', (int) $investigation_id);
        return $this->db->delete('investigation_actions');
    }

    /**
     * Apakah semua tindakan sudah selesai?
     * Status "tuntas" diambil dari statuses dengan untuk = 'tindakan',
     * bukan di-hardcode, supaya tidak rapuh kalau id-nya berubah.
     */
    public function semua_tindakan_selesai($investigation_id)
    {
        $tuntas = $this->db->from('statuses')
            ->where('kode', 'tuntas')
            ->where('untuk', 'tindakan')
            ->get()
            ->row_array();

        if (! $tuntas) {
            return FALSE;
        }

        $total = (int) $this->db->from('investigation_actions')
            ->where('investigation_id', (int) $investigation_id)
            ->count_all_results();

        $belum = (int) $this->db->from('investigation_actions')
            ->where('investigation_id', (int) $investigation_id)
            ->where('status_id !=', (int) $tuntas['id'])
            ->count_all_results();

        return $total > 0 && $belum === 0;
    }
}
