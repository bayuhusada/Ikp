<?php
defined('BASEPATH') OR exit('No direct script access allowed');


/**
 * Tabel: patients
 *
 * Satu pasien dapat memiliki banyak laporan insiden. Pencarian memakai
 * nomor rekam medis, bukan nama, karena nama bisa sama.
 */
class Patient_model extends MY_Model
{
    protected $table = 'patients';
    protected $primary_key = 'id';

    protected $search_cols = array('nama_pasien', 'nomor_mr');
    protected $sortable = array('id', 'nama_pasien', 'nomor_mr', 'ruangan_id', 'created_at');

    /**
     * Cari pasien berdasarkan nomor rekam medis.
     *
     * Nomor MR hanya unik di dalam satu ruangan pada sebagian faskes,
     * jadi pencarian dibatasi ke ruangan yang sama.
     */
    public function find_by_mr($nomor_mr, $ruangan_id)
    {
        return $this->db->from($this->table)
            ->where('nomor_mr', $nomor_mr)
            ->where('ruangan_id', (int) $ruangan_id)
            ->where('is_deleted', 0)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();
    }

    /**
     * Ambil pasien atau buat baru bila belum ada.
     * Dipakai saat menyimpan laporan, supaya satu pasien tidak terduplikasi.
     *
     * @return int ID pasien
     */
    public function find_or_create($data)
    {
        $ada = $this->find_by_mr($data['nomor_mr'], $data['ruangan_id']);

        if ($ada) {
            // Perbarui hanya field yang diisi pada form laporan
            $ubah = array();
            foreach (array('kelompok_umur_id', 'jenis_kelamin', 'penanggung_jawab_id',
                           'tanggal_masuk', 'jam_masuk') as $f) {
                if (array_key_exists($f, $data) && $data[$f] !== NULL && $data[$f] !== '') {
                    $ubah[$f] = $data[$f];
                }
            }
            if (! empty($ubah)) {
                $this->update($ada['id'], $ubah);
            }
            return (int) $ada['id'];
        }

        return $this->insert($data);
    }

    /** Daftar pasien untuk pencarian di form laporan. */
    public function cari($keyword, $ruangan_id = NULL, $limit = 20)
    {
        $this->db->from($this->table)->where('is_deleted', 0);

        if ($keyword !== '') {
            $this->db->group_start()
                ->like('nama_pasien', $keyword)
                ->or_like('nomor_mr', $keyword)
                ->group_end();
        }
        if ($ruangan_id) {
            $this->db->where('ruangan_id', (int) $ruangan_id);
        }

        return $this->db->order_by('nama_pasien', 'ASC')
            ->limit((int) $limit)
            ->get()
            ->result_array();
    }
}
