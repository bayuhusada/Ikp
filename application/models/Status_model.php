<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Master_model tidak dimuat otomatis oleh CI3
require_once APPPATH . 'models/Master_model.php';

/**
 * Tabel: statuses
 *
 * CATATAN PENTING: kode status TIDAK unik antar jenis.
 * Contohnya kode 'draft' dipakai oleh laporan DAN investigasi.
 * Karena itu pencarian kode WAJIB menyertakan jenis, kalau tidak
 * hasilnya ambigu dan transisi status bisa salah.
 */
class Status_model extends Master_model
{
    protected $table = 'statuses';
    protected $order_by = 'urutan';
    protected $asc = true;

    /**
     * Ambil status berdasarkan kode DAN jenis.
     *
     * @param string $kode   draft / dilaporkan / dalam_investigasi / dll
     * @param string $untuk  laporan / investigasi / tindakan
     * @return array|null
     */
    public function get_by_kode($kode, $untuk = 'laporan')
    {
        return $this->db->from($this->table)
            ->where('kode', $kode)
            ->where('untuk', $untuk)
            ->get()
            ->row_array();
    }

    /** Semua status untuk satu jenis saja. */
    public function get_by_jenis($untuk, $aktif_saja = FALSE)
    {
        $this->db->from($this->table)->where('untuk', $untuk);
        if ($aktif_saja) {
            $this->db->where('is_active', 1);
        }
        return $this->db->order_by('urutan', 'ASC')->get()->result_array();
    }

    /** Opsi dropdown untuk satu jenis. */
    public function options_by_jenis($untuk = 'laporan')
    {
        return $this->get_by_jenis($untuk, TRUE);
    }
}
